<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Controllers\LoginController;
use GreenNet\Controllers\SubscriberApiController;
use GreenNet\Core\Database;
use GreenNet\Core\Router;
use GreenNet\Services\SubscriberSecurityService;
use GreenNet\Services\UnifiedSubscriberService;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use GreenNet\Tests\Support\TempDatabase;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SubscriberHttpBoundaryTest extends TestCase
{
    private TempDatabase $database;
    private PDO $pdo;
    /** @var list<string> */
    private array $headers = [];

    protected function setUp(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', GRENNET_TEST_ROOT . '/src');
        }
        $this->database = new TempDatabase();
        $this->pdo = $this->database->connection();
        $this->bindDatabase($this->pdo);
        Database::migrate();
        $this->createFixtures();
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $this->bindDatabase(null);
        $this->database->cleanup();
    }

    public function testEverySubscriberApiRouteUsesRealRegistrationAndReturnsJsonWhenAuthenticated(): void
    {
        SubscriberSecurityService::establish('subscriber-a');

        $routes = [
            ['GET', '/api/v1/subscriber/session', ['authenticated', 'username', 'csrf_token']],
            ['GET', '/api/v1/subscriber/summary', ['portal_customer_id', 'router_username', 'package', 'usage']],
            ['GET', '/api/v1/subscriber/package', ['id', 'name', 'profile', 'quota_bytes']],
            ['GET', '/api/v1/subscriber/usage', ['download_bytes', 'upload_bytes', 'total_bytes']],
            ['GET', '/api/v1/subscriber/active-session', ['online', 'session']],
            ['GET', '/api/v1/subscriber/renewals', []],
            ['GET', '/api/v1/subscriber/payments', [0]],
            ['GET', '/api/v1/subscriber/notifications', [0, 1]],
            ['GET', '/api/v1/subscriber/support', ['phone', 'whatsapp', 'hours']],
        ];

        foreach ($routes as [$method, $path, $expectedKeys]) {
            $response = $this->dispatchApi($method, $path);
            self::assertSame(200, $response['status'], $path);
            self::assertTrue($response['json']['ok'], $path);
            self::assertNull($response['json']['error'], $path);
            self::assertJson($response['body'], $path);
            self::assertContains('Content-Type: application/json; charset=utf-8', $response['headers'], $path);
            self::assertContains('Cache-Control: no-store, private', $response['headers'], $path);
            foreach ($expectedKeys as $key) {
                self::assertArrayHasKey($key, $response['json']['data'], $path);
            }
        }

        $_POST = ['phone' => '000', 'message' => 'Boundary renewal'];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = SubscriberSecurityService::csrfToken();
        $created = $this->dispatchApi('POST', '/api/v1/subscriber/renewals');
        self::assertSame(201, $created['status']);
        self::assertTrue($created['json']['data']['created']);
    }

    public function testUnauthenticatedApiContractRejectsOwnedRoutesWithoutDataLeakage(): void
    {
        $ownedRoutes = [
            ['GET', '/api/v1/subscriber/summary'],
            ['GET', '/api/v1/subscriber/package'],
            ['GET', '/api/v1/subscriber/usage'],
            ['GET', '/api/v1/subscriber/active-session'],
            ['GET', '/api/v1/subscriber/renewals'],
            ['POST', '/api/v1/subscriber/renewals'],
            ['GET', '/api/v1/subscriber/payments'],
            ['GET', '/api/v1/subscriber/notifications'],
            ['GET', '/api/v1/subscriber/support'],
        ];

        foreach ($ownedRoutes as [$method, $path]) {
            $response = $this->dispatchApi($method, $path);
            self::assertSame(401, $response['status'], $path);
            self::assertFalse($response['json']['ok'], $path);
            self::assertNull($response['json']['data'], $path);
            self::assertSame('AUTH_REQUIRED', $response['json']['error']['code'], $path);
            self::assertStringNotContainsString('subscriber-a', $response['body'], $path);
            self::assertStringNotContainsString('subscriber-b', $response['body'], $path);
            self::assertContains('Content-Type: application/json; charset=utf-8', $response['headers'], $path);
            self::assertContains('Cache-Control: no-store, private', $response['headers'], $path);
        }

        $session = $this->dispatchApi('GET', '/api/v1/subscriber/session');
        self::assertSame(200, $session['status']);
        self::assertFalse($session['json']['data']['authenticated']);
        self::assertNull($session['json']['data']['username']);
        self::assertNull($session['json']['data']['csrf_token']);
        self::assertSame(0, $this->renewalCount());
    }

    public function testSubscriberOwnershipIsDerivedOnlyFromAuthenticatedSession(): void
    {
        SubscriberSecurityService::establish('subscriber-a');

        $summary = $this->dispatchApi('GET', '/api/v1/subscriber/summary?username=subscriber-b');
        self::assertSame('subscriber-a', $summary['json']['data']['router_username']);
        self::assertSame('Package A', $summary['json']['data']['package']['name']);

        $notifications = $this->dispatchApi('GET', '/api/v1/subscriber/notifications?username=subscriber-b');
        self::assertSame([2, 1], array_column($notifications['json']['data'], 'id'));
        self::assertNotContains(3, array_column($notifications['json']['data'], 'id'));

        $_POST = [
            'username' => 'subscriber-b',
            'phone' => '111',
            'message' => 'Tamper attempt',
            '_csrf' => SubscriberSecurityService::csrfToken(),
        ];
        $renewal = $this->dispatchApi('POST', '/api/v1/subscriber/renewals');
        self::assertSame(201, $renewal['status']);
        self::assertSame(
            ['subscriber-a'],
            $this->pdo->query('SELECT username FROM renewal_requests')->fetchAll(PDO::FETCH_COLUMN)
        );

        $history = $this->dispatchApi('GET', '/api/v1/subscriber/renewals?username=subscriber-b');
        self::assertCount(1, $history['json']['data']);
        self::assertStringNotContainsString('subscriber-b', $history['body']);
    }

    public function testRenewalPostEnforcesAuthenticationCsrfAndDeduplication(): void
    {
        $_POST = ['phone' => '111', 'message' => 'Unauthenticated'];
        self::assertSame(401, $this->dispatchApi('POST', '/api/v1/subscriber/renewals')['status']);
        self::assertSame(0, $this->renewalCount());

        SubscriberSecurityService::establish('subscriber-a');
        $_POST = ['phone' => '111', 'message' => 'Missing token'];
        self::assertSame(419, $this->dispatchApi('POST', '/api/v1/subscriber/renewals')['status']);
        self::assertSame(0, $this->renewalCount());

        $_POST['_csrf'] = 'invalid';
        self::assertSame(419, $this->dispatchApi('POST', '/api/v1/subscriber/renewals')['status']);
        self::assertSame(0, $this->renewalCount());

        $_POST = [
            'phone' => '111',
            'message' => 'Valid request',
            '_csrf' => SubscriberSecurityService::csrfToken(),
        ];
        $valid = $this->dispatchApi('POST', '/api/v1/subscriber/renewals');
        self::assertSame(201, $valid['status']);
        self::assertTrue($valid['json']['data']['created']);
        self::assertSame(1, $this->renewalCount());

        $duplicate = $this->dispatchApi('POST', '/api/v1/subscriber/renewals');
        self::assertSame(409, $duplicate['status']);
        self::assertSame('RENEWAL_PENDING', $duplicate['json']['error']['code']);
        self::assertSame(1, $this->renewalCount());
    }

    public function testPostLogoutRequiresCsrfAndInvalidatesTheSubscriberSession(): void
    {
        SubscriberSecurityService::establish('subscriber-a');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];
        $missing = $this->dispatchLogin('POST', '/logout');
        self::assertSame(419, $missing['status']);
        self::assertSame('subscriber-a', SubscriberSecurityService::username());

        $_POST['_csrf'] = 'invalid';
        $invalid = $this->dispatchLogin('POST', '/logout');
        self::assertSame(419, $invalid['status']);
        self::assertSame('subscriber-a', SubscriberSecurityService::username());

        $_POST['_csrf'] = SubscriberSecurityService::csrfToken();
        $valid = $this->dispatchLogin('POST', '/logout');
        self::assertSame(302, $valid['status']);
        self::assertContains('Location: /login?switch=1', $valid['headers']);
        self::assertSame('', SubscriberSecurityService::username());

        $_POST = [];
        self::assertSame(419, $this->dispatchLogin('POST', '/logout')['status']);

        SubscriberSecurityService::establish('subscriber-a');
        $get = $this->dispatchLogin('GET', '/logout');
        self::assertSame(302, $get['status']);
        self::assertSame('', SubscriberSecurityService::username());
    }

    public function testLoginBoundaryAuthenticatesOnlyValidCredentialsAndCsrf(): void
    {
        $token = SubscriberSecurityService::csrfToken();
        $_POST = ['username' => 'subscriber-a', 'password' => 'correct-password', '_csrf' => $token];
        $success = $this->dispatchLogin('POST', '/login');
        self::assertSame(302, $success['status']);
        self::assertContains('Location: /dashboard', $success['headers']);
        self::assertSame('subscriber-a', SubscriberSecurityService::username());

        $_SESSION = [];
        $_POST = ['username' => 'subscriber-a', 'password' => 'wrong', '_csrf' => SubscriberSecurityService::csrfToken()];
        $invalid = $this->dispatchLogin('POST', '/login');
        self::assertSame(200, $invalid['status']);
        self::assertStringContainsString('form', $invalid['body']);
        self::assertSame('', SubscriberSecurityService::username());

        $_SESSION = [];
        $_POST = ['username' => '', 'password' => '', '_csrf' => SubscriberSecurityService::csrfToken()];
        $missing = $this->dispatchLogin('POST', '/login');
        self::assertSame(200, $missing['status']);
        self::assertSame('', SubscriberSecurityService::username());

        $_SESSION = [];
        $_POST = ['username' => 'subscriber-a', 'password' => 'correct-password', '_csrf' => 'invalid'];
        $badCsrf = $this->dispatchLogin('POST', '/login');
        self::assertSame(200, $badCsrf['status']);
        self::assertSame('', SubscriberSecurityService::username());
    }

    /** @return array{status: int, headers: list<string>, body: string, json: array} */
    private function dispatchApi(string $method, string $uri): array
    {
        return $this->dispatch($method, $uri, fn (): SubscriberApiController => new SubscriberApiController(
            $this->subscriberService(),
            $this->captureHeader(...)
        ));
    }

    /** @return array{status: int, headers: list<string>, body: string, json: array} */
    private function dispatchLogin(string $method, string $uri): array
    {
        return $this->dispatch($method, $uri, fn (): LoginController => new LoginController($this->captureHeader(...)));
    }

    /** @return array{status: int, headers: list<string>, body: string, json: array} */
    private function dispatch(string $method, string $uri, callable $controllerFactory): array
    {
        $this->headers = [];
        $_SERVER['REQUEST_METHOD'] = $method;
        http_response_code(200);
        $router = new Router(static fn (string $controllerClass): object => $controllerFactory($controllerClass));
        require GRENNET_TEST_ROOT . '/src/app/Routes/web.php';

        ob_start();
        try {
            $router->dispatch($uri, $method);
            $body = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $json = json_decode($body, true);

        return [
            'status' => http_response_code(),
            'headers' => $this->headers,
            'body' => $body,
            'json' => is_array($json) ? $json : [],
        ];
    }

    private function subscriberService(): UnifiedSubscriberService
    {
        return new UnifiedSubscriberService($this->pdo, new FakeRouterOSReadGateway(), strtotime('2026-07-29'));
    }

    private function captureHeader(string $header): void
    {
        $this->headers[] = $header;
    }

    private function bindDatabase(?PDO $pdo): void
    {
        $property = new ReflectionProperty(Database::class, 'connection');
        $property->setValue(null, $pdo);
    }

    private function renewalCount(): int
    {
        $exists = $this->pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'renewal_requests'"
        )->fetchColumn();
        return (int) $exists === 1
            ? (int) $this->pdo->query('SELECT COUNT(*) FROM renewal_requests')->fetchColumn()
            : 0;
    }

    private function createFixtures(): void
    {
        $this->pdo->exec("ALTER TABLE customers_local ADD COLUMN subscriber_password_hash TEXT DEFAULT ''");
        $this->pdo->exec("ALTER TABLE customers_local ADD COLUMN password_changed_at TEXT DEFAULT ''");
        $this->pdo->exec("ALTER TABLE customers_local ADD COLUMN last_login_at TEXT DEFAULT ''");
        $this->pdo->exec('ALTER TABLE customers_local ADD COLUMN failed_login_attempts INTEGER DEFAULT 0');
        $this->pdo->exec("ALTER TABLE customers_local ADD COLUMN locked_until TEXT DEFAULT ''");
        $this->pdo->exec('ALTER TABLE customers_local ADD COLUMN must_change_password INTEGER DEFAULT 0');
        $this->pdo->exec("INSERT INTO service_packages
            (id, name, source_type, source_profile, access_type, rate_limit, duration_days, quota_gb, price, currency)
            VALUES
            (101, 'Package A', 'manual', 'package-a', 'hotspot', '10M/10M', 30, 10, 100, 'SYP'),
            (102, 'Package B', 'manual', 'package-b', 'pppoe', '20M/20M', 30, 20, 200, 'SYP')");
        $insert = $this->pdo->prepare("INSERT INTO customers_local
            (username, display_name, access_type, package_id, subscriber_password_hash, created_at)
            VALUES (:username, :display_name, :access_type, :package_id, :password_hash, '2026-07-01')");
        $insert->execute([
            'username' => 'subscriber-a',
            'display_name' => 'Subscriber A',
            'access_type' => 'hotspot',
            'package_id' => 101,
            'password_hash' => password_hash('correct-password', PASSWORD_DEFAULT),
        ]);
        $insert->execute([
            'username' => 'subscriber-b',
            'display_name' => 'Subscriber B',
            'access_type' => 'pppoe',
            'package_id' => 102,
            'password_hash' => password_hash('other-password', PASSWORD_DEFAULT),
        ]);
        $this->pdo->exec("INSERT INTO payments
            (id, username, amount, currency, status, package_id, package_name, starts_at, expires_at, paid_at)
            VALUES
            (1, 'subscriber-a', 100, 'SYP', 'paid', 101, 'Package A', '2026-07-01', '2026-08-01', '2026-07-01'),
            (2, 'subscriber-b', 200, 'SYP', 'paid', 102, 'Package B', '2026-07-01', '2026-08-01', '2026-07-01')");
        $this->pdo->exec("INSERT INTO notifications (id, username, title, body, type, is_read, created_at) VALUES
            (1, NULL, 'General', 'General notice', 'info', 0, '2026-07-01'),
            (2, 'subscriber-a', 'Owned A', 'A only', 'info', 0, '2026-07-02'),
            (3, 'subscriber-b', 'Owned B', 'B only', 'info', 0, '2026-07-03')");
    }
}
