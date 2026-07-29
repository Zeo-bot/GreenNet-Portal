<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\SubscriberRouterMigration;
use GreenNet\Services\SubscriberRouterMigrationService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class SubscriberRouterMigrationServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new ReflectionClass(Database::class))->getProperty('connection')->setValue(null, $this->pdo);
        Database::migrate();
        $this->pdo->exec("
            INSERT INTO routers (id,name,host,enabled,is_default) VALUES
                (1,'Source','192.0.2.1',1,1),
                (2,'Target','192.0.2.2',1,0);
            INSERT INTO service_packages (id,name,duration_days,quota_gb) VALUES (1,'Synthetic',30,5);
        ");
        CustomerLocal::create('migration-user', 'Synthetic', '', 'hotspot', 'paid', '', 1, 1, 'user-manager');
        $this->pdo->exec("
            INSERT INTO payments
                (username,amount,status,package_id,package_name,starts_at,expires_at,paid_at)
            VALUES
                ('migration-user',100,'paid',1,'Synthetic','2026-07-01','2026-08-01','2026-07-01')
        ");
    }

    protected function tearDown(): void
    {
        (new ReflectionClass(Database::class))->getProperty('connection')->setValue(null, null);
    }

    public function testVerifiedTargetCutsOverOnlyRoutingFieldsAndPreservesSubscriptionHistory(): void
    {
        $customer = CustomerLocal::findByUsername('migration-user');
        $migrationId = SubscriberRouterMigration::create([
            'customer_id' => (int) $customer['id'],
            'username' => 'migration-user',
            'source_router_id' => 1,
            'source_backend' => 'user-manager',
            'target_router_id' => 2,
            'target_backend' => 'native-hotspot',
            'package_id' => 1,
            'target_profile_name' => 'Synthetic Target',
            'status' => 'target_created',
            'usage_decision' => 'preserve_recorded_usage',
            'usage_snapshot' => ['used_bytes' => 123456],
        ]);

        (new SubscriberRouterMigrationService())->cutoverAfterVerifiedTarget($migrationId, '*target');

        $after = CustomerLocal::findByUsername('migration-user');
        self::assertSame((int) $customer['id'], (int) $after['id']);
        self::assertSame(2, (int) $after['router_id']);
        self::assertSame('native-hotspot', $after['service_backend']);
        self::assertSame(1, (int) $after['package_id']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM payments WHERE username='migration-user'")->fetchColumn());
        $migration = SubscriberRouterMigration::find($migrationId);
        self::assertSame('cutover_complete', $migration['status']);
        self::assertSame('*target', $migration['target_record_id']);
    }

    public function testChangedSourceAssignmentBlocksCutoverAndLeavesCustomerUntouched(): void
    {
        $customer = CustomerLocal::findByUsername('migration-user');
        $migrationId = SubscriberRouterMigration::create([
            'customer_id' => (int) $customer['id'],
            'username' => 'migration-user',
            'source_router_id' => 1,
            'source_backend' => 'user-manager',
            'target_router_id' => 2,
            'target_backend' => 'native-pppoe',
            'package_id' => 1,
            'target_profile_name' => 'Synthetic Target',
            'status' => 'target_created',
        ]);
        $this->pdo->exec("UPDATE customers_local SET service_backend='native-hotspot' WHERE username='migration-user'");

        try {
            (new SubscriberRouterMigrationService())->cutoverAfterVerifiedTarget($migrationId, '*target');
            self::fail('Expected stale-source cutover to fail.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('تغيّر تعيين المشترك', $e->getMessage());
        }

        $after = CustomerLocal::findByUsername('migration-user');
        self::assertSame(1, (int) $after['router_id']);
        self::assertSame('native-hotspot', $after['service_backend']);
        self::assertSame('target_created', SubscriberRouterMigration::find($migrationId)['status']);
    }
}
