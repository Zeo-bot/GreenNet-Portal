<?php

declare(strict_types=1);

namespace GreenNet\Models;

class Setting extends Model
{
    public static function all(): array
    {
        return self::allKeyValue();
    }

    public static function allRows(): array
    {
        $stmt = self::db()->query("
            SELECT *
            FROM settings
            ORDER BY setting_key ASC
        ");

        return $stmt->fetchAll();
    }

    public static function allKeyValue(): array
    {
        $stmt = self::db()->query("
            SELECT setting_key, setting_value
            FROM settings
            ORDER BY setting_key ASC
        ");

        $rows = $stmt->fetchAll();

        $settings = [];

        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }

        return $settings;
    }

    public static function get(string $key, string $default = ''): string
    {
        $stmt = self::db()->prepare("
            SELECT setting_value
            FROM settings
            WHERE setting_key = :key
            LIMIT 1
        ");

        $stmt->execute([
            'key' => $key,
        ]);

        $value = $stmt->fetchColumn();

        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = self::db()->prepare("
            INSERT INTO settings (setting_key, setting_value, created_at, updated_at)
            VALUES (:key, :value, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(setting_key)
            DO UPDATE SET
                setting_value = excluded.setting_value,
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            'key' => $key,
            'value' => $value,
        ]);
    }

    public static function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::set((string) $key, (string) $value);
        }
    }

    public static function setIfMissing(string $key, string $value): void
    {
        $stmt = self::db()->prepare("
            SELECT COUNT(*)
            FROM settings
            WHERE setting_key = :key
        ");

        $stmt->execute([
            'key' => $key,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            self::set($key, $value);
        }
    }

    public static function seedDefaults(array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            self::setIfMissing((string) $key, (string) $value);
        }
    }

    public static function count(): int
    {
        $stmt = self::db()->query("
            SELECT COUNT(*)
            FROM settings
        ");

        return (int) $stmt->fetchColumn();
    }
}