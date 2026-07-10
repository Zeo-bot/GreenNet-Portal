<?php

declare(strict_types=1);

namespace GreenNet\Services\Access;

use GreenNet\Core\Config;

class AccessServiceFactory
{
    public static function make(): AccessServiceInterface
    {
        return match (Config::accessMode()) {
            'hotspot' => new HotspotAccessService(),
            'ppp' => new PppAccessService(),
            default => new HybridAccessService(),
        };
    }
}