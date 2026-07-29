<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Contracts\RouterOSReadGatewayInterface;
use GreenNet\Core\Database;
use GreenNet\Models\Router;
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

final class RouterOnboardingService
{
    public const ROLES = ['user-manager', 'native-hotspot', 'native-pppoe', 'container-host', 'management'];

    public function detect(int $routerId, ?RouterOSReadGatewayInterface $read = null): array
    {
        $router = Router::find($routerId);
        if ($router === null) {
            throw new RuntimeException('Router not found.');
        }
        $read ??= RouterConnectionResolver::gatewayBundleForRouter($routerId, ['timeout' => 6])->read;

        $identity = $this->first($read->read('/system/identity/print'));
        $resource = $this->first($read->read('/system/resource/print'));
        $board = $this->optional($read, '/system/routerboard/print');
        $version = (string) ($resource['version'] ?? '');
        $architecture = strtolower((string) ($resource['architecture-name'] ?? $resource['architecture'] ?? ''));

        $capabilities = [
            'reachable' => true,
            'authenticated' => true,
            'api' => true,
            'identity' => (string) ($identity['name'] ?? ''),
            'routeros_version' => $version,
            'architecture' => $architecture,
            'board_name' => (string) ($board['board-name'] ?? ''),
            'user_manager' => $this->available($read, '/user-manager/profile/print'),
            'hotspot_configured' => $this->available($read, '/ip/hotspot/print', true),
            'pppoe_configured' => $this->available($read, '/interface/pppoe-server/server/print', true),
            'container_package' => $this->available($read, '/container/config/print'),
            'container_mode' => $this->deviceMode($read),
            'apps' => $this->versionAtLeast($version, '7.22')
                && in_array($architecture, ['arm64', 'x86', 'x86_64'], true)
                && $this->available($read, '/app/print'),
        ];

        $this->storeCapabilities($routerId, $capabilities);
        Router::updateStatus($routerId, [
            'ok' => true,
            'identity' => $capabilities['identity'],
            'routeros_version' => $version,
        ]);
        return $capabilities;
    }

    public function readiness(array $router): array
    {
        $roles = $this->roles($router);
        $caps = $this->capabilities($router);
        $checks = [];
        $checks['connection'] = $this->check(!empty($caps['reachable']), 'API connection', 'Run capability detection.');
        foreach ($roles as $role) {
            $checks[$role] = match ($role) {
                'user-manager' => $this->check(!empty($caps['user_manager']), 'User Manager', 'Install/enable User Manager or remove this role.'),
                'native-hotspot' => $this->check(!empty($caps['hotspot_configured']), 'Native Hotspot', 'Configure a Hotspot server manually.'),
                'native-pppoe' => $this->check(!empty($caps['pppoe_configured']), 'Native PPPoE', 'Configure a PPPoE server manually.'),
                'container-host' => $this->containerCheck($caps),
                default => ['status' => 'ready', 'label' => 'Management only', 'message' => 'Safe API management is sufficient.'],
            };
        }
        $backendRoles = array_intersect($roles, ['user-manager', 'native-hotspot', 'native-pppoe']);
        if ($backendRoles !== []) {
            $missing = count($this->mappingsMissing((int) ($router['id'] ?? 0)));
            $checks['package_mappings'] = [
                'status' => $missing === 0 ? 'ready' : 'missing',
                'label' => 'Package/profile mappings',
                'message' => $missing === 0 ? 'All active packages are mapped for selected backends.' : $missing . ' package/backend mappings remain.',
            ];
        }
        $ready = $checks !== [] && !array_filter($checks, static fn (array $row): bool => in_array($row['status'], ['missing', 'unsupported'], true));
        return ['status' => $ready ? 'ready' : 'setup_pending', 'checks' => $checks];
    }

    public function artifacts(array $router): array
    {
        $roles = $this->roles($router);
        $caps = $this->capabilities($router);
        return [
            'bootstrap.rsc' => $this->rsc($router, $roles),
            'greennet-app.yml' => $this->appsYaml($router, $caps),
            'greennet.env.example' => $this->environmentTemplate($router),
            'README.txt' => $this->instructions($router, $caps),
        ];
    }

