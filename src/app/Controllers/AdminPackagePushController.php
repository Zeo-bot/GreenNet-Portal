<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Contracts\AuthorizedRouterOSWriterInterface;
use GreenNet\Contracts\GuardedRouterOSWriteGatewayInterface;
use GreenNet\Contracts\RouterOSReadGatewayInterface;
use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\Models\AppLog;
use GreenNet\Models\Router;
use GreenNet\Models\RouterPackageProfile;
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use GreenNet\Services\WriteSafetyGuard;
use PDO;
use RuntimeException;
use Throwable;

class AdminPackagePushController
{
    public function __construct(
        private ?RouterOSReadGatewayInterface $readGateway = null,
        private ?GuardedRouterOSWriteGatewayInterface $writeGateway = null
    ) {
    }

    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $guard = new WriteSafetyGuard();
        $guard->ensureTables();

        return View::render('admin/package_push', [
            'title' => 'Push Package to MikroTik',
            'packages' => $this->localPackages(),
            'routers' => Router::enabled(),
            'matrix' => $this->provisioningMatrix(),
            'preflight' => $guard->preflight(),
            'result' => $_SESSION['package_push_result'] ?? null,
            'message' => $this->consumeFlash('message'),
            'message_type' => $this->consumeFlash('type', 'success'),
        ]);
    }

    public function preview(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $guard = new WriteSafetyGuard();
            $guard->ensureTables();
            $guard->assertDryRunAllowed();

            $packageId = (int) ($_POST['package_id'] ?? 0);
            $routerId = (int) ($_POST['router_id'] ?? 0);
            $backend = $this->backend((string) ($_POST['backend'] ?? 'user-manager'));

            if ($packageId <= 0) {
                throw new RuntimeException('اختر باقة صحيحة.');
            }

            $plan = $this->buildPushPlan($packageId);
            $plan['router_id'] = $routerId;

            $auditId = $guard->recordDryRun([
                'action' => 'push_package_to_mikrotik',
                'dataset' => $backend . '_package_profile',
                'username' => '',
                'command' => 'package push preview',
                'params' => $plan,
                'router_response' => 'Dry Run only. No MikroTik write.',
            ]);

            $plan['audit_id'] = $auditId;

            $_SESSION['package_push_result'] = $plan;

            AppLog::info('Package push Dry Run created', [
                'package_id' => $packageId,
                'package_name' => (string) ($plan['package']['name'] ?? ''),
                'audit_id' => $auditId,
                'can_execute_later' => !empty($plan['can_execute_later']),
            ]);

            $this->flash('تم إنشاء Dry Run للباقـة. لم يتم تنفيذ أي Write.', 'success');
        } catch (Throwable $e) {
            $_SESSION['package_push_result'] = [
                'ok' => false,
                'error' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            AppLog::error('Package push Dry Run failed', [
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل Dry Run: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/package-push');
        exit;
    }

    public function execute(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $packageId = (int) ($_POST['package_id'] ?? 0);
            $confirm = trim((string) ($_POST['confirm_push'] ?? ''));

            if ($packageId <= 0) {
                throw new RuntimeException('اختر باقة صحيحة.');
            }

            if ($confirm !== 'PUSH') {
                throw new RuntimeException('للتنفيذ اكتب PUSH في خانة التأكيد.');
            }

            $lastPlan = $this->requireLastPlan($packageId);

            if ((int) ($lastPlan['router_id'] ?? 0) !== (int) ($_POST['router_id'] ?? 0)) {
                throw new RuntimeException('Target router changed after Dry Run. Create a new preview.');
            }
            if ((string) ($lastPlan['backend'] ?? '') !== $this->backend((string) ($_POST['backend'] ?? ''))) {
                throw new RuntimeException('Target backend changed after Dry Run. Create a new preview.');
            }

            if (empty($lastPlan['can_execute_later'])) {
                throw new RuntimeException('آخر Dry Run لا يسمح بالتنفيذ.');
            }

            $freshPlan = $lastPlan;

            if (empty($freshPlan['can_execute_later'])) {
                throw new RuntimeException((string) ($freshPlan['block_reason'] ?? 'الخطة لم تعد قابلة للتنفيذ.'));
            }

            $execution = $this->executePushPlan($packageId, $lastPlan);

            $afterPlan = $this->buildPushPlan($packageId);
            $afterPlan['router_id'] = (int) ($lastPlan['router_id'] ?? 0);
            $afterPlan['executed'] = true;
            $afterPlan['real_execution'] = true;
            $afterPlan['real_result'] = $execution;

            $_SESSION['package_push_result'] = $afterPlan;

            if (empty($execution['ok'])) {
                throw new RuntimeException((string) ($execution['message'] ?? 'فشل تنفيذ Push.'));
            }

            $profileName = (string) ($freshPlan['router_names']['profile_name'] ?? '');
            $routerId = (int) ($freshPlan['router_id'] ?? 0);
            if ($routerId > 0) {
                RouterPackageProfile::save(
                    $routerId,
                    $packageId,
                    $profileName,
                    (string) ($afterPlan['existing']['profile']['id'] ?? ''),
                    (string) ($freshPlan['backend'] ?? 'user-manager')
                );
            } else {
                $this->markPackageSynced($packageId, $profileName);
            }

            $this->flash('تم Push الباقة إلى MikroTik بنجاح.', 'success');
        } catch (Throwable $e) {
            $previous = $_SESSION['package_push_result'] ?? [];

            if (!is_array($previous)) {
                $previous = [];
            }

            $previous['execute_error'] = $e->getMessage();
            $previous['executed'] = false;

            $_SESSION['package_push_result'] = $previous;

            AppLog::error('Package push execution failed', [
                'package_id' => (int) ($_POST['package_id'] ?? 0),
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل التنفيذ: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/package-push');
        exit;
    }

    private function buildPushPlan(int $packageId): array
    {
        $package = $this->findPackage($packageId);

        if ($package === null) {
            throw new RuntimeException('الباقة غير موجودة.');
        }

        $routerId = (int) ($_POST['router_id'] ?? $_GET['router_id'] ?? 0);
        $backend = $this->backend((string) ($_POST['backend'] ?? $_GET['backend'] ?? 'user-manager'));
        $override = $this->cleanRouterName((string) ($_POST['profile_name'] ?? $_GET['profile_name'] ?? ''));
        $fallbackName = $this->defaultProfileName($package, $backend);
        $profileName = $override !== '' ? $override : RouterPackageProfile::profileName(
            $routerId,
            $packageId,
            $fallbackName,
            $backend
        );
        if ($backend !== 'user-manager') {
            return $this->buildNativePlan($packageId, $package, $routerId, $backend, $profileName);
        }
        $limitationName = $profileName;

        if ($profileName === '') {
            throw new RuntimeException('اسم الباقة غير صالح للـ MikroTik.');
        }

        $quotaBytes = $this->quotaGbToBytes((float) ($package['quota_gb'] ?? 0));
        $durationDays = (int) ($package['duration_days'] ?? 0);
        $validity = $durationDays > 0 ? $durationDays . 'd' : 'unlimited';
        $uptimeLimit = $durationDays > 0 ? $durationDays . 'd' : '';
        $rateLimit = trim((string) ($package['rate_limit'] ?? ''));
        $price = (float) ($package['price'] ?? 0);

        $limitationDesired = [
            'name' => $limitationName,
        ];

        if ($quotaBytes > 0) {
            $limitationDesired['transfer-limit'] = (string) $quotaBytes;
        }

        if ($uptimeLimit !== '') {
            $limitationDesired['uptime-limit'] = $uptimeLimit;
        }

        /*
         * RouterOS User Manager 7.23.1 does not accept:
         *   rate-limit=1M/1M
         *
         * It accepts numeric bps fields:
         *   rate-limit-rx=1000000
         *   rate-limit-tx=1000000
         */
        $rateLimitParams = $this->routerRateLimitParams($rateLimit);

        foreach ($rateLimitParams as $key => $value) {
            $limitationDesired[$key] = $value;
        }

        $profileDesired = [
            'name' => $profileName,
            'name-for-users' => (string) ($package['name'] ?? $profileName),
            'starts-when' => 'first-auth',
            'validity' => $validity,
        ];

        if ($price > 0) {
            $profileDesired['price'] = (string) $price;
        }

        $existing = $this->readExistingRouterPackage($profileName, $limitationName);
        $mappedProfileName = RouterPackageProfile::profileName($routerId, $packageId, '', $backend);

        if (($mappedProfileName === '' || $mappedProfileName !== $profileName)
            && (!empty($existing['profile']['found']) || !empty($existing['limitation']['found']))) {
            return [
                'ok' => true,
                'action' => 'push_package_to_mikrotik',
                'backend' => $backend,
                'router_id' => $routerId,
                'package_id' => $packageId,
                'package' => $this->sanitizePackage($package),
                'router_names' => ['profile_name' => $profileName, 'limitation_name' => $limitationName],
                'desired' => ['profile' => $profileDesired, 'limitation' => $limitationDesired],
                'existing' => $existing,
                'operations' => [],
                'operations_count' => 0,
                'can_execute_later' => false,
                'block_reason' => 'Existing User Manager objects with this name are not mapped to this GreenNet package. Map them explicitly or choose another name.',
                'executed' => false,
                'real_execution' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'notes' => ['No RouterOS write was planned because ownership could not be proven.'],
            ];
        }

        $operations = [];

        if (!empty($existing['limitation']['found'])) {
            $operations[] = [
                'type' => 'update_limitation',
                'command' => '/user-manager/limitation/set',
                'params' => array_merge([
                    'numbers' => (string) ($existing['limitation']['id'] ?? ''),
                ], $this->withoutName($limitationDesired)),
            ];
        } else {
            $operations[] = [
                'type' => 'create_limitation',
                'command' => '/user-manager/limitation/add',
                'params' => $limitationDesired,
            ];
        }

        if (!empty($existing['profile']['found'])) {
            $operations[] = [
                'type' => 'update_profile',
                'command' => '/user-manager/profile/set',
                'params' => array_merge([
                    'numbers' => (string) ($existing['profile']['id'] ?? ''),
                ], $this->withoutName($profileDesired)),
            ];
        } else {
            $operations[] = [
                'type' => 'create_profile',
                'command' => '/user-manager/profile/add',
                'params' => $profileDesired,
            ];
        }

        if (empty($existing['profile_limitation']['found'])) {
            $operations[] = [
                'type' => 'create_profile_limitation',
                'command' => '/user-manager/profile-limitation/add',
                'params' => [
                    'profile' => $profileName,
                    'limitation' => $limitationName,
                ],
            ];
        }

        $blockReason = '';

        if (count($operations) === 0) {
            $blockReason = 'لا توجد عمليات مطلوبة. الباقة تبدو موجودة ومربوطة مسبقاً.';
        }

        return [
            'ok' => true,
            'action' => 'push_package_to_mikrotik',
            'backend' => $backend,
            'package_id' => $packageId,
            'package' => $this->sanitizePackage($package),
            'router_names' => [
                'profile_name' => $profileName,
                'limitation_name' => $limitationName,
            ],
            'desired' => [
                'profile' => $profileDesired,
                'limitation' => $limitationDesired,
                'profile_limitation' => [
                    'profile' => $profileName,
                    'limitation' => $limitationName,
                ],
            ],
            'existing' => $existing,
            'operations' => $operations,
            'operations_count' => count($operations),
            'can_execute_later' => count($operations) > 0,
            'block_reason' => $blockReason,
            'executed' => false,
            'real_execution' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => [
                'Dry Run فقط. لم يتم تنفيذ أي Write على MikroTik.',
                'عند التنفيذ سيتم إنشاء أو تحديث Limitation و Profile وربطهما عبر profile-limitation.',
                'السرعة تتحول من 1M/1M إلى rate-limit-rx/rate-limit-tx بقيم bps رقمية.',
                'بعد النجاح سيتم وسم الباقة داخل GreenNet كمزامنة مع User Manager.',
            ],
        ];
    }

    private function executePushPlan(int $packageId, array $lastPlan): array
    {
        $plan = $lastPlan;
        $operations = is_array($plan['operations'] ?? null) ? $plan['operations'] : [];

        if (empty($operations)) {
            return [
                'ok' => false,
                'message' => 'لا توجد عمليات للتنفيذ.',
                'operations' => [],
            ];
        }

        $executed = [];
        $result = $this->writeGateway()->execute(
            new WriteExecutionRequest(
                'push_package_to_mikrotik',
                (string) ($plan['backend'] ?? 'user-manager') . '_package_profile',
                '',
                'package push execute',
                [
                    'package_id' => $packageId,
                    'package_name' => (string) ($plan['package']['name'] ?? ''),
                    'profile_name' => (string) ($plan['router_names']['profile_name'] ?? ''),
                    'limitation_name' => (string) ($plan['router_names']['limitation_name'] ?? ''),
                ],
                (int) ($lastPlan['audit_id'] ?? 0),
                true
            ),
            function (AuthorizedRouterOSWriterInterface $writer) use ($packageId, &$executed): array {
            $plan = $this->buildPushPlan($packageId);
            $operations = is_array($plan['operations'] ?? null) ? $plan['operations'] : [];
            if (empty($plan['can_execute_later']) || empty($operations)) {
                throw new RuntimeException('RouterOS package push is no longer executable.');
            }
            foreach ($operations as $operation) {
                if (!is_array($operation)) {
                    continue;
                }

                $command = (string) ($operation['command'] ?? '');
                $params = is_array($operation['params'] ?? null) ? $operation['params'] : [];

                if ($command === '') {
                    continue;
                }

                $response = $writer->execute(new RouterOSWriteCommand($command, $params));

                $executed[] = [
                    'type' => (string) ($operation['type'] ?? ''),
                    'command' => $command,
                    'params' => $params,
                    'response' => $response,
                    'ok' => true,
                ];
            }

                $backend = (string) ($plan['backend'] ?? 'user-manager');
                if ($backend === 'user-manager') {
                    $after = $this->readExistingRouterPackage(
                        (string) ($plan['router_names']['profile_name'] ?? ''),
                        (string) ($plan['router_names']['limitation_name'] ?? '')
                    );
                    if (empty($after['profile']['found'])
                        || empty($after['limitation']['found'])
                        || empty($after['profile_limitation']['found'])) {
                        throw new RuntimeException('RouterOS package push verification failed.');
                    }
                } else {
                    $root = $backend === 'native-hotspot' ? '/ip/hotspot/user/profile' : '/ppp/profile';
                    $after = ['profile' => $this->readNativeProfile(
                        $root,
                        (string) ($plan['router_names']['profile_name'] ?? '')
                    )];
                    if (empty($after['profile']['found'])) {
                        throw new RuntimeException('RouterOS native profile verification failed.');
                    }
                }

                return ['operations' => $executed, 'verified' => true];
            }
        );

        return [
            'ok' => $result->ok,
            'message' => 'تم تنفيذ العمليات على MikroTik.',
            'operations' => array_map(static fn ($call): array => $call->toArray(), $result->calls),
            'audit_recorded' => $result->auditRecorded,
            'audit_id' => $result->auditId,
            'audit_warning' => $result->auditWarning,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function buildNativePlan(
        int $packageId,
        array $package,
        int $routerId,
        string $backend,
        string $profileName
    ): array {
        if ($routerId <= 0) {
            throw new RuntimeException('Select a registered router for native profile provisioning.');
        }
        if ($profileName === '') {
            throw new RuntimeException('The generated RouterOS profile name is empty.');
        }

        $commandRoot = $backend === 'native-hotspot' ? '/ip/hotspot/user/profile' : '/ppp/profile';
        $ownership = 'GreenNet package:' . $packageId . ' backend:' . $backend;
        $desired = ['name' => $profileName];
        $rateLimit = trim((string) ($package['rate_limit'] ?? ''));
        if ($rateLimit !== '' && $rateLimit !== '-') {
            $desired['rate-limit'] = $rateLimit;
        }
        $desired['comment'] = $ownership;

        $mappedName = RouterPackageProfile::profileName($routerId, $packageId, '', $backend);
        $existing = $this->readNativeProfile($commandRoot, $profileName);
        $owned = $mappedName === $profileName || (string) ($existing['row']['comment'] ?? '') === $ownership;
        $operations = [];
        $blockReason = '';

        if (!empty($existing['found'])) {
            if (!$owned) {
                $blockReason = 'A profile with this name already exists but is not proven to be owned by GreenNet. Map it explicitly or choose another name.';
            } else {
                $operations[] = [
                    'type' => 'update_native_profile',
                    'command' => $commandRoot . '/set',
                    'params' => ['numbers' => (string) ($existing['id'] ?? '')] + $this->withoutName($desired),
                ];
            }
        } else {
            $operations[] = [
                'type' => 'create_native_profile',
                'command' => $commandRoot . '/add',
                'params' => $desired,
            ];
        }

        return [
            'ok' => true,
            'action' => 'push_package_to_mikrotik',
            'backend' => $backend,
            'router_id' => $routerId,
            'package_id' => $packageId,
            'package' => $this->sanitizePackage($package),
            'router_names' => ['profile_name' => $profileName, 'limitation_name' => ''],
            'desired' => ['profile' => $desired],
            'existing' => [
                'profile' => $existing,
                'limitation' => ['found' => false, 'not_applicable' => true],
                'profile_limitation' => ['found' => false, 'not_applicable' => true],
            ],
            'operations' => $operations,
            'operations_count' => count($operations),
            'can_execute_later' => $operations !== [] && $blockReason === '',
            'block_reason' => $blockReason,
            'executed' => false,
            'real_execution' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => [
                'Only the RouterOS rate-limit is provisioned for this native backend.',
                'Duration and quota remain GreenNet subscription policy and are not falsely represented as native profile enforcement.',
            ],
        ];
    }

    private function readNativeProfile(string $commandRoot, string $profileName): array
    {
        $result = ['found' => false, 'id' => '', 'row' => [], 'error' => ''];
        try {
            $rows = $this->normalizeRows($this->readGateway()->read($commandRoot . '/print', ['?name' => $profileName]));
            $row = $this->findRowByName($rows, $profileName);
            if ($row !== null) {
                return [
                    'found' => true,
                    'id' => (string) ($row['.id'] ?? ''),
                    'row' => $this->sanitizeRowForDisplay($row),
                    'error' => '',
                ];
            }
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
        }
        return $result;
    }

    private function readExistingRouterPackage(string $profileName, string $limitationName): array
    {
        $result = [
            'profile' => [
                'found' => false,
                'id' => '',
                'row' => [],
                'error' => '',
            ],
            'limitation' => [
                'found' => false,
                'id' => '',
                'row' => [],
                'error' => '',
            ],
            'profile_limitation' => [
                'found' => false,
                'id' => '',
                'row' => [],
                'error' => '',
            ],
        ];

        try {
            $profiles = $this->normalizeRows($this->readGateway()->read('/user-manager/profile/print', [
                '?name' => $profileName,
            ]));

            $profile = $this->findRowByName($profiles, $profileName);

            if ($profile !== null) {
                $result['profile'] = [
                    'found' => true,
                    'id' => (string) ($profile['.id'] ?? ''),
                    'row' => $this->sanitizeRowForDisplay($profile),
                    'error' => '',
                ];
            }
        } catch (Throwable $e) {
            $result['profile']['error'] = $e->getMessage();
        }

        try {
            $limitations = $this->normalizeRows($this->readGateway()->read('/user-manager/limitation/print', [
                '?name' => $limitationName,
            ]));

            $limitation = $this->findRowByName($limitations, $limitationName);

            if ($limitation !== null) {
                $result['limitation'] = [
                    'found' => true,
                    'id' => (string) ($limitation['.id'] ?? ''),
                    'row' => $this->sanitizeRowForDisplay($limitation),
                    'error' => '',
                ];
            }
        } catch (Throwable $e) {
            $result['limitation']['error'] = $e->getMessage();
        }

        try {
            $links = $this->normalizeRows($this->readGateway()->read('/user-manager/profile-limitation/print'));
            $link = $this->findProfileLimitationLink($links, $profileName, $limitationName);

            if ($link !== null) {
                $result['profile_limitation'] = [
                    'found' => true,
                    'id' => (string) ($link['.id'] ?? ''),
                    'row' => $this->sanitizeRowForDisplay($link),
                    'error' => '',
                ];
            }
        } catch (Throwable $e) {
            $result['profile_limitation']['error'] = $e->getMessage();
        }

        return $result;
    }

    private function localPackages(): array
    {
        $this->ensureServicePackagesTable();

        try {
            $stmt = Database::connection()->query("
                SELECT *
                FROM service_packages
                ORDER BY is_active DESC, id DESC
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function findPackage(int $id): ?array
    {
        $this->ensureServicePackagesTable();

        $stmt = Database::connection()->prepare("
            SELECT *
            FROM service_packages
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function markPackageSynced(int $id, string $profileName): void
    {
        $this->ensureServicePackagesTable();

        $stmt = Database::connection()->prepare("
            UPDATE service_packages
            SET
                source_type = 'user-manager',
                source_profile = :source_profile,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':source_profile' => $profileName,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    private function ensureServicePackagesTable(): void
    {
        $pdo = Database::connection();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_packages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                source_type TEXT DEFAULT 'local',
                source_profile TEXT DEFAULT '',
                access_type TEXT DEFAULT 'hotspot',
                rate_limit TEXT DEFAULT '',
                duration_days INTEGER DEFAULT 0,
                quota_gb REAL DEFAULT 0,
                price REAL DEFAULT 0,
                currency TEXT DEFAULT 'SYP',
                is_active INTEGER DEFAULT 1,
                notes TEXT DEFAULT '',
                created_at TEXT,
                updated_at TEXT
            )
        ");

        $columns = $this->columns('service_packages');

        $required = [
            'source_type' => "ALTER TABLE service_packages ADD COLUMN source_type TEXT DEFAULT 'local'",
            'source_profile' => "ALTER TABLE service_packages ADD COLUMN source_profile TEXT DEFAULT ''",
            'access_type' => "ALTER TABLE service_packages ADD COLUMN access_type TEXT DEFAULT 'hotspot'",
            'rate_limit' => "ALTER TABLE service_packages ADD COLUMN rate_limit TEXT DEFAULT ''",
            'duration_days' => "ALTER TABLE service_packages ADD COLUMN duration_days INTEGER DEFAULT 0",
            'quota_gb' => "ALTER TABLE service_packages ADD COLUMN quota_gb REAL DEFAULT 0",
            'price' => "ALTER TABLE service_packages ADD COLUMN price REAL DEFAULT 0",
            'currency' => "ALTER TABLE service_packages ADD COLUMN currency TEXT DEFAULT 'SYP'",
            'is_active' => "ALTER TABLE service_packages ADD COLUMN is_active INTEGER DEFAULT 1",
            'notes' => "ALTER TABLE service_packages ADD COLUMN notes TEXT DEFAULT ''",
            'created_at' => "ALTER TABLE service_packages ADD COLUMN created_at TEXT",
            'updated_at' => "ALTER TABLE service_packages ADD COLUMN updated_at TEXT",
        ];

        foreach ($required as $column => $sql) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec($sql);
            }
        }
    }

    private function columns(string $table): array
    {
        try {
            $rows = Database::connection()->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC);

            $columns = [];

            foreach ($rows as $row) {
                $name = (string) ($row['name'] ?? '');

                if ($name !== '') {
                    $columns[] = $name;
                }
            }

            return $columns;
        } catch (Throwable) {
            return [];
        }
    }

    private function routerPackageName(array $package): string
    {
        $sourceProfile = trim((string) ($package['source_profile'] ?? ''));

        if ($sourceProfile !== '') {
            return $this->cleanRouterName($sourceProfile);
        }

        return $this->cleanRouterName((string) ($package['name'] ?? ''));
    }

    private function cleanRouterName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[\r\n\t]+/', ' ', $name) ?? '';
        $name = preg_replace('/\s+/', ' ', $name) ?? '';

        if (strlen($name) > 64) {
            $name = substr($name, 0, 64);
        }

        return trim($name);
    }

    private function quotaGbToBytes(float $gb): int
    {
        if ($gb <= 0) {
            return 0;
        }

        /*
         * Keep decimal GB behavior close to MikroTik/User Manager values.
         * 5 GB = 5,000,000,000 bytes.
         */
        return (int) round($gb * 1000 * 1000 * 1000);
    }

    private function routerRateLimitParams(string $rateLimit): array
    {
        $rateLimit = trim($rateLimit);

        if ($rateLimit === '' || $rateLimit === '-') {
            return [];
        }

        [$rx, $tx] = $this->splitRateLimit($rateLimit);

        $params = [];

        if ($rx > 0) {
            $params['rate-limit-rx'] = (string) $rx;
        }

        if ($tx > 0) {
            $params['rate-limit-tx'] = (string) $tx;
        }

        return $params;
    }

    private function splitRateLimit(string $rateLimit): array
    {
        $rateLimit = trim($rateLimit);

        if ($rateLimit === '') {
            return [0, 0];
        }

        $normalized = str_replace(['\\', '|', ','], '/', $rateLimit);
        $parts = preg_split('/\s*\/\s*|\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($parts) || count($parts) === 0) {
            return [0, 0];
        }

        if (count($parts) === 1) {
            $one = $this->rateToBps((string) $parts[0]);

            return [$one, $one];
        }

        return [
            $this->rateToBps((string) $parts[0]),
            $this->rateToBps((string) $parts[1]),
        ];
    }

    private function rateToBps(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $value = str_replace(' ', '', $value);

        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(k|kb|kbps|kbit|kbit\/s|m|mb|mbps|mbit|mbit\/s|g|gb|gbps|gbit|gbit\/s)$/i', $value, $m)) {
            $number = (float) $m[1];
            $unit = strtolower((string) $m[2]);

            $factor = match ($unit) {
                'k', 'kb', 'kbps', 'kbit', 'kbit/s' => 1000,
                'm', 'mb', 'mbps', 'mbit', 'mbit/s' => 1000 * 1000,
                'g', 'gb', 'gbps', 'gbit', 'gbit/s' => 1000 * 1000 * 1000,
                default => 1,
            };

            return (int) round($number * $factor);
        }

        return 0;
    }

    private function withoutName(array $params): array
    {
        unset($params['name']);

        return $params;
    }

    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['profile']) || isset($rows['limitation'])) {
            return [$rows];
        }

        $out = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    private function findRowByName(array $rows, string $name): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ((string) ($row['name'] ?? '') === $name) {
                return $row;
            }
        }

        if (count($rows) === 1 && is_array($rows[0] ?? null)) {
            return $rows[0];
        }

        return null;
    }

    private function findProfileLimitationLink(array $links, string $profileName, string $limitationName): ?array
    {
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $profile = (string) ($link['profile'] ?? '');
            $limitation = (string) ($link['limitation'] ?? '');

            if (strtolower($profile) === strtolower($profileName) && strtolower($limitation) === strtolower($limitationName)) {
                return $link;
            }
        }

        return null;
    }

    private function sanitizePackage(array $package): array
    {
        return [
            'id' => (int) ($package['id'] ?? 0),
            'name' => (string) ($package['name'] ?? ''),
            'source_type' => (string) ($package['source_type'] ?? ''),
            'source_profile' => (string) ($package['source_profile'] ?? ''),
            'access_type' => (string) ($package['access_type'] ?? ''),
            'rate_limit' => (string) ($package['rate_limit'] ?? ''),
            'duration_days' => (int) ($package['duration_days'] ?? 0),
            'quota_gb' => (float) ($package['quota_gb'] ?? 0),
            'price' => (float) ($package['price'] ?? 0),
            'currency' => (string) ($package['currency'] ?? 'SYP'),
            'is_active' => (int) ($package['is_active'] ?? 1),
        ];
    }

    private function sanitizeRowForDisplay(array $row): array
    {
        $hiddenKeys = [
            'password',
            'pass',
            'secret',
            'otp-secret',
            'token',
            'api-key',
            'key',
        ];

        $clean = [];

        foreach ($row as $key => $value) {
            $keyString = (string) $key;
            $lower = strtolower($keyString);

            $hide = false;

            foreach ($hiddenKeys as $hiddenKey) {
                if ($lower === $hiddenKey || str_contains($lower, $hiddenKey)) {
                    $hide = true;
                    break;
                }
            }

            $clean[$keyString] = $hide ? '<hidden>' : (string) $value;
        }

        return $clean;
    }

    private function requireLastPlan(int $packageId): array
    {
        $plan = $_SESSION['package_push_result'] ?? null;

        if (!is_array($plan)) {
            throw new RuntimeException('نفّذ Dry Run ناجح قبل التنفيذ.');
        }

        if ((int) ($plan['package_id'] ?? 0) !== $packageId) {
            throw new RuntimeException('الباقة لا تطابق آخر Dry Run.');
        }

        if ((string) ($plan['action'] ?? '') !== 'push_package_to_mikrotik') {
            throw new RuntimeException('نوع العملية لا يطابق آخر Dry Run.');
        }

        if (!empty($plan['executed'])) {
            throw new RuntimeException('آخر Dry Run تم تنفيذه مسبقاً. أعد إنشاء Dry Run جديد.');
        }

        return $plan;
    }

    private function jsonString(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    private function defaultProfileName(array $package, string $backend): string
    {
        $slug = strtolower($this->cleanRouterName((string) ($package['name'] ?? 'package')));
        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', $slug) ?? 'package';
        $slug = trim($slug, '-_');
        $suffix = match ($backend) {
            'native-hotspot' => 'hotspot',
            'native-pppoe' => 'pppoe',
            default => 'um',
        };

        return substr('GN-' . ($slug !== '' ? $slug : ('package-' . (int) ($package['id'] ?? 0))) . '-' . $suffix, 0, 64);
    }

    private function backend(string $backend): string
    {
        if (!in_array($backend, ['user-manager', 'native-hotspot', 'native-pppoe'], true)) {
            throw new RuntimeException('Unsupported package provisioning backend.');
        }
        return $backend;
    }

    private function provisioningMatrix(): array
    {
        try {
            return Database::connection()->query("
                SELECT sp.id AS package_id, sp.name AS package_name, sp.rate_limit,
                       sp.updated_at AS package_updated_at,
                       r.id AS router_id, r.name AS router_name, r.last_status,
                       b.backend, m.profile_name, m.profile_id, m.updated_at AS mapping_updated_at,
                       CASE
                         WHEN r.last_status = 'unreachable' THEN 'router_unavailable'
                         WHEN m.id IS NULL THEN 'missing_mapping'
                         WHEN datetime(m.updated_at) < datetime(sp.updated_at) THEN 'out_of_sync'
                         ELSE 'ready'
                       END AS status
                FROM service_packages sp
                CROSS JOIN routers r
                CROSS JOIN (
                    SELECT 'user-manager' AS backend
                    UNION ALL SELECT 'native-hotspot'
                    UNION ALL SELECT 'native-pppoe'
                ) b
                LEFT JOIN router_backend_package_profiles m
                  ON m.package_id = sp.id AND m.router_id = r.id AND m.backend = b.backend
                WHERE sp.is_active = 1 AND r.enabled = 1
                ORDER BY sp.name, r.name, b.backend
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function readGateway(): RouterOSReadGatewayInterface
    {
        if ($this->readGateway === null) {
            $this->resolveGateways();
        }
        return $this->readGateway;
    }

    private function writeGateway(): GuardedRouterOSWriteGatewayInterface
    {
        if ($this->writeGateway === null) {
            $this->resolveGateways();
        }
        return $this->writeGateway;
    }

    private function resolveGateways(): void
    {
        if ($this->readGateway !== null && $this->writeGateway !== null) {
            return;
        }

        $bundle = RouterConnectionResolver::gatewayBundleForRouter(
            (int) ($_POST['router_id'] ?? $_GET['router_id'] ?? 0),
            ['timeout' => 6]
        );
        $this->readGateway ??= $bundle->read;
        $this->writeGateway ??= $bundle->write;
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['package_push_flash_message'] = $message;
        $_SESSION['package_push_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'package_push_flash_' . $key;
        $value = (string) ($_SESSION[$sessionKey] ?? $default);

        unset($_SESSION[$sessionKey]);

        return $value;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}
