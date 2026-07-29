<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use DateTimeImmutable;
use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Services\SubscriptionLifecycleService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SubscriptionLifecycleServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $property = (new ReflectionClass(Database::class))->getProperty('connection');
        $property->setValue(null, $this->pdo);
        Database::migrate();
        $this->pdo->exec("INSERT INTO service_packages (id,name,duration_days,quota_gb,rate_limit) VALUES (1,'Synthetic',30,1,'1M/1M')");
        CustomerLocal::create('lifecycle-user', 'Synthetic', '', 'hotspot', 'paid', '', 1, null, 'native-hotspot');
    }

    public function testExpiredTimeRequiresEnforcement(): void
    {
        $this->payment('-2 days', '-1 day');
        $state = (new SubscriptionLifecycleService())->evaluate('lifecycle-user', 0);

        self::assertSame('expired_time', $state['state']);
        self::assertTrue($state['requires_enforcement']);
        self::assertSame('pending', $state['enforcement_state']);
    }

    public function testQuotaExhaustionRequiresEnforcement(): void
    {
        $this->payment('-1 day', '+20 days');
        $state = (new SubscriptionLifecycleService())->evaluate('lifecycle-user', 1_000_000_000);

        self::assertSame('quota_exhausted', $state['state']);
        self::assertTrue($state['requires_enforcement']);
    }

    public function testUnavailableUsageDoesNotExpireSubscriber(): void
    {
        $this->payment('-1 day', '+20 days');
        $state = (new SubscriptionLifecycleService())->evaluate('lifecycle-user', null);

        self::assertSame('active', $state['state']);
        self::assertSame('unavailable', $state['usage_state']);
        self::assertFalse($state['requires_enforcement']);
    }

    private function payment(string $start, string $expiry): void
    {
        Payment::create(
            'lifecycle-user',
            0,
            'SYP',
            'paid',
            'synthetic',
            1,
            'Synthetic',
            30,
            1,
            (new DateTimeImmutable($start))->format('Y-m-d H:i:s'),
            (new DateTimeImmutable($expiry))->format('Y-m-d H:i:s')
        );
    }
}
