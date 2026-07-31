<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Contracts\RouterOSWriteCommandPolicyInterface;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\Exceptions\WriteCommandNotAllowedException;

final class WriteCommandPolicy implements RouterOSWriteCommandPolicyInterface
{
    private const RULES = [
        '/user-manager/limitation/set' => [
            'required' => ['numbers'],
            'allowed' => ['numbers', 'transfer-limit', 'uptime-limit', 'rate-limit-rx', 'rate-limit-tx'],
        ],
        '/user-manager/limitation/add' => [
            'required' => ['name'],
            'allowed' => ['name', 'transfer-limit', 'uptime-limit', 'rate-limit-rx', 'rate-limit-tx'],
        ],
        '/user-manager/limitation/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/user-manager/profile/set' => [
            'required' => ['numbers'],
            'allowed' => ['numbers', 'name-for-users', 'starts-when', 'validity', 'price'],
        ],
        '/user-manager/profile/add' => [
            'required' => ['name'],
            'allowed' => ['name', 'name-for-users', 'starts-when', 'validity', 'price'],
        ],
        '/user-manager/profile/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/user-manager/profile-limitation/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/user-manager/profile-limitation/add' => [
            'required' => ['profile', 'limitation'],
            'allowed' => ['profile', 'limitation'],
        ],
        '/user-manager/user-profile/add' => [
            'required' => ['user', 'profile'],
            'allowed' => ['user', 'profile'],
        ],
        '/user-manager/user-profile/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/user-manager/user/add' => [
            'required' => ['name', 'password'],
            'allowed' => ['name', 'password'],
        ],
        '/user-manager/session/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/user-manager/user/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/ip/hotspot/active/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/ppp/active/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/ip/hotspot/user/add' => [
            'required' => ['name', 'password', 'profile'],
            'allowed' => ['name', 'password', 'profile', 'disabled', 'comment'],
        ],
        '/ip/hotspot/user/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/ip/hotspot/user/reset-counters' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/ppp/secret/add' => [
            'required' => ['name', 'password', 'service', 'profile'],
            'allowed' => ['name', 'password', 'service', 'profile', 'disabled', 'comment'],
        ],
        '/ppp/secret/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/ip/hotspot/user/profile/add' => [
            'required' => ['name'],
            'allowed' => ['name', 'rate-limit', 'comment'],
        ],
        '/ip/hotspot/user/profile/set' => [
            'required' => ['numbers'],
            'allowed' => ['numbers', 'rate-limit', 'comment'],
        ],
        '/ip/hotspot/user/profile/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
        '/ppp/profile/add' => [
            'required' => ['name'],
            'allowed' => ['name', 'rate-limit', 'comment'],
        ],
        '/ppp/profile/set' => [
            'required' => ['numbers'],
            'allowed' => ['numbers', 'rate-limit', 'comment', 'local-address', 'remote-address'],
        ],
        '/ppp/profile/remove' => [
            'required' => ['numbers'],
            'allowed' => ['numbers'],
        ],
    ];

    public function assertAllowed(RouterOSWriteCommand $command): void
    {
        if ($command->command === '/user-manager/user/set') {
            $this->assertUserSet($command);

            return;
        }

        if (in_array($command->command, ['/ip/hotspot/user/set', '/ppp/secret/set'], true)) {
            $this->assertNativeUserSet($command);
            return;
        }

        $rule = self::RULES[$command->command] ?? null;
        if (!is_array($rule)) {
            throw new WriteCommandNotAllowedException('RouterOS write command is not allowed.');
        }

        $this->assertParams($command->params, $rule['required'], $rule['allowed']);

        if (!in_array($command->action(), ['add', 'set', 'remove', 'reset-counters'], true)) {
            throw new WriteCommandNotAllowedException('RouterOS write action is not allowed.');
        }
    }

    private function assertNativeUserSet(RouterOSWriteCommand $command): void
    {
        $allowed = [
            'numbers', 'name', 'password', 'profile', 'disabled', 'comment',
            'limit-uptime', 'limit-bytes-in', 'limit-bytes-out', 'limit-bytes-total',
            'rate-limit', 'local-address', 'remote-address',
        ];
        $this->assertParams($command->params, ['numbers'], $allowed);
        $changes = array_diff(array_keys($command->params), ['numbers']);

        if (count($changes) < 1) {
            throw new WriteCommandNotAllowedException('Native account set requires at least one typed change.');
        }
        if (isset($command->params['disabled'])
            && !in_array((string) $command->params['disabled'], ['yes', 'no'], true)) {
            throw new WriteCommandNotAllowedException('RouterOS disabled value is not allowed.');
        }
    }

    private function assertUserSet(RouterOSWriteCommand $command): void
    {
        $allowed = ['numbers', 'name', 'password', 'disabled'];
        $this->assertParams($command->params, ['numbers'], $allowed);
        $changes = array_diff(array_keys($command->params), ['numbers']);
        if (count($changes) !== 1) {
            throw new WriteCommandNotAllowedException('RouterOS user set must change exactly one field.');
        }

        if (isset($command->params['disabled'])
            && !in_array((string) $command->params['disabled'], ['yes', 'no'], true)) {
            throw new WriteCommandNotAllowedException('RouterOS disabled value is not allowed.');
        }
    }

    private function assertParams(array $params, array $required, array $allowed): void
    {
        foreach (array_keys($params) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new WriteCommandNotAllowedException('RouterOS write parameters are not allowed.');
            }
        }

        foreach ($required as $key) {
            if (!array_key_exists($key, $params) || trim((string) $params[$key]) === '') {
                throw new WriteCommandNotAllowedException('RouterOS write parameters are incomplete.');
            }
        }
    }
}
