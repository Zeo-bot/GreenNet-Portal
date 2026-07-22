<?php

declare(strict_types=1);

namespace GreenNet\DTO\RouterOS;

final readonly class RouterOSWriteCommand
{
    public function __construct(
        public string $command,
        public array $params = []
    ) {
    }

    public function action(): string
    {
        $segments = explode('/', trim($this->command, '/'));

        return (string) (end($segments) ?: '');
    }
}
