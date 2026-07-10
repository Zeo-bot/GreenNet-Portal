<?php

declare(strict_types=1);

namespace GreenNet\Models;

class Announcement extends Model
{
    public static function active(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM announcements
            WHERE is_active = 1
            ORDER BY created_at DESC
            LIMIT 5
        ");

        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM announcements
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM announcements
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $announcement = $stmt->fetch();

        return $announcement ?: null;
    }

    public static function create(string $title, string $body, int $isActive = 1): void
    {
        $stmt = self::db()->prepare("
            INSERT INTO announcements (title, body, is_active)
            VALUES (:title, :body, :is_active)
        ");

        $stmt->execute([
            'title' => $title,
            'body' => $body,
            'is_active' => $isActive,
        ]);
    }

    public static function update(int $id, string $title, string $body, int $isActive): void
    {
        $stmt = self::db()->prepare("
            UPDATE announcements
            SET title = :title,
                body = :body,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'is_active' => $isActive,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = self::db()->prepare("
            DELETE FROM announcements
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
        ]);
    }

    public static function countActive(): int
    {
        return (int) self::db()->query("
            SELECT COUNT(*)
            FROM announcements
            WHERE is_active = 1
        ")->fetchColumn();
    }
}