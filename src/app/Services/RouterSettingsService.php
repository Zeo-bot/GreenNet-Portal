<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Core\Config;
use GreenNet\Models\Setting;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use Throwable;

class RouterSettingsService
{
    public static function current(): array
    {
        return [
            'runtime_mode' => Setting::get('router_runtime_mode', 'external'),
            'router_name' => Setting::get('router_name', 'Main MikroTik'),

            'host' => Setting::get('router_host', Config::mikrotikHost()),
            'api_port' => (int) Setting::get('router_api_port', (string) Config::mikrotikApiPort()),
            'username' => Setting::get('router_username', Config::mikrotikUsername()),
            'password' => Setting::get('router_password', Config::mikrotikPassword()),

            'access_mode' => Setting::get('router_access_mode', Config::accessMode()),
            'auth_backend' => Setting::get('router_auth_backend', Config::authBackend()),

            'identity' => Setting::get('router_identity', ''),
            'routeros_version' => Setting::get('routeros_version', ''),
            'board_name' => Setting::get('router_board_name', ''),
            'architecture' => Setting::get('router_architecture', ''),
            'uptime' => Setting::get('router_uptime', ''),
            'last_seen' => Setting::get('router_last_seen', ''),

            'detected_hotspot_users' => (int) Setting::get('router_detected_hotspot_users', '0'),
            'detected_ppp_secrets' => (int) Setting::get('router_detected_ppp_secrets', '0'),
            'detected_user_manager_users' => (int) Setting::get('router_detected_user_manager_users', '0'),
            'detected_profiles' => (int) Setting::get('router_detected_profiles', '0'),
        ];
    }

    public static function connectionSettings(?array $override = null): array
    {
        $current = self::current();

        if (is_array($override)) {
            $current = array_merge($current, $override);
        }

        return [
            'host' => trim((string) ($current['host'] ?? '')),
            'api_port' => (int) ($current['api_port'] ?? 8728),
            'username' => trim((string) ($current['username'] ?? '')),
            'password' => (string) ($current['password'] ?? ''),
        ];
    }

    public static function saveConnection(array $input): void
    {
        $current = self::current();

        $runtimeMode = self::allowedRuntimeMode((string) ($input['runtime_mode'] ?? $current['runtime_mode']));
        $accessMode = self::allowedAccessMode((string) ($input['access_mode'] ?? $current['access_mode']));
        $authBackend = self::allowedAuthBackend((string) ($input['auth_backend'] ?? $current['auth_backend']));

        $host = trim((string) ($input['host'] ?? $current['host']));
        $port = (int) ($input['api_port'] ?? $current['api_port']);
        $username = trim((string) ($input['username'] ?? $current['username']));
        $password = (string) ($input['password'] ?? '');

        if ($port <= 0) {
            $port = 8728;
        }

        if ($password === '') {
            $password = (string) ($current['password'] ?? '');
        }

        Setting::setMany([
            'router_runtime_mode' => $runtimeMode,
            'router_name' => trim((string) ($input['router_name'] ?? $current['router_name'])),
            'router_host' => $host,
            'router_api_port' => (string) $port,
            'router_username' => $username,
            'router_password' => $password,
            'router_access_mode' => $accessMode,
            'router_auth_backend' => $authBackend,
        ]);
    }

