<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use RuntimeException;

final class ReadCommandPolicy
{
    /** @var list<string> */
    private const ALLOWED_COMMANDS = [
        '/system/identity/print',
        '/system/resource/print',
        '/system/routerboard/print',
        '/system/device-mode/print',
        '/container/config/print',
        '/container/print',
        '/app/print',
        '/interface/print',
        '/ip/address/print',
        '/ip/hotspot/print',
        '/interface/pppoe-server/server/print',
        '/ip/hotspot/active/print',
        '/ip/hotspot/user/print',
        '/ip/hotspot/user/profile/print',
        '/ppp/active/print',
        '/ppp/secret/print',
        '/ppp/profile/print',
        '/user-manager/user/print',
        '/user-manager/user/monitor',
        '/user-manager/profile/print',
        '/user-manager/session/print',
        '/user-manager/user-profile/print',
        '/user-manager/limitation/print',
        '/user-manager/profile-limitation/print',
        '/user-manager/profile/limitation/print',
        '/user-manager/profile/limitations/print',
        '/tool/user-manager/user/print',
        '/tool/user-manager/profile/print',
        '/tool/user-manager/session/print',
    ];

    public function assertAllowed(string $command): void
    {
        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            throw new RuntimeException('RouterOS read command is not allowed.');
        }
    }
}
