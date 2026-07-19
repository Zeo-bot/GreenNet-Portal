<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use PDO;
use Throwable;
use RuntimeException;

final class AdminMediaController
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const MAX_UPLOAD_BYTES = 5242880; // 5 MB

    private const ASSET_SLOTS = [
        'brand_logo' => 'شعار النظام',
        'login_background' => 'خلفية تسجيل الدخول',
        'subscriber_background' => 'خلفية بوابة المشترك',
        'app_icon' => 'أيقونة التطبيق',
    ];

    public function index(): string
    {
        return $this->renderAdmin('admin/media', [
            'title' => 'Media Library',
            'assets' => $this->assets(),
            'assigned' => $this->assignedAssets(),
            'slots' => self::ASSET_SLOTS,
            'success' => (string) ($_GET['success'] ?? ''),
            'error' => '',
            'maxUploadMb' => 5,
        ]);
    }

    public function upload(): string
    {
        try {
            $file = $_FILES['media_file'] ?? $_FILES['file'] ?? null;

            if (!is_array($file)) {
                throw new RuntimeException('لم يتم اختيار ملف.');
            }

            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('فشل رفع الملف. الكود: ' . (string) ($file['error'] ?? 'unknown'));
            }

            $size = (int) ($file['size'] ?? 0);

            if ($size <= 0) {
                throw new RuntimeException('الملف فارغ.');
            }

            if ($size > self::MAX_UPLOAD_BYTES) {
                throw new RuntimeException('حجم الملف أكبر من الحد المسموح 5MB.');
            }

            $originalName = (string) ($file['name'] ?? 'media');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                throw new RuntimeException('نوع الملف غير مسموح. الأنواع المسموحة: JPG, PNG, WEBP, GIF.');
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('ملف الرفع غير صالح.');
            }

            $mimeType = $this->detectMime($tmpName);

            if (!$this->isAllowedMime($mimeType)) {
                throw new RuntimeException('نوع الملف غير مقبول: ' . $mimeType);
            }

            $uploadDir = $this->uploadDir();

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('تعذر إنشاء مجلد الرفع.');
            }

            $safeBase = $this->safeName(pathinfo($originalName, PATHINFO_FILENAME));
            $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $extension;
            $targetPath = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($tmpName, $targetPath)) {
                throw new RuntimeException('تعذر حفظ الملف.');
            }

            @chmod($targetPath, 0664);

            $publicPath = '/uploads/media/' . $filename;
            $tag = trim((string) ($_POST['tag'] ?? ''));

            $stmt = $this->pdo()->prepare("
                INSERT INTO media_assets (
                    filename,
                    original_name,
                    mime_type,
                    size_bytes,
                    public_path,
                    storage_path,
                    tag,
                    created_at,
                    updated_at
                )
                VALUES (
                    :filename,
                    :original_name,
                    :mime_type,
                    :size_bytes,
                    :public_path,
                    :storage_path,
                    :tag,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $stmt->execute([
                'filename' => $filename,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size_bytes' => $size,
                'public_path' => $publicPath,
                'storage_path' => $targetPath,
                'tag' => $tag,
            ]);

            return $this->redirect('/admin/media?success=uploaded');
        } catch (Throwable $e) {
            return $this->renderWithError($e->getMessage());
        }
    }

    public function assign(): string
    {
        try {
            $slot = trim((string) ($_POST['slot'] ?? ''));
            $path = trim((string) ($_POST['public_path'] ?? ''));

            if (!isset(self::ASSET_SLOTS[$slot])) {
                throw new RuntimeException('مكان التعيين غير معروف.');
            }

            if ($path === '' || !str_starts_with($path, '/uploads/media/')) {
                throw new RuntimeException('مسار الصورة غير صحيح.');
            }

            $this->saveSetting($slot, $path);
            $this->saveSetting('media_' . $slot, $path);

            return $this->redirect('/admin/media?success=assigned');
        } catch (Throwable $e) {
            return $this->renderWithError($e->getMessage());
        }
    }

    public function clearAsset(): string
    {
        try {
            $slot = trim((string) ($_POST['slot'] ?? ''));

            if (!isset(self::ASSET_SLOTS[$slot])) {
                throw new RuntimeException('مكان التعيين غير معروف.');
            }

            $this->deleteSetting($slot);
            $this->deleteSetting('media_' . $slot);

            return $this->redirect('/admin/media?success=cleared');
        } catch (Throwable $e) {
            return $this->renderWithError($e->getMessage());
        }
    }

    public function delete(): string
    {
        try {
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('معرّف الملف غير صحيح.');
            }

            $stmt = $this->pdo()->prepare("SELECT * FROM media_assets WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$asset) {
                throw new RuntimeException('الملف غير موجود.');
            }

            $publicPath = (string) ($asset['public_path'] ?? '');
            $storagePath = (string) ($asset['storage_path'] ?? '');

            if ($storagePath !== '' && is_file($storagePath)) {
                @unlink($storagePath);
            }

            if ($publicPath !== '') {
                foreach (array_keys(self::ASSET_SLOTS) as $slot) {
                    if ($this->readSetting($slot, '') === $publicPath) {
                        $this->deleteSetting($slot);
                    }

                    if ($this->readSetting('media_' . $slot, '') === $publicPath) {
                        $this->deleteSetting('media_' . $slot);
                    }
                }
            }

            $delete = $this->pdo()->prepare("DELETE FROM media_assets WHERE id = :id");
            $delete->execute(['id' => $id]);

            return $this->redirect('/admin/media?success=deleted');
        } catch (Throwable $e) {
            return $this->renderWithError($e->getMessage());
        }
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
            CREATE TABLE IF NOT EXISTS media_assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL,
                original_name TEXT DEFAULT '',
                mime_type TEXT DEFAULT '',
                size_bytes INTEGER DEFAULT 0,
                public_path TEXT NOT NULL,
                storage_path TEXT DEFAULT '',
                tag TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->addColumnIfMissing($pdo, 'media_assets', 'updated_at', "ALTER TABLE media_assets ADD COLUMN updated_at TEXT DEFAULT CURRENT_TIMESTAMP");
        $this->addColumnIfMissing($pdo, 'media_assets', 'tag', "ALTER TABLE media_assets ADD COLUMN tag TEXT DEFAULT ''");
        $this->addColumnIfMissing($pdo, 'media_assets', 'storage_path', "ALTER TABLE media_assets ADD COLUMN storage_path TEXT DEFAULT ''");

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

    private function addColumnIfMissing(PDO $pdo, string $table, string $column, string $sql): void
    {
        try {
            $rows = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                if ((string) ($row['name'] ?? '') === $column) {
                    return;
                }
            }

            $pdo->exec($sql);
        } catch (Throwable) {
            // Ignore old SQLite edge cases.
        }
    }

    private function assets(): array
    {
        $stmt = $this->pdo()->query("
            SELECT *
            FROM media_assets
            ORDER BY datetime(created_at) DESC, id DESC
            LIMIT 200
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function assignedAssets(): array
    {
        $assigned = [];

        foreach (array_keys(self::ASSET_SLOTS) as $slot) {
            $assigned[$slot] = $this->readSetting($slot, '');

            if ($assigned[$slot] === '') {
                $assigned[$slot] = $this->readSetting('media_' . $slot, '');
            }
        }

        return $assigned;
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
                    $stmt->execute(['key' => $key, 'value' => $value]);
                } elseif (in_array('key', $columns, true) && in_array('value', $columns, true)) {
                    $stmt = $this->pdo()->prepare("
                        INSERT INTO {$table} (\"key\", \"value\")
                        VALUES (:key, :value)
                        ON CONFLICT(\"key\") DO UPDATE SET
                            \"value\" = excluded.\"value\"
                    ");
                    $stmt->execute(['key' => $key, 'value' => $value]);
                }
            } catch (Throwable) {
                continue;
            }
        }
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

                    if (is_string($value)) {
                        return $value;
                    }
                }

                if (in_array('key', $columns, true) && in_array('value', $columns, true)) {
                    $stmt = $this->pdo()->prepare("SELECT \"value\" FROM {$table} WHERE \"key\" = :key LIMIT 1");
                    $stmt->execute(['key' => $key]);
                    $value = $stmt->fetchColumn();

                    if (is_string($value)) {
                        return $value;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $fallback;
    }

    private function deleteSetting(string $key): void
    {
        foreach (['app_settings', 'greennet_settings', 'settings'] as $table) {
            try {
                $columns = $this->columns($table);

                if (in_array('setting_key', $columns, true)) {
                    $stmt = $this->pdo()->prepare("DELETE FROM {$table} WHERE setting_key = :key");
                    $stmt->execute(['key' => $key]);
                } elseif (in_array('key', $columns, true)) {
                    $stmt = $this->pdo()->prepare("DELETE FROM {$table} WHERE \"key\" = :key");
                    $stmt->execute(['key' => $key]);
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

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return 'application/octet-stream';
    }

    private function isAllowedMime(string $mime): bool
    {
        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ], true);
    }

    private function safeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_-]+/i', '-', $name) ?: 'media';
        $name = trim($name, '-_');

        return $name !== '' ? substr($name, 0, 50) : 'media';
    }

    private function uploadDir(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads/media';
    }

    private function renderWithError(string $message): string
    {
        return $this->renderAdmin('admin/media', [
            'title' => 'Media Library',
            'assets' => $this->assets(),
            'assigned' => $this->assignedAssets(),
            'slots' => self::ASSET_SLOTS,
            'success' => '',
            'error' => $message,
            'maxUploadMb' => 5,
        ]);
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