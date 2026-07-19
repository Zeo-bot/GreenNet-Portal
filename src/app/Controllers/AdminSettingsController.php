<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use PDO;
use Throwable;
use RuntimeException;

final class AdminSettingsController
{
    private const SETTING_FIELDS = [
        'app_name',
        'app_title',
        'organization_name',
        'admin_panel_title',
        'subscriber_portal_title',
        'subscriber_login_title',
        'subscriber_login_subtitle',
        'support_phone',
        'support_country_code',
        'default_currency',
        'timezone',
        'access_mode',
        'auth_backend',
        'primary_color',
        'secondary_color',
        'brand_slogan',
        'system_notice',
        'maintenance_mode',
        'show_subscriber_support',
        'show_renewal_requests',
        'show_usage_cards',
    ];

    public function index(): string
    {
        $error = '';
        $settings = $this->loadSettings();

        return $this->renderAdmin('admin/settings', [
            'title' => 'الإعدادات والهوية',
            'settings' => $settings,
            'success' => (string) ($_GET['success'] ?? ''),
            'error' => $error,
            'whatsappUrl' => $this->whatsappUrl($settings),
        ]);
    }

    public function update(): string
    {
        try {
            $action = (string) ($_POST['gn_settings_action'] ?? 'save');

            if ($action === 'reset_visual') {
                $defaults = $this->defaults();

                $this->saveSetting('primary_color', $defaults['primary_color']);
                $this->saveSetting('secondary_color', $defaults['secondary_color']);
                $this->saveSetting('brand_slogan', $defaults['brand_slogan']);

                return $this->redirect('/admin/settings?success=visual_reset');
            }

            foreach (self::SETTING_FIELDS as $field) {
                if (in_array($field, ['maintenance_mode', 'show_subscriber_support', 'show_renewal_requests', 'show_usage_cards'], true)) {
                    $value = isset($_POST[$field]) ? '1' : '0';
                } else {
                    $value = trim((string) ($_POST[$field] ?? ''));
                }

                if (in_array($field, ['primary_color', 'secondary_color'], true)) {
                    if ($value === '') {
                        $value = (string) ($this->defaults()[$field] ?? '#11945a');
                    }

                    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                        throw new RuntimeException('قيمة اللون غير صحيحة: ' . $field);
                    }
                }

                $this->saveSetting($field, $value);
            }

            return $this->redirect('/admin/settings?success=saved');
        } catch (Throwable $e) {
            $settings = $this->loadSettings();

            return $this->renderAdmin('admin/settings', [
                'title' => 'الإعدادات والهوية',
                'settings' => $settings,
                'success' => '',
                'error' => $e->getMessage(),
                'whatsappUrl' => $this->whatsappUrl($settings),
            ]);
        }
    }

    public function branding(): string
    {
        return $this->index();
    }

    public function appearance(): string
    {
        return $this->index();
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
    }

    private function defaults(): array
    {
        return [
            'app_name' => (string) ($_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'GreenNet'),
            'app_title' => (string) ($_ENV['APP_TITLE'] ?? getenv('APP_TITLE') ?: 'GreenNet Portal'),
            'organization_name' => 'نورية الكلبونة',
            'admin_panel_title' => 'لوحة المدير',
            'subscriber_portal_title' => 'بوابة المشترك',
            'subscriber_login_title' => 'دخول المشترك',
            'subscriber_login_subtitle' => 'تابع باقتك واستهلاكك واطلب التجديد من مكان واحد.',
            'support_phone' => (string) ($_ENV['SUPPORT_PHONE'] ?? getenv('SUPPORT_PHONE') ?: '0966393915'),
            'support_country_code' => (string) ($_ENV['SUPPORT_COUNTRY_CODE'] ?? getenv('SUPPORT_COUNTRY_CODE') ?: '963'),
            'default_currency' => 'SYP',
            'timezone' => (string) ($_ENV['TZ'] ?? getenv('TZ') ?: 'Asia/Damascus'),
            'access_mode' => (string) ($_ENV['ACCESS_MODE'] ?? getenv('ACCESS_MODE') ?: 'hybrid'),
            'auth_backend' => (string) ($_ENV['AUTH_BACKEND'] ?? getenv('AUTH_BACKEND') ?: 'user-manager'),
            'primary_color' => '#11945a',
            'secondary_color' => '#0f7f4d',
            'brand_slogan' => 'إدارة ذكية لمشتركي الإنترنت',
            'system_notice' => '',
            'maintenance_mode' => '0',
            'show_subscriber_support' => '1',
            'show_renewal_requests' => '1',
            'show_usage_cards' => '1',
        ];
    }

    private function loadSettings(): array
    {
        $defaults = $this->defaults();
        $settings = [];

        foreach ($defaults as $key => $fallback) {
            $settings[$key] = $this->readSetting($key, (string) $fallback);
        }

        return $settings;
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

    private function whatsappUrl(array $settings): string
    {
        $number = preg_replace(
            '/\D+/',
            '',
            (string) ($settings['support_country_code'] ?? '') . ltrim((string) ($settings['support_phone'] ?? ''), '0')
        );

        return $number !== '' ? 'https://wa.me/' . $number : '';
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