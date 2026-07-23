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
        '/user-manager/profile/set' => [
            'required' => ['numbers'],
            'allowed' => ['numbers', 'name-for-users', 'starts-when', 'validity', 'price'],
        ],
        '/user-manager/profile/add' => [
            'required' => ['name'],
            'allowed' => ['name', 'name-for-users', 'starts-when', 'validity', 'price'],
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
    ];

    public function assertAllowed(RouterOSWriteCommand $command): void
    {
        if ($command->command === '/user-manager/user/set') {
            $this->assertUserSet($command);

            return;
        }

        $rule = self::RULES[$command->command] ?? null;
        if (!is_array($rule)) {
            throw new WriteCommandNotAllowedException('RouterOS write command is not allowed.');
        }

        $this->assertParams($command->params, $rule['required'], $rule['allowed']);

        if (!in_array($command->action(), ['add', 'set', 'remove'], true)) {
            throw new WriteCommandNotAllowedException('RouterOS write action is not allowed.');
        }
    }

    private function assertUserSet(RouterOSWriteCommand $command): void
    {
        $hasDisabled = array_key_exists('disabled', $command->params);
        $hasPassword = array_key_exists('password', $command->params);

        if ($hasDisabled === $hasPassword) {
            throw new WriteCommandNotAllowedException('RouterOS user set parameters are not allowed.');
        }

        $allowed = $hasDisabled ? ['numbers', 'disabled'] : ['numbers', 'password'];
        $this->assertParams($command->params, $allowed, $allowed);

        if ($hasDisabled && !in_array((string) $command->params['disabled'], ['yes', 'no'], true)) {
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
