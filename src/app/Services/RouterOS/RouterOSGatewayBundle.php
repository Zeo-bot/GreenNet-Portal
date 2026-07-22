<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Contracts\GuardedRouterOSWriteGatewayInterface;
use GreenNet\Contracts\RouterOSReadGatewayInterface;

final class RouterOSGatewayBundle
{
    public function __construct(
        public readonly RouterOSReadGatewayInterface $read,
        public readonly GuardedRouterOSWriteGatewayInterface $write
    ) {
    }
}
