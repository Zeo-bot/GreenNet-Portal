<?php

declare(strict_types=1);

namespace GreenNet\Services\Access;

use GreenNet\Services\RouterOS\MikroTikService;

class PppAccessService implements AccessServiceInterface
{
    public function __construct(private array $routerSettings = [])
    {
    }

    public function sourceName(): string
    {
        return 'ppp';
    }

    public function getSubscriberStatus(string $username): array
    {
        $mikrotik = new MikroTikService(null, $this->routerSettings);
        $activeUser = $mikrotik->findPppActiveUser($username);

        if (!$activeUser) {
            return $this->offlineData($username);
        }

        return [
            'username' => $username,
            'package' => 'PPP / PPPoE',
            'remaining' => 'سيتم ربط الباقة لاحقاً',
            'used' => 'سيتم ربط الاستهلاك لاحقاً',
            'used_percent' => 0,
            'days_left' => 'سيتم ربط الصلاحية لاحقاً',
            'speed' => 'حسب الباقة',
            'access_type' => 'PPP / PPPoE',
            'connection_status' => 'متصل الآن',
            'routeros_found' => true,
            'ip_address' => $activeUser['address'] ?? '-',
            'mac_address' => $activeUser['caller-id'] ?? '-',
            'uptime' => $activeUser['uptime'] ?? '-',
            'bytes_in' => '-',
            'bytes_out' => '-',
            'source_detail' => 'MikroTik PPP Active',
        ];
    }

    private function offlineData(string $username): array
    {
        return [
            'username' => $username,
            'package' => 'غير معروف حالياً',
            'remaining' => '-',
            'used' => '-',
            'used_percent' => 0,
            'days_left' => '-',
            'speed' => '-',
            'access_type' => 'PPP / PPPoE',
            'connection_status' => 'غير متصل حالياً',
            'routeros_found' => false,
            'ip_address' => '-',
            'mac_address' => '-',
            'uptime' => '-',
            'bytes_in' => '-',
            'bytes_out' => '-',
            'source_detail' => 'MikroTik PPP Active',
        ];
    }
}
