<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use PDO;
use Throwable;

class AdminSubscriberPasswordController
{
    public function show(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensureSubscriberSecurityColumns();

        $username = trim((string) ($_GET['username'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));

        if ($username === '') {
            return View::render('admin/customer_password', [
                'title' => 'كلمات مرور المشتركين',
                'mode' => 'list',
                'customers' => $this->customers($q),
                'counts' => $this->counts(),
                'q' => $q,
                'customer' => [],
                'username' => '',
                'error' => '',
                'message' => '',
                'generated_pin' => '',
            ]);
        }

        $customer = $this->findCustomer($username);

        if (!$customer) {
            return View::render('admin/customer_password', [
                'title' => 'كلمة مرور المشترك',
                'mode' => 'detail',
                'customers' => [],
                'counts' => $this->counts(),
                'q' => '',
                'customer' => [],
                'username' => $username,
                'error' => 'لم يتم العثور على المشترك داخل CRM.',
                'message' => '',
                'generated_pin' => '',
            ]);
        }

        return View::render('admin/customer_password', [
            'title' => 'كلمة مرور المشترك',
            'mode' => 'detail',
            'customers' => [],
            'counts' => $this->counts(),
            'q' => '',
            'customer' => $customer,
            'username' => (string) ($customer['username'] ?? $username),
            'error' => '',
            'message' => '',
            'generated_pin' => '',
        ]);
    }

    public function update(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensureSubscriberSecurityColumns();

        $username = trim((string) ($_POST['username'] ?? ''));
        $action = trim((string) ($_POST['action'] ?? 'set'));
        $password = (string) ($_POST['password'] ?? '');
        $mustChange = isset($_POST['must_change_password']) ? 1 : 0;

        if ($username === '') {
            return View::render('admin/customer_password', [
                'title' => 'كلمة مرور المشترك',
                'mode' => 'detail',
                'customers' => [],
                'counts' => $this->counts(),
                'q' => '',
                'customer' => [],
                'username' => '',
                'error' => 'اسم المستخدم غير صالح.',
                'message' => '',
                'generated_pin' => '',
            ]);
        }

        $customer = $this->findCustomer($username);

        if (!$customer) {
            return View::render('admin/customer_password', [
                'title' => 'كلمة مرور المشترك',
                'mode' => 'detail',
                'customers' => [],
                'counts' => $this->counts(),
                'q' => '',
                'customer' => [],
                'username' => $username,
                'error' => 'لم يتم العثور على المشترك داخل CRM.',
                'message' => '',
                'generated_pin' => '',
            ]);
        }

        $generatedPin = '';

        if ($action === 'generate') {
            $password = (string) random_int(100000, 999999);
            $generatedPin = $password;
        }

        if (strlen($password) < 4) {
            return View::render('admin/customer_password', [
                'title' => 'كلمة مرور المشترك',
                'mode' => 'detail',
                'customers' => [],
                'counts' => $this->counts(),
                'q' => '',
                'customer' => $customer,
                'username' => $username,
                'error' => 'كلمة المرور يجب أن تكون 4 خانات على الأقل.',
                'message' => '',
                'generated_pin' => '',
            ]);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = Database::connection()->prepare("
            UPDATE customers_local
            SET
                subscriber_password_hash = :hash,
                password_changed_at = :changed_at,
                failed_login_attempts = 0,
                locked_until = '',
                must_change_password = :must_change_password,
                updated_at = CURRENT_TIMESTAMP
            WHERE lower(username) = lower(:username)
        ");

        $stmt->execute([
            'hash' => $hash,
            'changed_at' => date('Y-m-d H:i:s'),
            'must_change_password' => $mustChange,
            'username' => $username,
        ]);

        AppLog::info('تم تعيين كلمة مرور مشترك من لوحة المدير', [
            'username' => $username,
            'generated_pin' => $generatedPin !== '',
            'must_change_password' => $mustChange,
        ]);

        $updatedCustomer = $this->findCustomer($username) ?? $customer;

        return View::render('admin/customer_password', [
            'title' => 'كلمة مرور المشترك',
            'mode' => 'detail',
            'customers' => [],
            'counts' => $this->counts(),
            'q' => '',
            'customer' => $updatedCustomer,
            'username' => $username,
            'error' => '',
            'message' => 'تم تحديث كلمة مرور المشترك بنجاح.',
            'generated_pin' => $generatedPin,
        ]);
    }

    private function customers(string $q = ''): array
    {
        try {
            $params = [];
            $where = '';

            if ($q !== '') {
                $where = "
                    WHERE
                        lower(username) LIKE lower(:q)
                        OR lower(full_name) LIKE lower(:q)
                        OR lower(phone) LIKE lower(:q)
                ";

                $params['q'] = '%' . $q . '%';
            }

            $stmt = Database::connection()->prepare("
                SELECT
                    username,
                    full_name,
                    phone,
                    payment_status,
                    subscriber_password_hash,
                    password_changed_at,
                    last_login_at,
                    failed_login_attempts,
                    locked_until,
                    must_change_password,
                    created_at,
                    updated_at
                FROM customers_local
                {$where}
                ORDER BY username ASC
                LIMIT 300
            ");

            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function counts(): array
    {
        $counts = [
            'total' => 0,
            'with_password' => 0,
            'without_password' => 0,
            'locked' => 0,
        ];

        try {
            $stmt = Database::connection()->query("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN subscriber_password_hash IS NOT NULL AND subscriber_password_hash != '' THEN 1 ELSE 0 END) AS with_password,
                    SUM(CASE WHEN subscriber_password_hash IS NULL OR subscriber_password_hash = '' THEN 1 ELSE 0 END) AS without_password,
                    SUM(CASE WHEN locked_until IS NOT NULL AND locked_until != '' THEN 1 ELSE 0 END) AS locked
                FROM customers_local
            ");

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $counts['total'] = (int) ($row['total'] ?? 0);
                $counts['with_password'] = (int) ($row['with_password'] ?? 0);
                $counts['without_password'] = (int) ($row['without_password'] ?? 0);
                $counts['locked'] = (int) ($row['locked'] ?? 0);
            }
        } catch (Throwable) {
            return $counts;
        }

        return $counts;
    }

    private function findCustomer(string $username): ?array
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM customers_local
                WHERE lower(username) = lower(:username)
                LIMIT 1
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function ensureSubscriberSecurityColumns(): void
    {
        $this->ensureColumn('customers_local', 'subscriber_password_hash', "TEXT DEFAULT ''");
        $this->ensureColumn('customers_local', 'password_changed_at', "TEXT DEFAULT ''");
        $this->ensureColumn('customers_local', 'last_login_at', "TEXT DEFAULT ''");
        $this->ensureColumn('customers_local', 'failed_login_attempts', "INTEGER DEFAULT 0");
        $this->ensureColumn('customers_local', 'locked_until', "TEXT DEFAULT ''");
        $this->ensureColumn('customers_local', 'must_change_password', "INTEGER DEFAULT 0");
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $allowedTables = [
            'customers_local',
        ];

        if (!in_array($table, $allowedTables, true)) {
            return;
        }

        $stmt = Database::connection()->query("PRAGMA table_info({$table})");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $existingColumn) {
            if ((string) ($existingColumn['name'] ?? '') === $column) {
                return;
            }
        }

        Database::connection()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}