<?php

declare(strict_types=1);

namespace GreenNet\Services\Access;

use GreenNet\Core\Config;

class AccessServiceFactory
{
    public static function make(array $settings = [], string $accessMode = ''): AccessServiceInterface
    {
        return match ($accessMode !== '' ? $accessMode : Config::accessMode()) {
            'hotspot' => new HotspotAccessService($settings),
            'ppp', 'pppoe' => new PppAccessService($settings),
            default => new HybridAccessService($settings),
        };
    }
}
