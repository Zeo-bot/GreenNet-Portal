<?php

declare(strict_types=1);

namespace GreenNet\Contracts;

use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\DTO\RouterOS\WriteExecutionResult;

interface GuardedRouterOSWriteGatewayInterface
{
    /** @param callable(AuthorizedRouterOSWriterInterface): mixed $operation */
    public function execute(WriteExecutionRequest $request, callable $operation): WriteExecutionResult;
}
