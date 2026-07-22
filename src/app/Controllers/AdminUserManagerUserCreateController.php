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
use GreenNet\Services\RouterOS\RouterOSGatewayBundleFactory;
use GreenNet\Services\WriteSafetyGuard;
use PDO;
use RuntimeException;
use Throwable;

class AdminUserManagerUserCreateController
{
    public function __construct(
        private ?RouterOSReadGatewayInterface $readGateway = null,
        private ?GuardedRouterOSWriteGatewayInterface $writeGateway = null,
        private ?PDO $databaseConnection = null
    ) {
    }

    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $guard = new WriteSafetyGuard();
        $guard->ensureTables();

        return View::render('admin/user_manager_user_create', [
            'title' => 'Create User Manager User',
            'customers' => $this->localCustomers(),
            'packages' => $this->localPackages(),
            'preflight' => $guard->preflight(),
            'result' => $_SESSION['um_user_create_result'] ?? null,
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

            $username = trim((string) ($_POST['username'] ?? ''));
            $packageId = (int) ($_POST['package_id'] ?? 0);

            if ($username === '') {
                throw new RuntimeException('اختر مشتركاً صحيحاً.');
            }

            if ($packageId <= 0) {
                throw new RuntimeException('اختر باقة صحيحة.');
            }

            $this->validateUsername($username);

            $plan = $this->buildCreatePlan($username, $packageId);

            $auditId = $guard->recordDryRun([
                'action' => 'create_user_manager_user',
                'dataset' => 'user_manager_user',
                'username' => $username,
                'command' => '/user-manager/user/add + /user-manager/user-profile/add',
                'params' => $plan,
                'router_response' => 'Dry Run only. No MikroTik write.',
            ]);

            $plan['audit_id'] = $auditId;

            $_SESSION['um_user_create_result'] = $plan;

            AppLog::info('User Manager user create Dry Run created', [
                'username' => $username,
                'package_id' => $packageId,
                'profile_name' => (string) ($plan['router_profile_name'] ?? ''),
                'audit_id' => $auditId,
                'can_execute_later' => !empty($plan['can_execute_later']),
            ]);

            $this->flash('تم إنشاء Dry Run لإنشاء المستخدم. لم يتم تنفيذ أي Write.', 'success');
        } catch (Throwable $e) {
            $_SESSION['um_user_create_result'] = [
                'ok' => false,
                'error' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            AppLog::error('User Manager user create Dry Run failed', [
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل Dry Run: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/user-manager-user-create');
        exit;
    }

    public function execute(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = trim((string) ($_POST['password'] ?? ''));
            $packageId = (int) ($_POST['package_id'] ?? 0);
            $confirm = trim((string) ($_POST['confirm_create'] ?? ''));

            if ($username === '') {
                throw new RuntimeException('اسم المستخدم مطلوب.');
            }

            if ($password === '') {
                throw new RuntimeException('كلمة المرور مطلوبة للتنفيذ.');
            }

            if ($packageId <= 0) {
                throw new RuntimeException('الباقة مطلوبة.');
            }

            if ($confirm !== 'CREATE') {
                throw new RuntimeException('للتنفيذ اكتب CREATE في خانة التأكيد.');
            }

            $this->validateUsername($username);
            $this->validatePassword($password);

            $lastPlan = $this->requireLastPlan($username, $packageId);

            if (empty($lastPlan['can_execute_later'])) {
                throw new RuntimeException('آخر Dry Run لا يسمح بالتنفيذ.');
            }

            $execution = $this->executeCreate(
                $username,
                $password,
                $packageId,
                (string) ($lastPlan['router_profile_name'] ?? ''),
                $lastPlan
            );

            $afterPlan = $lastPlan;
            $afterPlan['executed'] = true;
            $afterPlan['real_execution'] = true;
            $afterPlan['real_result'] = $execution;

            $_SESSION['um_user_create_result'] = $afterPlan;

            if (empty($execution['ok'])) {
                throw new RuntimeException((string) ($execution['message'] ?? 'فشل إنشاء المستخدم.'));
            }

            $this->flash('تم إنشاء مستخدم User Manager وربطه بالباقـة بنجاح.', 'success');
        } catch (Throwable $e) {
            $previous = $_SESSION['um_user_create_result'] ?? [];

            if (!is_array($previous)) {
                $previous = [];
            }

            $previous['execute_error'] = $e->getMessage();
            $previous['executed'] = false;

            $_SESSION['um_user_create_result'] = $previous;

            AppLog::error('User Manager user create execution failed', [
                'username' => (string) ($_POST['username'] ?? ''),
                'package_id' => (int) ($_POST['package_id'] ?? 0),
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل التنفيذ: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/user-manager-user-create');
        exit;
    }

    private function buildCreatePlan(string $username, int $packageId): array
    {
        $customer = $this->findCustomer($username);
        $package = $this->findPackage($packageId);

        if ($customer === null) {
            throw new RuntimeException('المشترك غير موجود داخل GreenNet.');
        }

        if ($package === null) {
            throw new RuntimeException('الباقة غير موجودة داخل GreenNet.');
        }

        $profileName = $this->routerProfileName($package);

        if ($profileName === '') {
            throw new RuntimeException('الباقة لا تحتوي اسم Profile صالح للـ MikroTik.');
        }

        $router = $this->readRouterState($username, $profileName);

        $userFound = !empty($router['user']['found']);
        $profileFound = !empty($router['profile']['found']);
        $localPackageId = (int) ($customer['package_id'] ?? 0);
        $localNeedsUpdate = $localPackageId !== $packageId;

        $operations = [];

        if (!$userFound && $profileFound) {
            $operations[] = [
                'type' => 'router_create_user_manager_user',
                'command' => '/user-manager/user/add',
                'params' => [
                    'name' => $username,
                    'password' => '<hidden>',
                ],
            ];

            $operations[] = [
                'type' => 'router_add_user_profile',
                'command' => '/user-manager/user-profile/add',
                'params' => [
                    'user' => $username,
                    'profile' => $profileName,
                ],
            ];
        }

        if ($localNeedsUpdate) {
            $operations[] = [
                'type' => 'local_update_customer_package',
                'command' => 'UPDATE customers_local SET package_id = ...',
                'params' => [
                    'username' => $username,
                    'package_id' => $packageId,
                ],
            ];
        }

        $blockReason = '';

        if ($userFound) {
            $blockReason = 'المستخدم موجود مسبقاً في MikroTik User Manager. استخدم Assign / Replace بدل Create.';
        } elseif (!$profileFound) {
            $blockReason = 'الـ Profile غير موجود في MikroTik. اعمل Push للباقة أولاً.';
        } elseif (count($operations) === 0) {
            $blockReason = 'لا توجد عمليات مطلوبة.';
        }

        return [
            'ok' => true,
            'action' => 'create_user_manager_user',
            'username' => $username,
            'package_id' => $packageId,
            'customer' => $this->sanitizeCustomer($customer),
            'package' => $this->sanitizePackage($package),
            'router_profile_name' => $profileName,
            'router' => $router,
            'user_found' => $userFound,
            'profile_found' => $profileFound,
            'local_current_package_id' => $localPackageId,
            'local_needs_update' => $localNeedsUpdate,
            'operations' => $operations,
            'operations_count' => count($operations),
            'can_execute_later' => !$userFound && $profileFound && count($operations) > 0,
            'block_reason' => $blockReason,
            'executed' => false,
            'real_execution' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => [
                'هذه العملية تنشئ User جديد داخل MikroTik User Manager.',
                'بعد الإنشاء يتم إضافة User Profile للمستخدم حسب الباقة المختارة.',
                'كلمة المرور لا يتم عرضها داخل Raw Result ولا داخل Audit.',
                'إذا كان المستخدم موجوداً مسبقاً، استخدم صفحة Assign / Replace Package.',
            ],
        ];
    }

    private function executeCreate(
        string $username,
        string $password,
        int $packageId,
        string $profileName,
        array $lastPlan
    ): array {
        $expectedProfileId = (string) ($lastPlan['router']['profile']['id'] ?? '');
        $createdUserId = '';
        $createdRelationId = '';

        $result = $this->writeGateway()->execute(
            new WriteExecutionRequest(
                'create_user_manager_user',
                'user_manager_user',
                $username,
                '/user-manager/user/add + /user-manager/user-profile/add',
                [
                    'username' => $username,
                    'password' => '<hidden>',
                    'package_id' => $packageId,
                    'profile_name' => $profileName,
                ],
                (int) ($lastPlan['audit_id'] ?? 0),
                true
            ),
            function (AuthorizedRouterOSWriterInterface $writer) use (
                $username,
                $password,
                $packageId,
                $profileName,
                $expectedProfileId,
                $lastPlan,
                &$createdUserId,
                &$createdRelationId
            ): array {
                $before = $this->readRouterState($username, $profileName);
                $currentProfileId = (string) ($before['profile']['id'] ?? '');

                if (!empty($before['user']['found'])) {
                    throw new RuntimeException('User Manager user already exists.');
                }

                if (empty($before['profile']['found']) || $currentProfileId === '') {
                    throw new RuntimeException('User Manager profile no longer exists.');
                }

                if ($expectedProfileId !== '' && $currentProfileId !== $expectedProfileId) {
                    throw new RuntimeException('User Manager profile changed after preview.');
                }

                $writer->execute(new RouterOSWriteCommand('/user-manager/user/add', [
                    'name' => $username,
                    'password' => $password,
                ]));

                $writer->execute(new RouterOSWriteCommand('/user-manager/user-profile/add', [
                    'user' => $username,
                    'profile' => $profileName,
                ]));

                $after = $this->readRouterState($username, $profileName);
                $createdUserId = (string) ($after['user']['id'] ?? '');
                $relation = $this->readUserProfileRelation($username, $profileName);
                $createdRelationId = (string) ($relation['id'] ?? '');

                if (empty($after['user']['found']) || $createdUserId === '') {
                    throw new RuntimeException('Created User Manager user verification failed.');
                }

                if (empty($relation['found']) || $createdRelationId === '') {
                    throw new RuntimeException('Created User Manager profile relation verification failed.');
                }

                if (!empty($lastPlan['local_needs_update'])) {
                    $this->updateLocalCustomerPackage($username, $packageId);
                }

                return [
                    'user_id' => $createdUserId,
                    'relation_id' => $createdRelationId,
                    'profile_name' => $profileName,
                    'verified' => true,
                ];
            }
        );

        return [
            'ok' => $result->ok,
            'message' => 'تم إنشاء المستخدم وربطه بالباقـة.',
            'operations' => array_map(
                static fn ($call): array => $call->toArray(),
                $result->calls
            ),
            'router_user_id' => $createdUserId,
            'router_user_profile_id' => $createdRelationId,
            'verified' => true,
            'audit_recorded' => $result->auditRecorded,
            'audit_id' => $result->auditId,
            'audit_warning' => $result->auditWarning,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function readRouterState(string $username, string $profileName): array
    {
        $result = [
            'user' => [
                'found' => false,
                'id' => '',
                'row' => [],
                'error' => '',
            ],
            'profile' => [
                'found' => false,
                'id' => '',
                'row' => [],
                'error' => '',
            ],
        ];

        try {
                $users = $this->normalizeRows($this->readGateway()->read('/user-manager/user/print', [
                    '?name' => $username,
                ]));

                $user = $this->findUserRow($users, $username);

                if ($user !== null) {
                    $result['user'] = [
                        'found' => true,
                        'id' => (string) ($user['.id'] ?? ''),
                        'row' => $this->projectUser($user),
                        'error' => '',
                    ];
                }
        } catch (Throwable $e) {
            $result['user']['error'] = $e->getMessage();
        }

        try {
            $profiles = $this->normalizeRows($this->readGateway()->read('/user-manager/profile/print', [
                '?name' => $profileName,
            ]));

            $profile = $this->findRowByName($profiles, $profileName);

            if ($profile !== null) {
                $result['profile'] = [
                    'found' => true,
                    'id' => (string) ($profile['.id'] ?? ''),
                    'row' => $this->projectProfile($profile),
                    'error' => '',
                ];
            }
        } catch (Throwable $e) {
            $result['profile']['error'] = $e->getMessage();
        }

        return $result;
    }

    private function readUserProfileRelation(string $username, string $profileName): array
    {
        $rows = $this->normalizeRows($this->readGateway()->read('/user-manager/user-profile/print', [
            '?user' => $username,
        ]));
        $matches = array_values(array_filter($rows, static fn (array $row): bool =>
            (string) ($row['user'] ?? '') === $username
            && (string) ($row['profile'] ?? '') === $profileName
        ));

        if (count($matches) !== 1) {
            return ['found' => false, 'id' => '', 'row' => []];
        }

        $row = $matches[0];
        return [
            'found' => true,
            'id' => (string) ($row['.id'] ?? ''),
            'row' => $this->projectRelation($row),
        ];
    }

    private function localCustomers(): array
    {
        $this->ensureCustomersLocalColumns();

        try {
            $stmt = $this->database()->query("
                SELECT *
                FROM customers_local
                ORDER BY username ASC
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function localPackages(): array
    {
        $this->ensureServicePackagesTable();

        try {
            $stmt = $this->database()->query("
                SELECT *
                FROM service_packages
                WHERE COALESCE(is_active, 1) = 1
                ORDER BY id DESC
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function findCustomer(string $username): ?array
    {
        $this->ensureCustomersLocalColumns();

        $stmt = $this->database()->prepare("
            SELECT *
            FROM customers_local
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findPackage(int $id): ?array
    {
        $this->ensureServicePackagesTable();

        $stmt = $this->database()->prepare("
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

    private function updateLocalCustomerPackage(string $username, int $packageId): void
    {
        $username = trim($username);

        if ($username === '' || $packageId <= 0) {
            return;
        }

        $this->ensureCustomersLocalColumns();

        $stmt = $this->database()->prepare("
            UPDATE customers_local
            SET
                package_id = :package_id,
                updated_at = :updated_at
            WHERE username = :username
        ");

        $stmt->execute([
            ':package_id' => $packageId,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':username' => $username,
        ]);
    }

    private function ensureCustomersLocalColumns(): void
    {
        $pdo = $this->database();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS customers_local (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                full_name TEXT DEFAULT '',
                phone TEXT DEFAULT '',
                payment_status TEXT DEFAULT 'unknown',
                package_id INTEGER DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        $columns = $this->columns('customers_local');

        $required = [
            'full_name' => "ALTER TABLE customers_local ADD COLUMN full_name TEXT DEFAULT ''",
            'phone' => "ALTER TABLE customers_local ADD COLUMN phone TEXT DEFAULT ''",
            'payment_status' => "ALTER TABLE customers_local ADD COLUMN payment_status TEXT DEFAULT 'unknown'",
            'package_id' => "ALTER TABLE customers_local ADD COLUMN package_id INTEGER DEFAULT 0",
            'created_at' => "ALTER TABLE customers_local ADD COLUMN created_at TEXT",
            'updated_at' => "ALTER TABLE customers_local ADD COLUMN updated_at TEXT",
        ];

        foreach ($required as $column => $sql) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec($sql);
            }
        }
    }

    private function ensureServicePackagesTable(): void
    {
        $pdo = $this->database();

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
            $rows = $this->database()->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC);

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

    private function routerProfileName(array $package): string
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

    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['user']) || isset($rows['profile'])) {
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

    private function findUserRow(array $rows, string $username): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ((string) ($row['name'] ?? '') === $username || (string) ($row['username'] ?? '') === $username) {
                return $row;
            }
        }

        if (count($rows) === 1 && is_array($rows[0] ?? null)) {
            return $rows[0];
        }

        return null;
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

    private function sanitizeCustomer(array $customer): array
    {
        return [
            'id' => (int) ($customer['id'] ?? 0),
            'username' => (string) ($customer['username'] ?? ''),
            'full_name' => (string) ($customer['full_name'] ?? $customer['display_name'] ?? ''),
            'phone' => (string) ($customer['phone'] ?? ''),
            'payment_status' => (string) ($customer['payment_status'] ?? ''),
            'package_id' => (int) ($customer['package_id'] ?? 0),
        ];
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
        ];
    }

    private function projectUser(array $row): array
    {
        return [
            '.id' => (string) ($row['.id'] ?? ''),
            'name' => (string) ($row['name'] ?? $row['username'] ?? ''),
            'disabled' => (string) ($row['disabled'] ?? ''),
        ];
    }

    private function projectProfile(array $row): array
    {
        return [
            '.id' => (string) ($row['.id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
        ];
    }

    private function projectRelation(array $row): array
    {
        return [
            '.id' => (string) ($row['.id'] ?? ''),
            'user' => (string) ($row['user'] ?? ''),
            'profile' => (string) ($row['profile'] ?? ''),
        ];
    }

    private function requireLastPlan(string $username, int $packageId): array
    {
        $plan = $_SESSION['um_user_create_result'] ?? null;

        if (!is_array($plan)) {
            throw new RuntimeException('نفّذ Dry Run ناجح قبل التنفيذ.');
        }

        if ((string) ($plan['username'] ?? '') !== $username) {
            throw new RuntimeException('اسم المستخدم لا يطابق آخر Dry Run.');
        }

        if ((int) ($plan['package_id'] ?? 0) !== $packageId) {
            throw new RuntimeException('الباقة لا تطابق آخر Dry Run.');
        }

        if ((string) ($plan['action'] ?? '') !== 'create_user_manager_user') {
            throw new RuntimeException('نوع العملية لا يطابق آخر Dry Run.');
        }

        if (!empty($plan['executed'])) {
            throw new RuntimeException('آخر Dry Run تم تنفيذه مسبقاً. أعد إنشاء Dry Run جديد.');
        }

        return $plan;
    }

    private function validateUsername(string $username): void
    {
        if (strlen($username) > 128) {
            throw new RuntimeException('اسم المستخدم طويل جداً.');
        }

        if (preg_match('/[\r\n\t]/', $username)) {
            throw new RuntimeException('اسم المستخدم يحتوي رموز غير مسموحة.');
        }
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 3) {
            throw new RuntimeException('كلمة المرور قصيرة جداً.');
        }

        if (strlen($password) > 128) {
            throw new RuntimeException('كلمة المرور طويلة جداً.');
        }

        if (preg_match('/[\r\n\t]/', $password)) {
            throw new RuntimeException('كلمة المرور تحتوي رموز غير مسموحة.');
        }
    }

    private function readGateway(): RouterOSReadGatewayInterface
    {
        $this->resolveGateways();
        return $this->readGateway;
    }

    private function writeGateway(): GuardedRouterOSWriteGatewayInterface
    {
        $this->resolveGateways();
        return $this->writeGateway;
    }

    private function resolveGateways(): void
    {
        if ($this->readGateway !== null && $this->writeGateway !== null) {
            return;
        }
        $bundle = RouterOSGatewayBundleFactory::create(['timeout' => 6]);
        $this->readGateway ??= $bundle->read;
        $this->writeGateway ??= $bundle->write;
    }

    private function database(): PDO
    {
        return $this->databaseConnection ??= Database::connection();
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['um_user_create_flash_message'] = $message;
        $_SESSION['um_user_create_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'um_user_create_flash_' . $key;
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
