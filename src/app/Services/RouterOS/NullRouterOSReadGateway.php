<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Contracts\RouterOSReadGatewayInterface;
use RuntimeException;

final class NullRouterOSReadGateway implements RouterOSReadGatewayInterface
{
    public function read(string $command, array $params = []): array
    {
        throw new RuntimeException('RouterOS reads are disabled.');
    }
}
