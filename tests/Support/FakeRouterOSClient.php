<?php

declare(strict_types=1);

namespace GreenNet\Tests\Support;

use GreenNet\Contracts\RouterOSClientInterface;
use Throwable;

final class FakeRouterOSClient implements RouterOSClientInterface
{
    /** @var list<array{command: string, params: array}> */
    public array $calls = [];

    /** @var list<array> */
    private array $responses = [];

    private ?Throwable $failure = null;

    public function queueResponse(array $response): void
    {
        $this->responses[] = $response;
    }

    public function failWith(Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function comm(string $command, array $params = []): array
    {
        $this->calls[] = ['command' => $command, 'params' => $params];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return array_shift($this->responses) ?? [];
    }
}
