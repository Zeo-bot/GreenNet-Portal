<?php

declare(strict_types=1);

namespace GreenNet\DTO\RouterOS;

final readonly class WriteExecutionRequest
{
    public function __construct(
        public string $action,
        public string $dataset,
        public string $username,
        public string $auditCommand,
        public array $auditParams,
        public int $dryRunAuditId,
        public bool $confirmed,
        public bool $allowSafeMode = false
    ) {
    }
}
