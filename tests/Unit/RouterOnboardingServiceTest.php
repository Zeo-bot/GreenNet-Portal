<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Core\Database;
use GreenNet\Models\Router;
use GreenNet\Services\RouterOnboardingService;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RouterOnboardingServiceTest extends TestCase
{
    private int $routerId;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new ReflectionClass(Database::class))->getProperty('connection')->setValue(null, $pdo);
        Database::migrate();
        $this->routerId = Router::save([
            'name' => 'Synthetic Router',
            'host' => '192.0.2.44',
            'api_port' => 8728,
            'username' => 'synthetic',
            'password' => 'synthetic-only',
            'enabled' => 1,
        ]);
    }

    public function testDetectionStoresOnlyCapabilityProjectionAndBuildsBackendReadiness(): void
    {
        $read = new FakeRouterOSReadGateway();
        foreach ([
            [['name' => 'synthetic-router']],
            [['version' => '7.22.1', 'architecture-name' => 'arm64']],
            [['board-name' => 'Synthetic Board']],
            [['name' => 'um-profile']],
            [['name' => 'hotspot1']],
            [['service-name' => 'pppoe1']],
            [['registry-url' => '']],
            [['container' => 'yes']],
            [],
        ] as $response) {
            $read->queueResponse($response);
        }

        $service = new RouterOnboardingService();
        $service->saveRolesAndState($this->routerId, ['user-manager', 'native-hotspot', 'native-pppoe', 'container-host'], 'existing');
        $capabilities = $service->detect($this->routerId, $read);
        $router = Router::find($this->routerId);
        $readiness = $service->readiness($router ?? []);

        self::assertTrue($capabilities['reachable']);
        self::assertTrue($capabilities['user_manager']);
        self::assertTrue($capabilities['hotspot_configured']);
        self::assertTrue($capabilities['pppoe_configured']);
        self::assertTrue($capabilities['container_mode']);
        self::assertSame('ready', $readiness['status']);
        self::assertStringNotContainsString('synthetic-only', (string) ($router['capabilities_json'] ?? ''));
    }

    public function testArtifactsAreConservativeAndContainNoStoredCredential(): void
    {
        $service = new RouterOnboardingService();
        $service->saveRolesAndState($this->routerId, ['container-host'], 'bootstrap');
        $router = Router::find($this->routerId) ?? [];
        $artifacts = $service->artifacts($router);
        $combined = implode("\n", $artifacts);

        self::assertArrayHasKey('bootstrap.rsc', $artifacts);
        self::assertArrayHasKey('greennet-app.yml', $artifacts);
        self::assertStringContainsString('<SET-ONE-TIME-PASSWORD>', $artifacts['bootstrap.rsc']);
        self::assertStringContainsString('no artifact has been applied automatically', strtolower($artifacts['README.txt']));
        self::assertStringNotContainsString('synthetic-only', $combined);
    }
}
