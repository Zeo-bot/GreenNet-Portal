<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Tests\Support\TempDatabase;
use PHPUnit\Framework\TestCase;

final class TempDatabaseTest extends TestCase
{
    public function testCreatesWritesAndReadsAnIsolatedSqliteDatabase(): void
    {
        $database = new TempDatabase();
        $pdo = $database->connection();

        $pdo->exec('CREATE TABLE synthetic_records (id INTEGER PRIMARY KEY, label TEXT NOT NULL)');
        $statement = $pdo->prepare('INSERT INTO synthetic_records (label) VALUES (:label)');
        $statement->execute(['label' => 'synthetic-test-record']);

        self::assertFileExists($database->path());
        self::assertSame('synthetic-test-record', $pdo->query('SELECT label FROM synthetic_records')->fetchColumn());

        $database->cleanup();
    }

    public function testDatabasePathCannotBeTheLiveDatabaseOrBackupDirectory(): void
    {
        $database = new TempDatabase();
        $normalizedPath = str_replace('\\', '/', $database->path());

        self::assertNotSame(str_replace('\\', '/', GRENNET_LIVE_DATABASE), $normalizedPath);
        self::assertStringNotContainsString(
            rtrim(str_replace('\\', '/', GRENNET_LIVE_BACKUPS), '/') . '/',
            $normalizedPath
        );
        self::assertStringStartsWith(rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/', $normalizedPath);

        $database->cleanup();
    }

    public function testCleanupRemovesTheTemporarySqliteFileAndDirectory(): void
    {
        $database = new TempDatabase();
        $path = $database->path();
        $directory = dirname($path);

        $database->connection()->exec('CREATE TABLE disposable_record (id INTEGER PRIMARY KEY)');
        self::assertFileExists($path);

        $database->cleanup();

        self::assertFileDoesNotExist($path);
        self::assertDirectoryDoesNotExist($directory);
    }
}
