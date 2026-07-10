<?php

declare(strict_types=1);

namespace GreenNet\Models;

class Admin extends Model
{
    public static function count(): int
    {
        return (int) self::db()->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = self::db()->prepare("
            SELECT *
            FROM admins
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute(['username' => $username]);

        $admin = $stmt->fetch();

        return $admin ?: null;
    }

    public static function verifyPassword(string $username, string $password): bool
    {
        $admin = self::findByUsername($username);

        if (!$admin || (int) $admin['is_active'] !== 1) {
            return false;
        }

        return password_verify($password, $admin['password_hash']);
    }
}