    public function saveRolesAndState(int $routerId, array $roles, string $mode): void
    {
        $roles = array_values(array_intersect(self::ROLES, array_unique(array_map('strval', $roles))));
        $stmt = Database::connection()->prepare("
            UPDATE routers SET selected_roles=:roles,onboarding_mode=:mode,onboarding_status='registered',updated_at=CURRENT_TIMESTAMP
            WHERE id=:id
        ");
        $stmt->execute([
            'roles' => json_encode($roles, JSON_UNESCAPED_SLASHES),
            'mode' => $mode === 'bootstrap' ? 'bootstrap' : 'existing',
            'id' => $routerId,
        ]);
    }

    public function finish(int $routerId): void
    {
        $router = Router::find($routerId);
        if ($router === null) {
            return;
        }
        $readiness = $this->readiness($router);
        $stmt = Database::connection()->prepare("UPDATE routers SET onboarding_status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id");
        $stmt->execute(['status' => $readiness['status'], 'id' => $routerId]);
    }

    public function markDetectionFailure(int $routerId): void
    {
        $this->storeCapabilities($routerId, [
            'reachable' => false,
            'authenticated' => false,
            'api' => false,
        ]);
    }

    public function mappingsMissing(int $routerId): array
    {
        $router = Router::find($routerId) ?? [];
        $roles = array_intersect($this->roles($router), ['user-manager', 'native-hotspot', 'native-pppoe']);
        if ($roles === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = Database::connection()->prepare("
            SELECT sp.id,sp.name,b.backend
            FROM service_packages sp
            CROSS JOIN (SELECT 'user-manager' backend UNION ALL SELECT 'native-hotspot' UNION ALL SELECT 'native-pppoe') b
            LEFT JOIN router_backend_package_profiles m ON m.router_id=? AND m.package_id=sp.id AND m.backend=b.backend
            WHERE sp.is_active=1 AND b.backend IN ({$placeholders}) AND m.id IS NULL
            ORDER BY sp.name,b.backend
        ");
        $stmt->execute(array_merge([$routerId], array_values($roles)));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roles(array $router): array
    {
        $roles = json_decode((string) ($router['selected_roles'] ?? '[]'), true);
        return is_array($roles) ? array_values(array_intersect(self::ROLES, $roles)) : [];
    }

    public function capabilities(array $router): array
    {
        $data = json_decode((string) ($router['capabilities_json'] ?? '{}'), true);
        return is_array($data) ? $data : [];
    }

    private function storeCapabilities(int $routerId, array $capabilities): void
    {
        $stmt = Database::connection()->prepare("
            UPDATE routers SET capabilities_json=:json,capabilities_checked_at=CURRENT_TIMESTAMP,onboarding_status='detected',updated_at=CURRENT_TIMESTAMP WHERE id=:id
        ");
        $stmt->execute(['json' => json_encode($capabilities, JSON_UNESCAPED_SLASHES), 'id' => $routerId]);
    }

    private function available(RouterOSReadGatewayInterface $read, string $command, bool $requireRows = false): bool
    {
        try {
            $rows = $read->read($command);
            return !$requireRows || count($rows) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function optional(RouterOSReadGatewayInterface $read, string $command): array
    {
        try {
            return $this->first($read->read($command));
        } catch (Throwable) {
            return [];
        }
    }

    private function deviceMode(RouterOSReadGatewayInterface $read): bool
    {
        $row = $this->optional($read, '/system/device-mode/print');
        return in_array(strtolower((string) ($row['container'] ?? '')), ['yes', 'true'], true);
    }

    private function first(array $rows): array
    {
        if (isset($rows[0]) && is_array($rows[0])) {
            return $rows[0];
        }
        return $rows;
    }

    private function check(bool $ok, string $label, string $missing): array
    {
        return ['status' => $ok ? 'ready' : 'missing', 'label' => $label, 'message' => $ok ? 'Available.' : $missing];
    }

    private function containerCheck(array $caps): array
    {
        if (!in_array((string) ($caps['architecture'] ?? ''), ['arm64', 'x86', 'x86_64'], true)) {
            return ['status' => 'unsupported', 'label' => 'Container host', 'message' => 'Architecture is not supported for RouterOS containers/apps.'];
        }
        if (empty($caps['container_package'])) {
            return ['status' => 'missing', 'label' => 'Container host', 'message' => 'Container package is unavailable.'];
        }
        return ['status' => !empty($caps['container_mode']) ? 'ready' : 'warning', 'label' => 'Container host', 'message' => !empty($caps['container_mode']) ? 'Container mode is enabled.' : 'Container mode requires a deliberate device-mode change and physical confirmation.'];
    }

    private function versionAtLeast(string $value, string $minimum): bool
    {
        preg_match('/\d+(?:\.\d+)+/', $value, $match);
        return isset($match[0]) && version_compare($match[0], $minimum, '>=');
    }

    private function rsc(array $router, array $roles): string
    {
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($router['name'] ?? 'router')) ?: 'router';
        $lines = [
            '# GreenNet conservative bootstrap for ' . $name,
            '# Review every command before importing. This file is never applied automatically.',
            ':if ([:len [/user/group find where name="greennet-api"]]=0) do={/user/group add name="greennet-api" policy=read,write,api,!local,!telnet,!ssh,!ftp,!reboot,!policy,!test,!password,!web,!sniff,!sensitive,!romon comment="GreenNet managed API group"}',
            ':if ([:len [/user find where name="greennet-api"]]=0) do={/user add name="greennet-api" group="greennet-api" password="<SET-ONE-TIME-PASSWORD>" comment="GreenNet managed API user"}',
            '# Review allowed-address before enabling the API service:',
            '/ip/service set [find where name="api"] disabled=no address=<GREENNET-MANAGEMENT-CIDR>',
        ];
        if (in_array('container-host', $roles, true)) {
            $lines[] = '# Container preparation requires supported hardware, external storage, and physical confirmation.';
            $lines[] = '# /system/device-mode/update container=yes';
            $lines[] = '# /interface/veth add name=greennet-veth address=<CONTAINER-IP/CIDR> gateway=<CONTAINER-GATEWAY>';
            $lines[] = '# Add the veth to your chosen bridge and add only the NAT rules required by your topology.';
            $lines[] = '# /container/add remote-image=<GREENNET-IMAGE> interface=greennet-veth root-dir=<STORAGE>/greennet start-on-boot=yes logging=yes';
        }
        return implode("\r\n", $lines) . "\r\n";
    }

    private function appsYaml(array $router, array $caps): string
    {
        if (empty($caps['apps'])) {
            return "# RouterOS Apps is not detected as supported on this router.\n# Custom apps require RouterOS 7.22+, container package/mode, and arm64 or x86.\n";
        }
        return "name: greennet\n"
            . "descr: GreenNet Portal\n"
            . "page: https://example.invalid/greennet\n"
            . "category: network\n"
            . "default-credentials: none\n"
            . "services:\n"
            . "  portal:\n"
            . "    image: <GREENNET-CONTAINER-IMAGE>\n"
            . "    ports:\n"
            . "      - 8080:80:tcp\n"
            . "    volumes:\n"
            . "      - <ROUTER-STORAGE>/greennet:/var/www/database\n";
    }

    private function environmentTemplate(array $router): string
    {
        return "APP_ENV=production\nDB_DATABASE=/var/www/database/database.sqlite\n"
            . "MIKROTIK_HOST=" . (string) ($router['host'] ?? '') . "\n"
            . "MIKROTIK_API_PORT=" . (int) ($router['api_port'] ?? 8728) . "\n"
            . "MIKROTIK_USERNAME=greennet-api\nMIKROTIK_PASSWORD=<SET-SECURELY-NOT-IN-ARTIFACT>\n";
    }

    private function instructions(array $router, array $caps): string
    {
        return "GreenNet router onboarding\n\n1. Keep a RouterOS backup/export.\n2. Review bootstrap.rsc line by line.\n3. Replace every <PLACEHOLDER> locally.\n4. Import manually only on the intended router.\n5. Re-run capability detection and readiness.\n6. Complete package mappings in GreenNet.\n\nNo artifact has been applied automatically.\n";
    }
}
