<?php

declare(strict_types=1);

namespace GreenNet\Contracts;

use GreenNet\DTO\RouterOS\RouterOSWriteCommand;

interface RouterOSWriteCommandPolicyInterface
{
    public function assertAllowed(RouterOSWriteCommand $command): void;
}
