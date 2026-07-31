<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Contracts\RouterOSReadGatewayInterface;

class RouterOSProfilesService
{
    public function __construct(private ?RouterOSReadGatewayInterface $readGateway = null)
    {
    }

    public function getSummary(): array
    {
        $gateway = $this->readGateway ??= RouterOSReadGatewayFactory::create(['timeout' => 3]);
        $hotspot = $this->normalizeHotspotProfiles(['ok' => true, 'rows' => $gateway->read('/ip/hotspot/user/profile/print')]);
        $ppp = $this->normalizePppProfiles(['ok' => true, 'rows' => $gateway->read('/ppp/profile/print')]);
        $userManager = $this->normalizeUserManagerProfiles(['ok' => true, 'rows' => $gateway->read('/user-manager/profile/print')]);

        $allProfiles = array_merge(
            $hotspot['profiles'],
            $ppp['profiles'],
            $userManager['profiles']
        );

        $rateLimitedProfiles = array_values(array_filter(
            $allProfiles,
            fn (array $profile) => ($profile['rate_limit'] ?? '-') !== '-'
        ));

        return [
            'hotspot' => $hotspot,
            'ppp' => $ppp,
            'user_manager' => $userManager,

            'all_profiles' => $allProfiles,
            'total_count' => count($allProfiles),
            'rate_limited_count' => count($rateLimitedProfiles),

            'hotspot_count' => count($hotspot['profiles']),
            'ppp_count' => count($ppp['profiles']),
            'user_manager_count' => count($userManager['profiles']),
        ];
    }

    private function normalizeHotspotProfiles(array $response): array
    {
        if (($response['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => $response['error'] ?? 'Unknown error',
                'profiles' => [],
            ];
        }

        $profiles = [];

        foreach ($response['rows'] as $row) {
            $profiles[] = [
                'type' => 'Hotspot',
                'name' => $row['name'] ?? '-',
                'rate_limit' => $row['rate-limit'] ?? '-',
                'shared_users' => $row['shared-users'] ?? '-',
                'address_pool' => $row['address-pool'] ?? '-',
                'session_timeout' => $row['session-timeout'] ?? '-',
                'idle_timeout' => $row['idle-timeout'] ?? '-',
                'keepalive_timeout' => $row['keepalive-timeout'] ?? '-',
                'mac_cookie_timeout' => $row['mac-cookie-timeout'] ?? '-',
                'only_one' => $row['only-one'] ?? '-',
                'local_address' => '-',
                'remote_address' => '-',
                'comment' => $row['comment'] ?? '-',
                'raw' => $row,
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'profiles' => $profiles,
        ];
    }

    private function normalizePppProfiles(array $response): array
    {
        if (($response['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => $response['error'] ?? 'Unknown error',
                'profiles' => [],
            ];
        }

        $profiles = [];

        foreach ($response['rows'] as $row) {
            $profiles[] = [
                'type' => 'PPP',
                'name' => $row['name'] ?? '-',
                'rate_limit' => $row['rate-limit'] ?? '-',
                'shared_users' => '-',
                'address_pool' => $row['remote-address'] ?? '-',
                'session_timeout' => $row['session-timeout'] ?? '-',
                'idle_timeout' => $row['idle-timeout'] ?? '-',
                'keepalive_timeout' => '-',
                'mac_cookie_timeout' => '-',
                'only_one' => $row['only-one'] ?? '-',
                'local_address' => $row['local-address'] ?? '-',
                'remote_address' => $row['remote-address'] ?? '-',
                'comment' => $row['comment'] ?? '-',
                'raw' => $row,
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'profiles' => $profiles,
        ];
    }

    private function normalizeUserManagerProfiles(array $response): array
    {
        if (($response['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => $response['error'] ?? 'Unknown error',
                'profiles' => [],
            ];
        }

        $profiles = [];

        foreach ($response['rows'] as $row) {
            $profiles[] = [
                'type' => 'User Manager',
                'name' => $row['name'] ?? ($row['profile'] ?? '-'),
                'rate_limit' => $row['rate-limit'] ?? ($row['rate'] ?? '-'),
                'shared_users' => $row['shared-users'] ?? '-',
                'address_pool' => '-',
                'session_timeout' => $row['session-timeout'] ?? ($row['validity'] ?? '-'),
                'idle_timeout' => $row['idle-timeout'] ?? '-',
                'keepalive_timeout' => '-',
                'mac_cookie_timeout' => '-',
                'only_one' => '-',
                'local_address' => '-',
                'remote_address' => '-',
                'comment' => $row['comment'] ?? '-',
                'raw' => $row,
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'profiles' => $profiles,
        ];
    }
}
