<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Services\RouterOS\RouterOSProfilesService;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouterOSProfilesServiceTest extends TestCase
{
    public function testReadsAllSupportedProfileBackendsThroughGateway(): void
    {
        $gateway = new FakeRouterOSReadGateway();
        $gateway->queueResponse([['name' => 'hs-live', 'rate-limit' => '2M/2M']]);
        $gateway->queueResponse([['name' => 'ppp-live', 'rate-limit' => '3M/3M']]);
        $gateway->queueResponse([['name' => 'um-live', 'validity' => '30d']]);

        $summary = (new RouterOSProfilesService($gateway))->getSummary();

        self::assertSame(3, $summary['total_count']);
        self::assertSame([
            '/ip/hotspot/user/profile/print',
            '/ppp/profile/print',
            '/user-manager/profile/print',
        ], array_column($gateway->calls, 'command'));
        self::assertSame(['Hotspot', 'PPP', 'User Manager'], array_column($summary['all_profiles'], 'type'));
    }

    public function testConnectionFailureIsNotReportedAsEmptyImport(): void
    {
        $gateway = new FakeRouterOSReadGateway();
        $gateway->failWith(new RuntimeException('Synthetic timeout.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Synthetic timeout.');

        (new RouterOSProfilesService($gateway))->getSummary();
    }
}
