<?php

declare(strict_types=1);

namespace GreenNet\Contracts;

interface RouterOSClientInterface
{
    public function comm(string $command, array $params = []): array;
}
