<?php

declare(strict_types=1);

namespace GreenNet\Models;

class AppLog extends Model
{
    public static function create(string $level, string $message, array $context = []): void
    {
        $stmt = self::db()->prepare("
            INSERT INTO app_logs (level, message, context)
            VALUES (:level, :message, :context)
        ");

        $stmt->execute([
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public static function info(string $message, array $context = []): void
    {
        self::create('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::create('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::create('error', $message, $context);
    }

    public static function latest(int $limit = 100): array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM app_logs
            ORDER BY created_at DESC, id DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function count(): int
    {
        return (int) self::db()->query("
            SELECT COUNT(*)
            FROM app_logs
        ")->fetchColumn();
    }
}