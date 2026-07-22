<?php

declare(strict_types=1);

namespace GreenNet\DTO\RouterOS;

final readonly class WriteExecutionResult
{
    /** @param list<WriteCommandCall> $calls */
    public function __construct(
        public bool $ok,
        public mixed $value,
        public array $calls,
        public bool $partialFailure,
        public int $successfulCallCount,
        public int $attemptedCallCount,
        public bool $auditRecorded,
        public int $auditId = 0,
        public string $safeError = '',
        public string $auditWarning = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'value' => $this->value,
            'calls' => array_map(
                static fn (WriteCommandCall $call): array => $call->toArray(),
                $this->calls
            ),
            'partial_failure' => $this->partialFailure,
            'successful_call_count' => $this->successfulCallCount,
            'attempted_call_count' => $this->attemptedCallCount,
            'audit_recorded' => $this->auditRecorded,
            'audit_id' => $this->auditId,
            'error' => $this->safeError,
            'audit_warning' => $this->auditWarning,
        ];
    }
}
