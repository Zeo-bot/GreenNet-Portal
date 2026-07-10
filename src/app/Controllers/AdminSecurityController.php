<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use PDO;
use Throwable;

class AdminSecurityController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $flash = $_SESSION['security_flash'] ?? null;
        unset($_SESSION['security_flash']);

        return View::render('admin/security', [
            'title' => 'أمان المدير',
            'admin_username' => $this->currentAdminUsername(),
            'flash' => $flash,
        ]);
    }

    public function updatePassword(): void
    {
        Database::migrate();
        $this->requireLogin();

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $this->flash('error', 'يرجى تعبئة جميع الحقول.');
            $this->redirect();
        }

        if ($newPassword !== $confirmPassword) {
            $this->flash('error', 'كلمة المرور الجديدة وتأكيدها غير متطابقين.');
            $this->redirect();
        }

        if (strlen($newPassword) < 8) {
            $this->flash('error', 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.');
            $this->redirect();
        }

        if ($currentPassword === $newPassword) {
            $this->flash('error', 'كلمة المرور الجديدة يجب أن تختلف عن الحالية.');
            $this->redirect();
        }

        try {
            $admin = $this->findAdmin();

            if ($admin === null) {
                $this->flash('error', 'لم يتم العثور على حساب المدير الحالي.');
                $this->redirect();
            }

            $passwordColumn = $this->detectPasswordColumn();

            if ($passwordColumn === null) {
                $this->flash('error', 'تعذر تحديد حقل كلمة المرور في جدول admins.');
                $this->redirect();
            }

            $storedPassword = (string) ($admin[$passwordColumn] ?? '');

            if (!$this->verifyPassword($currentPassword, $storedPassword)) {
                AppLog::warning('محاولة فاشلة لتغيير كلمة مرور المدير', [
                    'admin_username' => $this->currentAdminUsername(),
                ]);

                $this->flash('error', 'كلمة المرور الحالية غير صحيحة.');
                $this->redirect();
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $this->db()->prepare("
                UPDATE admins
                SET {$passwordColumn} = :password,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->execute([
                'password' => $newHash,
                'id' => (int) $admin['id'],
            ]);

            AppLog::info('تم تغيير كلمة مرور المدير بنجاح', [
                'admin_username' => (string) ($admin['username'] ?? $this->currentAdminUsername()),
            ]);

            $this->flash('success', 'تم تغيير كلمة مرور المدير بنجاح.');
        } catch (Throwable $e) {
            $this->flash('error', 'فشل تغيير كلمة المرور: ' . $e->getMessage());
        }

        $this->redirect();
    }

    private function findAdmin(): ?array
    {
        $username = $this->currentAdminUsername();

        $stmt = $this->db()->prepare("
            SELECT *
            FROM admins
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username,
        ]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($admin)) {
            return $admin;
        }

        $stmt = $this->db()->query("
            SELECT *
            FROM admins
            ORDER BY id ASC
            LIMIT 1
        ");

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($admin) ? $admin : null;
    }

    private function detectPasswordColumn(): ?string
    {
        $columns = $this->db()->query("PRAGMA table_info(admins)")->fetchAll(PDO::FETCH_ASSOC);

        $availableColumns = [];

        foreach ($columns as $column) {
            $availableColumns[] = (string) ($column['name'] ?? '');
        }

        if (in_array('password_hash', $availableColumns, true)) {
            return 'password_hash';
        }

        if (in_array('password', $availableColumns, true)) {
            return 'password';
        }

        return null;
    }

    private function verifyPassword(string $plainPassword, string $storedPassword): bool
    {
        if ($storedPassword === '') {
            return false;
        }

        if (password_verify($plainPassword, $storedPassword)) {
            return true;
        }

        return hash_equals($storedPassword, $plainPassword);
    }

    private function currentAdminUsername(): string
    {
        $sessionUsername = trim((string) ($_SESSION['admin_username'] ?? ''));

        if ($sessionUsername !== '') {
            return $sessionUsername;
        }

        return (string) Config::get('ADMIN_USERNAME', 'admin');
    }

    private function db(): PDO
    {
        return Database::connection();
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['security_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function redirect(): void
    {
        header('Location: /admin/security');
        exit;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}