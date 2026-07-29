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

class AdminUserManagerUserDeleteController
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

        return View::render('admin/user_manager_user_delete', [
            'title' => 'Delete User Manager User',
            'customers' => $this->localCustomers(),
            'preflight' => $guard->preflight(),
            'result' => $_SESSION['um_user_delete_result'] ?? null,
            'message' => $this->consumeFlash('message'),
            'message_type' => $this->consumeFlash('type', 'success'),
            'requested_username' => trim((string) ($_GET['username'] ?? '')),
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

            if ($username === '') {
                throw new RuntimeException('اختر مستخدماً صحيحاً.');
            }

            $this->validateUsername($username);

            $plan = $this->buildDeletePlan($username);

            $auditId = $guard->recordDryRun([
                'action' => 'delete_user_manager_user',
                'dataset' => 'user_manager_user',
                'username' => $username,
                'command' => '/user-manager/session/remove + /user-manager/user-profile/remove + /user-manager/user/remove',
                'params' => $plan,
                'router_response' => 'Dry Run only. No MikroTik write.',
            ]);

            $plan['audit_id'] = $auditId;

            $_SESSION['um_user_delete_result'] = $plan;

            AppLog::info('User Manager user delete Dry Run created', [
                'username' => $username,
                'audit_id' => $auditId,
                'operations_count' => (int) ($plan['operations_count'] ?? 0),
                'can_execute_later' => !empty($plan['can_execute_later']),
            ]);

            $this->flash('تم إنشاء Dry Run لحذف مستخدم User Manager. لم يتم تنفيذ أي Write.', 'success');
        } catch (Throwable $e) {
            $_SESSION['um_user_delete_result'] = [
                'ok' => false,
                'error' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            AppLog::error('User Manager user delete Dry Run failed', [
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل Dry Run: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/user-manager-user-delete');
        exit;
    }

    public function execute(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $username = trim((string) ($_POST['username'] ?? ''));
            $confirm = trim((string) ($_POST['confirm_delete'] ?? ''));

            if ($username === '') {
                throw new RuntimeException('اسم المستخدم مطلوب.');
            }

            if ($confirm !== 'DELETE') {
                throw new RuntimeException('للتنفيذ اكتب DELETE في خانة التأكيد.');
            }

            $this->validateUsername($username);

            $lastPlan = $this->requireLastPlan($username);

            if (empty($lastPlan['can_execute_later'])) {
                throw new RuntimeException('آخر Dry Run لا يسمح بالتنفيذ.');
            }

            $execution = $this->executeDelete($username, $lastPlan);

            $afterPlan = $lastPlan;
            $afterPlan['executed'] = true;
            $afterPlan['real_execution'] = true;
            $afterPlan['real_result'] = $execution;

            $_SESSION['um_user_delete_result'] = $afterPlan;

            if (empty($execution['ok'])) {
                throw new RuntimeException((string) ($execution['message'] ?? 'فشل حذف مستخدم User Manager.'));
            }

            $this->flash('تم حذف مستخدم User Manager وتنظيف ارتباطاته بنجاح.', 'success');
        } catch (Throwable $e) {
            $previous = $_SESSION['um_user_delete_result'] ?? [];

            if (!is_array($previous)) {
                $previous = [];
            }

            $previous['execute_error'] = $e->getMessage();
            $previous['executed'] = false;

            $_SESSION['um_user_delete_result'] = $previous;

            AppLog::error('User Manager user delete execution failed', [
                'username' => (string) ($_POST['username'] ?? ''),
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل التنفيذ: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/user-manager-user-delete');
        exit;
    }

    private function buildDeletePlan(string $username): array
    {
        $customer = $this->findCustomer($username);
        $router = $this->readRouterState($username);

        $userFound = !empty($router['user']['found']);
        $userId = (string) ($router['user']['id'] ?? '');

        $userProfiles = is_array($router['user_profiles']['id_rows'] ?? null)
            ? $router['user_profiles']['id_rows']
            : [];

        $sessions = is_array($router['sessions']['id_rows'] ?? null)
            ? $router['sessions']['id_rows']
            : [];

        $operations = [];

        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }

            $id = (string) ($session['.id'] ?? '');

            if ($id === '') {
                continue;
            }

            $operations[] = [
                'type' => 'router_remove_user_session',
                'command' => '/user-manager/session/remove',
                'params' => [
                    'numbers' => $id,
                ],
                'display' => $this->sessionDisplay($session),
            ];
        }

        foreach ($userProfiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $id = (string) ($profile['.id'] ?? '');

            if ($id === '') {
                continue;
            }

            $operations[] = [
                'type' => 'router_remove_user_profile',
                'command' => '/user-manager/user-profile/remove',
                'params' => [
                    'numbers' => $id,
                ],
                'display' => [
                    'profile' => (string) ($profile['profile'] ?? ''),
                    'state' => (string) ($profile['state'] ?? ''),
                    'end_time' => (string) ($profile['end-time'] ?? ''),
                ],
            ];
        }

        if ($userFound && $userId !== '') {
            $operations[] = [
                'type' => 'router_remove_user',
                'command' => '/user-manager/user/remove',
                'params' => [
                    'numbers' => $userId,
                ],
            ];
        }

        $blockReason = '';

        if (!$userFound) {
            $blockReason = 'المستخدم غير موجود في MikroTik User Manager.';
        } elseif ($userId === '') {
            $blockReason = 'لم يتم العثور على ID صالح للمستخدم داخل MikroTik.';
        } elseif (count($operations) === 0) {
            $blockReason = 'لا توجد عمليات مطلوبة.';
        }

        return [
            'ok' => true,
            'action' => 'delete_user_manager_user',
            'username' => $username,
            'customer' => $customer !== null ? $this->sanitizeCustomer($customer) : null,
            'router' => $this->sanitizeRouterStateForSession($router),
            'user_found' => $userFound,
            'router_user_id' => $userId,
            'sessions_count' => count($sessions),
            'user_profiles_count' => count($userProfiles),
            'operations' => $operations,
            'operations_count' => count($operations),
            'can_execute_later' => $userFound && $userId !== '' && count($operations) > 0,
            'block_reason' => $blockReason,
            'executed' => false,
            'real_execution' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => [
                'هذه العملية تحذف مستخدم MikroTik User Manager فقط.',
                'لا تحذف المشترك من قاعدة GreenNet المحلية.',
                'يتم حذف user-profile entries قبل حذف المستخدم.',
                'إذا وُجدت sessions للمستخدم سيتم حذفها أولاً.',
            ],
        ];
    }

    private function executeDelete(string $username, array $plan): array
    {
        $operations = is_array($plan['operations'] ?? null) ? $plan['operations'] : [];
        $expectedUserId = (string) ($plan['router_user_id'] ?? '');
        $result = $this->writeGateway()->execute(
            new WriteExecutionRequest(
                'delete_user_manager_user',
                'user_manager_user',
                $username,
                '/user-manager/session/remove + /user-manager/user-profile/remove + /user-manager/user/remove',
                ['username' => $username, 'user_id' => $expectedUserId],
                (int) ($plan['audit_id'] ?? 0),
                true
            ),
            function (AuthorizedRouterOSWriterInterface $writer) use ($username, $operations, $expectedUserId): array {
                $current = $this->readRouterState($username);
                $currentUserId = (string) ($current['user']['id'] ?? '');
                if (empty($current['user']['found']) || $currentUserId === '' || $currentUserId !== $expectedUserId) {
                    throw new RuntimeException('User Manager delete target changed after preview.');
                }

                $currentIds = [];
                foreach (array_merge(
                    $current['sessions']['id_rows'] ?? [],
                    $current['user_profiles']['id_rows'] ?? []
                ) as $row) {
                    $currentIds[(string) ($row['.id'] ?? '')] = true;
                }

                foreach ($operations as $operation) {
                    $command = (string) ($operation['command'] ?? '');
                    $id = (string) ($operation['params']['numbers'] ?? '');
                    if ($id === '') {
                        throw new RuntimeException('RouterOS delete operation has no exact identifier.');
                    }
                    if ($command !== '/user-manager/user/remove' && empty($currentIds[$id])) {
                        throw new RuntimeException('RouterOS delete child target changed after preview.');
                    }
                    if ($command === '/user-manager/user/remove' && $id !== $currentUserId) {
                        throw new RuntimeException('RouterOS delete user identifier mismatch.');
                    }
                    $writer->execute(new RouterOSWriteCommand($command, ['numbers' => $id]));
                }

                $after = $this->readRouterState($username);
                if (!empty($after['user']['found'])
                    || (int) ($after['sessions']['rows_count'] ?? 0) !== 0
                    || (int) ($after['user_profiles']['rows_count'] ?? 0) !== 0) {
                    throw new RuntimeException('User Manager delete verification failed.');
                }
                return ['verified_absent' => true, 'user_id' => $currentUserId];
            }
        );

        return [
            'ok' => $result->ok,
            'message' => 'تم حذف المستخدم من MikroTik User Manager.',
            'operations' => array_map(static fn ($call): array => $call->toArray(), $result->calls),
            'verified_absent' => true,
            'audit_recorded' => $result->auditRecorded,
            'audit_id' => $result->auditId,
            'audit_warning' => $result->auditWarning,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function readRouterState(string $username): array
    {
        $result = [
            'user' => [
                'found' => false,
                'id' => '',
                'row' => [],
                'error' => '',
            ],
            'user_profiles' => [
                'rows' => [],
                'id_rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
            'sessions' => [
                'rows' => [],
                'id_rows' => [],
                'rows_count' => 0,
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
                $profiles = $this->normalizeRows($this->readGateway()->read('/user-manager/user-profile/print', [
                    '?user' => $username,
                ]));

                $profiles = $this->filterRowsForUsername($profiles, $username);

                $result['user_profiles'] = [
                    'rows' => $this->projectRelations($profiles),
                    'id_rows' => $this->projectRelations($profiles),
                    'rows_count' => count($profiles),
                    'error' => '',
                ];
            } catch (Throwable $e) {
                $result['user_profiles']['error'] = $e->getMessage();
            }

            try {
                $sessions = $this->normalizeRows($this->readGateway()->read('/user-manager/session/print', [
                    '?user' => $username,
                ]));

                $sessions = $this->filterRowsForUsername($sessions, $username);

                if (count($sessions) === 0) {
                    $allSessions = $this->normalizeRows($this->readGateway()->read('/user-manager/session/print'));
                    $sessions = $this->filterRowsForUsername($allSessions, $username);
                }

                $result['sessions'] = [
                    'rows' => $this->projectSessions($sessions),
                    'id_rows' => $this->projectSessions($sessions),
                    'rows_count' => count($sessions),
                    'error' => '',
                ];
            } catch (Throwable $e) {
                $result['sessions']['error'] = $e->getMessage();
            }
        return $result;
    }

    private function filterRowsForUsername(array $rows, string $username): array
    {
        $usernameLower = strtolower($username);
        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $candidates = [
                (string) ($row['user'] ?? ''),
                (string) ($row['username'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['user-name'] ?? ''),
                (string) ($row['customer'] ?? ''),
                (string) ($row['owner'] ?? ''),
            ];

            foreach ($candidates as $candidate) {
                if (strtolower(trim($candidate)) === $usernameLower) {
                    $out[] = $row;
                    break;
                }
            }
        }

        return $out;
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

    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['user']) || isset($rows['username'])) {
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

    private function projectRelations(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = [
                    '.id' => (string) ($row['.id'] ?? ''),
                    'user' => (string) ($row['user'] ?? ''),
                    'profile' => (string) ($row['profile'] ?? ''),
                ];
            }
        }
        return $out;
    }

    private function projectSessions(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = [
                    '.id' => (string) ($row['.id'] ?? ''),
                    'user' => (string) ($row['user'] ?? $row['username'] ?? ''),
                ];
            }
        }
        return $out;
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

    private function projectUser(array $row): array
    {
        return [
            '.id' => (string) ($row['.id'] ?? ''),
            'name' => (string) ($row['name'] ?? $row['username'] ?? ''),
            'disabled' => (string) ($row['disabled'] ?? ''),
        ];
    }

    private function sessionDisplay(array $session): array
    {
        return [
            'id' => (string) ($session['.id'] ?? ''),
            'user' => (string) ($session['user'] ?? $session['username'] ?? ''),
            'from_time' => (string) ($session['from-time'] ?? $session['started'] ?? ''),
            'till_time' => (string) ($session['till-time'] ?? $session['ended'] ?? ''),
            'download' => (string) ($session['download'] ?? $session['download-used'] ?? ''),
            'upload' => (string) ($session['upload'] ?? $session['upload-used'] ?? ''),
            'active' => (string) ($session['active'] ?? ''),
        ];
    }

    private function sanitizeRouterStateForSession(array $router): array
    {
        return $router;
    }

    private function requireLastPlan(string $username): array
    {
        $plan = $_SESSION['um_user_delete_result'] ?? null;

        if (!is_array($plan)) {
            throw new RuntimeException('نفّذ Dry Run ناجح قبل التنفيذ.');
        }

        if ((string) ($plan['username'] ?? '') !== $username) {
            throw new RuntimeException('اسم المستخدم لا يطابق آخر Dry Run.');
        }

        if ((string) ($plan['action'] ?? '') !== 'delete_user_manager_user') {
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
        $_SESSION['um_user_delete_flash_message'] = $message;
        $_SESSION['um_user_delete_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'um_user_delete_flash_' . $key;
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
