<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use GreenNet\Services\WriteSafetyGuard;
use PDO;
use RuntimeException;
use Throwable;

class AdminUserManagerUserDeleteController
{
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

            $guard = new WriteSafetyGuard();
            $guard->ensureTables();
            $guard->assertRealWriteAllowed([
                'confirmed' => true,
            ]);

            $freshPlan = $this->buildDeletePlan($username);

            if (empty($freshPlan['can_execute_later'])) {
                throw new RuntimeException((string) ($freshPlan['block_reason'] ?? 'الخطة لم تعد قابلة للتنفيذ.'));
            }

            $execution = $this->executeDeletePlan($freshPlan);

            $afterPlan = $this->buildDeletePlan($username);
            $afterPlan['executed'] = true;
            $afterPlan['real_execution'] = true;
            $afterPlan['real_result'] = $execution;

            $_SESSION['um_user_delete_result'] = $afterPlan;

            $guard->recordRealAttempt([
                'action' => 'delete_user_manager_user',
                'dataset' => 'user_manager_user',
                'username' => $username,
                'command' => '/user-manager/session/remove + /user-manager/user-profile/remove + /user-manager/user/remove',
                'params' => [
                    'username' => $username,
                    'dry_run_audit_id' => (int) ($lastPlan['audit_id'] ?? 0),
                    'confirmed' => true,
                ],
                'executed' => 1,
                'success' => !empty($execution['ok']) ? 1 : 0,
                'router_response' => $this->jsonString($execution),
            ]);

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

        $userProfiles = is_array($router['user_profiles']['raw_rows'] ?? null)
            ? $router['user_profiles']['raw_rows']
            : [];

        $sessions = is_array($router['sessions']['raw_rows'] ?? null)
            ? $router['sessions']['raw_rows']
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

    private function executeDeletePlan(array $plan): array
    {
        $operations = is_array($plan['operations'] ?? null) ? $plan['operations'] : [];

        if (empty($operations)) {
            return [
                'ok' => false,
                'message' => 'لا توجد عمليات للتنفيذ.',
                'operations' => [],
            ];
        }

        $executed = [];
        $client = new RouterOSApiClient([
            'timeout' => 6,
        ]);

        try {
            foreach ($operations as $operation) {
                if (!is_array($operation)) {
                    continue;
                }

                $type = (string) ($operation['type'] ?? '');
                $command = (string) ($operation['command'] ?? '');
                $params = is_array($operation['params'] ?? null) ? $operation['params'] : [];

                if (!in_array($type, [
                    'router_remove_user_session',
                    'router_remove_user_profile',
                    'router_remove_user',
                ], true)) {
                    continue;
                }

                $response = $client->comm($command, $params);

                $executed[] = [
                    'type' => $type,
                    'command' => $command,
                    'params' => $params,
                    'response' => $response,
                    'ok' => true,
                ];
            }
        } catch (Throwable $e) {
            $executed[] = [
                'ok' => false,
                'error' => $e->getMessage(),
            ];

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'operations' => $executed,
            ];
        } finally {
            $client->disconnect();
        }

        return [
            'ok' => true,
            'message' => 'تم حذف المستخدم من MikroTik User Manager.',
            'operations' => $executed,
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
                'raw_rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
            'sessions' => [
                'rows' => [],
                'raw_rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
        ];

        $client = new RouterOSApiClient([
            'timeout' => 6,
        ]);

        try {
            try {
                $users = $this->normalizeRows($client->comm('/user-manager/user/print', [
                    '?name' => $username,
                ]));

                $user = $this->findUserRow($users, $username);

                if ($user !== null) {
                    $result['user'] = [
                        'found' => true,
                        'id' => (string) ($user['.id'] ?? ''),
                        'row' => $this->sanitizeRowForDisplay($user),
                        'error' => '',
                    ];
                }
            } catch (Throwable $e) {
                $result['user']['error'] = $e->getMessage();
            }

            try {
                $profiles = $this->normalizeRows($client->comm('/user-manager/user-profile/print', [
                    '?user' => $username,
                ]));

                $profiles = $this->filterRowsForUsername($profiles, $username);

                $result['user_profiles'] = [
                    'rows' => $this->sanitizeRows($profiles),
                    'raw_rows' => $profiles,
                    'rows_count' => count($profiles),
                    'error' => '',
                ];
            } catch (Throwable $e) {
                $result['user_profiles']['error'] = $e->getMessage();
            }

            try {
                $sessions = $this->normalizeRows($client->comm('/user-manager/session/print', [
                    '?user' => $username,
                ]));

                $sessions = $this->filterRowsForUsername($sessions, $username);

                if (count($sessions) === 0) {
                    $allSessions = $this->normalizeRows($client->comm('/user-manager/session/print'));
                    $sessions = $this->filterRowsForUsername($allSessions, $username);
                }

                $result['sessions'] = [
                    'rows' => $this->sanitizeRows($sessions),
                    'raw_rows' => $sessions,
                    'rows_count' => count($sessions),
                    'error' => '',
                ];
            } catch (Throwable $e) {
                $result['sessions']['error'] = $e->getMessage();
            }
        } finally {
            $client->disconnect();
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
            $stmt = Database::connection()->query("
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

        $stmt = Database::connection()->prepare("
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
        $pdo = Database::connection();

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

    private function sanitizeRows(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->sanitizeRowForDisplay($row);
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
        if (isset($router['user_profiles']['raw_rows'])) {
            unset($router['user_profiles']['raw_rows']);
        }

        if (isset($router['sessions']['raw_rows'])) {
            unset($router['sessions']['raw_rows']);
        }

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

    private function jsonString(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
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