<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Contracts\RouterOSReadGatewayInterface;

final class RouterOSReadGatewayFactory
{
    public static function create(array $settings = []): RouterOSReadGatewayInterface
    {
        return new RealRouterOSReadGateway(new RouterOSApiClient($settings));
    }
}
