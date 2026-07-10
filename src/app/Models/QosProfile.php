<?php

declare(strict_types=1);

namespace GreenNet\Models;

class QosProfile extends Model
{
    public static function all(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM qos_profiles
            ORDER BY priority ASC, name ASC
        ");

        return $stmt->fetchAll();
    }

    public static function active(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM qos_profiles
            WHERE is_active = 1
            ORDER BY priority ASC, name ASC
        ");

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM qos_profiles
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $profile = $stmt->fetch();

        return $profile ?: null;
    }

    public static function update(
        int $id,
        string $name,
        string $mode,
        int $priority,
        string $description,
        int $isActive
    ): void {
        $stmt = self::db()->prepare("
            UPDATE qos_profiles
            SET name = :name,
                mode = :mode,
                priority = :priority,
                description = :description,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'mode' => $mode,
            'priority' => $priority,
            'description' => $description,
            'is_active' => $isActive,
        ]);
    }

    public static function countActive(): int
    {
        return (int) self::db()->query("
            SELECT COUNT(*)
            FROM qos_profiles
            WHERE is_active = 1
        ")->fetchColumn();
    }
}