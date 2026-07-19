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

class AdminUserManagerPasswordController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $guard = new WriteSafetyGuard();
        $guard->ensureTables();

        return View::render('admin/user_manager_password', [
            'title' => 'Change User Manager Password',
            'customers' => $this->localCustomers(),
            'preflight' => $guard->preflight(),
            'result' => $_SESSION['um_password_result'] ?? null,
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
            $password = trim((string) ($_POST['password'] ?? ''));

            if ($username === '') {
                throw new RuntimeException('اختر مستخدماً صحيحاً.');
            }

            if ($password === '') {
                throw new RuntimeException('كلمة المرور الجديدة مطلوبة.');
            }

            $this->validateUsername($username);
            $this->validatePassword($password);

            $plan = $this->buildPasswordPlan($username, $password, false);

            $auditId = $guard->recordDryRun([
                'action' => 'change_user_manager_password',
                'dataset' => 'user_manager_user',
                'username' => $username,
                'command' => '/user-manager/user/set',
                'params' => $plan,
                'router_response' => 'Dry Run only. No MikroTik write.',
            ]);

            $plan['audit_id'] = $auditId;

            $_SESSION['um_password_result'] = $plan;

            AppLog::info('User Manager password Dry Run created', [
                'username' => $username,
                'audit_id' => $auditId,
                'can_execute_later' => !empty($plan['can_execute_later']),
            ]);

            $this->flash('تم إنشاء Dry Run لتغيير كلمة المرور. لم يتم تنفيذ أي Write.', 'success');
        } catch (Throwable $e) {
            $_SESSION['um_password_result'] = [
                'ok' => false,
                'error' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            AppLog::error('User Manager password Dry Run failed', [
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل Dry Run: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/user-manager-password');
        exit;
    }

    public function execute(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = trim((string) ($_POST['password'] ?? ''));
            $confirm = trim((string) ($_POST['confirm_password'] ?? ''));

            if ($username === '') {
                throw new RuntimeException('اسم المستخدم مطلوب.');
            }

            if ($password === '') {
                throw new RuntimeException('كلمة المرور الجديدة مطلوبة.');
            }

            if ($confirm !== 'PASSWORD') {
                throw new RuntimeException('للتنفيذ اكتب PASSWORD في خانة التأكيد.');
            }

            $this->validateUsername($username);
            $this->validatePassword($password);

            $lastPlan = $this->requireLastPlan($username);

            if (empty($lastPlan['can_execute_later'])) {
                throw new RuntimeException('آخر Dry Run لا يسمح بالتنفيذ.');
            }

            $guard = new WriteSafetyGuard();
            $guard->ensureTables();
            $guard->assertRealWriteAllowed([
                'confirmed' => true,
            ]);

            $freshPlan = $this->buildPasswordPlan($username, $password, true);

            if (empty($freshPlan['can_execute_later'])) {
                throw new RuntimeException((string) ($freshPlan['block_reason'] ?? 'الخطة لم تعد قابلة للتنفيذ.'));
            }

            $execution = $this->executePasswordPlan($freshPlan);

            $afterPlan = $this->buildPasswordPlan($username, '', false);
            $afterPlan['executed'] = true;
            $afterPlan['real_execution'] = true;
            $afterPlan['real_result'] = $execution;

            $_SESSION['um_password_result'] = $afterPlan;

            $guard->recordRealAttempt([
                'action' => 'change_user_manager_password',
                'dataset' => 'user_manager_user',
                'username' => $username,
                'command' => '/user-manager/user/set',
                'params' => [
                    'username' => $username,
                    'password' => '<hidden>',
                    'dry_run_audit_id' => (int) ($lastPlan['audit_id'] ?? 0),
                    'confirmed' => true,
                ],
                'executed' => 1,
                'success' => !empty($execution['ok']) ? 1 : 0,
                'router_response' => $this->jsonString($execution),
            ]);

            if (empty($execution['ok'])) {
                throw new RuntimeException((string) ($execution['message'] ?? 'فشل تغيير كلمة المرور.'));
            }

            $this->flash('تم تغيير كلمة مرور مستخدم User Manager بنجاح.', 'success');
        } catch (Throwable $e) {
            $previous = $_SESSION['um_password_result'] ?? [];

            if (!is_array($previous)) {
                $previous = [];
            }

            $previous['execute_error'] = $e->getMessage();
            $previous['executed'] = false;

            $_SESSION['um_password_result'] = $previous;

            AppLog::error('User Manager password execution failed', [
                'username' => (string) ($_POST['username'] ?? ''),
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل التنفيذ: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/user-manager-password');
        exit;
    }

    private function buildPasswordPlan(string $username, string $password, bool $realPassword): array
    {
        $customer = $this->findCustomer($username);
        $router = $this->readRouterUser($username);

        $userFound = !empty($router['user']['found']);
        $userId = (string) ($router['user']['id'] ?? '');

        $operations = [];

        if ($userFound && $userId !== '') {
            $operations[] = [
                'type' => 'router_change_user_password',
                'command' => '/user-manager/user/set',
                'params' => [
                    'numbers' => $userId,
                    'password' => $realPassword ? $password : '<hidden>',
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
            'action' => 'change_user_manager_password',
            'username' => $username,
            'customer' => $customer !== null ? $this->sanitizeCustomer($customer) : null,
            'router' => $router,
            'user_found' => $userFound,
            'router_user_id' => $userId,
            'operations' => $operations,
            'operations_count' => count($operations),
            'can_execute_later' => $userFound && $userId !== '' && count($operations) > 0,
            'block_reason' => $blockReason,
            'executed' => false,
            'real_execution' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => [
                'هذه العملية تغيّر كلمة مرور User Manager داخل MikroTik فقط.',
                'لا تغيّر كلمة مرور دخول المشترك إلى لوحة GreenNet المحلية.',
                'كلمة المرور لا تظهر في Raw Result ولا في Audit.',
            ],
        ];
    }

    private function executePasswordPlan(array $plan): array
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

                if ($type !== 'router_change_user_password') {
                    continue;
                }

                $response = $client->comm($command, $params);

                $executed[] = [
                    'type' => $type,
                    'command' => $command,
                    'params' => $this->maskSensitiveParams($params),
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
            'message' => 'تم تغيير كلمة المرور.',
            'operations' => $executed,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function readRouterUser(string $username): array
    {
        $result = [
            'user' => [
                'found' => false,
                'id' => '',
                'row' => [],
                'error' => '',
            ],
        ];

        $client = new RouterOSApiClient([
            'timeout' => 6,
        ]);

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
        } finally {
            $client->disconnect();
        }

        return $result;
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

        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['username'])) {
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

    private function maskSensitiveParams(array $params): array
    {
        foreach ($params as $key => $value) {
            $lower = strtolower((string) $key);

            if (str_contains($lower, 'password') || str_contains($lower, 'pass') || str_contains($lower, 'secret')) {
                $params[$key] = '<hidden>';
            }
        }

        return $params;
    }

    private function requireLastPlan(string $username): array
    {
        $plan = $_SESSION['um_password_result'] ?? null;

        if (!is_array($plan)) {
            throw new RuntimeException('نفّذ Dry Run ناجح قبل التنفيذ.');
        }

        if ((string) ($plan['username'] ?? '') !== $username) {
            throw new RuntimeException('اسم المستخدم لا يطابق آخر Dry Run.');
        }

        if ((string) ($plan['action'] ?? '') !== 'change_user_manager_password') {
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

    private function jsonString(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['um_password_flash_message'] = $message;
        $_SESSION['um_password_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'um_password_flash_' . $key;
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