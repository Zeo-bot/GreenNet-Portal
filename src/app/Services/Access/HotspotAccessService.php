<?php

declare(strict_types=1);

namespace GreenNet\Services\Access;

use GreenNet\Services\RouterOS\MikroTikService;

class HotspotAccessService implements AccessServiceInterface
{
    public function __construct(private array $routerSettings = [])
    {
    }

    public function sourceName(): string
    {
        return 'hotspot';
    }

    public function getSubscriberStatus(string $username): array
    {
        $mikrotik = new MikroTikService(null, $this->routerSettings);
        $activeUser = $mikrotik->findHotspotActiveUser($username);

        if (!$activeUser) {
            return $this->offlineData($username);
        }

        $bytesIn = (int) ($activeUser['bytes-in'] ?? 0);
        $bytesOut = (int) ($activeUser['bytes-out'] ?? 0);
        $usedBytes = $bytesIn + $bytesOut;

        $quotaBytes = 10 * 1024 * 1024 * 1024;
        $usedPercent = (int) floor(($usedBytes / $quotaBytes) * 100);

        if ($usedPercent > 100) {
            $usedPercent = 100;
        }

        $remainingBytes = max(0, $quotaBytes - $usedBytes);

        return [
            'username' => $username,
            'package' => '10 GB / 10 أيام',
            'remaining' => MikroTikService::humanBytes($remainingBytes),
            'used' => MikroTikService::humanBytes($usedBytes),
            'used_percent' => $usedPercent,
            'days_left' => 'سيتم ربط الصلاحية لاحقاً',
            'speed' => 'حسب الباقة',
            'access_type' => 'Hotspot',
            'connection_status' => 'متصل الآن',
            'routeros_found' => true,
            'ip_address' => $activeUser['address'] ?? '-',
            'mac_address' => $activeUser['mac-address'] ?? '-',
            'uptime' => $activeUser['uptime'] ?? '-',
            'bytes_in' => MikroTikService::humanBytes($bytesIn),
            'bytes_out' => MikroTikService::humanBytes($bytesOut),
            'source_detail' => 'MikroTik Hotspot Active',
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
            'access_type' => 'Hotspot',
            'connection_status' => 'غير متصل حالياً',
            'routeros_found' => false,
            'ip_address' => '-',
            'mac_address' => '-',
            'uptime' => '-',
            'bytes_in' => '-',
            'bytes_out' => '-',
            'source_detail' => 'MikroTik Hotspot Active',
        ];
    }
}
