<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Services\WriteSafetyGuard;

final class RouterOSGatewayBundleFactory
{
    public static function create(array $settings = []): RouterOSGatewayBundle
    {
        $client = new RouterOSApiClient($settings);
        $guard = new WriteSafetyGuard();
        $guard->ensureTables();

        return new RouterOSGatewayBundle(
            new RealRouterOSReadGateway($client),
            new GuardedRouterOSWriteGateway(
                $client,
                $guard,
                new WriteCommandPolicy(),
                new RouterOSWriteRedactor()
            )
        );
    }
}
