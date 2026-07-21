<?php

declare(strict_types=1);

namespace GreenNet\Tests\Support;

use GreenNet\Contracts\RouterOSReadGatewayInterface;
use Throwable;

final class FakeRouterOSReadGateway implements RouterOSReadGatewayInterface
{
    /** @var list<array{command: string, params: array}> */
    public array $calls = [];

    /** @var list<array|Throwable> */
    private array $outcomes = [];

    private ?Throwable $failure = null;

    public function queueResponse(array $response): void
    {
        $this->outcomes[] = $response;
    }

    public function queueFailure(Throwable $failure): void
    {
        $this->outcomes[] = $failure;
    }

    public function failWith(Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function read(string $command, array $params = []): array
    {
        $this->calls[] = ['command' => $command, 'params' => $params];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $outcome = array_shift($this->outcomes) ?? [];

        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}
