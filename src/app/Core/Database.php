<?php

declare(strict_types=1);

namespace GreenNet\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $databasePath = Config::get('DB_DATABASE', '/var/www/database/database.sqlite');

        $directory = dirname($databasePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (!file_exists($databasePath)) {
            touch($databasePath);
        }

        try {
            self::$connection = new PDO('sqlite:' . $databasePath);
            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return self::$connection;
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function migrate(): void
    {
        $db = self::connection();

        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                name TEXT DEFAULT 'Administrator',
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS customers_local (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                display_name TEXT,
                phone TEXT,
                access_type TEXT DEFAULT 'hybrid',
                payment_status TEXT DEFAULT 'unknown',
                package_id INTEGER DEFAULT 0,
                notes TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        self::ensureColumn('customers_local', 'package_id', 'INTEGER DEFAULT 0');

        $db->exec("
            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                amount INTEGER DEFAULT 0,
                currency TEXT DEFAULT 'SYP',
                status TEXT DEFAULT 'paid',
                note TEXT,
                package_id INTEGER DEFAULT 0,
                package_name TEXT DEFAULT '',
                duration_days INTEGER DEFAULT 0,
                quota_gb REAL DEFAULT 0,
                starts_at TEXT,
                expires_at TEXT,
                paid_at TEXT DEFAULT CURRENT_TIMESTAMP,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        self::ensureColumn('payments', 'package_id', 'INTEGER DEFAULT 0');
        self::ensureColumn('payments', 'package_name', "TEXT DEFAULT ''");
        self::ensureColumn('payments', 'duration_days', 'INTEGER DEFAULT 0');
        self::ensureColumn('payments', 'quota_gb', 'REAL DEFAULT 0');
        self::ensureColumn('payments', 'starts_at', 'TEXT');
        self::ensureColumn('payments', 'expires_at', 'TEXT');

        $db->exec("
            CREATE TABLE IF NOT EXISTS announcements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                starts_at TEXT,
                ends_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                type TEXT DEFAULT 'info',
                is_read INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS qos_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                mode TEXT DEFAULT 'smart',
                priority INTEGER DEFAULT 5,
                description TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS service_packages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                source_type TEXT DEFAULT 'manual',
                source_profile TEXT DEFAULT '',
                access_type TEXT DEFAULT 'hybrid',
                rate_limit TEXT DEFAULT '-',
                duration_days INTEGER DEFAULT 0,
                quota_gb REAL DEFAULT 0,
                price INTEGER DEFAULT 0,
                currency TEXT DEFAULT 'SYP',
                is_active INTEGER DEFAULT 1,
                notes TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_service_packages_source
            ON service_packages (source_type, source_profile);
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS app_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT DEFAULT 'info',
                message TEXT NOT NULL,
                context TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        self::seedDefaults();
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        $db = self::connection();

        $stmt = $db->query("PRAGMA table_info({$table})");
        $columns = $stmt->fetchAll();

        foreach ($columns as $existingColumn) {
            if (($existingColumn['name'] ?? '') === $column) {
                return;
            }
        }

        $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    private static function seedDefaults(): void
    {
        $db = self::connection();

        $adminUsername = Config::get('ADMIN_USERNAME', 'admin');
        $adminPassword = Config::get('ADMIN_PASSWORD', 'ChangeThisPassword');

        $stmt = $db->prepare("SELECT COUNT(*) FROM admins WHERE username = :username");
        $stmt->execute(['username' => $adminUsername]);

        if ((int) $stmt->fetchColumn() === 0) {
            $insert = $db->prepare("
                INSERT INTO admins (username, password_hash, name)
                VALUES (:username, :password_hash, :name)
            ");

            $insert->execute([
                'username' => $adminUsername,
                'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
                'name' => 'GreenNet Admin'
            ]);
        }

        $defaults = [
            'app_name' => Config::appName(),
            'support_phone' => Config::supportPhone(),
            'access_mode' => Config::accessMode(),
            'auth_backend' => Config::authBackend(),
            'qos_enabled' => Config::qosEnabled() ? 'true' : 'false',
        ];

        foreach ($defaults as $key => $value) {
            $stmt = $db->prepare("
                INSERT OR IGNORE INTO settings (setting_key, setting_value)
                VALUES (:key, :value)
            ");

            $stmt->execute([
                'key' => $key,
                'value' => (string) $value
            ]);
        }

        $qosProfiles = [
            ['normal', 'smart', 5, 'الوضع العادي للشبكة'],
            ['calls', 'smart', 3, 'رفع أولوية المكالمات والتصفح'],
            ['stability', 'smart', 7, 'وضع الاستقرار وقت الضغط العالي'],
            ['limited', 'smart', 8, 'أولوية منخفضة للمشتركين المحدودين أو المتأخرين بالدفع'],
            ['vip', 'smart', 2, 'أولوية عالية للمشتركين المميزين'],
        ];

        foreach ($qosProfiles as [$name, $mode, $priority, $description]) {
            $stmt = $db->prepare("
                INSERT OR IGNORE INTO qos_profiles (name, mode, priority, description)
                VALUES (:name, :mode, :priority, :description)
            ");

            $stmt->execute([
                'name' => $name,
                'mode' => $mode,
                'priority' => $priority,
                'description' => $description,
            ]);
        }
    }
}