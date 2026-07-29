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
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use GreenNet\Services\WriteSafetyGuard;
use PDO;
use RuntimeException;
use Throwable;

class AdminUserManagerPasswordController
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

            if ($username === '') {
                throw new RuntimeException('اختر مستخدماً صحيحاً.');
            }

            $this->validateUsername($username);

            $plan = $this->buildPasswordPlan($username);

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

            $this->flash('تم فحص الجاهزية. أدخل كلمة المرور الجديدة للمتابعة.', 'success');
        } catch (Throwable $e) {
            $_SESSION['um_password_result'] = [
                'ok' => false,
                'error' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            AppLog::error('User Manager password Dry Run failed', [
                'error' => $e->getMessage(),
            ]);

            $this->flash('تعذر تجهيز العملية: ' . $e->getMessage(), 'warning');
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

            $execution = $this->executePasswordChange($username, $password, $lastPlan);

            $afterPlan = $lastPlan;
            $afterPlan['executed'] = true;
            $afterPlan['real_execution'] = true;
            $afterPlan['real_result'] = $execution;

            $_SESSION['um_password_result'] = $afterPlan;

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

    private function buildPasswordPlan(string $username): array
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
                    'password' => '<hidden>',
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

    private function executePasswordChange(string $username, string $password, array $lastPlan): array
    {
        $expectedId = trim((string) ($lastPlan['router_user_id'] ?? ''));
        $command = '/user-manager/user/set';
        $verifiedId = '';

        $result = $this->writeGateway()->execute(
            new WriteExecutionRequest(
                'change_user_manager_password',
                'user_manager_user',
                $username,
                $command,
                ['username' => $username, 'numbers' => $expectedId, 'password' => '<hidden>'],
                (int) ($lastPlan['audit_id'] ?? 0),
                true
            ),
            function (AuthorizedRouterOSWriterInterface $writer) use (
                $username,
                $password,
                $expectedId,
                $command,
                &$verifiedId
            ): array {
                $before = $this->readRouterUser($username);
                $beforeId = trim((string) ($before['user']['id'] ?? ''));

                if (empty($before['user']['found']) || $beforeId === '' || $beforeId !== $expectedId) {
                    throw new RuntimeException('User Manager target changed after the password preview.');
                }

                $writer->execute(new RouterOSWriteCommand($command, [
                    'numbers' => $beforeId,
                    'password' => $password,
                ]));

                $after = $this->readRouterUser($username);
                $verifiedId = trim((string) ($after['user']['id'] ?? ''));

                if (empty($after['user']['found']) || $verifiedId !== $beforeId) {
                    throw new RuntimeException('Password command completed, but target continuity verification failed.');
                }

                return [
                    'verified_target_continuity' => true,
                    'router_user_id' => $verifiedId,
                ];
            }
        );

        return [
            'ok' => $result->ok,
            'message' => 'Password command completed and target continuity was verified.',
            'command' => $command,
            'params' => ['numbers' => $verifiedId, 'password' => '<hidden>'],
            'verified_target_continuity' => true,
            'audit_recorded' => $result->auditRecorded,
            'audit_id' => $result->auditId,
            'audit_warning' => $result->auditWarning,
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

        try {
            $users = $this->normalizeRows($this->readGateway()->read('/user-manager/user/print', [
                '?name' => $username,
            ]));

            $user = $this->findUserRow($users, $username);

            if ($user !== null) {
                $result['user'] = [
                    'found' => true,
                    'id' => (string) ($user['.id'] ?? ''),
                    'row' => $this->projectRouterUser($user),
                    'error' => '',
                ];
            }
        } catch (Throwable $e) {
            $result['user']['error'] = $e->getMessage();
        }

        return $result;
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

    private function projectRouterUser(array $row): array
    {
        return [
            '.id' => (string) ($row['.id'] ?? ''),
            'name' => (string) ($row['name'] ?? $row['username'] ?? ''),
            'disabled' => (string) ($row['disabled'] ?? ''),
        ];
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

        $bundle = RouterConnectionResolver::gatewayBundleForCustomer(
            (string) ($_POST['username'] ?? $_GET['username'] ?? ''),
            ['timeout' => 6]
        );
        $this->readGateway ??= $bundle->read;
        $this->writeGateway ??= $bundle->write;
    }

    private function database(): PDO
    {
        return $this->databaseConnection ??= Database::connection();
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
