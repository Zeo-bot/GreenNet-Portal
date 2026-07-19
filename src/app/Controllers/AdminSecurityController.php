<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use PDO;
use Throwable;
use RuntimeException;

final class AdminSecurityController
{
    public function index(): string
    {
        $security = $this->securityState();

        return $this->renderAdmin('admin/security', [
            'title' => 'الأمان',
            'security' => $security,
            'success' => (string) ($_GET['success'] ?? ''),
            'error' => '',
        ]);
    }

    public function update(): string
    {
        try {
            $action = (string) ($_POST['gn_security_action'] ?? '');

            if ($action === 'profile') {
                $username = trim((string) ($_POST['admin_username'] ?? ''));

                if ($username === '') {
                    throw new RuntimeException('اسم المدير مطلوب.');
                }

                if (!preg_match('/^[A-Za-z0-9_.@:-]{3,64}$/', $username)) {
                    throw new RuntimeException('اسم المدير يجب أن يكون من أحرف وأرقام ورموز بسيطة فقط.');
                }

                $this->saveSetting('admin_username', $username);
                $this->saveSetting('ADMIN_USERNAME', $username);

                return $this->redirect('/admin/security?success=profile_saved');
            }

            if ($action === 'password') {
                $currentPassword = (string) ($_POST['current_password'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                if (!$this->verifyCurrentPassword($currentPassword)) {
                    throw new RuntimeException('كلمة المرور الحالية غير صحيحة.');
                }

                if (strlen($newPassword) < 8) {
                    throw new RuntimeException('كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new RuntimeException('تأكيد كلمة المرور غير مطابق.');
                }

                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $changedAt = date('Y-m-d H:i:s');

                $this->saveSetting('admin_password_hash', $hash);
                $this->saveSetting('ADMIN_PASSWORD_HASH', $hash);
                $this->saveSetting('security_admin_password_hash', $hash);
                $this->saveSetting('admin_password_changed_at', $changedAt);

                return $this->redirect('/admin/security?success=password_changed');
            }

            if ($action === 'reset_failed_logins') {
                $this->saveSetting('admin_failed_login_attempts', '0');
                $this->saveSetting('admin_locked_until', '');

                return $this->redirect('/admin/security?success=failed_reset');
            }

            throw new RuntimeException('عملية غير معروفة.');
        } catch (Throwable $e) {
            return $this->renderAdmin('admin/security', [
                'title' => 'الأمان',
                'security' => $this->securityState(),
                'success' => '',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function save(): string
    {
        return $this->update();
    }

    public function store(): string
    {
        return $this->update();
    }

    private function pdo(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        Database::migrate();

        $pdo = Database::connection();
        $this->ensureTables($pdo);

        return $pdo;
    }

    private function ensureTables(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS greennet_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_security_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT DEFAULT '',
                event_message TEXT DEFAULT '',
                ip_address TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function securityState(): array
    {
        $username = $this->readSetting('admin_username', (string) ($_ENV['ADMIN_USERNAME'] ?? getenv('ADMIN_USERNAME') ?: 'admin'));
        $hash = $this->readSetting('admin_password_hash', '');
        $changedAt = $this->readSetting('admin_password_changed_at', '');
        $failedAttempts = $this->readSetting('admin_failed_login_attempts', '0');
        $lockedUntil = $this->readSetting('admin_locked_until', '');
        $envPasswordExists = (string) ($_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: '') !== '';

        return [
            'admin_username' => $username !== '' ? $username : 'admin',
            'has_password_hash' => $hash !== '',
            'password_changed_at' => $changedAt,
            'failed_login_attempts' => $failedAttempts,
            'locked_until' => $lockedUntil,
            'env_password_exists' => $envPasswordExists,
            'session_user' => (string) ($_SESSION['admin_username'] ?? $username),
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
    }

    private function verifyCurrentPassword(string $password): bool
    {
        $hash = $this->readSetting('admin_password_hash', '');

        if ($hash !== '' && password_verify($password, $hash)) {
            return true;
        }

        $hash2 = $this->readSetting('ADMIN_PASSWORD_HASH', '');

        if ($hash2 !== '' && password_verify($password, $hash2)) {
            return true;
        }

        $envPassword = (string) ($_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: '');

        if ($envPassword !== '') {
            return hash_equals($envPassword, $password);
        }

        if ($hash === '' && $hash2 === '' && $envPassword === '') {
            return true;
        }

        return false;
    }

    private function readSetting(string $key, string $fallback = ''): string
    {
        foreach (['app_settings', 'greennet_settings', 'settings'] as $table) {
            try {
                $columns = $this->columns($table);

                if (in_array('setting_key', $columns, true) && in_array('setting_value', $columns, true)) {
                    $stmt = $this->pdo()->prepare("SELECT setting_value FROM {$table} WHERE setting_key = :key LIMIT 1");
                    $stmt->execute(['key' => $key]);
                    $value = $stmt->fetchColumn();

                    if (is_string($value) && trim($value) !== '') {
                        return $value;
                    }
                }

                if (in_array('key', $columns, true) && in_array('value', $columns, true)) {
                    $stmt = $this->pdo()->prepare("SELECT \"value\" FROM {$table} WHERE \"key\" = :key LIMIT 1");
                    $stmt->execute(['key' => $key]);
                    $value = $stmt->fetchColumn();

                    if (is_string($value) && trim($value) !== '') {
                        return $value;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $fallback;
    }

    private function saveSetting(string $key, string $value): void
    {
        foreach (['app_settings', 'greennet_settings', 'settings'] as $table) {
            try {
                $columns = $this->columns($table);

                if (in_array('setting_key', $columns, true) && in_array('setting_value', $columns, true)) {
                    $stmt = $this->pdo()->prepare("
                        INSERT INTO {$table} (setting_key, setting_value, created_at, updated_at)
                        VALUES (:key, :value, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                        ON CONFLICT(setting_key) DO UPDATE SET
                            setting_value = excluded.setting_value,
                            updated_at = CURRENT_TIMESTAMP
                    ");

                    $stmt->execute([
                        'key' => $key,
                        'value' => $value,
                    ]);
                } elseif (in_array('key', $columns, true) && in_array('value', $columns, true)) {
                    $stmt = $this->pdo()->prepare("
                        INSERT INTO {$table} (\"key\", \"value\")
                        VALUES (:key, :value)
                        ON CONFLICT(\"key\") DO UPDATE SET
                            \"value\" = excluded.\"value\"
                    ");

                    $stmt->execute([
                        'key' => $key,
                        'value' => $value,
                    ]);
                }
            } catch (Throwable) {
                continue;
            }
        }
    }

    private function columns(string $table): array
    {
        try {
            $rows = $this->pdo()->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function redirect(string $url): string
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }

        return '<script>window.location.href=' . json_encode($url) . ';</script>';
    }

    private function renderAdmin(string $view, array $data = []): string
    {
        $viewsPath = dirname(__DIR__) . '/Views';
        $viewFile = $viewsPath . '/' . $view . '.php';
        $layoutFile = $viewsPath . '/layouts/admin.php';

        if (!is_file($viewFile)) {
            return 'View not found: ' . htmlspecialchars($viewFile, ENT_QUOTES, 'UTF-8');
        }

        if (!is_file($layoutFile)) {
            return 'Layout not found: ' . htmlspecialchars($layoutFile, ENT_QUOTES, 'UTF-8');
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        ob_start();
        require $layoutFile;

        return (string) ob_get_clean();
    }
}