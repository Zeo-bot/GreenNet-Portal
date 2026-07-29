<?php

declare(strict_types=1);

namespace GreenNet\Models;

use PDO;

final class RouterPackageProfile extends Model
{
    public static function forRouter(int $routerId): array
    {
        $stmt = self::db()->prepare("
            SELECT rpm.*, sp.name AS package_name
            FROM router_backend_package_profiles rpm
            JOIN service_packages sp ON sp.id = rpm.package_id
            WHERE rpm.router_id = :router_id
            ORDER BY sp.name ASC
        ");
        $stmt->execute(['router_id' => $routerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function profileName(
        int $routerId,
        int $packageId,
        string $fallback,
        string $backend = 'user-manager'
    ): string
    {
        if ($routerId <= 0 || $packageId <= 0) {
            return $fallback;
        }

        $stmt = self::db()->prepare("
            SELECT profile_name FROM router_backend_package_profiles
            WHERE router_id = :router_id AND package_id = :package_id AND backend = :backend
            LIMIT 1
        ");
        $stmt->execute(['router_id' => $routerId, 'package_id' => $packageId, 'backend' => $backend]);
        $name = $stmt->fetchColumn();

        return is_string($name) && trim($name) !== '' ? trim($name) : $fallback;
    }

    public static function save(
        int $routerId,
        int $packageId,
        string $profileName,
        string $profileId = '',
        string $backend = 'user-manager'
    ): void
    {
        $stmt = self::db()->prepare("
            INSERT INTO router_backend_package_profiles
                (router_id, package_id, backend, profile_name, profile_id, created_at, updated_at)
            VALUES
                (:router_id, :package_id, :backend, :profile_name, :profile_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(router_id, package_id, backend) DO UPDATE SET
                profile_name = excluded.profile_name,
                profile_id = excluded.profile_id,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            'router_id' => $routerId,
            'package_id' => $packageId,
            'backend' => $backend,
            'profile_name' => trim($profileName),
            'profile_id' => trim($profileId),
        ]);
    }
}
