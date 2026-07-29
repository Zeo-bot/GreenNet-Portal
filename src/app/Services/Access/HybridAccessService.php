<?php

declare(strict_types=1);

namespace GreenNet\Services\Access;

class HybridAccessService implements AccessServiceInterface
{
    public function __construct(private array $routerSettings = [])
    {
    }

    public function sourceName(): string
    {
        return 'hybrid';
    }

    public function getSubscriberStatus(string $username): array
    {
        $hotspot = new HotspotAccessService($this->routerSettings);
        $hotspotData = $hotspot->getSubscriberStatus($username);

        if (($hotspotData['routeros_found'] ?? false) === true) {
            $hotspotData['access_type'] = 'Hybrid / Hotspot';
            return $hotspotData;
        }

        $ppp = new PppAccessService($this->routerSettings);
        $pppData = $ppp->getSubscriberStatus($username);

        if (($pppData['routeros_found'] ?? false) === true) {
            $pppData['access_type'] = 'Hybrid / PPP';
            return $pppData;
        }

        return [
            'username' => $username,
            'package' => 'غير معروف حالياً',
            'remaining' => '-',
            'used' => '-',
            'used_percent' => 0,
            'days_left' => '-',
            'speed' => '-',
            'access_type' => 'Hybrid',
            'connection_status' => 'غير متصل حالياً',
            'routeros_found' => false,
            'ip_address' => '-',
            'mac_address' => '-',
            'uptime' => '-',
            'bytes_in' => '-',
            'bytes_out' => '-',
            'source_detail' => 'MikroTik Hotspot + PPP Active',
        ];
    }
}
