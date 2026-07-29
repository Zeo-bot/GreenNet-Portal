<?php

declare(strict_types=1);

define('BASE_PATH', '/var/www');

spl_autoload_register(static function (string $class): void {
    $prefix = 'GreenNet\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use GreenNet\Core\Config;
use GreenNet\Core\Database;

Config::load(BASE_PATH . '/.env');
Database::migrate();
$pdo = Database::connection();

foreach ([
    'subscriber_password_hash' => "TEXT DEFAULT ''",
    'password_changed_at' => "TEXT DEFAULT ''",
    'last_login_at' => "TEXT DEFAULT ''",
    'failed_login_attempts' => 'INTEGER DEFAULT 0',
    'locked_until' => "TEXT DEFAULT ''",
    'must_change_password' => 'INTEGER DEFAULT 0',
] as $column => $definition) {
    $columns = $pdo->query('PRAGMA table_info(customers_local)')->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array($column, array_column($columns, 'name'), true)) {
        $pdo->exec("ALTER TABLE customers_local ADD COLUMN {$column} {$definition}");
    }
}

$pdo->exec("DELETE FROM notifications;
    DELETE FROM payments;
    DELETE FROM customers_local;
    DELETE FROM service_packages;");

$pdo->exec("INSERT INTO service_packages
    (id, name, source_type, source_profile, access_type, rate_limit, duration_days, quota_gb, price, currency)
    VALUES
    (901, 'Smoke Package A', 'manual', 'smoke-a', 'hotspot', '10M/10M', 30, 10, 100, 'SYP'),
    (902, 'Smoke Package B', 'manual', 'smoke-b', 'ppp', '20M/20M', 30, 20, 200, 'SYP')");

$customer = $pdo->prepare("INSERT INTO customers_local
    (username, display_name, phone, access_type, package_id, subscriber_password_hash, created_at)
    VALUES (:username, :display_name, :phone, :access_type, :package_id, :password_hash, '2026-07-01')");
$customer->execute([
    'username' => 'smoke-user-a',
    'display_name' => 'Synthetic Smoke Subscriber A',
    'phone' => '0000000001',
    'access_type' => 'hotspot',
    'package_id' => 901,
    'password_hash' => password_hash('SmokePass-A-2026!', PASSWORD_DEFAULT),
]);
$customer->execute([
    'username' => 'smoke-user-b',
    'display_name' => 'Synthetic Smoke Subscriber B',
    'phone' => '0000000002',
    'access_type' => 'pppoe',
    'package_id' => 902,
    'password_hash' => password_hash('SmokePass-B-2026!', PASSWORD_DEFAULT),
]);

$pdo->exec("INSERT INTO payments
    (username, amount, currency, status, package_id, package_name, starts_at, expires_at, paid_at)
    VALUES
    ('smoke-user-a', 100, 'SYP', 'paid', 901, 'Smoke Package A', '2026-07-01', '2026-08-31', '2026-07-01'),
    ('smoke-user-b', 200, 'SYP', 'paid', 902, 'Smoke Package B', '2026-07-01', '2026-08-31', '2026-07-01')");

$pdo->exec("INSERT INTO notifications (username, title, body, type, is_read, created_at) VALUES
    (NULL, 'Synthetic General Notice', 'Visible to smoke subscribers', 'info', 0, '2026-07-01'),
    ('smoke-user-a', 'Synthetic A Notice', 'Owned only by smoke-user-a', 'info', 0, '2026-07-02'),
    ('smoke-user-b', 'Synthetic B Notice', 'Owned only by smoke-user-b', 'info', 0, '2026-07-03')");

