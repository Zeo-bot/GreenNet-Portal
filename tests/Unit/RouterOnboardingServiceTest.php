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
        self::assertStringContainsString('<SET-ONE-TIME-ADMIN-PASSWORD>', $artifacts['bootstrap.rsc']);
        self::assertStringContainsString('no artifact has been applied automatically', strtolower($artifacts['README.txt']));
        self::assertStringNotContainsString('synthetic-only', $combined);
    }

    public function testEligibleArm64ArtifactsUseFinalProductionRuntimeModel(): void
    {
        $service = new RouterOnboardingService();
        $router = Router::find($this->routerId) ?? [];
        $router['capabilities_json'] = json_encode([
            'routeros_version' => '7.23.1',
            'architecture' => 'arm64',
            'board_name' => 'hAP ax3',
            'container_package' => true,
            'container_mode' => true,
            'apps' => true,
            'interface_names' => [],
            'router_addresses' => [],
            'container_names' => [],
        ]);
        $deployment = $service->deployment($router, [
            'method' => 'apps',
            'app_name' => 'greennet',
            'storage_path' => 'disk1/greennet',
            'container_ip' => '172.30.30.2',
            'gateway_ip' => '172.30.30.1',
            'prefix' => 28,
            'veth_name' => 'veth-greennet',
            'bridge_name' => 'greennet-containers',
            'http_port' => 8080,
            'timezone' => 'Asia/Damascus',
            'automation_interval' => 300,
            'admin_username' => 'greennet-operator',
            'image_reference' => 'registry.example/greennet/portal:1.0.0',
        ]);
        $artifacts = $service->artifacts($router, $deployment, ['admin_password' => 'synthetic-admin-secret']);

        self::assertStringContainsString('8080:8080:tcp', $artifacts['greennet-app.yml']);
        self::assertStringContainsString('disk1/greennet/data:/greennet-data', $artifacts['greennet-app.yml']);
        self::assertStringContainsString('DB_DATABASE=/greennet-data/database/database.sqlite', $artifacts['greennet-app.yml']);
        self::assertStringContainsString('MIKROTIK_HOST=172.30.30.1', $artifacts['greennet-app.yml']);
        self::assertStringNotContainsString('/var/www/database', $artifacts['greennet-app.yml']);
    }

    public function testTraditionalArtifactIsConflictAwareAndUsesLocalArchive(): void
    {
        $service = new RouterOnboardingService();
        $router = Router::find($this->routerId) ?? [];
        $router['capabilities_json'] = json_encode([
            'routeros_version' => '7.23.1',
            'architecture' => 'arm64',
            'container_package' => true,
            'container_mode' => true,
            'apps' => false,
        ]);
        $deployment = $service->deployment($router, [
            'method' => 'container',
            'app_name' => 'greennet',
            'storage_path' => 'disk1/greennet',
            'container_ip' => '172.30.30.2',
            'gateway_ip' => '172.30.30.1',
            'prefix' => 28,
            'veth_name' => 'veth-greennet',
            'bridge_name' => 'greennet-containers',
            'http_port' => 8080,
            'timezone' => 'Asia/Damascus',
            'automation_interval' => 300,
            'admin_username' => 'greennet-operator',
            'image_reference' => 'disk1/greennet-mikrotik-arm64.tar',
        ]);
        $rsc = $service->artifacts($router, $deployment, ['admin_password' => 'synthetic-admin-secret'])['bootstrap.rsc'];

        self::assertStringContainsString('/container add file="disk1/greennet-mikrotik-arm64.tar"', $rsc);
        self::assertStringContainsString('dst=/greennet-data', $rsc);
        self::assertStringContainsString('MIKROTIK_HOST value="172.30.30.1"', $rsc);
        self::assertStringContainsString('already exists', $rsc);
        self::assertStringNotContainsString('/ip/hotspot', $rsc);
        self::assertStringNotContainsString('/interface/pppoe', $rsc);
    }
}
