<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Contracts\RouterOSClientInterface;
use GreenNet\Services\RouterOS\NullRouterOSReadGateway;
use GreenNet\Services\RouterOS\RealRouterOSReadGateway;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use GreenNet\Tests\Support\FakeRouterOSClient;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class RouterOSReadGatewayTest extends TestCase
{
    public function testApiClientImplementsContractWithoutOpeningAConnection(): void
    {
        $client = (new ReflectionClass(RouterOSApiClient::class))->newInstanceWithoutConstructor();

        self::assertInstanceOf(RouterOSClientInterface::class, $client);
        self::assertFalse($client->isConnected());
    }

    public function testRealGatewayPreservesCommandParamsAndResponse(): void
    {
        $client = new FakeRouterOSClient();
        $response = [['name' => 'synthetic-router']];
        $params = ['?.id' => '*synthetic'];
        $client->queueResponse($response);

        $actual = (new RealRouterOSReadGateway($client))->read('/system/identity/print', $params);

        self::assertSame($response, $actual);
        self::assertSame([
            ['command' => '/system/identity/print', 'params' => $params],
        ], $client->calls);
    }

    public function testRealGatewayPreservesClientExceptionInstance(): void
    {
        $client = new FakeRouterOSClient();
        $failure = new RuntimeException('Synthetic connection failure.');
        $client->failWith($failure);

        try {
            (new RealRouterOSReadGateway($client))->read('/system/resource/print');
            self::fail('Expected the synthetic client failure.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    #[DataProvider('allowedReadCommands')]
    public function testEveryCurrentReadCommandIsAllowed(string $command): void
    {
        $client = new FakeRouterOSClient();

        (new RealRouterOSReadGateway($client))->read($command);

        self::assertSame($command, $client->calls[0]['command']);
    }

    /** @return iterable<string, array{string}> */
    public static function allowedReadCommands(): iterable
    {
        foreach ([
            '/system/identity/print',
            '/system/resource/print',
            '/system/routerboard/print',
            '/ip/hotspot/active/print',
            '/ip/hotspot/user/print',
            '/ip/hotspot/user/profile/print',
            '/ppp/active/print',
            '/ppp/secret/print',
            '/ppp/profile/print',
            '/user-manager/user/print',
            '/user-manager/user/monitor',
            '/user-manager/profile/print',
            '/user-manager/session/print',
            '/user-manager/user-profile/print',
            '/user-manager/limitation/print',
            '/user-manager/profile-limitation/print',
            '/user-manager/profile/limitation/print',
            '/user-manager/profile/limitations/print',
            '/tool/user-manager/user/print',
            '/tool/user-manager/profile/print',
            '/tool/user-manager/session/print',
        ] as $command) {
            yield $command => [$command];
        }
    }

    #[DataProvider('rejectedWriteCommands')]
    public function testEveryCurrentWriteActionIsRejectedBeforeClient(string $command): void
    {
        $client = new FakeRouterOSClient();

        try {
            (new RealRouterOSReadGateway($client))->read($command);
            self::fail('Expected the write command to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('RouterOS read command is not allowed.', $exception->getMessage());
            self::assertSame([], $client->calls);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedWriteCommands(): iterable
    {
        return [
            'add' => ['/user-manager/user/add'],
            'set' => ['/user-manager/user/set'],
            'remove' => ['/user-manager/user/remove'],
            'reset-counters' => ['/ip/hotspot/user/reset-counters'],
        ];
    }

    public function testUnknownCommandIsRejectedFailClosedBeforeClient(): void
    {
        $client = new FakeRouterOSClient();

        try {
            (new RealRouterOSReadGateway($client))->read('/unknown/resource/print');
            self::fail('Expected the unknown command to be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('RouterOS read command is not allowed.', $exception->getMessage());
            self::assertSame([], $client->calls);
        }
    }

    public function testNullGatewayFailsWithoutLeakingAnAddress(): void
    {
        try {
            (new NullRouterOSReadGateway())->read('/system/identity/print');
            self::fail('Expected reads to be disabled.');
        } catch (RuntimeException $exception) {
            self::assertSame('RouterOS reads are disabled.', $exception->getMessage());
            self::assertStringNotContainsString('://', $exception->getMessage());
            self::assertStringNotContainsString(':8728', $exception->getMessage());
        }
    }

    public function testFakeGatewayRecordsCallsAndReturnsFixture(): void
    {
        $gateway = new FakeRouterOSReadGateway();
        $fixture = [['name' => 'synthetic-profile']];
        $gateway->queueResponse($fixture);

        $actual = $gateway->read('/user-manager/profile/print', ['?name' => 'synthetic']);

        self::assertSame($fixture, $actual);
        self::assertSame([[
            'command' => '/user-manager/profile/print',
            'params' => ['?name' => 'synthetic'],
        ]], $gateway->calls);
    }

    public function testFakeGatewayCanRaiseSyntheticFailure(): void
    {
        $gateway = new FakeRouterOSReadGateway();
        $failure = new RuntimeException('Synthetic read failure.');
        $gateway->failWith($failure);

        try {
            $gateway->read('/system/identity/print');
            self::fail('Expected the synthetic gateway failure.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
            self::assertCount(1, $gateway->calls);
        }
    }

    public function testProductionSourceDoesNotReferenceTestFakesOrUnguardedWriteGateway(): void
    {
        $sourceRoot = GRENNET_TEST_ROOT . '/src';
        $references = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents !== false && (
                str_contains($contents, 'GreenNet\\Tests')
                || str_contains($contents, 'FakeRouterOS')
                || str_contains($contents, 'RealRouterOSWriteGateway')
                || str_contains($contents, 'RouterOSGatewayInterface')
            )) {
                $references[] = $file->getPathname();
            }
        }

        self::assertSame([], $references);
    }
}
