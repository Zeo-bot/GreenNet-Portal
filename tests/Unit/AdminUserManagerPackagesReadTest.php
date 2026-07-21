<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Controllers\AdminUserManagerPackagesController;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AdminUserManagerPackagesReadTest extends TestCase
{
    public function testDiscoveryPreservesResultShapeAndReadOrder(): void
    {
        $gateway = new FakeRouterOSReadGateway();
        $gateway->queueResponse([[
            '.id' => '*profile-1',
            'name' => 'Synthetic Bronze',
            'name-for-users' => 'Synthetic Bronze Package',
            'validity' => '30d',
        ]]);
        $gateway->queueResponse([[
            '.id' => '*limit-1',
            'name' => 'Synthetic Limit',
            'transfer-limit' => '1073741824',
            'rate-limit' => '10M/10M',
        ]]);
        $gateway->queueResponse([[
            'profile' => '*profile-1',
            'limitation' => '*limit-1',
        ]]);

        $result = $this->discover($gateway);

        self::assertTrue($result['ok']);
        self::assertTrue($result['read_only']);
        self::assertFalse($result['mikrotik_write']);
        self::assertSame(1, $result['summary']['profiles_count']);
        self::assertSame(1, $result['summary']['limitations_count']);
        self::assertSame(1, $result['summary']['links_count']);
        self::assertSame(1, $result['summary']['candidates_count']);
        self::assertSame('linked', $result['package_candidates'][0]['match_status']);
        self::assertSame('Synthetic Bronze', $result['package_candidates'][0]['suggested_source_profile']);
        self::assertSame([
            ['command' => '/user-manager/profile/print', 'params' => []],
            ['command' => '/user-manager/limitation/print', 'params' => []],
            ['command' => '/user-manager/profile-limitation/print', 'params' => []],
        ], $gateway->calls);
    }

    public function testEmptyResponsesPreserveFallbackAttemptsAndEmptySummary(): void
    {
        $gateway = new FakeRouterOSReadGateway();
        for ($index = 0; $index < 5; $index++) {
            $gateway->queueResponse([]);
        }

        $result = $this->discover($gateway);

        self::assertTrue($result['ok']);
        self::assertSame([], $result['package_candidates']);
        self::assertSame(0, $result['summary']['profiles_count']);
        self::assertSame(0, $result['summary']['limitations_count']);
        self::assertSame(0, $result['summary']['links_count']);
        self::assertCount(3, $result['link_attempts']);
        self::assertSame([
            '/user-manager/profile/print',
            '/user-manager/limitation/print',
            '/user-manager/profile-limitation/print',
            '/user-manager/profile/limitation/print',
            '/user-manager/profile/limitations/print',
        ], array_column($gateway->calls, 'command'));
        self::assertSame([[], [], [], [], []], array_column($gateway->calls, 'params'));
    }

    public function testSyntheticReadFailuresPreserveCurrentErrorResult(): void
    {
        $gateway = new FakeRouterOSReadGateway();
        $gateway->failWith(new RuntimeException('Synthetic timeout.'));

        $result = $this->discover($gateway);

        self::assertFalse($result['ok']);
        self::assertSame('unreachable', $result['profiles']['status']);
        self::assertSame('Synthetic timeout.', $result['profiles']['error']);
        self::assertSame([], $result['profiles']['rows']);
        self::assertSame('unreachable', $result['limitations']['status']);
        self::assertCount(3, $result['link_attempts']);
        self::assertCount(5, $gateway->calls);
    }

    public function testControllerHasNoDirectClientConstructionOrCommCall(): void
    {
        $reflection = new ReflectionClass(AdminUserManagerPackagesController::class);
        $source = file_get_contents((string) $reflection->getFileName());

        self::assertIsString($source);
        self::assertStringNotContainsString('new RouterOSApiClient', $source);
        self::assertStringNotContainsString('->comm(', $source);
        self::assertStringContainsString('RouterOSReadGatewayInterface', $source);
    }

    public function testFakeGatewayHasNoSocketState(): void
    {
        $gateway = new FakeRouterOSReadGateway();

        self::assertFalse((new ReflectionClass($gateway))->hasProperty('socket'));
        self::assertSame([], $gateway->calls);
    }

    private function discover(FakeRouterOSReadGateway $gateway): array
    {
        $controller = new AdminUserManagerPackagesController($gateway);
        $method = (new ReflectionClass($controller))->getMethod('discover');

        return $method->invoke($controller);
    }
}
