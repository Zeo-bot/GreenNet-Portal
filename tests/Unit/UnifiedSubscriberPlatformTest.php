<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Services\SubscriberSecurityService;
use GreenNet\Services\UnifiedSubscriberService;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use GreenNet\Tests\Support\TempDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class UnifiedSubscriberPlatformTest extends TestCase
{
    private TempDatabase $database;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->database = new TempDatabase();
        $this->pdo = $this->database->connection();
        $this->pdo->exec("CREATE TABLE customers_local (
            id INTEGER PRIMARY KEY, username TEXT UNIQUE, display_name TEXT, phone TEXT,
            access_type TEXT, package_id INTEGER, created_at TEXT
        )");
        $this->pdo->exec("CREATE TABLE service_packages (
            id INTEGER PRIMARY KEY, name TEXT, source_profile TEXT, rate_limit TEXT, quota_gb REAL
        )");
        $this->pdo->exec("CREATE TABLE payments (
            id INTEGER PRIMARY KEY, username TEXT, package_name TEXT, amount INTEGER, currency TEXT,
            status TEXT, starts_at TEXT, expires_at TEXT, paid_at TEXT, created_at TEXT
        )");
        $this->pdo->exec("CREATE TABLE notifications (
            id INTEGER PRIMARY KEY, username TEXT, title TEXT, body TEXT, type TEXT, is_read INTEGER,
            created_at TEXT
        )");
        $this->pdo->exec("INSERT INTO service_packages VALUES
            (1, 'Green 10', 'green-10', '10M/10M', 10)");
        $this->pdo->exec("INSERT INTO customers_local VALUES
            (1, 'hot-user', 'Hot Subscriber', '', 'hotspot', 1, '2026-01-01'),
            (2, 'ppp-user', 'PPP Subscriber', '', 'pppoe', 1, '2026-01-01')");
        $this->pdo->exec("INSERT INTO payments VALUES
            (1, 'hot-user', 'Green 10', 100, 'SYP', 'paid', '2026-07-01', '2026-08-01', '2026-07-01', '2026-07-01')");
        $this->pdo->exec("INSERT INTO notifications VALUES
            (1, NULL, 'General', 'Safe general notice', 'info', 0, '2026-07-01'),
            (2, 'hot-user', 'Private', 'Owned notice', 'info', 0, '2026-07-02'),
            (3, 'ppp-user', 'Other', 'Not owned', 'info', 0, '2026-07-03')");
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->database->cleanup();
    }

    public function testHotspotSubscriberIsNormalizedWithoutRawRows(): void
    {
        $gateway = $this->gateway([
            [['.id' => '*u1', 'name' => 'hot-user', 'disabled' => 'no', 'profile' => 'green-10', 'password' => 'synthetic-secret']],
            [],
            [],
            [['.id' => '*a1', 'user' => 'hot-user', 'address' => '192.0.2.10', 'uptime' => '1h', 'bytes-in' => '100', 'bytes-out' => '300']],
            [],
        ]);
        $summary = (new UnifiedSubscriberService($this->pdo, $gateway, strtotime('2026-07-20')))->summary('hot-user');

        self::assertSame('hotspot', $summary['access_type']);
        self::assertSame('hotspot', $summary['router_backend']);
        self::assertTrue($summary['enabled']);
        self::assertTrue($summary['online']);
        self::assertSame(100, $summary['usage']['upload_bytes']);
        self::assertSame(300, $summary['usage']['download_bytes']);
        self::assertSame(400, $summary['usage']['total_bytes']);
        self::assertSame(12, $summary['remaining_days']);
        self::assertStringNotContainsString('synthetic-secret', serialize($summary));
        self::assertArrayNotHasKey('raw', $summary);
    }

    public function testPppoeAndUnknownValuesAreExplicitlyNormalized(): void
    {
        $gateway = $this->gateway([
            [],
            [['.id' => '*p1', 'name' => 'ppp-user', 'disabled' => 'yes', 'profile' => 'green-10']],
            [],
            [],
            [],
        ]);
        $summary = (new UnifiedSubscriberService($this->pdo, $gateway, strtotime('2026-07-20')))->summary('ppp-user');

        self::assertSame('pppoe', $summary['access_type']);
        self::assertSame('disabled', $summary['status']);
        self::assertFalse($summary['enabled']);
        self::assertFalse($summary['online']);
        self::assertNull($summary['usage']['download_bytes']);
        self::assertNull($summary['usage']['upload_bytes']);
        self::assertNull($summary['usage']['total_bytes']);
        self::assertNull($summary['usage']['remaining_quota_bytes']);
    }

    public function testOwnershipAndDuplicateRenewalProtection(): void
    {
        $service = new UnifiedSubscriberService($this->pdo, $this->gateway([[], [], [], [], []]), strtotime('2026-07-20'));
        $notifications = $service->notifications('hot-user');
        self::assertSame([2, 1], array_column($notifications, 'id'));
        self::assertNotContains(3, array_column($notifications, 'id'));

        $first = $service->createRenewal('hot-user', '', 'Synthetic renewal');
        $second = $service->createRenewal('hot-user', '', 'Duplicate');
        self::assertTrue($first['created']);
        self::assertFalse($first['duplicate']);
        self::assertFalse($second['created']);
        self::assertTrue($second['duplicate']);
        self::assertCount(1, $service->renewalHistory('hot-user'));
    }

    public function testSubscriberSessionAndCsrfLifecycle(): void
    {
        $_SESSION = [];
        $token = SubscriberSecurityService::csrfToken();
        self::assertNotSame('', $token);
        self::assertTrue(SubscriberSecurityService::validateCsrf($token));
        self::assertFalse(SubscriberSecurityService::validateCsrf('wrong'));
        SubscriberSecurityService::establish('hot-user');
        self::assertSame('hot-user', SubscriberSecurityService::username());
        self::assertNotSame($token, SubscriberSecurityService::csrfToken());
        SubscriberSecurityService::logout();
        self::assertSame('', SubscriberSecurityService::username());
    }

    public function testPwaAndSubscriberBoundaryAreFailClosed(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode((string) file_get_contents($root . '/src/public/manifest.webmanifest'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ar', $manifest['lang']);
        self::assertSame('rtl', $manifest['dir']);
        self::assertSame('standalone', $manifest['display']);

        $worker = (string) file_get_contents($root . '/src/public/service-worker.js');
        self::assertStringContainsString("url.pathname.startsWith('/api/')", $worker);
        self::assertStringContainsString("url.pathname === '/login'", $worker);
        self::assertStringContainsString("url.pathname === '/logout'", $worker);
        self::assertStringContainsString('navigationNoCache', $worker);

        $layout = (string) file_get_contents($root . '/src/app/Views/layouts/app.php');
        self::assertStringContainsString('<html lang="ar" dir="rtl">', $layout);
        self::assertStringContainsString('name="viewport"', $layout);

        $subscriberSource = (string) file_get_contents($root . '/src/app/Services/UnifiedSubscriberService.php');
        self::assertStringNotContainsString('->comm(', $subscriberSource);
        self::assertStringNotContainsString('RouterOSApiClient', $subscriberSource);
        self::assertStringNotContainsString("'raw' =>", $subscriberSource);
    }

    private function gateway(array $responses): FakeRouterOSReadGateway
    {
        $gateway = new FakeRouterOSReadGateway();
        foreach ($responses as $response) {
            $gateway->queueResponse($response);
        }
        return $gateway;
    }
}
