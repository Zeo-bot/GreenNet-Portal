<?php

declare(strict_types=1);

namespace GreenNet\Exceptions;

use GreenNet\DTO\RouterOS\WriteExecutionResult;
use RuntimeException;

final class GuardedWriteExecutionException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly WriteExecutionResult $result
    ) {
        parent::__construct($message);
    }

    public function result(): WriteExecutionResult
    {
        return $this->result;
    }
}
