<?php

declare(strict_types=1);

namespace GreenNet\Contracts;

use GreenNet\DTO\RouterOS\RouterOSWriteCommand;

interface AuthorizedRouterOSWriterInterface
{
    public function execute(RouterOSWriteCommand $command): array;
}
