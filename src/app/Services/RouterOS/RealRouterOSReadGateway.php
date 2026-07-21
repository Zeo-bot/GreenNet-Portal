<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Contracts\RouterOSClientInterface;
use GreenNet\Contracts\RouterOSReadGatewayInterface;

final class RealRouterOSReadGateway implements RouterOSReadGatewayInterface
{
    public function __construct(
        private readonly RouterOSClientInterface $client,
        private readonly ReadCommandPolicy $policy = new ReadCommandPolicy()
    ) {
    }

    public function read(string $command, array $params = []): array
    {
        $this->policy->assertAllowed($command);

        return $this->client->comm($command, $params);
    }
}
