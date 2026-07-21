<?php

declare(strict_types=1);

namespace GreenNet\Contracts;

interface RouterOSReadGatewayInterface
{
    public function read(string $command, array $params = []): array;
}
