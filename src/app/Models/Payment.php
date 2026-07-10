<?php

declare(strict_types=1);

namespace GreenNet\Models;

class Payment extends Model
{
    public static function latest(int $limit = 10): array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM payments
            ORDER BY paid_at DESC, id DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function forUser(string $username): array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM payments
            WHERE username = :username
            ORDER BY paid_at DESC, id DESC
        ");

        $stmt->execute([
            'username' => $username,
        ]);

        return $stmt->fetchAll();
    }

    public static function latestRenewalForUser(string $username): ?array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM payments
            WHERE username = :username
              AND status = 'paid'
              AND package_id > 0
            ORDER BY paid_at DESC, id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username,
        ]);

        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    public static function create(
        string $username,
        int $amount,
        string $currency = 'SYP',
        string $status = 'paid',
        string $note = '',
        int $packageId = 0,
        string $packageName = '',
        int $durationDays = 0,
        float $quotaGb = 0,
        ?string $startsAt = null,
        ?string $expiresAt = null
    ): void {
        $stmt = self::db()->prepare("
            INSERT INTO payments
            (
                username,
                amount,
                currency,
                status,
                note,
                package_id,
                package_name,
                duration_days,
                quota_gb,
                starts_at,
                expires_at,
                paid_at
            )
            VALUES
            (
                :username,
                :amount,
                :currency,
                :status,
                :note,
                :package_id,
                :package_name,
                :duration_days,
                :quota_gb,
                :starts_at,
                :expires_at,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            'username' => $username,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'note' => $note,
            'package_id' => $packageId,
            'package_name' => $packageName,
            'duration_days' => $durationDays,
            'quota_gb' => $quotaGb,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    public static function totalPaid(): int
    {
        $stmt = self::db()->query("
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE status = 'paid'
        ");

        return (int) $stmt->fetchColumn();
    }
}