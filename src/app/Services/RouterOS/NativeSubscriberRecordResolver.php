<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Contracts\RouterOSReadGatewayInterface;
use RuntimeException;

final class NativeSubscriberRecordResolver
{
    public function resolve(
        RouterOSReadGatewayInterface $read,
        string $username,
        string $backend
    ): ?array {
        $command = match ($backend) {
            'native-hotspot' => '/ip/hotspot/user/print',
            'native-pppoe' => '/ppp/secret/print',
            default => throw new RuntimeException('Unsupported native subscriber backend.'),
        };
        $rows = $read->read($command, ['?name' => $username]);
        $matches = [];

        foreach ($rows as $row) {
            if (is_array($row) && (string) ($row['name'] ?? '') === $username) {
                $matches[] = $row;
            }
        }
        if (count($matches) > 1) {
            throw new RuntimeException('Multiple matching native records were found; operation stopped.');
        }

        return $matches[0] ?? null;
    }
}
