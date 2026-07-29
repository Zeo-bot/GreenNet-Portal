<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Core\Config;
use GreenNet\Core\Database;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

final class ProductionBackupService
{
    public const FORMAT = 'greennet-portable-backup';
    public const FORMAT_VERSION = 1;

    private string $databasePath;
    private string $backupDirectory;
    private string $uploadsDirectory;
    private int $retention;

    public function __construct(
        ?string $databasePath = null,
        ?string $backupDirectory = null,
        ?string $uploadsDirectory = null,
        ?int $retention = null
    ) {
        $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $this->databasePath = $databasePath ?? (string) Config::get('DB_DATABASE', $root . '/database/database.sqlite');
        $this->backupDirectory = $backupDirectory
            ?? (string) Config::get('BACKUP_STORAGE_PATH', $root . '/storage/backups');
        $this->uploadsDirectory = $uploadsDirectory ?? $root . '/public/uploads';
        $this->retention = max(1, $retention ?? (int) Config::get('BACKUP_RETENTION_COUNT', 10));
    }

    public function create(string $prefix = 'greennet'): array
    {
        $this->assertRuntime();
        $this->ensureBackupDirectory();
        $stamp = date('Ymd_His');
        $nonce = bin2hex(random_bytes(3));
        $working = $this->temporaryDirectory('create');
        $snapshot = $working . '/greennet.sqlite';
        $target = $this->backupDirectory . '/' . $this->safePrefix($prefix)
            . '_' . $stamp . '_' . $nonce . '.gnbackup.zip';

        try {
            $this->sqliteSnapshot($snapshot);
            $manifest = $this->manifest($snapshot);
            $zip = new ZipArchive();
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('تعذر إنشاء حزمة النسخة الاحتياطية.');
            }
            $zip->addFile($snapshot, 'database/greennet.sqlite');
            $zip->addFromString(
                'manifest.json',
                json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );
            $this->addUploads($zip);
            if (!$zip->close()) {
                throw new RuntimeException('تعذر إغلاق حزمة النسخة الاحتياطية.');
            }
            @chmod($target, 0660);
            $this->applyRetention();
            return $this->describe($target, $manifest);
        } catch (Throwable $e) {
            if (is_file($target)) {
                @unlink($target);
            }
            throw $e;
        } finally {
            $this->removeDirectory($working);
        }
    }

    public function validate(string $archivePath): array
    {
        $this->assertRuntime();
        if (!is_file($archivePath) || filesize($archivePath) <= 0) {
            throw new RuntimeException('حزمة النسخة غير موجودة أو فارغة.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('تعذر فتح حزمة النسخة.');
        }
        try {
            $allowedRoots = ['manifest.json', 'database/greennet.sqlite', 'uploads/'];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/')
                    || preg_match('/^[A-Za-z]:/', $name)) {
                    throw new RuntimeException('تحتوي الحزمة على مسار غير آمن.');
                }
                if (!in_array($name, array_slice($allowedRoots, 0, 2), true)
                    && !str_starts_with($name, 'uploads/')) {
                    throw new RuntimeException('تحتوي الحزمة على مكوّن غير معروف.');
                }
            }
            $manifestRaw = $zip->getFromName('manifest.json');
            $databaseRaw = $zip->getFromName('database/greennet.sqlite');
            if (!is_string($manifestRaw) || !is_string($databaseRaw)) {
                throw new RuntimeException('الحزمة لا تحتوي Manifest وقاعدة البيانات المطلوبة.');
            }
            $manifest = json_decode($manifestRaw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($manifest)
                || ($manifest['format'] ?? '') !== self::FORMAT
                || (int) ($manifest['format_version'] ?? 0) !== self::FORMAT_VERSION) {
                throw new RuntimeException('إصدار حزمة النسخة غير مدعوم.');
            }
            if (!hash_equals((string) ($manifest['database']['sha256'] ?? ''), hash('sha256', $databaseRaw))) {
                throw new RuntimeException('بصمة قاعدة البيانات داخل الحزمة غير مطابقة.');
            }
            $working = $this->temporaryDirectory('validate');
            try {
                $candidate = $working . '/candidate.sqlite';
                if (file_put_contents($candidate, $databaseRaw, LOCK_EX) === false) {
                    throw new RuntimeException('تعذر تجهيز قاعدة البيانات للتحقق.');
                }
                $this->assertSqliteIntegrity($candidate);
            } finally {
                $this->removeDirectory($working);
            }
            return $manifest;
        } finally {
            $zip->close();
        }
    }

    public function import(string $uploadedPath, string $originalName): array
    {
        $this->ensureBackupDirectory();
        if (!is_file($uploadedPath)) {
            throw new RuntimeException('ملف الرفع غير صالح.');
        }
        $manifest = $this->validate($uploadedPath);
        $base = pathinfo(basename($originalName), PATHINFO_FILENAME);
        $target = $this->backupDirectory . '/imported_' . date('Ymd_His') . '_'
            . substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?: 'backup', 0, 50)
            . '.gnbackup.zip';
        if (!copy($uploadedPath, $target)) {
            throw new RuntimeException('تعذر حفظ حزمة النسخة المرفوعة.');
        }
        @chmod($target, 0660);
        $this->applyRetention();
        return $this->describe($target, $manifest);
    }

    public function restore(string $archivePath): array
    {
        $manifest = $this->validate($archivePath);
        $working = $this->temporaryDirectory('restore');
        $candidateDb = $working . '/greennet.sqlite';
        $candidateUploads = $working . '/uploads';
        $rollbackDb = $this->databasePath . '.restore-rollback';
        $rollbackUploads = $this->uploadsDirectory . '.restore-rollback';
        $databaseReplaced = false;
        $uploadsReplaced = false;
        $uploadsExisted = is_dir($this->uploadsDirectory);
        $this->acquireAutomationLock();

        try {
            $this->extractValidated($archivePath, $candidateDb, $candidateUploads);
            $this->assertSqliteIntegrity($candidateDb);
            $this->normalizePortablePaths($candidateDb);
            $safety = $this->create('before_restore');
            $this->ensureDirectory(dirname($this->databasePath));
            Database::connection()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            Database::disconnect();
            foreach ([$this->databasePath . '-wal', $this->databasePath . '-shm'] as $sidecar) {
                if (is_file($sidecar)) {
                    @unlink($sidecar);
                }
            }
            foreach ([$rollbackDb, $rollbackUploads] as $stale) {
                if (is_file($stale)) {
                    @unlink($stale);
                } elseif (is_dir($stale)) {
                    $this->removeDirectory($stale);
                }
            }
            if (is_file($this->databasePath) && !rename($this->databasePath, $rollbackDb)) {
                throw new RuntimeException('تعذر تجهيز قاعدة البيانات الحالية للرجوع الآمن.');
            }
            if (!rename($candidateDb, $this->databasePath)) {
                if (is_file($rollbackDb)) {
                    @rename($rollbackDb, $this->databasePath);
                }
                throw new RuntimeException('تعذر تثبيت قاعدة البيانات المستعادة.');
            }
            @chmod($this->databasePath, 0660);
            $databaseReplaced = true;
            if (is_dir($candidateUploads)) {
                if (is_dir($this->uploadsDirectory) && !rename($this->uploadsDirectory, $rollbackUploads)) {
                    throw new RuntimeException('تعذر تجهيز ملفات الرفع الحالية للرجوع الآمن.');
                }
                $this->ensureDirectory(dirname($this->uploadsDirectory));
                if (!rename($candidateUploads, $this->uploadsDirectory)) {
                    throw new RuntimeException('تعذر تثبيت ملفات الرفع المستعادة.');
                }
                $uploadsReplaced = true;
            }
            $this->assertSqliteIntegrity($this->databasePath);
            Database::disconnect();
            $restored = new PDO('sqlite:' . $this->databasePath);
            $restored->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if ($this->tableExists($restored, 'automation_lock')) {
                $restored->exec("DELETE FROM automation_lock WHERE owner='greennet-restore'");
            }
            if (is_file($rollbackDb)) {
                @unlink($rollbackDb);
            }
            if (is_dir($rollbackUploads)) {
                $this->removeDirectory($rollbackUploads);
            }
            return [
                'ok' => true,
                'manifest' => $manifest,
                'safety_backup' => $safety['name'],
            ];
        } catch (Throwable $e) {
            Database::disconnect();
            if ($databaseReplaced && is_file($rollbackDb)) {
                @unlink($this->databasePath);
                @rename($rollbackDb, $this->databasePath);
            }
            if ($uploadsReplaced) {
                $this->removeDirectory($this->uploadsDirectory);
                if ($uploadsExisted && is_dir($rollbackUploads)) {
                    @rename($rollbackUploads, $this->uploadsDirectory);
                }
            } elseif (!$uploadsReplaced && is_dir($rollbackUploads) && !is_dir($this->uploadsDirectory)) {
                @rename($rollbackUploads, $this->uploadsDirectory);
            }
            throw $e;
        } finally {
            $this->removeDirectory($working);
            $this->releaseAutomationLock();
        }
    }

    public function list(): array
    {
        $this->ensureBackupDirectory();
        $items = [];
        foreach (glob($this->backupDirectory . '/*.gnbackup.zip') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            try {
                $manifest = $this->manifestFromArchive($path);
                $items[] = $this->describe($path, $manifest);
            } catch (Throwable) {
                $items[] = $this->describe($path, [], false);
            }
        }
        usort($items, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        return $items;
    }

    public function delete(string $name): void
    {
        $path = $this->storedPath($name);
        if (!is_file($path) || !unlink($path)) {
            throw new RuntimeException('تعذر حذف النسخة المحددة.');
        }
    }

    public function storedPath(string $name): string
    {
        $safe = basename($name);
        if ($safe === '' || !str_ends_with($safe, '.gnbackup.zip')) {
            throw new RuntimeException('اسم حزمة النسخة غير صحيح.');
        }
        return $this->backupDirectory . '/' . $safe;
    }

    public function backupDirectory(): string
    {
        return $this->backupDirectory;
    }

    private function sqliteSnapshot(string $target): void
    {
        $pdo = Database::connection();
        $pdo->exec('PRAGMA wal_checkpoint(PASSIVE)');
        $pdo->exec('VACUUM main INTO ' . $pdo->quote($target));
        $snapshot = new PDO('sqlite:' . $target);
        $snapshot->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->tableExists($snapshot, 'automation_lock')) {
            $snapshot->exec('DELETE FROM automation_lock');
        }
        $this->assertSqliteIntegrity($target);
    }

    private function manifest(string $snapshot): array
    {
        $pdo = new PDO('sqlite:' . $snapshot);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'created_at' => gmdate(DATE_ATOM),
            'application_version' => Config::appVersion(),
            'application_commit' => (string) Config::get('APP_COMMIT', ''),
            'schema_user_version' => (int) $pdo->query('PRAGMA user_version')->fetchColumn(),
            'source_deployment' => (string) Config::get('DEPLOYMENT_TYPE', 'server-container'),
            'components' => [
                'database' => true,
                'uploads' => is_dir($this->uploadsDirectory),
            ],
            'database' => [
                'path' => 'database/greennet.sqlite',
                'bytes' => filesize($snapshot) ?: 0,
                'sha256' => hash_file('sha256', $snapshot),
                'tables' => count($tables),
            ],
            'configuration' => [
                'environment_file_included' => false,
                'deployment_secrets_in_manifest' => false,
                'note' => 'Database-backed settings remain compatible; deployment environment secrets must be restored separately.',
            ],
        ];
    }

    private function extractValidated(string $archivePath, string $databaseTarget, string $uploadsTarget): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('تعذر فتح الحزمة للاستعادة.');
        }
        try {
            $database = $zip->getFromName('database/greennet.sqlite');
            if (!is_string($database) || file_put_contents($databaseTarget, $database, LOCK_EX) === false) {
                throw new RuntimeException('تعذر استخراج قاعدة البيانات.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (!str_starts_with($name, 'uploads/') || str_ends_with($name, '/')) {
                    continue;
                }
                $relative = substr($name, strlen('uploads/'));
                if ($relative === '' || str_contains($relative, '..')) {
                    throw new RuntimeException('مسار ملف رفع غير آمن.');
                }
                $content = $zip->getFromIndex($i);
                if (!is_string($content)) {
                    throw new RuntimeException('تعذر قراءة ملف رفع من الحزمة.');
                }
                $target = $uploadsTarget . '/' . str_replace('\\', '/', $relative);
                $this->ensureDirectory(dirname($target));
                if (file_put_contents($target, $content, LOCK_EX) === false) {
                    throw new RuntimeException('تعذر استخراج ملفات الرفع.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function addUploads(ZipArchive $zip): void
    {
        if (!is_dir($this->uploadsDirectory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->uploadsDirectory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = trim(str_replace('\\', '/', substr($file->getPathname(), strlen($this->uploadsDirectory))), '/');
            $zip->addFile($file->getPathname(), 'uploads/' . $relative);
        }
    }

    private function normalizePortablePaths(string $database): void
    {
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->tableExists($pdo, 'media_assets')) {
            $stmt = $pdo->query("SELECT id,filename FROM media_assets");
            $update = $pdo->prepare("UPDATE media_assets SET storage_path=:path WHERE id=:id");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $update->execute([
                    'path' => rtrim($this->uploadsDirectory, '/\\') . '/media/' . basename((string) $row['filename']),
                    'id' => (int) $row['id'],
                ]);
            }
        }
        if ($this->tableExists($pdo, 'automation_lock')) {
            $pdo->exec('DELETE FROM automation_lock');
        }
    }

    private function acquireAutomationLock(): void
    {
        Database::migrate();
        $pdo = Database::connection();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS automation_lock (
                id INTEGER PRIMARY KEY,
                acquired_at TEXT NOT NULL,
                owner TEXT DEFAULT ''
            )"
        );
        $pdo->beginTransaction();
        try {
            $row = $pdo->query("SELECT acquired_at FROM automation_lock WHERE id=1")->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && strtotime((string) $row['acquired_at']) > time() - 900) {
                throw new RuntimeException('توجد مهمة آلية أو استعادة أخرى قيد التشغيل.');
            }
            $pdo->exec("INSERT INTO automation_lock (id,acquired_at,owner) VALUES (1,CURRENT_TIMESTAMP,'greennet-restore')
                ON CONFLICT(id) DO UPDATE SET acquired_at=CURRENT_TIMESTAMP,owner='greennet-restore'");
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function releaseAutomationLock(): void
    {
        try {
            Database::disconnect();
            if (is_file($this->databasePath)) {
                $pdo = new PDO('sqlite:' . $this->databasePath);
                if ($this->tableExists($pdo, 'automation_lock')) {
                    $pdo->exec("DELETE FROM automation_lock WHERE owner='greennet-restore'");
                }
            }
        } catch (Throwable) {
            // Preserve the primary restore result.
        } finally {
            Database::disconnect();
        }
    }

    private function assertSqliteIntegrity(string $path): void
    {
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $result = $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if ($result !== 'ok') {
            throw new RuntimeException('فشل فحص سلامة قاعدة بيانات النسخة.');
        }
    }

    private function manifestFromArchive(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Invalid archive.');
        }
        try {
            $raw = $zip->getFromName('manifest.json');
            return is_string($raw) ? (json_decode($raw, true, 32, JSON_THROW_ON_ERROR) ?: []) : [];
        } finally {
            $zip->close();
        }
    }

    private function describe(string $path, array $manifest, bool $valid = true): array
    {
        return [
            'name' => basename($path),
            'path' => $path,
            'size' => filesize($path) ?: 0,
            'mtime' => filemtime($path) ?: 0,
            'type' => 'GreenNet Portable',
            'extension' => 'zip',
            'format_version' => (int) ($manifest['format_version'] ?? 0),
            'created_at' => (string) ($manifest['created_at'] ?? ''),
            'valid' => $valid && ($manifest['format'] ?? '') === self::FORMAT,
        ];
    }

    private function applyRetention(): void
    {
        $files = glob($this->backupDirectory . '/*.gnbackup.zip') ?: [];
        usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        foreach (array_slice($files, $this->retention) as $old) {
            @unlink($old);
        }
    }

    private function temporaryDirectory(string $purpose): string
    {
        $this->ensureBackupDirectory();
        $dir = $this->backupDirectory . '/.' . $purpose . '-' . bin2hex(random_bytes(8));
        $this->ensureDirectory($dir);
        return $dir;
    }

    private function ensureBackupDirectory(): void
    {
        $this->ensureDirectory($this->backupDirectory);
        if (!is_writable($this->backupDirectory)) {
            throw new RuntimeException('مجلد النسخ الاحتياطية غير قابل للكتابة.');
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('تعذر إنشاء مجلد التخزين المطلوب.');
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir) || !str_starts_with(realpath($dir) ?: '', realpath($this->backupDirectory) ?: $this->backupDirectory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $stmt->execute(['name' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    private function safePrefix(string $prefix): string
    {
        return substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', $prefix) ?: 'greennet', 0, 40);
    }

    private function assertRuntime(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('امتداد ZIP غير متوفر في PHP.');
        }
        if ($this->databasePath === '' || !is_file($this->databasePath)) {
            throw new RuntimeException('قاعدة بيانات GreenNet غير موجودة.');
        }
    }
}
