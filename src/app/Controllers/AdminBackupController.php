<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use PDO;
use Throwable;
use ZipArchive;

class AdminBackupController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $flash = $_SESSION['backup_flash'] ?? null;
        unset($_SESSION['backup_flash']);

        return View::render('admin/backup', [
            'title' => 'النسخ الاحتياطي والاستعادة',
            'flash' => $flash,
            'database_path' => $this->databasePath(),
            'database_exists' => is_file($this->databasePath()),
            'database_size' => $this->fileSize($this->databasePath()),
            'media_dir' => $this->mediaDirectory(),
            'media_exists' => is_dir($this->mediaDirectory()),
            'media_stats' => $this->mediaStats(),
            'stored_backups' => $this->storedBackups(),
            'zip_available' => class_exists(ZipArchive::class),
        ]);
    }

    public function download(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $backupPath = $this->createSqliteBackup('greennet-db-backup');

            AppLog::info('تم إنشاء SQLite Backup', [
                'file' => basename($backupPath),
            ]);

            $this->sendFile($backupPath, 'application/octet-stream');
        } catch (Throwable $e) {
            $this->flash('error', 'فشل إنشاء نسخة SQLite: ' . $e->getMessage());
            $this->redirect();
        }
    }

    public function downloadFull(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $backupPath = $this->createFullBackup('greennet-full-backup');

            AppLog::info('تم إنشاء Full Backup', [
                'file' => basename($backupPath),
            ]);

            $this->sendFile($backupPath, 'application/zip');
        } catch (Throwable $e) {
            $this->flash('error', 'فشل إنشاء النسخة الكاملة: ' . $e->getMessage());
            $this->redirect();
        }
    }

    public function downloadStored(): void
    {
        Database::migrate();
        $this->requireLogin();

        $file = basename((string) ($_GET['file'] ?? ''));

        if ($file === '') {
            $this->flash('error', 'لم يتم تحديد ملف النسخة.');
            $this->redirect();
        }

        $path = $this->backupDirectory() . '/' . $file;

        if (!is_file($path)) {
            $this->flash('error', 'ملف النسخة غير موجود.');
            $this->redirect();
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($extension, ['sqlite', 'db', 'zip'], true)) {
            $this->flash('error', 'نوع ملف النسخة غير مسموح.');
            $this->redirect();
        }

        $mime = $extension === 'zip' ? 'application/zip' : 'application/octet-stream';

        $this->sendFile($path, $mime);
    }

    public function restore(): void
    {
        Database::migrate();
        $this->requireLogin();

        $confirmation = trim((string) ($_POST['confirmation'] ?? ''));

        if ($confirmation !== 'RESTORE') {
            $this->flash('error', 'للاستعادة اكتب RESTORE في خانة التأكيد.');
            $this->redirect();
        }

        try {
            $uploadedPath = $this->uploadedFilePath('backup_file');

            if ($uploadedPath === null) {
                $this->flash('error', 'يرجى اختيار ملف SQLite للاستعادة.');
                $this->redirect();
            }

            $this->validateSqliteBackup($uploadedPath);

            $beforeBackup = $this->createSqliteBackup('before-sqlite-restore');

            copy($uploadedPath, $this->databasePath());
            @chmod($this->databasePath(), 0664);

            AppLog::warning('تمت استعادة SQLite Backup', [
                'before_backup' => basename($beforeBackup),
            ]);

            $this->flash('success', 'تمت استعادة قاعدة البيانات بنجاح. تم إنشاء نسخة قبل الاستعادة: ' . basename($beforeBackup));
        } catch (Throwable $e) {
            $this->flash('error', 'فشلت استعادة SQLite: ' . $e->getMessage());
        }

        $this->redirect();
    }

    public function restoreFull(): void
    {
        Database::migrate();
        $this->requireLogin();

        $confirmation = trim((string) ($_POST['confirmation'] ?? ''));

        if ($confirmation !== 'RESTORE_FULL') {
            $this->flash('error', 'للاستعادة الكاملة اكتب RESTORE_FULL في خانة التأكيد.');
            $this->redirect();
        }

        try {
            if (!class_exists(ZipArchive::class)) {
                throw new \RuntimeException('ZipArchive غير متاح داخل PHP Container.');
            }

            $uploadedPath = $this->uploadedFilePath('full_backup_file');

            if ($uploadedPath === null) {
                $this->flash('error', 'يرجى اختيار ملف Full Backup ZIP.');
                $this->redirect();
            }

            $zip = new ZipArchive();

            if ($zip->open($uploadedPath) !== true) {
                throw new \RuntimeException('تعذر فتح ملف ZIP.');
            }

            if ($zip->locateName('database/database.sqlite') === false) {
                $zip->close();
                throw new \RuntimeException('ملف ZIP لا يحتوي على database/database.sqlite.');
            }

            $restoreDir = $this->uploadDirectory() . '/full-restore-' . date('Ymd-His');

            if (!is_dir($restoreDir)) {
                mkdir($restoreDir, 0775, true);
            }

            if (!$zip->extractTo($restoreDir)) {
                $zip->close();
                throw new \RuntimeException('تعذر استخراج ملف ZIP.');
            }

            $zip->close();

            $restoredDatabase = $restoreDir . '/database/database.sqlite';

            if (!is_file($restoredDatabase)) {
                throw new \RuntimeException('قاعدة البيانات غير موجودة داخل النسخة الكاملة.');
            }

            $this->validateSqliteBackup($restoredDatabase);

            $beforeBackup = $this->createFullBackup('before-full-restore');

            copy($restoredDatabase, $this->databasePath());
            @chmod($this->databasePath(), 0664);

            $restoredMediaDir = $restoreDir . '/public/media';

            if (is_dir($restoredMediaDir)) {
                $this->copyDirectory($restoredMediaDir, $this->mediaDirectory());
            }

            $this->deleteDirectory($restoreDir);

            AppLog::warning('تمت استعادة Full Backup', [
                'before_backup' => basename($beforeBackup),
            ]);

            $this->flash('success', 'تمت الاستعادة الكاملة بنجاح. تم إنشاء نسخة كاملة قبل الاستعادة: ' . basename($beforeBackup));
        } catch (Throwable $e) {
            $this->flash('error', 'فشلت الاستعادة الكاملة: ' . $e->getMessage());
        }

        $this->redirect();
    }

    public function cleanDevelopmentData(): void
    {
        Database::migrate();
        $this->requireLogin();

        $confirmation = trim((string) ($_POST['confirmation'] ?? ''));

        if ($confirmation !== 'CLEAN') {
            $this->flash('error', 'للتنظيف اكتب CLEAN في خانة التأكيد.');
            $this->redirect();
        }

        $cleanCustomers = isset($_POST['clean_customers']);
        $cleanPayments = isset($_POST['clean_payments']);
        $cleanAnnouncements = isset($_POST['clean_announcements']);
        $cleanPackages = isset($_POST['clean_packages']);
        $cleanLogs = isset($_POST['clean_logs']);

        if (!$cleanCustomers && !$cleanPayments && !$cleanAnnouncements && !$cleanPackages && !$cleanLogs) {
            $this->flash('error', 'اختر نوع بيانات واحد على الأقل لتنظيفه.');
            $this->redirect();
        }

        try {
            $beforeBackup = $this->createFullBackup('before-clean');

            $db = Database::connection();
            $db->beginTransaction();

            if ($cleanCustomers) {
                $db->exec("DELETE FROM notifications");
                $db->exec("DELETE FROM payments");
                $db->exec("DELETE FROM customers_local");
            }

            if ($cleanPayments && !$cleanCustomers) {
                $db->exec("DELETE FROM payments");
            }

            if ($cleanAnnouncements) {
                $db->exec("DELETE FROM announcements");
            }

            if ($cleanPackages) {
                $db->exec("DELETE FROM service_packages");
            }

            if ($cleanLogs) {
                $db->exec("DELETE FROM app_logs");
            }

            $db->commit();

            AppLog::warning('تم تنظيف بيانات التطوير', [
                'before_backup' => basename($beforeBackup),
                'clean_customers' => $cleanCustomers,
                'clean_payments' => $cleanPayments,
                'clean_announcements' => $cleanAnnouncements,
                'clean_packages' => $cleanPackages,
                'clean_logs' => $cleanLogs,
            ]);

            $this->flash('success', 'تم التنظيف بنجاح. تم إنشاء Full Backup قبل التنظيف: ' . basename($beforeBackup));
        } catch (Throwable $e) {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }

            $this->flash('error', 'فشل التنظيف: ' . $e->getMessage());
        }

        $this->redirect();
    }

    private function createSqliteBackup(string $prefix): string
    {
        $dbPath = $this->databasePath();

        if (!is_file($dbPath)) {
            throw new \RuntimeException('ملف قاعدة البيانات غير موجود.');
        }

        $backupDir = $this->backupDirectory();

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $filename = $prefix . '-' . date('Ymd-His') . '.sqlite';
        $destination = $backupDir . '/' . $filename;

        if (!copy($dbPath, $destination)) {
            throw new \RuntimeException('تعذر نسخ قاعدة البيانات.');
        }

        @chmod($destination, 0664);

        return $destination;
    }

    private function createFullBackup(string $prefix): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive غير متاح داخل PHP Container.');
        }

        $dbPath = $this->databasePath();

        if (!is_file($dbPath)) {
            throw new \RuntimeException('ملف قاعدة البيانات غير موجود.');
        }

        $backupDir = $this->backupDirectory();

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $filename = $prefix . '-' . date('Ymd-His') . '.zip';
        $destination = $backupDir . '/' . $filename;

        $zip = new ZipArchive();

        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('تعذر إنشاء ملف ZIP.');
        }

        $zip->addFile($dbPath, 'database/database.sqlite');

        $mediaDir = $this->mediaDirectory();

        if (is_dir($mediaDir)) {
            $this->addDirectoryToZip($zip, $mediaDir, 'public/media');
        }

        $backupInfo = [
            'app' => 'GreenNet Portal',
            'type' => 'full',
            'created_at' => date('Y-m-d H:i:s'),
            'contains' => [
                'database/database.sqlite',
                'public/media',
            ],
            'database_size_bytes' => $this->fileSize($dbPath),
            'media' => $this->mediaStats(),
        ];

        $zip->addFromString(
            'backup-info.json',
            json_encode($backupInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $zip->close();

        @chmod($destination, 0664);

        return $destination;
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $zipBasePath): void
    {
        $directory = rtrim($directory, '/\\');

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $absolutePath = $directory . '/' . $item;
            $relativePath = $zipBasePath . '/' . $item;

            if (is_dir($absolutePath)) {
                $this->addDirectoryToZip($zip, $absolutePath, $relativePath);
                continue;
            }

            if (is_file($absolutePath)) {
                $zip->addFile($absolutePath, $relativePath);
            }
        }
    }

    private function validateSqliteBackup(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException('ملف قاعدة البيانات غير موجود.');
        }

        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $requiredTables = [
                'settings',
                'admins',
                'customers_local',
                'payments',
            ];

            foreach ($requiredTables as $table) {
                $stmt = $pdo->prepare("
                    SELECT name
                    FROM sqlite_master
                    WHERE type = 'table'
                    AND name = :table
                    LIMIT 1
                ");

                $stmt->execute([
                    'table' => $table,
                ]);

                if ($stmt->fetchColumn() === false) {
                    throw new \RuntimeException('قاعدة البيانات لا تحتوي على الجدول المطلوب: ' . $table);
                }
            }
        } catch (Throwable $e) {
            throw new \RuntimeException('ملف SQLite غير صالح: ' . $e->getMessage());
        }
    }

    private function uploadedFilePath(string $fieldName): ?string
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
            throw new \RuntimeException('الملف المرفوع غير صالح.');
        }

        $uploadDir = $this->uploadDirectory();

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $originalName = basename((string) ($file['name'] ?? 'uploaded-backup'));
        $destination = $uploadDir . '/' . date('Ymd-His') . '-' . preg_replace('/[^a-zA-Z0-9._-]+/', '-', $originalName);

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new \RuntimeException('تعذر حفظ الملف المرفوع.');
        }

        @chmod($destination, 0664);

        return $destination;
    }

    private function storedBackups(): array
    {
        $backupDir = $this->backupDirectory();

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $files = [];

        foreach (['*.sqlite', '*.db', '*.zip'] as $pattern) {
            $matches = glob($backupDir . '/' . $pattern);

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

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            $items[] = [
                'filename' => basename($file),
                'type' => $extension === 'zip' ? 'Full ZIP' : 'SQLite',
                'size_bytes' => filesize($file) ?: 0,
                'modified_at' => date('Y-m-d H:i:s', filemtime($file) ?: time()),
            ];
        }

        usort($items, function (array $a, array $b): int {
            return strcmp((string) $b['modified_at'], (string) $a['modified_at']);
        });

        return $items;
    }

    private function mediaStats(): array
    {
        $dir = $this->mediaDirectory();

        $stats = [
            'files_count' => 0,
            'total_size_bytes' => 0,
        ];

        if (!is_dir($dir)) {
            return $stats;
        }

        $items = scandir($dir);

        if ($items === false) {
            return $stats;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_file($path)) {
                $stats['files_count']++;
                $stats['total_size_bytes'] += filesize($path) ?: 0;
            }
        }

        return $stats;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0775, true);
        }

        $items = scandir($source);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $item;
            $destinationPath = $destination . '/' . $item;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath);
                continue;
            }

            if (is_file($sourcePath)) {
                copy($sourcePath, $destinationPath);
                @chmod($destinationPath, 0664);
            }
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private function sendFile(string $path, string $contentType): void
    {
        if (!is_file($path)) {
            $this->flash('error', 'الملف غير موجود.');
            $this->redirect();
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($path);
        exit;
    }

    private function fileSize(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }

        return filesize($path) ?: 0;
    }

    private function databasePath(): string
    {
        return BASE_PATH . '/database/database.sqlite';
    }

    private function backupDirectory(): string
    {
        return BASE_PATH . '/storage/backups';
    }

    private function uploadDirectory(): string
    {
        return BASE_PATH . '/storage/uploads';
    }

    private function mediaDirectory(): string
    {
        return BASE_PATH . '/public/media';
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['backup_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function redirect(): void
    {
        header('Location: /admin/backup');
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