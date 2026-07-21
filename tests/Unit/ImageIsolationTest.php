<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ImageIsolationTest extends TestCase
{
    public function testImageDoesNotContainOperationalConfigurationDatabaseOrBackups(): void
    {
        self::assertFileDoesNotExist('/app/.env');
        self::assertFileDoesNotExist('/app/src/.env');
        self::assertFileDoesNotExist('/app/src/database/database.sqlite');
        self::assertDirectoryDoesNotExist('/app/src/storage/backups');
    }
}
