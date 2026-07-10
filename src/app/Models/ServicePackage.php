<?php

declare(strict_types=1);

namespace GreenNet\Models;

class ServicePackage extends Model
{
    public static function all(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM service_packages
            ORDER BY is_active DESC, access_type ASC, name ASC
        ");

        return $stmt->fetchAll();
    }

    public static function active(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM service_packages
            WHERE is_active = 1
            ORDER BY access_type ASC, name ASC
        ");

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM service_packages
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $package = $stmt->fetch();

        return $package ?: null;
    }

    public static function findBySource(string $sourceType, string $sourceProfile): ?array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM service_packages
            WHERE source_type = :source_type
              AND source_profile = :source_profile
            LIMIT 1
        ");

        $stmt->execute([
            'source_type' => $sourceType,
            'source_profile' => $sourceProfile,
        ]);

        $package = $stmt->fetch();

        return $package ?: null;
    }

    public static function upsertRouterProfile(
        string $name,
        string $sourceType,
        string $sourceProfile,
        string $accessType,
        string $rateLimit,
        string $notes
    ): string {
        $existing = self::findBySource($sourceType, $sourceProfile);

        if ($existing === null) {
            $stmt = self::db()->prepare("
                INSERT INTO service_packages
                (
                    name,
                    source_type,
                    source_profile,
                    access_type,
                    rate_limit,
                    duration_days,
                    quota_gb,
                    price,
                    currency,
                    is_active,
                    notes
                )
                VALUES
                (
                    :name,
                    :source_type,
                    :source_profile,
                    :access_type,
                    :rate_limit,
                    0,
                    0,
                    0,
                    'SYP',
                    1,
                    :notes
                )
            ");

            $stmt->execute([
                'name' => $name,
                'source_type' => $sourceType,
                'source_profile' => $sourceProfile,
                'access_type' => $accessType,
                'rate_limit' => $rateLimit,
                'notes' => $notes,
            ]);

            return 'created';
        }

        $stmt = self::db()->prepare("
            UPDATE service_packages
            SET access_type = :access_type,
                rate_limit = :rate_limit,
                notes = CASE
                    WHEN notes IS NULL OR notes = '' THEN :notes
                    ELSE notes
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $existing['id'],
            'access_type' => $accessType,
            'rate_limit' => $rateLimit,
            'notes' => $notes,
        ]);

        return 'updated';
    }

    public static function updateDetails(
        int $id,
        string $name,
        int $price,
        string $currency,
        int $durationDays,
        float $quotaGb,
        int $isActive,
        string $notes
    ): void {
        $stmt = self::db()->prepare("
            UPDATE service_packages
            SET name = :name,
                price = :price,
                currency = :currency,
                duration_days = :duration_days,
                quota_gb = :quota_gb,
                is_active = :is_active,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'currency' => $currency,
            'duration_days' => $durationDays,
            'quota_gb' => $quotaGb,
            'is_active' => $isActive,
            'notes' => $notes,
        ]);
    }

    public static function count(): int
    {
        return (int) self::db()->query("
            SELECT COUNT(*)
            FROM service_packages
        ")->fetchColumn();
    }

    public static function activeCount(): int
    {
        return (int) self::db()->query("
            SELECT COUNT(*)
            FROM service_packages
            WHERE is_active = 1
        ")->fetchColumn();
    }

    public static function countByAccessType(string $accessType): int
    {
        $stmt = self::db()->prepare("
            SELECT COUNT(*)
            FROM service_packages
            WHERE access_type = :access_type
        ");

        $stmt->execute([
            'access_type' => $accessType,
        ]);

        return (int) $stmt->fetchColumn();
    }
}