<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Services\ProductionBackupService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class ProductionBackupServiceTest extends TestCase
{
    private string $root;
    private string $database;
    private string $backups;
    private string $uploads;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/greennet-production-backup-' . bin2hex(random_bytes(8));
        $this->database = $this->root . '/database/greennet.sqlite';
        $this->backups = $this->root . '/backups';
        $this->uploads = $this->root . '/uploads';
        mkdir(dirname($this->database), 0700, true);
        mkdir($this->backups, 0700, true);
        mkdir($this->uploads . '/media', 0700, true);
        $this->pdo = new PDO('sqlite:' . $this->database);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new ReflectionClass(Database::class))->getProperty('connection')->setValue(null, $this->pdo);
        Database::migrate();
        CustomerLocal::create('backup-user', 'Before backup', '', 'hotspot', 'paid', '', 0);
        file_put_contents($this->uploads . '/media/logo.txt', 'original-upload');
    }

    protected function tearDown(): void
    {
        Database::disconnect();
        $this->remove($this->root);
    }

    public function testPortablePackageRoundTripRestoresDatabaseAndUploads(): void
    {
        $service = new ProductionBackupService(
            $this->database,
            $this->backups,
            $this->uploads,
            5
        );
        $backup = $service->create();
        $manifest = $service->validate($backup['path']);
        self::assertSame(ProductionBackupService::FORMAT, $manifest['format']);
        self::assertFalse($manifest['configuration']['environment_file_included']);

        $this->pdo->exec("UPDATE customers_local SET display_name='After backup' WHERE username='backup-user'");
        file_put_contents($this->uploads . '/media/logo.txt', 'changed-upload');

        $result = $service->restore($backup['path']);
        self::assertTrue($result['ok']);
        self::assertNotSame('', $result['safety_backup']);

        $restored = new PDO('sqlite:' . $this->database);
        self::assertSame(
            'Before backup',
            $restored->query("SELECT display_name FROM customers_local WHERE username='backup-user'")->fetchColumn()
        );
        self::assertSame('original-upload', file_get_contents($this->uploads . '/media/logo.txt'));
        self::assertSame('ok', $restored->query('PRAGMA integrity_check')->fetchColumn());
    }

    public function testInvalidArchiveIsRejectedBeforeCurrentDatabaseChanges(): void
    {
        $service = new ProductionBackupService($this->database, $this->backups, $this->uploads, 5);
        $invalid = $this->backups . '/invalid.gnbackup.zip';
        file_put_contents($invalid, 'not-a-zip');
        $before = hash_file('sha256', $this->database);

        $this->expectException(RuntimeException::class);
        try {
            $service->restore($invalid);
        } finally {
            self::assertSame($before, hash_file('sha256', $this->database));
        }
    }

    private function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
