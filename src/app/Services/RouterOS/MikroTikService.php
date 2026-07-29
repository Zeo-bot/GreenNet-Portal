<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use Throwable;

class MikroTikService
{
    private RouterOSApiClient $client;

    public function __construct(?RouterOSApiClient $client = null, array $settings = [])
    {
        $this->client = $client ?? new RouterOSApiClient($settings);
    }

    public function status(): array
    {
        $identity = $this->safeRows('/system/identity/print');
        $resource = $this->safeRows('/system/resource/print');

        return [
            'ok' => true,
            'connected' => true,
            'identity' => (string) ($identity[0]['name'] ?? ''),
            'version' => (string) ($resource[0]['version'] ?? ''),
            'board_name' => (string) ($resource[0]['board-name'] ?? ''),
            'architecture' => (string) ($resource[0]['architecture-name'] ?? ''),
            'uptime' => (string) ($resource[0]['uptime'] ?? ''),
        ];
    }

    public function hotspotActiveUsers(): array
    {
        return $this->client->comm('/ip/hotspot/active/print');
    }

    public function pppActiveUsers(): array
    {
        return $this->client->comm('/ppp/active/print');
    }

    public function readHotspotUsers(): array
    {
        return $this->client->comm('/ip/hotspot/user/print');
    }

    public function readPppSecrets(): array
    {
        return $this->client->comm('/ppp/secret/print');
    }

    public function readUserManagerUsers(): array
    {
        return $this->client->comm('/user-manager/user/print');
    }

    public function readHotspotProfiles(): array
    {
        return $this->client->comm('/ip/hotspot/user/profile/print');
    }

    public function readPppProfiles(): array
    {
        return $this->client->comm('/ppp/profile/print');
    }

    public function readUserManagerProfiles(): array
    {
        return $this->client->comm('/user-manager/profile/print');
    }

    public function activeUsersSummary(): array
    {
        $hotspot = $this->safeRows('/ip/hotspot/active/print');
        $ppp = $this->safeRows('/ppp/active/print');

        return [
            'hotspot_count' => count($hotspot),
            'ppp_count' => count($ppp),
            'total_count' => count($hotspot) + count($ppp),
            'hotspot' => $hotspot,
            'ppp' => $ppp,
        ];
    }

    public function findHotspotActiveUser(string $username): ?array
    {
        foreach ($this->hotspotActiveUsers() as $row) {
            $user = (string) ($row['user'] ?? $row['name'] ?? '');

            if ($user === $username) {
                return $row;
            }
        }

        return null;
    }

    public function findPppActiveUser(string $username): ?array
    {
        foreach ($this->pppActiveUsers() as $row) {
            $user = (string) ($row['name'] ?? $row['user'] ?? '');

            if ($user === $username) {
                return $row;
            }
        }

        return null;
    }

    public function dataDiscovery(): array
    {
        $commands = [
            '/ip/hotspot/active/print',
            '/ip/hotspot/user/print',
            '/ip/hotspot/user/profile/print',

            '/ppp/active/print',
            '/ppp/secret/print',
            '/ppp/profile/print',

            '/user-manager/user/print',
            '/user-manager/profile/print',
            '/user-manager/session/print',

            '/tool/user-manager/user/print',
            '/tool/user-manager/profile/print',
            '/tool/user-manager/session/print',
        ];

        $results = [];

        foreach ($commands as $command) {
            $startedAt = microtime(true);

            try {
                $rows = $this->client->comm($command);

                $results[] = [
                    'command' => $command,
                    'ok' => true,
                    'rows' => $rows,
                    'rows_count' => count($rows),
                    'error' => '',
                    'duration_ms' => $this->elapsedMs($startedAt),
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'command' => $command,
                    'ok' => false,
                    'rows' => [],
                    'rows_count' => 0,
                    'error' => $e->getMessage(),
                    'duration_ms' => $this->elapsedMs($startedAt),
                ];
            }
        }

        return $results;
    }

    private function safeRows(string $command): array
    {
        try {
            return $this->client->comm($command);
        } catch (Throwable) {
            return [];
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