    public static function testConnection(?array $override = null): array
    {
        $startedAt = microtime(true);
        $settings = self::connectionSettings($override);

        try {
            $client = new RouterOSApiClient($settings);

            $identityRows = $client->comm('/system/identity/print');
            $resourceRows = $client->comm('/system/resource/print');

            $identity = $identityRows[0] ?? [];
            $resource = $resourceRows[0] ?? [];

            $routerboard = self::safeCommand($client, '/system/routerboard/print');

            $data = [
                'ok' => true,
                'message' => 'تم الاتصال بنجاح.',
                'duration_ms' => self::elapsedMs($startedAt),
                'identity' => (string) ($identity['name'] ?? ''),
                'routeros_version' => (string) ($resource['version'] ?? ''),
                'board_name' => (string) ($resource['board-name'] ?? ($routerboard[0]['model'] ?? '')),
                'architecture' => (string) ($resource['architecture-name'] ?? ''),
                'uptime' => (string) ($resource['uptime'] ?? ''),
                'cpu' => (string) ($resource['cpu'] ?? ''),
                'free_memory' => (string) ($resource['free-memory'] ?? ''),
                'total_memory' => (string) ($resource['total-memory'] ?? ''),
                'free_hdd_space' => (string) ($resource['free-hdd-space'] ?? ''),
                'total_hdd_space' => (string) ($resource['total-hdd-space'] ?? ''),
            ];

            Setting::setMany([
                'router_identity' => $data['identity'],
                'routeros_version' => $data['routeros_version'],
                'router_board_name' => $data['board_name'],
                'router_architecture' => $data['architecture'],
                'router_uptime' => $data['uptime'],
                'router_last_seen' => date('Y-m-d H:i:s'),
            ]);

            return $data;
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'duration_ms' => self::elapsedMs($startedAt),
                'identity' => '',
                'routeros_version' => '',
                'board_name' => '',
                'architecture' => '',
                'uptime' => '',
            ];
        }
    }

    public static function detectServices(?array $override = null): array
    {
        $startedAt = microtime(true);
        $settings = self::connectionSettings($override);

        $checks = [];

        try {
            $client = new RouterOSApiClient($settings);

            $checks[] = self::detectCommand($client, 'Hotspot Active', '/ip/hotspot/active/print');
            $checks[] = self::detectCommand($client, 'Hotspot Users', '/ip/hotspot/user/print');
            $checks[] = self::detectCommand($client, 'Hotspot Profiles', '/ip/hotspot/user/profile/print');

            $checks[] = self::detectCommand($client, 'PPP Active', '/ppp/active/print');
            $checks[] = self::detectCommand($client, 'PPP Secrets', '/ppp/secret/print');
            $checks[] = self::detectCommand($client, 'PPP Profiles', '/ppp/profile/print');

            $checks[] = self::detectCommand($client, 'User Manager Users', '/user-manager/user/print');
            $checks[] = self::detectCommand($client, 'User Manager Profiles', '/user-manager/profile/print');
            $checks[] = self::detectCommand($client, 'User Manager Sessions', '/user-manager/session/print');

            $summary = self::detectionSummary($checks);

            Setting::setMany([
                'router_detected_hotspot_users' => (string) $summary['hotspot_users'],
                'router_detected_ppp_secrets' => (string) $summary['ppp_secrets'],
                'router_detected_user_manager_users' => (string) $summary['user_manager_users'],
                'router_detected_profiles' => (string) $summary['profiles'],
                'router_last_seen' => date('Y-m-d H:i:s'),
            ]);

            return [
                'ok' => true,
                'message' => 'تم فحص الخدمات بنجاح.',
                'duration_ms' => self::elapsedMs($startedAt),
                'checks' => $checks,
                'summary' => $summary,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'duration_ms' => self::elapsedMs($startedAt),
                'checks' => $checks,
                'summary' => self::detectionSummary($checks),
            ];
        }
    }

    private static function detectCommand(RouterOSApiClient $client, string $label, string $command): array
    {
        $startedAt = microtime(true);

        try {
            $rows = $client->comm($command);

            return [
                'label' => $label,
                'command' => $command,
                'ok' => true,
                'rows_count' => count($rows),
                'message' => 'OK',
                'duration_ms' => self::elapsedMs($startedAt),
            ];
        } catch (Throwable $e) {
            return [
                'label' => $label,
                'command' => $command,
                'ok' => false,
                'rows_count' => 0,
                'message' => $e->getMessage(),
                'duration_ms' => self::elapsedMs($startedAt),
            ];
        }
    }

    private static function safeCommand(RouterOSApiClient $client, string $command): array
    {
        try {
            return $client->comm($command);
        } catch (Throwable) {
            return [];
        }
    }

    private static function detectionSummary(array $checks): array
    {
        $summary = [
            'ok_count' => 0,
            'failed_count' => 0,
            'hotspot_users' => 0,
            'ppp_secrets' => 0,
            'user_manager_users' => 0,
            'profiles' => 0,
            'suggested_access_mode' => 'hybrid',
        ];

        foreach ($checks as $check) {
            if (!empty($check['ok'])) {
                $summary['ok_count']++;
            } else {
                $summary['failed_count']++;
            }

            $label = (string) ($check['label'] ?? '');
            $count = (int) ($check['rows_count'] ?? 0);

            if ($label === 'Hotspot Users') {
                $summary['hotspot_users'] = $count;
            }

            if ($label === 'PPP Secrets') {
                $summary['ppp_secrets'] = $count;
            }

            if ($label === 'User Manager Users') {
                $summary['user_manager_users'] = $count;
            }

            if (str_contains($label, 'Profiles')) {
                $summary['profiles'] += $count;
            }
        }

        if ($summary['hotspot_users'] > 0 && $summary['ppp_secrets'] === 0) {
            $summary['suggested_access_mode'] = 'hotspot';
        } elseif ($summary['ppp_secrets'] > 0 && $summary['hotspot_users'] === 0) {
            $summary['suggested_access_mode'] = 'ppp';
        } elseif (
            $summary['hotspot_users'] > 0
            || $summary['ppp_secrets'] > 0
            || $summary['user_manager_users'] > 0
        ) {
            $summary['suggested_access_mode'] = 'hybrid';
        }

        return $summary;
    }

    private static function allowedRuntimeMode(string $mode): string
    {
        return match ($mode) {
            'external',
            'routeros-container',
            'routeros-app' => $mode,
            default => 'external',
        };
    }

    private static function allowedAccessMode(string $mode): string
    {
        return match ($mode) {
            'hotspot',
            'ppp',
            'user-manager',
            'hybrid' => $mode,
            default => 'hybrid',
        };
    }

    private static function allowedAuthBackend(string $backend): string
    {
        return match ($backend) {
            'user-manager',
            'routeros-local',
            'freeradius',
            'hybrid' => $backend,
            default => 'user-manager',
        };
    }

    private static function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}