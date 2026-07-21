<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Core\Router;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use GreenNet\Services\WriteSafetyGuard;
use PHPUnit\Framework\TestCase;

final class CoreLoadingTest extends TestCase
{
    public function testCoreClassesAutoloadWithoutInstantiatingRouterOsClient(): void
    {
        self::assertTrue(class_exists(Config::class));
        self::assertTrue(class_exists(Database::class));
        self::assertTrue(class_exists(Router::class));
        self::assertTrue(class_exists(WriteSafetyGuard::class));
        self::assertTrue(class_exists(RouterOSApiClient::class));
    }
}
