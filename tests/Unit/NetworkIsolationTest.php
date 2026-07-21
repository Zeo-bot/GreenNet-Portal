<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NetworkIsolationTest extends TestCase
{
    public function testContainerHasOnlyLoopbackNetworkInterface(): void
    {
        $interfaces = array_map('basename', glob('/sys/class/net/*') ?: []);
        sort($interfaces);

        self::assertSame(['lo'], $interfaces, 'The test container must run with network_mode: none.');
    }
}
