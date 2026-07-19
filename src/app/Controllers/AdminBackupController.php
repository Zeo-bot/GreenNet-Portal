<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use PDO;
use Throwable;
use RuntimeException;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class AdminBackupController
{
    private const MAX_RESTORE_BYTES = 52428800; // 50 MB

    public function index(): string
    {
        return $this->renderBackup('', '');
    }

    public function download(): string
    {
        try {
            $file = $this->createDatabaseBackup();

            return $this->sendFile($file);
        } catch (Throwable $e) {
            return $this->renderBackup('', $e->getMessage());
        }
    }

    public function downloadFull(): string
    {
        try {
            $file = $this->createFullBackup();

            return $this->sendFile($file);
        } catch (Throwable $e) {
            return $this->renderBackup('', $e->getMessage());
        }
    }

    public function downloadStored(): string
    {
        try {
            $name = basename((string) ($_GET['file'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('اسم ملف النسخة غير صحيح.');
            }

            $file = $this->backupDir() . '/' . $name;

            if (!is_file($file)) {
                throw new RuntimeException('ملف النسخة غير موجود.');
            }

            return $this->sendFile($file);
        } catch (Throwable $e) {
            return $this->renderBackup('', $e->getMessage());
        }
    }

    public function restore(): string
    {
        try {
            $this->requireRestoreConfirmation('RESTORE');

            $file = $_FILES['backup_file'] ?? null;

            if (!is_array($file)) {
                throw new RuntimeException('لم يتم اختيار ملف الاستعادة.');
            }

            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('فشل رفع ملف الاستعادة. الكود: ' . (string) ($file['error'] ?? 'unknown'));
            }

            $size = (int) ($file['size'] ?? 0);

            if ($size <= 0) {
                throw new RuntimeException('ملف الاستعادة فارغ.');
            }

            if ($size > self::MAX_RESTORE_BYTES) {
                throw new RuntimeException('حجم ملف الاستعادة أكبر من 50MB.');
            }

            $originalName = (string) ($file['name'] ?? '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($extension, ['sqlite', 'db', 'bak'], true)) {
                throw new RuntimeException('ملف استعادة قاعدة البيانات يجب أن يكون sqlite أو db أو bak.');
            }

            $tmp = (string) ($file['tmp_name'] ?? '');

            if ($tmp === '' || !is_uploaded_file($tmp)) {
                throw new RuntimeException('ملف الاستعادة غير صالح.');
            }

            $dbPath = $this->databasePath();

            if ($dbPath === '') {
                throw new RuntimeException('تعذر تحديد مسار قاعدة البيانات.');
            }

            $this->createDatabaseBackup('before_restore');

            clearstatcache(true, $dbPath);

            if (is_file($dbPath) && !is_writable($dbPath)) {
                throw new RuntimeException('ملف قاعدة البيانات غير قابل للكتابة.');
            }

            $dbDir = dirname($dbPath);

            if (!is_dir($dbDir) && !mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
                throw new RuntimeException('تعذر إنشاء مجلد قاعدة البيانات.');
            }

            if (!copy($tmp, $dbPath)) {
                throw new RuntimeException('فشل استبدال قاعدة البيانات.');
            }

            @chmod($dbPath, 0664);

            return $this->redirect('/admin/backup?success=restored');
        } catch (Throwable $e) {
            return $this->renderBackup('', $e->getMessage());
        }
    }

    public function restoreFull(): string
    {
        try {
            $this->requireRestoreConfirmation('RESTORE FULL');

            if (!class_exists(ZipArchive::class)) {
                throw new RuntimeException('ZipArchive غير متوفر في PHP حالياً، لا يمكن استعادة Full Backup.');
            }

            $file = $_FILES['full_backup_file'] ?? null;

            if (!is_array($file)) {
                throw new RuntimeException('لم يتم اختيار ملف Full Backup.');
            }

            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('فشل رفع ملف Full Backup. الكود: ' . (string) ($file['error'] ?? 'unknown'));
            }

            $size = (int) ($file['size'] ?? 0);

            if ($size <= 0) {
                throw new RuntimeException('ملف Full Backup فارغ.');
            }

            if ($size > self::MAX_RESTORE_BYTES) {
                throw new RuntimeException('حجم ملف Full Backup أكبر من 50MB.');
            }

            $originalName = (string) ($file['name'] ?? '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if ($extension !== 'zip') {
                throw new RuntimeException('ملف Full Backup يجب أن يكون ZIP.');
            }

            $tmp = (string) ($file['tmp_name'] ?? '');

            if ($tmp === '' || !is_uploaded_file($tmp)) {
                throw new RuntimeException('ملف Full Backup غير صالح.');
            }

            $this->createFullBackup('before_full_restore');

            $extractDir = $this->backupDir() . '/restore_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));

            if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
                throw new RuntimeException('تعذر إنشاء مجلد مؤقت للاستعادة.');
            }

            $zip = new ZipArchive();
            $opened = $zip->open($tmp);

            if ($opened !== true) {
                throw new RuntimeException('تعذر فتح ملف ZIP.');
            }

            $zip->extractTo($extractDir);
            $zip->close();

            $restoredDb = $this->findFirstFile($extractDir, ['sqlite', 'db', 'bak']);

            if ($restoredDb === '') {
                throw new RuntimeException('لم يتم العثور على قاعدة بيانات داخل ملف ZIP.');
            }

            $dbPath = $this->databasePath();

            if ($dbPath === '') {
                throw new RuntimeException('تعذر تحديد مسار قاعدة البيانات.');
            }

            if (!copy($restoredDb, $dbPath)) {
                throw new RuntimeException('فشل استعادة قاعدة البيانات من Full Backup.');
            }

            @chmod($dbPath, 0664);

            $uploadsSource = $extractDir . '/uploads';

            if (is_dir($uploadsSource)) {
                $this->copyDirectory($uploadsSource, $this->uploadsDir());
            }

            $this->removeDirectory($extractDir);

            return $this->redirect('/admin/backup?success=full_restored');
        } catch (Throwable $e) {
            return $this->renderBackup('', $e->getMessage());
        }
    }

    public function cleanDevelopmentData(): string
    {
        try {
            $confirm = trim((string) ($_POST['confirm_cleanup'] ?? ''));

            if ($confirm !== 'CLEAN') {
                throw new RuntimeException('للتنظيف يجب كتابة CLEAN في خانة التأكيد.');
            }

            $pdo = $this->pdo();

            $this->createDatabaseBackup('before_cleanup');

            $tables = [
                'api_audit_logs',
                'app_logs',
                'admin_notifications',
                'customer_timeline_notes',
                'renewal_requests',
            ];

            foreach ($tables as $table) {
                if ($this->tableExists($pdo, $table)) {
                    $pdo->exec("DELETE FROM {$table}");
                }
            }

            return $this->redirect('/admin/backup?success=cleaned');
        } catch (Throwable $e) {
            return $this->renderBackup('', $e->getMessage());
        }
    }

    private function renderBackup(string $success = '', string $error = ''): string
    {
        $success = $success !== '' ? $success : (string) ($_GET['success'] ?? '');

        return $this->renderAdmin('admin/backup', [
            'title' => 'Backup & Restore',
            'success' => $success,
            'error' => $error,
            'backups' => $this->storedBackups(),
            'dbPath' => $this->databasePath(),
            'backupDir' => $this->backupDir(),
            'uploadsDir' => $this->uploadsDir(),
            'zipAvailable' => class_exists(ZipArchive::class),
        ]);
    }

    private function pdo(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        Database::migrate();

        $pdo = Database::connection();

        return $pdo;
    }

    private function databasePath(): string
    {
        try {
            $rows = $this->pdo()->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                if ((string) ($row['name'] ?? '') === 'main') {
                    $file = (string) ($row['file'] ?? '');

                    if ($file !== '') {
                        return $file;
                    }
                }
            }
        } catch (Throwable) {
            // Continue to env fallback.
        }

        $env = (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');

        if ($env !== '') {
            return $env;
        }

        return dirname(__DIR__, 2) . '/database/database.sqlite';
    }

    private function backupDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/backups';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function uploadsDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function createDatabaseBackup(string $prefix = 'db'): string
    {
        $dbPath = $this->databasePath();

        if ($dbPath === '' || !is_file($dbPath)) {
            throw new RuntimeException('ملف قاعدة البيانات غير موجود: ' . $dbPath);
        }

        $target = $this->backupDir()
            . '/'
            . $prefix
            . '_backup_'
            . date('Ymd_His')
            . '.sqlite';

        if (!copy($dbPath, $target)) {
            throw new RuntimeException('فشل إنشاء نسخة قاعدة البيانات.');
        }

        @chmod($target, 0664);

        return $target;
    }

    private function createFullBackup(string $prefix = 'full'): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive غير متوفر في PHP حالياً. استخدم DB Backup فقط.');
        }

        $dbBackup = $this->createDatabaseBackup($prefix . '_db');

        $target = $this->backupDir()
            . '/'
            . $prefix
            . '_backup_'
            . date('Ymd_His')
            . '.zip';

        $zip = new ZipArchive();
        $opened = $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('تعذر إنشاء ملف ZIP.');
        }

        $zip->addFile($dbBackup, 'database/' . basename($dbBackup));

        $uploads = $this->uploadsDir();

        if (is_dir($uploads)) {
            $this->addDirectoryToZip($zip, $uploads, 'uploads');
        }

        $zip->addFromString('manifest.txt', implode(PHP_EOL, [
            'GreenNet Full Backup',
            'Created: ' . date('Y-m-d H:i:s'),
            'Database: ' . basename($dbBackup),
            'Uploads included: ' . (is_dir($uploads) ? 'yes' : 'no'),
        ]));

        $zip->close();

        @chmod($target, 0664);

        return $target;
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipBase): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();
            $relative = trim(str_replace('\\', '/', substr($path, strlen($dir))), '/');
            $zipPath = trim($zipBase . '/' . $relative, '/');

            if ($fileInfo->isDir()) {
                $zip->addEmptyDir($zipPath);
                continue;
            }

            if ($fileInfo->isFile()) {
                $zip->addFile($path, $zipPath);
            }
        }
    }

    private function storedBackups(): array
    {
        $dir = $this->backupDir();

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*') ?: [];
        $items = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (!in_array($extension, ['sqlite', 'db', 'bak', 'zip'], true)) {
                continue;
            }

            $items[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file) ?: 0,
                'mtime' => filemtime($file) ?: 0,
                'type' => $extension === 'zip' ? 'Full ZIP' : 'Database',
                'extension' => $extension,
            ];
        }

        usort($items, static fn (array $a, array $b): int => (int) $b['mtime'] <=> (int) $a['mtime']);

        return array_slice($items, 0, 50);
    }

    private function requireRestoreConfirmation(string $expected): void
    {
        $confirm = trim((string) ($_POST['confirm_restore'] ?? ''));

        if ($confirm !== $expected) {
            throw new RuntimeException('عبارة التأكيد غير صحيحة. اكتب: ' . $expected);
        }
    }

    private function sendFile(string $path): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('الملف غير موجود.');
        }

        if (headers_sent()) {
            return 'Cannot download file because headers already sent.';
        }

        $filename = basename($path);
        $mime = str_ends_with(strtolower($filename), '.zip')
            ? 'application/zip'
            : 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($path);
        exit;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :name LIMIT 1");
        $stmt->execute(['name' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private function findFirstFile(string $dir, array $extensions): string
    {
        if (!is_dir($dir)) {
            return '';
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));

            if (in_array($extension, $extensions, true)) {
                return $fileInfo->getPathname();
            }
        }

        return '';
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($target)) {
            @mkdir($target, 0775, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $relative = trim(str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($source))), '/');
            $destination = $target . '/' . $relative;

            if ($fileInfo->isDir()) {
                if (!is_dir($destination)) {
                    @mkdir($destination, 0775, true);
                }

                continue;
            }

            if (!is_dir(dirname($destination))) {
                @mkdir(dirname($destination), 0775, true);
            }

            copy($fileInfo->getPathname(), $destination);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getPathname());
            } else {
                @unlink($fileInfo->getPathname());
            }
        }

        @rmdir($dir);
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