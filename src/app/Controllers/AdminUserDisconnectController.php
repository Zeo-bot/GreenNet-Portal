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

class AdminUserDisconnectController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $guard = new WriteSafetyGuard();
        $guard->ensureTables();

        return View::render('admin/user_disconnect', [
            'title' => 'Disconnect Active Sessions',
            'customers' => $this->localCustomers(),
            'preflight' => $guard->preflight(),
            'result' => $_SESSION['user_disconnect_result'] ?? null,
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

            $plan = $this->buildDisconnectPlan($username);

            $auditId = $guard->recordDryRun([
                'action' => 'disconnect_active_sessions',
                'dataset' => 'router_active_sessions',
                'username' => $username,
                'command' => '/ip/hotspot/active/remove + /ppp/active/remove',
                'params' => $plan,
                'router_response' => 'Dry Run only. No MikroTik write.',
            ]);

            $plan['audit_id'] = $auditId;

            $_SESSION['user_disconnect_result'] = $plan;

            AppLog::info('Disconnect sessions Dry Run created', [
                'username' => $username,
                'audit_id' => $auditId,
                'operations_count' => (int) ($plan['operations_count'] ?? 0),
            ]);

            $this->flash('تم إنشاء Dry Run لفصل الجلسات النشطة. لم يتم تنفيذ أي Write.', 'success');
        } catch (Throwable $e) {
            $_SESSION['user_disconnect_result'] = [
                'ok' => false,
                'error' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            AppLog::error('Disconnect sessions Dry Run failed', [
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل Dry Run: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/user-disconnect');
        exit;
    }

    public function execute(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $username = trim((string) ($_POST['username'] ?? ''));
            $confirm = trim((string) ($_POST['confirm_disconnect'] ?? ''));

            if ($username === '') {
                throw new RuntimeException('اسم المستخدم مطلوب.');
            }

            if ($confirm !== 'DISCONNECT') {
                throw new RuntimeException('للتنفيذ اكتب DISCONNECT في خانة التأكيد.');
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

            $freshPlan = $this->buildDisconnectPlan($username);

            if (empty($freshPlan['can_execute_later'])) {
                throw new RuntimeException((string) ($freshPlan['block_reason'] ?? 'لا توجد جلسات نشطة حالياً.'));
            }

            $execution = $this->executeDisconnectPlan($freshPlan);

            $afterPlan = $this->buildDisconnectPlan($username);
            $afterPlan['executed'] = true;
            $afterPlan['real_execution'] = true;
            $afterPlan['real_result'] = $execution;

            $_SESSION['user_disconnect_result'] = $afterPlan;

            $guard->recordRealAttempt([
                'action' => 'disconnect_active_sessions',
                'dataset' => 'router_active_sessions',
                'username' => $username,
                'command' => '/ip/hotspot/active/remove + /ppp/active/remove',
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
                throw new RuntimeException((string) ($execution['message'] ?? 'فشل فصل الجلسات.'));
            }

            $this->flash('تم فصل الجلسات النشطة بنجاح.', 'success');
        } catch (Throwable $e) {
            $previous = $_SESSION['user_disconnect_result'] ?? [];

            if (!is_array($previous)) {
                $previous = [];
            }

            $previous['execute_error'] = $e->getMessage();
            $previous['executed'] = false;

            $_SESSION['user_disconnect_result'] = $previous;

            AppLog::error('Disconnect sessions execution failed', [
                'username' => (string) ($_POST['username'] ?? ''),
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل التنفيذ: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/user-disconnect');
        exit;
    }

    private function buildDisconnectPlan(string $username): array
    {
        $customer = $this->findCustomer($username);
        $router = $this->readRouterSessions($username);

        $hotspotRows = is_array($router['hotspot_active']['raw_rows'] ?? null)
            ? $router['hotspot_active']['raw_rows']
            : [];

        $pppRows = is_array($router['ppp_active']['raw_rows'] ?? null)
            ? $router['ppp_active']['raw_rows']
            : [];

        $operations = [];

        foreach ($hotspotRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (string) ($row['.id'] ?? '');

            if ($id === '') {
                continue;
            }

            $operations[] = [
                'type' => 'router_remove_hotspot_active',
                'command' => '/ip/hotspot/active/remove',
                'params' => [
                    'numbers' => $id,
                ],
                'display' => $this->hotspotDisplay($row),
            ];
        }

        foreach ($pppRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (string) ($row['.id'] ?? '');

            if ($id === '') {
                continue;
            }

            $operations[] = [
                'type' => 'router_remove_ppp_active',
                'command' => '/ppp/active/remove',
                'params' => [
                    'numbers' => $id,
                ],
                'display' => $this->pppDisplay($row),
            ];
        }

        $blockReason = '';

        if (count($operations) === 0) {
            $blockReason = 'لا توجد جلسات Hotspot أو PPP نشطة لهذا المستخدم حالياً.';
        }

        return [
            'ok' => true,
            'action' => 'disconnect_active_sessions',
            'username' => $username,
            'customer' => $customer !== null ? $this->sanitizeCustomer($customer) : null,
            'router' => $this->sanitizeRouterStateForSession($router),
            'hotspot_active_count' => count($hotspotRows),
            'ppp_active_count' => count($pppRows),
            'user_manager_sessions_count' => (int) ($router['user_manager_sessions']['rows_count'] ?? 0),
            'operations' => $operations,
            'operations_count' => count($operations),
            'can_execute_later' => count($operations) > 0,
            'block_reason' => $blockReason,
            'executed' => false,
            'real_execution' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => [
                'يتم فصل Hotspot Active و PPP Active فقط.',
                'User Manager Sessions تظهر قراءة فقط ولا يتم حذفها هنا حتى لا نخسر سجل الاستخدام.',
                'هذه العملية مفيدة بعد تغيير الباقة أو كلمة المرور لإجبار المستخدم على إعادة تسجيل الدخول.',
            ],
        ];
    }

    private function executeDisconnectPlan(array $plan): array
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
                    'router_remove_hotspot_active',
                    'router_remove_ppp_active',
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
            'message' => 'تم فصل الجلسات النشطة.',
            'operations' => $executed,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function readRouterSessions(string $username): array
    {
        $result = [
            'hotspot_active' => [
                'rows' => [],
                'raw_rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
            'ppp_active' => [
                'rows' => [],
                'raw_rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
            'user_manager_sessions' => [
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
                $hotspot = $this->normalizeRows($client->comm('/ip/hotspot/active/print', [
                    '?user' => $username,
                ]));

                $hotspot = $this->filterRowsForUsername($hotspot, $username);

                $result['hotspot_active'] = [
                    'rows' => $this->sanitizeRows($hotspot),
                    'raw_rows' => $hotspot,
                    'rows_count' => count($hotspot),
                    'error' => '',
                ];
            } catch (Throwable $e) {
                $result['hotspot_active']['error'] = $e->getMessage();
            }

            try {
                $ppp = $this->normalizeRows($client->comm('/ppp/active/print', [
                    '?name' => $username,
                ]));

                $ppp = $this->filterRowsForUsername($ppp, $username);

                $result['ppp_active'] = [
                    'rows' => $this->sanitizeRows($ppp),
                    'raw_rows' => $ppp,
                    'rows_count' => count($ppp),
                    'error' => '',
                ];
            } catch (Throwable $e) {
                $result['ppp_active']['error'] = $e->getMessage();
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

                $result['user_manager_sessions'] = [
                    'rows' => $this->sanitizeRows($sessions),
                    'raw_rows' => $sessions,
                    'rows_count' => count($sessions),
                    'error' => '',
                ];
            } catch (Throwable $e) {
                $result['user_manager_sessions']['error'] = $e->getMessage();
            }
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

        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['user']) || isset($rows['address'])) {
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

    private function filterRowsForUsername(array $rows, string $username): array
    {
        $usernameLower = strtolower(trim($username));
        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $candidates = [
                (string) ($row['user'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['username'] ?? ''),
                (string) ($row['user-name'] ?? ''),
                (string) ($row['customer'] ?? ''),
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

    private function hotspotDisplay(array $row): array
    {
        return [
            'id' => (string) ($row['.id'] ?? ''),
            'user' => (string) ($row['user'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'mac_address' => (string) ($row['mac-address'] ?? ''),
            'uptime' => (string) ($row['uptime'] ?? ''),
            'bytes_in' => (string) ($row['bytes-in'] ?? ''),
            'bytes_out' => (string) ($row['bytes-out'] ?? ''),
            'login_by' => (string) ($row['login-by'] ?? ''),
        ];
    }

    private function pppDisplay(array $row): array
    {
        return [
            'id' => (string) ($row['.id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'service' => (string) ($row['service'] ?? ''),
            'caller_id' => (string) ($row['caller-id'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'uptime' => (string) ($row['uptime'] ?? ''),
        ];
    }

    private function sanitizeRouterStateForSession(array $router): array
    {
        if (isset($router['hotspot_active']['raw_rows'])) {
            unset($router['hotspot_active']['raw_rows']);
        }

        if (isset($router['ppp_active']['raw_rows'])) {
            unset($router['ppp_active']['raw_rows']);
        }

        if (isset($router['user_manager_sessions']['raw_rows'])) {
            unset($router['user_manager_sessions']['raw_rows']);
        }

        return $router;
    }

    private function requireLastPlan(string $username): array
    {
        $plan = $_SESSION['user_disconnect_result'] ?? null;

        if (!is_array($plan)) {
            throw new RuntimeException('نفّذ Dry Run ناجح قبل التنفيذ.');
        }

        if ((string) ($plan['username'] ?? '') !== $username) {
            throw new RuntimeException('اسم المستخدم لا يطابق آخر Dry Run.');
        }

        if ((string) ($plan['action'] ?? '') !== 'disconnect_active_sessions') {
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
        $_SESSION['user_disconnect_flash_message'] = $message;
        $_SESSION['user_disconnect_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'user_disconnect_flash_' . $key;
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