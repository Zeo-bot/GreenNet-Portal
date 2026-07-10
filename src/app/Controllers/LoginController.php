<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use PDO;
use Throwable;

class LoginController
{
    private int $maxAttempts = 5;
    private int $lockMinutes = 10;

    public function show(): string
    {
        Database::migrate();
        $this->ensureSubscriberSecurityColumns();

        if (isset($_GET['switch']) && (string) $_GET['switch'] === '1') {
            $this->clearSubscriberSession();
        }

        if (($_SESSION['subscriber_logged_in'] ?? false) === true) {
            header('Location: /dashboard');
            exit;
        }

        return View::render('login', [
            'title' => 'تسجيل دخول المشترك',
            'error' => '',
            'username' => '',
        ]);
    }

    public function login(): string
    {
        Database::migrate();
        $this->ensureSubscriberSecurityColumns();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            return View::render('login', [
                'title' => 'تسجيل دخول المشترك',
                'error' => 'يرجى إدخال اسم المستخدم وكلمة المرور.',
                'username' => $username,
            ]);
        }

        $customer = $this->findCustomer($username);

        if (!$customer) {
            AppLog::warning('محاولة دخول مشترك غير موجود في CRM', [
                'username' => $username,
                'ip' => $this->clientIp(),
            ]);

            return View::render('login', [
                'title' => 'تسجيل دخول المشترك',
                'error' => 'بيانات الدخول غير صحيحة.',
                'username' => $username,
            ]);
        }

        $realUsername = (string) ($customer['username'] ?? $username);

        if ($this->isLocked($customer)) {
            AppLog::warning('محاولة دخول إلى حساب مشترك مقفل مؤقتاً', [
                'username' => $realUsername,
                'locked_until' => (string) ($customer['locked_until'] ?? ''),
                'ip' => $this->clientIp(),
            ]);

            return View::render('login', [
                'title' => 'تسجيل دخول المشترك',
                'error' => 'تم قفل الحساب مؤقتاً بسبب محاولات دخول خاطئة. حاول لاحقاً.',
                'username' => $username,
            ]);
        }

        $hash = (string) ($customer['subscriber_password_hash'] ?? '');

        if ($hash === '') {
            AppLog::warning('محاولة دخول لمشترك بدون كلمة مرور مفعلة', [
                'username' => $realUsername,
                'ip' => $this->clientIp(),
            ]);

            return View::render('login', [
                'title' => 'تسجيل دخول المشترك',
                'error' => 'لا توجد كلمة مرور مفعلة لهذا الحساب. تواصل مع الإدارة لتفعيل الدخول.',
                'username' => $username,
            ]);
        }

        if (!password_verify($password, $hash)) {
            $this->registerFailedAttempt($realUsername, (int) ($customer['failed_login_attempts'] ?? 0));

            AppLog::warning('محاولة دخول مشترك فاشلة', [
                'username' => $realUsername,
                'ip' => $this->clientIp(),
            ]);

            return View::render('login', [
                'title' => 'تسجيل دخول المشترك',
                'error' => 'بيانات الدخول غير صحيحة.',
                'username' => $username,
            ]);
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->updatePasswordHash($realUsername, password_hash($password, PASSWORD_DEFAULT));
        }

        $this->registerSuccessfulLogin($realUsername);

        $_SESSION['subscriber_logged_in'] = true;
        $_SESSION['subscriber_username'] = $realUsername;
        $_SESSION['subscriber_login_at'] = date('Y-m-d H:i:s');

        AppLog::info('تسجيل دخول مشترك ناجح', [
            'username' => $realUsername,
            'ip' => $this->clientIp(),
        ]);

        header('Location: /dashboard');
        exit;
    }

    public function logout(): void
    {
        $this->clearSubscriberSession();

        header('Location: /login?switch=1');
        exit;
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

    private function isLocked(array $customer): bool
    {
        $lockedUntil = trim((string) ($customer['locked_until'] ?? ''));

        if ($lockedUntil === '') {
            return false;
        }

        $lockedTimestamp = strtotime($lockedUntil);

        if ($lockedTimestamp === false) {
            return false;
        }

        return $lockedTimestamp > time();
    }

    private function registerFailedAttempt(string $username, int $currentAttempts): void
    {
        $attempts = $currentAttempts + 1;
        $lockedUntil = '';

        if ($attempts >= $this->maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + ($this->lockMinutes * 60));
        }

        $stmt = Database::connection()->prepare("
            UPDATE customers_local
            SET
                failed_login_attempts = :attempts,
                locked_until = :locked_until,
                updated_at = CURRENT_TIMESTAMP
            WHERE lower(username) = lower(:username)
        ");

        $stmt->execute([
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
            'username' => $username,
        ]);

        if ($lockedUntil !== '') {
            AppLog::warning('تم قفل حساب مشترك مؤقتاً بسبب محاولات دخول خاطئة', [
                'username' => $username,
                'locked_until' => $lockedUntil,
                'attempts' => $attempts,
            ]);
        }
    }

    private function registerSuccessfulLogin(string $username): void
    {
        $stmt = Database::connection()->prepare("
            UPDATE customers_local
            SET
                failed_login_attempts = 0,
                locked_until = '',
                last_login_at = :last_login_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE lower(username) = lower(:username)
        ");

        $stmt->execute([
            'last_login_at' => date('Y-m-d H:i:s'),
            'username' => $username,
        ]);
    }

    private function updatePasswordHash(string $username, string $hash): void
    {
        $stmt = Database::connection()->prepare("
            UPDATE customers_local
            SET
                subscriber_password_hash = :hash,
                password_changed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE lower(username) = lower(:username)
        ");

        $stmt->execute([
            'hash' => $hash,
            'username' => $username,
        ]);
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

    private function clearSubscriberSession(): void
    {
        unset(
            $_SESSION['subscriber_logged_in'],
            $_SESSION['subscriber_username'],
            $_SESSION['subscriber_login_at'],
            $_SESSION['subscriber_flash']
        );
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}