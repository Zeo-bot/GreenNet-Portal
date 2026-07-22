<?php

declare(strict_types=1);

namespace GreenNet\Tests\Support;

use Closure;
use GreenNet\Contracts\RouterOSClientInterface;
use Throwable;

final class FakeRouterOSClient implements RouterOSClientInterface
{
    /** @var list<array{command: string, params: array}> */
    public array $calls = [];

    /** @var list<array|Throwable> */
    private array $outcomes = [];

    private ?Throwable $failure = null;
    private ?Closure $onCall;

    public function __construct(?callable $onCall = null)
    {
        $this->onCall = $onCall !== null ? Closure::fromCallable($onCall) : null;
    }

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

    public function comm(string $command, array $params = []): array
    {
        $this->calls[] = ['command' => $command, 'params' => $params];
        ($this->onCall ?? static fn (): null => null)($command, $params);

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
