<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Models\Setting;
use GreenNet\Services\SiteSettingsService;
use Throwable;

class AdminMediaController
{
    private array $allowedSettingKeys = [
        'site_logo_path',
        'app_icon_path',
        'login_background_path',
    ];

    public function index(): string
    {
        Database::migrate();
        SiteSettingsService::ensureDefaults();

        $this->requireLogin();

        $flash = $_SESSION['media_flash'] ?? null;
        unset($_SESSION['media_flash']);

        return View::render('admin/media', [
            'title' => 'الصور والهوية',
            'settings' => SiteSettingsService::all(),
            'media_files' => $this->mediaFiles(),
            'flash' => $flash,
        ]);
    }

    public function upload(): void
    {
        Database::migrate();
        SiteSettingsService::ensureDefaults();

        $this->requireLogin();

        $assetType = trim((string) ($_POST['asset_type'] ?? 'gallery'));

        try {
            $path = $this->handleImageUpload('media_file', $assetType);

            if ($path === null) {
                $this->flash('error', 'يرجى اختيار صورة للرفع.');
                $this->redirect();
            }

            $settingKey = $this->assetTypeToSettingKey($assetType);

            if ($settingKey !== null) {
                Setting::set($settingKey, $path);
            }

            AppLog::info('تم رفع ملف Media جديد', [
                'path' => $path,
                'asset_type' => $assetType,
                'assigned_to' => $settingKey,
            ]);

            $this->flash('success', 'تم رفع الصورة بنجاح.');
        } catch (Throwable $e) {
            $this->flash('error', 'فشل رفع الصورة: ' . $e->getMessage());
        }

        $this->redirect();
    }

    public function assign(): void
    {
        Database::migrate();
        SiteSettingsService::ensureDefaults();

        $this->requireLogin();

        $settingKey = trim((string) ($_POST['setting_key'] ?? ''));
        $path = SiteSettingsService::normalizePublicMediaPath((string) ($_POST['path'] ?? ''));

        if (!in_array($settingKey, $this->allowedSettingKeys, true)) {
            $this->flash('error', 'نوع التعيين غير صالح.');
            $this->redirect();
        }

        if ($path === '') {
            $this->flash('error', 'مسار الصورة غير صالح.');
            $this->redirect();
        }

        if (!$this->mediaPathExists($path)) {
            $this->flash('error', 'الصورة غير موجودة داخل مجلد media.');
            $this->redirect();
        }

        Setting::set($settingKey, $path);

        AppLog::info('تم تعيين صورة Media', [
            'setting_key' => $settingKey,
            'path' => $path,
        ]);

        $this->flash('success', 'تم تعيين الصورة بنجاح.');
        $this->redirect();
    }

    public function clearAsset(): void
    {
        Database::migrate();
        SiteSettingsService::ensureDefaults();

        $this->requireLogin();

        $settingKey = trim((string) ($_POST['setting_key'] ?? ''));

        if (!in_array($settingKey, $this->allowedSettingKeys, true)) {
            $this->flash('error', 'نوع الإزالة غير صالح.');
            $this->redirect();
        }

        Setting::set($settingKey, '');

        AppLog::info('تم إزالة تعيين صورة Media', [
            'setting_key' => $settingKey,
        ]);

        $this->flash('success', 'تم إزالة الصورة من هذا الاستخدام.');
        $this->redirect();
    }

    public function delete(): void
    {
        Database::migrate();
        SiteSettingsService::ensureDefaults();

        $this->requireLogin();

        $path = SiteSettingsService::normalizePublicMediaPath((string) ($_POST['path'] ?? ''));

        if ($path === '') {
            $this->flash('error', 'مسار الصورة غير صالح.');
            $this->redirect();
        }

        $settings = SiteSettingsService::all();

        foreach ($this->allowedSettingKeys as $settingKey) {
            if (($settings[$settingKey] ?? '') === $path) {
                $this->flash('error', 'لا يمكن حذف صورة مستخدمة حالياً. أزل تعيينها أولاً.');
                $this->redirect();
            }
        }

        $absolutePath = $this->publicPathToAbsolute($path);

        if (!is_file($absolutePath)) {
            $this->flash('error', 'الصورة غير موجودة.');
            $this->redirect();
        }

        if (!unlink($absolutePath)) {
            $this->flash('error', 'تعذر حذف الصورة.');
            $this->redirect();
        }

        AppLog::warning('تم حذف ملف Media', [
            'path' => $path,
        ]);

        $this->flash('success', 'تم حذف الصورة بنجاح.');
        $this->redirect();
    }

    private function handleImageUpload(string $fieldName, string $assetType): ?string
    {
        if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
            return null;
        }

        $file = $_FILES[$fieldName];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('فشل رفع الملف.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('ملف غير صالح.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > 4 * 1024 * 1024) {
            throw new \RuntimeException('حجم الصورة يجب أن يكون أقل من 4MB.');
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
            throw new \RuntimeException('صيغة الصورة غير مدعومة. استخدم PNG أو JPG أو WEBP أو GIF.');
        }

        $mediaDir = $this->mediaDirectory();

        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0775, true);
        }

        $safePrefix = match ($assetType) {
            'logo' => 'logo',
            'icon' => 'icon',
            'login_background' => 'login-bg',
            default => 'media',
        };

        $filename = $safePrefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
        $destination = $mediaDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new \RuntimeException('تعذر حفظ الصورة.');
        }

        @chmod($destination, 0664);

        return '/media/' . $filename;
    }

    private function mediaFiles(): array
    {
        $mediaDir = $this->mediaDirectory();

        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0775, true);
        }

        $files = [];
        $extensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

        foreach ($extensions as $extension) {
            $matches = glob($mediaDir . '/*.' . $extension);

            if (is_array($matches)) {
                foreach ($matches as $match) {
                    $files[] = $match;
                }
            }
        }

        $files = array_values(array_unique($files));

        $items = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $filename = basename($file);
            $path = '/media/' . $filename;

            $items[] = [
                'filename' => $filename,
                'path' => $path,
                'size_bytes' => filesize($file) ?: 0,
                'modified_at' => date('Y-m-d H:i:s', filemtime($file) ?: time()),
            ];
        }

        usort($items, function (array $a, array $b): int {
            return strcmp((string) $b['modified_at'], (string) $a['modified_at']);
        });

        return $items;
    }

    private function mediaPathExists(string $path): bool
    {
        return is_file($this->publicPathToAbsolute($path));
    }

    private function publicPathToAbsolute(string $path): string
    {
        $path = SiteSettingsService::normalizePublicMediaPath($path);

        if ($path === '') {
            return '';
        }

        return BASE_PATH . '/public' . $path;
    }

    private function mediaDirectory(): string
    {
        return BASE_PATH . '/public/media';
    }

    private function assetTypeToSettingKey(string $assetType): ?string
    {
        return match ($assetType) {
            'logo' => 'site_logo_path',
            'icon' => 'app_icon_path',
            'login_background' => 'login_background_path',
            default => null,
        };
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['media_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function redirect(): void
    {
        header('Location: /admin/media');
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