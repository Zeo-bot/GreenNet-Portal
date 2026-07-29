<?php

declare(strict_types=1);

namespace GreenNet\Models;

class CustomerLocal extends Model
{
    public static function all(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM customers_local
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public static function search(string $query, int $limit = 30): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $stmt = self::db()->prepare("
            SELECT *
            FROM customers_local
            WHERE username LIKE :query
               OR display_name LIKE :query
               OR phone LIKE :query
               OR notes LIKE :query
               OR access_type LIKE :query
               OR payment_status LIKE :query
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':query', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function create(
        string $username,
        string $displayName,
        string $phone,
        string $accessType,
        string $paymentStatus,
        string $notes,
        int $packageId = 0,
        ?int $routerId = null
    ): void {
        $stmt = self::db()->prepare("
            INSERT INTO customers_local
            (username, display_name, phone, access_type, payment_status, package_id, router_id, notes)
            VALUES
            (:username, :display_name, :phone, :access_type, :payment_status, :package_id, :router_id, :notes)
        ");

        $stmt->execute([
            'username' => $username,
            'display_name' => $displayName,
            'phone' => $phone,
            'access_type' => $accessType,
            'payment_status' => $paymentStatus,
            'package_id' => $packageId,
            'router_id' => $routerId,
            'notes' => $notes,
        ]);
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM customers_local
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute(['username' => $username]);

        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    public static function updateDetails(
        string $username,
        string $displayName,
        string $phone,
        string $accessType,
        string $paymentStatus,
        string $notes,
        ?int $routerId = null
    ): void {
        $stmt = self::db()->prepare("
            UPDATE customers_local
            SET display_name = :display_name,
                phone = :phone,
                access_type = :access_type,
                payment_status = :payment_status,
                router_id = :router_id,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE username = :username
        ");

        $stmt->execute([
            'username' => $username,
            'display_name' => $displayName,
            'phone' => $phone,
            'access_type' => $accessType,
            'payment_status' => $paymentStatus,
            'router_id' => $routerId,
            'notes' => $notes,
        ]);
    }

    public static function updatePackage(string $username, int $packageId): void
    {
        $stmt = self::db()->prepare("
            UPDATE customers_local
            SET package_id = :package_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE username = :username
        ");

        $stmt->execute([
            'username' => $username,
            'package_id' => $packageId,
        ]);
    }

    public static function deleteByUsername(string $username): void
    {
        $stmt = self::db()->prepare("
            DELETE FROM customers_local
            WHERE username = :username
        ");

        $stmt->execute([
            'username' => $username,
        ]);
    }

    public static function byPaymentStatus(string $status): array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM customers_local
            WHERE payment_status = :status
            ORDER BY created_at DESC
        ");

        $stmt->execute(['status' => $status]);

        return $stmt->fetchAll();
    }

    public static function unpaid(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM customers_local
            WHERE payment_status IN ('due', 'pending', 'unknown')
            ORDER BY
                CASE payment_status
                    WHEN 'due' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'unknown' THEN 3
                    ELSE 4
                END,
                created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public static function updatePaymentStatus(string $username, string $status): void
    {
        $stmt = self::db()->prepare("
            UPDATE customers_local
            SET payment_status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE username = :username
        ");

        $stmt->execute([
            'username' => $username,
            'status' => $status,
        ]);
    }

    public static function count(): int
    {
        return (int) self::db()->query("SELECT COUNT(*) FROM customers_local")->fetchColumn();
    }

    public static function countByPaymentStatus(string $status): int
    {
        $stmt = self::db()->prepare("
            SELECT COUNT(*)
            FROM customers_local
            WHERE payment_status = :status
        ");

        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    public static function unpaidCount(): int
    {
        return (int) self::db()->query("
            SELECT COUNT(*)
            FROM customers_local
            WHERE payment_status IN ('due', 'pending', 'unknown')
        ")->fetchColumn();
    }
}
