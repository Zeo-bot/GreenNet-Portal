<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Contracts\RouterOSReadGatewayInterface;
use GreenNet\Services\RouterOS\RealRouterOSReadGateway;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use GreenNet\Services\RouterOS\RouterOSReadGatewayFactory;
use GreenNet\Tests\Support\StubRouterSettingsService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RouterOSReadGatewayFactoryTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryCreatesDisconnectedRealGatewayWithSyntheticSettings(): void
    {
        class_alias(StubRouterSettingsService::class, 'GreenNet\\Services\\RouterSettingsService');

        $gateway = RouterOSReadGatewayFactory::create([
            'host' => '192.0.2.1',
            'port' => 8728,
            'username' => 'synthetic-user',
            'password' => 'synthetic-password',
            'timeout' => 1,
        ]);

        self::assertInstanceOf(RouterOSReadGatewayInterface::class, $gateway);
        self::assertInstanceOf(RealRouterOSReadGateway::class, $gateway);
        self::assertSame(1, StubRouterSettingsService::$calls);

        $clientProperty = (new ReflectionClass($gateway))->getProperty('client');
        $client = $clientProperty->getValue($gateway);

        self::assertInstanceOf(RouterOSApiClient::class, $client);
        self::assertFalse($client->isConnected());
    }
}
