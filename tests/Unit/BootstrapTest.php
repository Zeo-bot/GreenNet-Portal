<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    public function testPhpUnitBootsInTestingModeWithoutRouterCredentials(): void
    {
        self::assertSame('testing', getenv('APP_ENV'));
        self::assertSame('', getenv('MIKROTIK_HOST'));
        self::assertSame('', getenv('MIKROTIK_USERNAME'));
        self::assertSame('', getenv('MIKROTIK_PASSWORD'));
    }
}
