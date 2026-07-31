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
        $userManager = $this->available($read, '/user-manager/profile/print');
        $hotspotConfigured = $this->available($read, '/ip/hotspot/print', true);
        $pppoeConfigured = $this->available($read, '/interface/pppoe-server/server/print', true);
        $containerPackage = $this->available($read, '/container/config/print');
        $containerMode = $this->deviceMode($read);
        $appsMenu = $this->available($read, '/app/print');

        $capabilities = [
            'reachable' => true,
            'authenticated' => true,
            'api' => true,
            'identity' => (string) ($identity['name'] ?? ''),
            'routeros_version' => $version,
            'architecture' => $architecture,
            'board_name' => (string) ($board['board-name'] ?? ''),
            'user_manager' => $userManager,
            'hotspot_configured' => $hotspotConfigured,
            'pppoe_configured' => $pppoeConfigured,
            'container_package' => $containerPackage,
            'container_mode' => $containerMode,
            'apps' => $this->versionAtLeast($version, '7.22')
                && $architecture === 'arm64'
                && $containerPackage
                && $containerMode
                && $appsMenu,
            'interface_names' => $this->values($this->optionalRows($read, '/interface/print'), 'name'),
            'router_addresses' => $this->values($this->optionalRows($read, '/ip/address/print'), 'address'),
            'container_names' => $this->values($this->optionalRows($read, '/container/print'), 'name'),
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

    public function artifacts(array $router, array $deployment = [], array $secrets = []): array
    {
        $caps = $this->capabilities($router);
        $deployment = $this->deployment($router, $deployment);
        $adminPassword = (string) ($secrets['admin_password'] ?? '<SET-ONE-TIME-ADMIN-PASSWORD>');
        return [
            'bootstrap.rsc' => $this->rsc($deployment, $adminPassword),
            'greennet-app.yml' => $this->appsYaml($deployment, $caps, $adminPassword),
            'greennet.env.example' => $this->environmentTemplate($deployment),
            'README.txt' => $this->instructions($deployment, $caps),
        ];
    }

    public function deployment(array $router, array $input = []): array
    {
        $caps = $this->capabilities($router);
        $method = (string) ($input['method'] ?? (!empty($caps['apps']) ? 'apps' : 'container'));
        $slug = strtolower(trim((string) ($input['app_name'] ?? 'greennet')));
        $data = [
            'method' => $method,
            'app_name' => $slug,
            'storage_path' => trim((string) ($input['storage_path'] ?? 'disk1/greennet')),
            'container_ip' => trim((string) ($input['container_ip'] ?? '172.30.30.2')),
            'gateway_ip' => trim((string) ($input['gateway_ip'] ?? '172.30.30.1')),
            'prefix' => (int) ($input['prefix'] ?? 28),
            'veth_name' => trim((string) ($input['veth_name'] ?? 'veth-greennet')),
            'bridge_name' => trim((string) ($input['bridge_name'] ?? 'greennet-containers')),
            'http_port' => (int) ($input['http_port'] ?? 8080),
            'timezone' => trim((string) ($input['timezone'] ?? 'Asia/Damascus')),
            'automation_interval' => (int) ($input['automation_interval'] ?? 300),
            'admin_username' => trim((string) ($input['admin_username'] ?? 'greennet-admin')),
            'image_reference' => trim((string) ($input['image_reference'] ?? ($method === 'apps'
                ? '<OPERATOR-REGISTRY>/greennet/portal-mikrotik:1.0.0'
                : 'greennet-mikrotik-arm64.tar'))),
            'architecture' => (string) ($caps['architecture'] ?? ''),
            'router_name' => (string) ($router['name'] ?? 'Router'),
            'router_model' => (string) ($caps['board_name'] ?? ''),
            'routeros_version' => (string) ($caps['routeros_version'] ?? ''),
        ];
        if ($input !== []) {
            $this->validateDeployment($data, $caps);
        }
        return $data;
    }

    public function installationMethods(array $router): array
    {
        $caps = $this->capabilities($router);
        $architecture = (string) ($caps['architecture'] ?? '');
        $imageAvailable = $architecture === 'arm64';
        $container = $imageAvailable && !empty($caps['container_package']);
        $apps = $container && !empty($caps['container_mode']) && !empty($caps['apps'])
            && $this->versionAtLeast((string) ($caps['routeros_version'] ?? ''), '7.22');
        return [
            'apps' => ['supported' => $apps, 'reason' => $apps ? '' : 'يتطلب RouterOS 7.22+ وApps وContainer mode وصورة متوافقة مع المعمارية.'],
            'container' => ['supported' => $container, 'reason' => $container ? '' : 'حزمة Container أو صورة GreenNet المتوافقة غير متاحة لهذه المعمارية.'],
        ];
    }

    public function deploymentWarnings(array $router, array $deployment): array
    {
        $caps = $this->capabilities($router);
        $warnings = [];
        if (in_array($deployment['veth_name'], $caps['interface_names'] ?? [], true)) {
            $warnings[] = 'اسم veth مستخدم مسبقًا؛ اختر اسمًا جديدًا.';
        }
        if (in_array($deployment['app_name'], $caps['container_names'] ?? [], true)) {
            $warnings[] = 'اسم الحاوية مستخدم مسبقًا؛ لن تتم الكتابة فوقه.';
        }
        foreach ($caps['router_addresses'] ?? [] as $address) {
            if (str_starts_with((string) $address, $deployment['container_ip'] . '/')) {
                $warnings[] = 'عنوان الحاوية مملوك للراوتر حاليًا.';
            }
            if (str_starts_with((string) $address, $deployment['gateway_ip'] . '/')) {
                $warnings[] = 'عنوان البوابة موجود مسبقًا؛ راجع الواجهة والشبكة قبل التنفيذ.';
            }
        }
        if (preg_match('#^(flash/|$)#', $deployment['storage_path']) === 1) {
            $warnings[] = 'التخزين الداخلي/المؤقت غير مفضل؛ استخدم USB أو SSD دائمًا إن أمكن.';
        }
        return array_values(array_unique($warnings));
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

    public function markInstallationStatus(int $routerId, string $status): void
    {
        $allowed = ['artifact_ready', 'installation_pending', 'operator_installed', 'greennet_reachable', 'needs_attention'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid installation status.');
        }
        $stmt = Database::connection()->prepare(
            "UPDATE routers SET onboarding_status=:status,updated_at=CURRENT_TIMESTAMP WHERE id=:id"
        );
        $stmt->execute(['status' => $status, 'id' => $routerId]);
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

    private function optionalRows(RouterOSReadGatewayInterface $read, string $command): array
    {
        try {
            return $read->read($command);
        } catch (Throwable) {
            return [];
        }
    }

    private function values(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row[$key]) && is_scalar($row[$key])) {
                $values[] = (string) $row[$key];
            }
        }
        return array_values(array_unique($values));
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

    private function validateDeployment(array $data, array $caps): void
    {
        $method = $data['method'];
        if (!in_array($method, ['apps', 'container'], true)) {
            throw new RuntimeException('Invalid installation method.');
        }
        $methods = $this->installationMethods(['capabilities_json' => json_encode($caps)]);
        if (empty($methods[$method]['supported'])) {
            throw new RuntimeException((string) $methods[$method]['reason']);
        }
        foreach (['app_name', 'veth_name', 'bridge_name'] as $field) {
            if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $data[$field]) !== 1) {
                throw new RuntimeException('Names may contain only letters, numbers, underscores, and hyphens.');
            }
        }
        if (preg_match('#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#', $data['storage_path']) !== 1
            || str_contains($data['storage_path'], '..')) {
            throw new RuntimeException('An explicit safe persistent storage path is required.');
        }
        if (filter_var($data['container_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || filter_var($data['gateway_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || $data['container_ip'] === $data['gateway_ip']
            || in_array($data['gateway_ip'], ['127.0.0.1', '0.0.0.0'], true)) {
            throw new RuntimeException('Container and RouterOS gateway IPv4 addresses must be explicit and distinct.');
        }
        if ($data['prefix'] < 8 || $data['prefix'] > 30 || $data['http_port'] < 1 || $data['http_port'] > 65535) {
            throw new RuntimeException('Invalid prefix or HTTP port.');
        }
        $mask = (0xFFFFFFFF << (32 - $data['prefix'])) & 0xFFFFFFFF;
        if (((int) ip2long($data['container_ip']) & $mask) !== ((int) ip2long($data['gateway_ip']) & $mask)) {
            throw new RuntimeException('Container and RouterOS gateway addresses must share the selected subnet.');
        }
        if ($data['automation_interval'] < 60 || $data['admin_username'] === ''
            || in_array(strtolower($data['admin_username']), ['admin', 'change_me_admin_user'], true)) {
            throw new RuntimeException('A deliberate administrator username and interval of at least 60 seconds are required.');
        }
        if (preg_match('#^[A-Za-z0-9_./:@-]+$#', $data['image_reference']) !== 1
            || preg_match('#^[A-Za-z0-9_+-]+(?:/[A-Za-z0-9_+-]+)*$#', $data['timezone']) !== 1) {
            throw new RuntimeException('An image or archive reference is required.');
        }
        if ($method === 'apps' && (str_ends_with(strtolower($data['image_reference']), '.tar')
            || !str_contains($data['image_reference'], '/'))) {
            throw new RuntimeException('RouterOS Apps requires an architecture-compatible image in an operator-controlled registry.');
        }
        if ($method === 'container' && !str_ends_with(strtolower($data['image_reference']), '.tar')) {
            throw new RuntimeException('Traditional Container requires the local Docker archive path from Part B.');
        }
    }

    private function rsc(array $d, string $adminPassword): string
    {
        $q = fn (string $value): string => '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
        $cidr = $d['container_ip'] . '/' . $d['prefix'];
        $gatewayCidr = $d['gateway_ip'] . '/' . $d['prefix'];
        $dataPath = $d['storage_path'] . '/data';
        $lines = [
            '# GreenNet traditional Container bootstrap; review before manual import.',
            '# Sensitive one-time artifact: protect and delete after use.',
            ':local gnBridge ' . $q($d['bridge_name']),
            ':local gnVeth ' . $q($d['veth_name']),
            ':local gnName ' . $q($d['app_name']),
            ':if ([:len [/interface find where name=$gnVeth]]>0) do={:error "GreenNet veth name already exists"}',
            ':if ([:len [/container find where name=$gnName]]>0) do={:error "GreenNet container name already exists"}',
            ':if ([:len [/container/mounts find where list="greennet-data"]]>0) do={:error "GreenNet mount list already exists"}',
            ':if ([:len [/container/envs find where list="greennet-env"]]>0) do={:error "GreenNet environment list already exists"}',
            ':if ([:len [/interface/bridge find where name=$gnBridge]]=0) do={/interface/bridge add name=$gnBridge comment="GreenNet Container only"}',
            '/ip/address add address=' . $gatewayCidr . ' interface=$gnBridge comment="GreenNet Container gateway"',
            '/interface/veth add name=$gnVeth address=' . $cidr . ' gateway=' . $d['gateway_ip'],
            '/interface/bridge/port add bridge=$gnBridge interface=$gnVeth',
            '/container/mounts add list=greennet-data src=' . $q($dataPath) . ' dst=/greennet-data',
            '/container/envs add list=greennet-env key=APP_ENV value=production',
            '/container/envs add list=greennet-env key=TZ value=' . $q($d['timezone']),
            '/container/envs add list=greennet-env key=DB_DATABASE value=/greennet-data/database/database.sqlite',
            '/container/envs add list=greennet-env key=UPLOADS_STORAGE_PATH value=/greennet-data/uploads',
            '/container/envs add list=greennet-env key=BACKUP_STORAGE_PATH value=/greennet-data/backups',
            '/container/envs add list=greennet-env key=ADMIN_USERNAME value=' . $q($d['admin_username']),
            '/container/envs add list=greennet-env key=ADMIN_PASSWORD value=' . $q($adminPassword),
            '/container/envs add list=greennet-env key=MIKROTIK_HOST value=' . $q($d['gateway_ip']),
            '/container/envs add list=greennet-env key=AUTOMATION_ENABLED value=true',
            '/container/envs add list=greennet-env key=AUTOMATION_INTERVAL_SECONDS value=' . $d['automation_interval'],
            '/container/config set tmpdir=' . $q($d['storage_path'] . '/tmp'),
            '/container add file=' . $q($d['image_reference']) . ' interface=$gnVeth root-dir=' . $q($d['storage_path'] . '/root') . ' mountlists=greennet-data envlist=greennet-env name=$gnName dns=' . $d['gateway_ip'] . ' start-on-boot=yes logging=yes memory-high=256M auto-restart-interval=30s',
            '# No Hotspot, PPPoE, existing bridge, default route, or broad firewall rule is changed.',
        ];
        return implode("\r\n", $lines) . "\r\n";
    }

    private function appsYaml(array $d, array $caps, string $adminPassword): string
    {
        if (empty($caps['apps'])) {
            return "# RouterOS Apps is not eligible on this detected router.\n";
        }
        $yaml = fn (string $value): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        return "name: " . $d['app_name'] . "\n"
            . "descr: GreenNet Portal\n"
            . "page: /admin/login\n"
            . "category: network\n"
            . "default-credentials: none\n"
            . "services:\n"
            . "  portal:\n"
            . "    image: " . $d['image_reference'] . "\n"
            . "    ports:\n"
            . "      - " . $d['http_port'] . ":8080:tcp\n"
            . "    volumes:\n"
            . "      - " . $d['storage_path'] . "/data:/greennet-data\n"
            . "    environment:\n"
            . "      - APP_ENV=production\n"
            . "      - TZ=" . $d['timezone'] . "\n"
            . "      - DB_DATABASE=/greennet-data/database/database.sqlite\n"
            . "      - UPLOADS_STORAGE_PATH=/greennet-data/uploads\n"
            . "      - BACKUP_STORAGE_PATH=/greennet-data/backups\n"
            . "      - ADMIN_USERNAME=" . $yaml($d['admin_username']) . "\n"
            . "      - ADMIN_PASSWORD=" . $yaml($adminPassword) . "\n"
            . "      - MIKROTIK_HOST=auto\n"
            . "      - MIKROTIK_TIMEOUT=3\n"
            . "      - AUTOMATION_ENABLED=true\n"
            . "      - AUTOMATION_INTERVAL_SECONDS=" . $d['automation_interval'] . "\n";
    }

    private function environmentTemplate(array $d): string
    {
        return "APP_ENV=production\nDB_DATABASE=/greennet-data/database/database.sqlite\n"
            . "UPLOADS_STORAGE_PATH=/greennet-data/uploads\nBACKUP_STORAGE_PATH=/greennet-data/backups\n"
            . "MIKROTIK_HOST=" . ($d['method'] === 'apps' ? 'auto' : $d['gateway_ip']) . "\nMIKROTIK_API_PORT=8728\nMIKROTIK_TIMEOUT=3\n"
            . "ADMIN_USERNAME=" . $d['admin_username'] . "\nADMIN_PASSWORD=<ENTER-ONLY-WHEN-GENERATING>\n"
            . "MIKROTIK_USERNAME=<ROUTEROS-API-USER>\nMIKROTIK_PASSWORD=<SET-SECURELY-AFTER-INSTALL>\n";
    }

    private function instructions(array $d, array $caps): string
    {
        $method = $d['method'] === 'apps' ? 'RouterOS Apps' : 'Traditional Container';
        return "GreenNet installation: {$method}\n\n"
            . "1. Keep a RouterOS backup/export and review the generated sensitive artifact.\n"
            . ($d['method'] === 'apps'
                ? "2. Make the architecture-compatible image available from an operator-controlled registry.\n3. Upload the YAML, run /app add yaml=[/file get <FILE> contents], select the RouterOS-managed internal network, then enable the app. MIKROTIK_HOST=auto resolves the actual Apps gateway at container startup.\n"
                : "2. Copy {$d['image_reference']} to {$d['storage_path']}.\n3. Import/run bootstrap.rsc manually, wait for extraction, then start {$d['app_name']}.\n")
            . ($d['method'] === 'apps'
                ? "4. Open the UI-URL reported by /app print and use the GreenNet check in onboarding.\n"
                : "4. Open http://{$d['container_ip']}:{$d['http_port']}/admin/login and use the GreenNet check in onboarding.\n")
            . "5. Delete the sensitive artifact after installation.\n\nNo artifact has been applied automatically.\n";
    }
}
