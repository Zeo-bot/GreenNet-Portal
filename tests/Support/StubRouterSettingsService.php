<?php

declare(strict_types=1);

namespace GreenNet\Tests\Support;

final class StubRouterSettingsService
{
    public static int $calls = 0;

    public static function connectionSettings(): array
    {
        self::$calls++;

        return [];
    }
}
