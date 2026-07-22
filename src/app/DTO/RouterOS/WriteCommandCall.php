<?php

declare(strict_types=1);

namespace GreenNet\DTO\RouterOS;

final readonly class WriteCommandCall
{
    public function __construct(
        public string $action,
        public string $command,
        public array $redactedParams,
        public array $response,
        public bool $success,
        public string $safeError = ''
    ) {
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'command' => $this->command,
            'params' => $this->redactedParams,
            'response' => $this->response,
            'success' => $this->success,
            'error' => $this->safeError,
        ];
    }
}
