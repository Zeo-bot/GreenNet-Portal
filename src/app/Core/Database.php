<?php

declare(strict_types=1);

namespace GreenNet\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function hasConnection(): bool
    {
        return self::$connection !== null;
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }

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
        self::ensureColumn('customers_local', 'router_id', 'INTEGER DEFAULT NULL');
        self::ensureColumn('customers_local', 'service_backend', "TEXT DEFAULT 'user-manager'");
        self::ensureColumn('customers_local', 'service_status', "TEXT DEFAULT 'active'");

        $db->exec("
            CREATE TABLE IF NOT EXISTS routers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                host TEXT NOT NULL,
                api_port INTEGER DEFAULT 8728,
                username TEXT DEFAULT '',
                password TEXT DEFAULT '',
                enabled INTEGER DEFAULT 1,
                is_default INTEGER DEFAULT 0,
                access_mode TEXT DEFAULT 'hybrid',
                auth_backend TEXT DEFAULT 'user-manager',
                identity TEXT DEFAULT '',
                routeros_version TEXT DEFAULT '',
                last_status TEXT DEFAULT 'unknown',
                last_error TEXT DEFAULT '',
                last_checked_at TEXT,
                last_seen_at TEXT,
                location TEXT DEFAULT '',
                notes TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_routers_default
            ON routers (is_default)
            WHERE is_default = 1
        ");
        self::ensureColumn('routers', 'onboarding_mode', "TEXT DEFAULT 'existing'");
        self::ensureColumn('routers', 'onboarding_status', "TEXT DEFAULT 'registered'");
        self::ensureColumn('routers', 'selected_roles', "TEXT DEFAULT '[]'");
        self::ensureColumn('routers', 'capabilities_json', "TEXT DEFAULT '{}'");
        self::ensureColumn('routers', 'capabilities_checked_at', 'TEXT');

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
            CREATE TABLE IF NOT EXISTS router_package_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER NOT NULL,
                package_id INTEGER NOT NULL,
                profile_name TEXT NOT NULL,
                profile_id TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(router_id, package_id)
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS router_backend_package_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER NOT NULL,
                package_id INTEGER NOT NULL,
                backend TEXT NOT NULL,
                profile_name TEXT NOT NULL,
                profile_id TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(router_id, package_id, backend)
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS subscriber_router_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                username TEXT NOT NULL,
                source_router_id INTEGER NOT NULL,
                source_backend TEXT NOT NULL,
                target_router_id INTEGER NOT NULL,
                target_backend TEXT NOT NULL,
                package_id INTEGER NOT NULL,
                target_profile_name TEXT NOT NULL,
                target_record_id TEXT DEFAULT '',
                source_record_id TEXT DEFAULT '',
                status TEXT NOT NULL DEFAULT 'ready',
                source_cleanup_action TEXT DEFAULT 'leave',
                source_cleanup_state TEXT DEFAULT 'not_requested',
                usage_decision TEXT DEFAULT '',
                usage_snapshot_json TEXT DEFAULT '{}',
                failure_reason TEXT DEFAULT '',
                started_at TEXT DEFAULT CURRENT_TIMESTAMP,
                completed_at TEXT,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $db->exec("
            CREATE INDEX IF NOT EXISTS idx_subscriber_router_migrations_customer
            ON subscriber_router_migrations(customer_id, id DESC)
        ");
        $db->exec("
            INSERT OR IGNORE INTO router_backend_package_profiles
                (router_id, package_id, backend, profile_name, profile_id, created_at, updated_at)
            SELECT router_id, package_id, 'user-manager', profile_name, profile_id, created_at, updated_at
            FROM router_package_profiles
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
