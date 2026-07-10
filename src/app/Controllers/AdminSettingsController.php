<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Models\Setting;
use GreenNet\Services\SiteSettingsService;
use Throwable;

class AdminSettingsController
{
    public function index(): string
    {
        Database::migrate();
        SiteSettingsService::ensureDefaults();

        $this->requireLogin();

        $flash = $_SESSION['settings_flash'] ?? null;
        unset($_SESSION['settings_flash']);

        return View::render('admin/settings', [
            'title' => 'إعدادات النظام والهوية',
            'app_name' => Config::appName(),
            'settings' => SiteSettingsService::all(),
            'flash' => $flash,
        ]);
    }

    public function update(): void
    {
        Database::migrate();
        SiteSettingsService::ensureDefaults();

        $this->requireLogin();

        $settings = [
            'network_name' => trim((string) ($_POST['network_name'] ?? 'GreenNet')),
            'app_name' => trim((string) ($_POST['app_name'] ?? 'GreenNet')),
            'app_title' => trim((string) ($_POST['app_title'] ?? 'GreenNet Portal')),

            'support_phone' => trim((string) ($_POST['support_phone'] ?? '')),
            'support_country_code' => trim((string) ($_POST['support_country_code'] ?? '963')),
            'support_whatsapp' => trim((string) ($_POST['support_whatsapp'] ?? '')),

            'default_currency' => trim((string) ($_POST['default_currency'] ?? 'SYP')),

            'theme_color' => SiteSettingsService::normalizeThemeColor((string) ($_POST['theme_color'] ?? '#16a34a')),

            'login_welcome_text' => trim((string) ($_POST['login_welcome_text'] ?? '')),
            'subscriber_welcome_text' => trim((string) ($_POST['subscriber_welcome_text'] ?? '')),
            'support_text' => trim((string) ($_POST['support_text'] ?? '')),
            'footer_text' => trim((string) ($_POST['footer_text'] ?? '')),
            'whatsapp_renew_message' => trim((string) ($_POST['whatsapp_renew_message'] ?? '')),

            'show_price_to_subscriber' => isset($_POST['show_price_to_subscriber']) ? '1' : '0',
            'show_quota_to_subscriber' => isset($_POST['show_quota_to_subscriber']) ? '1' : '0',
            'show_mikrotik_profile_to_subscriber' => isset($_POST['show_mikrotik_profile_to_subscriber']) ? '1' : '0',
        ];

        if ($settings['network_name'] === '') {
            $settings['network_name'] = 'GreenNet';
        }

        if ($settings['app_name'] === '') {
            $settings['app_name'] = $settings['network_name'];
        }

        if ($settings['app_title'] === '') {
            $settings['app_title'] = $settings['app_name'] . ' Portal';
        }

        if ($settings['support_country_code'] === '') {
            $settings['support_country_code'] = '963';
        }

        if ($settings['default_currency'] === '') {
            $settings['default_currency'] = 'SYP';
        }

        try {
            Setting::setMany($settings);

            if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
                Setting::set('site_logo_path', '');
            }

            $uploadedLogo = $this->handleLogoUpload();

            if ($uploadedLogo !== null) {
                Setting::set('site_logo_path', $uploadedLogo);
            }

            AppLog::info('تم تحديث إعدادات النظام والهوية', [
                'network_name' => $settings['network_name'],
                'app_name' => $settings['app_name'],
                'theme_color' => $settings['theme_color'],
                'logo_updated' => $uploadedLogo !== null,
            ]);

            $this->flash('success', 'تم حفظ الإعدادات بنجاح.');
        } catch (Throwable $e) {
            $this->flash('error', 'فشل حفظ الإعدادات: ' . $e->getMessage());
        }

        header('Location: /admin/settings');
        exit;
    }

    private function handleLogoUpload(): ?string
    {
        if (!isset($_FILES['site_logo']) || !is_array($_FILES['site_logo'])) {
            return null;
        }

        $file = $_FILES['site_logo'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('فشل رفع الشعار.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('ملف الشعار غير صالح.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            throw new \RuntimeException('حجم الشعار يجب أن يكون أقل من 2MB.');
        }

        $imageInfo = @getimagesize($tmpName);

        if ($imageInfo === false) {
            throw new \RuntimeException('الملف المرفوع ليس صورة صالحة.');
        }

        $mime = (string) ($imageInfo['mime'] ?? '');

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => '',
        };

        if ($extension === '') {
            throw new \RuntimeException('صيغة الشعار غير مدعومة. استخدم PNG أو JPG أو WEBP.');
        }

        $mediaDir = BASE_PATH . '/public/media';

        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0775, true);
        }

        $filename = 'logo-' . date('YmdHis') . '.' . $extension;
        $destination = $mediaDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new \RuntimeException('تعذر حفظ الشعار.');
        }

        @chmod($destination, 0664);

        return '/media/' . $filename;
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['settings_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}