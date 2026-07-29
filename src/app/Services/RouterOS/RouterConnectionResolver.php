<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Router;
use GreenNet\Services\RouterSettingsService;
use RuntimeException;

final class RouterConnectionResolver
{
    public static function routerForCustomer(string $username): array
    {
        Database::migrate();
        $customer = CustomerLocal::findByUsername(trim($username));
        $routerId = (int) ($customer['router_id'] ?? 0);
        $router = $routerId > 0 ? Router::find($routerId) : Router::default();

        if (is_array($router)) {
            if (empty($router['enabled'])) {
                throw new RuntimeException('The assigned router is disabled.');
            }

            $router['legacy_fallback'] = false;
            return $router;
        }

        $legacy = RouterSettingsService::current();

        return [
            'id' => 0,
            'name' => (string) ($legacy['router_name'] ?? 'Default MikroTik'),
            'host' => (string) ($legacy['host'] ?? ''),
            'api_port' => (int) ($legacy['api_port'] ?? 8728),
            'username' => (string) ($legacy['username'] ?? ''),
            'password' => (string) ($legacy['password'] ?? ''),
            'enabled' => 1,
            'is_default' => 1,
            'access_mode' => (string) ($legacy['access_mode'] ?? 'hybrid'),
            'auth_backend' => (string) ($legacy['auth_backend'] ?? 'user-manager'),
            'last_status' => (string) (($legacy['last_seen'] ?? '') !== '' ? 'available' : 'unknown'),
            'legacy_fallback' => true,
        ];
    }

    public static function settingsForCustomer(string $username, array $overrides = []): array
    {
        $router = self::routerForCustomer($username);

        return array_merge(Router::connectionSettings($router), $overrides);
    }

    public static function gatewayBundleForCustomer(string $username, array $overrides = []): RouterOSGatewayBundle
    {
        return RouterOSGatewayBundleFactory::create(self::settingsForCustomer($username, $overrides));
    }

    public static function settingsForRouter(int $routerId, array $overrides = []): array
    {
        Database::migrate();
        $router = $routerId > 0 ? Router::find($routerId) : Router::default();

        if ($router === null) {
            return array_merge(RouterSettingsService::connectionSettings(), $overrides);
        }
        if (empty($router['enabled'])) {
            throw new RuntimeException('The selected router is disabled.');
        }

        return array_merge(Router::connectionSettings($router), $overrides);
    }

    public static function gatewayBundleForRouter(int $routerId, array $overrides = []): RouterOSGatewayBundle
    {
        return RouterOSGatewayBundleFactory::create(self::settingsForRouter($routerId, $overrides));
    }
}
