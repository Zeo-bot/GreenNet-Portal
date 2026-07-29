<?php

declare(strict_types=1);

namespace GreenNet\Models;

use PDO;

final class Router extends Model
{
    public static function allWithCustomerCounts(): array
    {
        $stmt = self::db()->query("
            SELECT r.*, COUNT(c.id) AS customer_count
            FROM routers r
            LEFT JOIN customers_local c ON c.router_id = r.id
            GROUP BY r.id
            ORDER BY r.is_default DESC, r.name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function enabled(): array
    {
        $stmt = self::db()->query("
            SELECT id, name, host, api_port, is_default
            FROM routers
            WHERE enabled = 1
            ORDER BY is_default DESC, name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = self::db()->prepare("SELECT * FROM routers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function default(): ?array
    {
        $row = self::db()->query("
            SELECT * FROM routers
            WHERE enabled = 1 AND is_default = 1
            ORDER BY id ASC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function save(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        if ($isDefault === 1) {
            self::db()->exec("UPDATE routers SET is_default = 0");
        }

        if ($id > 0) {
            $current = self::find($id);
            $password = (string) ($data['password'] ?? '');
            if ($password === '') {
                $password = (string) ($current['password'] ?? '');
            }

            $stmt = self::db()->prepare("
                UPDATE routers SET
                    name = :name, host = :host, api_port = :api_port,
                    username = :username, password = :password,
                    enabled = :enabled, is_default = :is_default,
                    access_mode = :access_mode, auth_backend = :auth_backend,
                    location = :location, notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(self::params($data, $password, $isDefault) + ['id' => $id]);

            return $id;
        }

        $stmt = self::db()->prepare("
            INSERT INTO routers (
                name, host, api_port, username, password, enabled, is_default,
                access_mode, auth_backend, location, notes
            ) VALUES (
                :name, :host, :api_port, :username, :password, :enabled, :is_default,
                :access_mode, :auth_backend, :location, :notes
            )
        ");
        $stmt->execute(self::params($data, (string) ($data['password'] ?? ''), $isDefault));

        return (int) self::db()->lastInsertId();
    }

    public static function updateStatus(int $id, array $status): void
    {
        $stmt = self::db()->prepare("
            UPDATE routers SET
                identity = :identity,
                routeros_version = :routeros_version,
                last_status = :last_status,
                last_error = :last_error,
                last_checked_at = CURRENT_TIMESTAMP,
                last_seen_at = CASE WHEN :available = 1 THEN CURRENT_TIMESTAMP ELSE last_seen_at END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute([
            'identity' => (string) ($status['identity'] ?? ''),
            'routeros_version' => (string) ($status['routeros_version'] ?? ''),
            'last_status' => !empty($status['ok']) ? 'available' : 'unreachable',
            'last_error' => !empty($status['ok']) ? '' : (string) ($status['message'] ?? 'Connection failed'),
            'available' => !empty($status['ok']) ? 1 : 0,
            'id' => $id,
        ]);
    }

    public static function connectionSettings(array $router): array
    {
        return [
            'host' => (string) ($router['host'] ?? ''),
            'api_port' => (int) ($router['api_port'] ?? 8728),
            'username' => (string) ($router['username'] ?? ''),
            'password' => (string) ($router['password'] ?? ''),
        ];
    }

    private static function params(array $data, string $password, int $isDefault): array
    {
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'host' => trim((string) ($data['host'] ?? '')),
            'api_port' => max(1, (int) ($data['api_port'] ?? 8728)),
            'username' => trim((string) ($data['username'] ?? '')),
            'password' => $password,
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'is_default' => $isDefault,
            'access_mode' => (string) ($data['access_mode'] ?? 'hybrid'),
            'auth_backend' => (string) ($data['auth_backend'] ?? 'user-manager'),
            'location' => trim((string) ($data['location'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];
    }
}
