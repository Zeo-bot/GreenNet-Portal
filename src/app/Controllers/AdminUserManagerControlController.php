<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Contracts\RouterOSReadGatewayInterface;
use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Services\RouterOS\RouterOSReadGatewayFactory;
use PDO;
use Throwable;

class AdminUserManagerControlController
{
    public function __construct(private ?RouterOSReadGatewayInterface $readGateway = null)
    {
    }

    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $username = trim((string) ($_GET['username'] ?? ''));

        return View::render('admin/user_manager_control', [
            'title' => 'User Manager Control Center',
            'customers' => $this->localCustomers(),
            'selected_username' => $username,
            'snapshot' => $username !== '' ? $this->snapshot($username) : null,
        ]);
    }

    private function snapshot(string $username): array
    {
        $this->validateUsername($username);

        $customer = $this->findCustomer($username);
        $package = null;

        if ($customer !== null) {
            $packageId = (int) ($customer['package_id'] ?? 0);

            if ($packageId > 0) {
                $package = $this->findPackage($packageId);
            }
        }

        $router = $this->readRouterState($username);

        return [
            'ok' => true,
            'username' => $username,
            'customer' => $customer !== null ? $this->sanitizeCustomer($customer) : null,
            'package' => $package !== null ? $this->sanitizePackage($package) : null,
            'router' => $router,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function readRouterState(string $username): array
    {
        $result = [
            'user' => [
                'found' => false,
                'id' => '',
                'row' => [],
                'disabled' => '',
                'error' => '',
            ],
            'monitor' => [
                'found' => false,
                'row' => [],
                'error' => '',
            ],
            'user_profiles' => [
                'rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
            'hotspot_active' => [
                'rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
            'ppp_active' => [
                'rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
            'user_manager_sessions' => [
                'rows' => [],
                'rows_count' => 0,
                'error' => '',
            ],
        ];

        try {
                $users = $this->normalizeRows($this->readGateway()->read('/user-manager/user/print', [
                    '?name' => $username,
                ]));

                $user = $this->findUserRow($users, $username);

                if ($user !== null) {
                    $result['user'] = [
                        'found' => true,
                        'id' => (string) ($user['.id'] ?? ''),
                        'row' => $this->sanitizeRowForDisplay($user),
                        'disabled' => (string) ($user['disabled'] ?? ''),
                        'error' => '',
                    ];
                }
        } catch (Throwable $e) {
            $result['user']['error'] = $e->getMessage();
        }

        try {
                $userId = (string) ($result['user']['id'] ?? '');

                if ($userId !== '') {
                    $monitor = $this->normalizeRows($this->readGateway()->read('/user-manager/user/monitor', [
                        'numbers' => $userId,
                        'once' => '',
                    ]));

                    if (!empty($monitor[0]) && is_array($monitor[0])) {
                        $result['monitor'] = [
                            'found' => true,
                            'row' => $this->sanitizeRowForDisplay($monitor[0]),
                            'error' => '',
                        ];
                    }
                }
        } catch (Throwable $e) {
            $result['monitor']['error'] = $e->getMessage();
        }

        try {
                $profiles = $this->normalizeRows($this->readGateway()->read('/user-manager/user-profile/print', [
                    '?user' => $username,
                ]));

                $profiles = $this->filterRowsForUsername($profiles, $username);

                $result['user_profiles'] = [
                    'rows' => $this->sanitizeRows($profiles),
                    'rows_count' => count($profiles),
                    'error' => '',
                ];
        } catch (Throwable $e) {
            $result['user_profiles']['error'] = $e->getMessage();
        }

        try {
                $hotspot = $this->normalizeRows($this->readGateway()->read('/ip/hotspot/active/print', [
                    '?user' => $username,
                ]));

                $hotspot = $this->filterRowsForUsername($hotspot, $username);

                $result['hotspot_active'] = [
                    'rows' => $this->sanitizeRows($hotspot),
                    'rows_count' => count($hotspot),
                    'error' => '',
                ];
        } catch (Throwable $e) {
            $result['hotspot_active']['error'] = $e->getMessage();
        }

        try {
                $ppp = $this->normalizeRows($this->readGateway()->read('/ppp/active/print', [
                    '?name' => $username,
                ]));

                $ppp = $this->filterRowsForUsername($ppp, $username);

                $result['ppp_active'] = [
                    'rows' => $this->sanitizeRows($ppp),
                    'rows_count' => count($ppp),
                    'error' => '',
                ];
        } catch (Throwable $e) {
            $result['ppp_active']['error'] = $e->getMessage();
        }

        try {
                $sessions = $this->normalizeRows($this->readGateway()->read('/user-manager/session/print', [
                    '?user' => $username,
                ]));

                $sessions = $this->filterRowsForUsername($sessions, $username);

                if (count($sessions) === 0) {
                    $allSessions = $this->normalizeRows($this->readGateway()->read('/user-manager/session/print'));
                    $sessions = $this->filterRowsForUsername($allSessions, $username);
                }

                $result['user_manager_sessions'] = [
                    'rows' => $this->sanitizeRows($sessions),
                    'rows_count' => count($sessions),
                    'error' => '',
                ];
        } catch (Throwable $e) {
            $result['user_manager_sessions']['error'] = $e->getMessage();
        }

        return $result;
    }

    private function readGateway(): RouterOSReadGatewayInterface
    {
        return $this->readGateway ??= RouterOSReadGatewayFactory::create(['timeout' => 6]);
    }

    private function localCustomers(): array
    {
        $this->ensureCustomersLocalColumns();

        try {
            $stmt = Database::connection()->query("
                SELECT *
                FROM customers_local
                ORDER BY username ASC
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function findCustomer(string $username): ?array
    {
        $this->ensureCustomersLocalColumns();

        $stmt = Database::connection()->prepare("
            SELECT *
            FROM customers_local
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findPackage(int $id): ?array
    {
        $this->ensureServicePackagesTable();

        $stmt = Database::connection()->prepare("
            SELECT *
            FROM service_packages
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function ensureCustomersLocalColumns(): void
    {
        $pdo = Database::connection();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS customers_local (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                full_name TEXT DEFAULT '',
                phone TEXT DEFAULT '',
                payment_status TEXT DEFAULT 'unknown',
                package_id INTEGER DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        $columns = $this->columns('customers_local');

        $required = [
            'full_name' => "ALTER TABLE customers_local ADD COLUMN full_name TEXT DEFAULT ''",
            'phone' => "ALTER TABLE customers_local ADD COLUMN phone TEXT DEFAULT ''",
            'payment_status' => "ALTER TABLE customers_local ADD COLUMN payment_status TEXT DEFAULT 'unknown'",
            'package_id' => "ALTER TABLE customers_local ADD COLUMN package_id INTEGER DEFAULT 0",
            'created_at' => "ALTER TABLE customers_local ADD COLUMN created_at TEXT",
            'updated_at' => "ALTER TABLE customers_local ADD COLUMN updated_at TEXT",
        ];

        foreach ($required as $column => $sql) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec($sql);
            }
        }
    }

    private function ensureServicePackagesTable(): void
    {
        $pdo = Database::connection();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_packages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                source_type TEXT DEFAULT 'local',
                source_profile TEXT DEFAULT '',
                access_type TEXT DEFAULT 'hotspot',
                rate_limit TEXT DEFAULT '',
                duration_days INTEGER DEFAULT 0,
                quota_gb REAL DEFAULT 0,
                price REAL DEFAULT 0,
                currency TEXT DEFAULT 'SYP',
                is_active INTEGER DEFAULT 1,
                notes TEXT DEFAULT '',
                created_at TEXT,
                updated_at TEXT
            )
        ");

        $columns = $this->columns('service_packages');

        $required = [
            'source_type' => "ALTER TABLE service_packages ADD COLUMN source_type TEXT DEFAULT 'local'",
            'source_profile' => "ALTER TABLE service_packages ADD COLUMN source_profile TEXT DEFAULT ''",
            'access_type' => "ALTER TABLE service_packages ADD COLUMN access_type TEXT DEFAULT 'hotspot'",
            'rate_limit' => "ALTER TABLE service_packages ADD COLUMN rate_limit TEXT DEFAULT ''",
            'duration_days' => "ALTER TABLE service_packages ADD COLUMN duration_days INTEGER DEFAULT 0",
            'quota_gb' => "ALTER TABLE service_packages ADD COLUMN quota_gb REAL DEFAULT 0",
            'price' => "ALTER TABLE service_packages ADD COLUMN price REAL DEFAULT 0",
            'currency' => "ALTER TABLE service_packages ADD COLUMN currency TEXT DEFAULT 'SYP'",
            'is_active' => "ALTER TABLE service_packages ADD COLUMN is_active INTEGER DEFAULT 1",
            'notes' => "ALTER TABLE service_packages ADD COLUMN notes TEXT DEFAULT ''",
            'created_at' => "ALTER TABLE service_packages ADD COLUMN created_at TEXT",
            'updated_at' => "ALTER TABLE service_packages ADD COLUMN updated_at TEXT",
        ];

        foreach ($required as $column => $sql) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec($sql);
            }
        }
    }

    private function columns(string $table): array
    {
        try {
            $rows = Database::connection()->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC);

            $columns = [];

            foreach ($rows as $row) {
                $name = (string) ($row['name'] ?? '');

                if ($name !== '') {
                    $columns[] = $name;
                }
            }

            return $columns;
        } catch (Throwable) {
            return [];
        }
    }

    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['user']) || isset($rows['profile']) || isset($rows['address'])) {
            return [$rows];
        }

        $out = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    private function filterRowsForUsername(array $rows, string $username): array
    {
        $usernameLower = strtolower(trim($username));
        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $candidates = [
                (string) ($row['user'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['username'] ?? ''),
                (string) ($row['user-name'] ?? ''),
                (string) ($row['customer'] ?? ''),
                (string) ($row['owner'] ?? ''),
            ];

            foreach ($candidates as $candidate) {
                if (strtolower(trim($candidate)) === $usernameLower) {
                    $out[] = $row;
                    break;
                }
            }
        }

        return $out;
    }

    private function findUserRow(array $rows, string $username): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ((string) ($row['name'] ?? '') === $username || (string) ($row['username'] ?? '') === $username) {
                return $row;
            }
        }

        if (count($rows) === 1 && is_array($rows[0] ?? null)) {
            return $rows[0];
        }

        return null;
    }

    private function sanitizeRows(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->sanitizeRowForDisplay($row);
            }
        }

        return $out;
    }

    private function sanitizeRowForDisplay(array $row): array
    {
        $hiddenKeys = [
            'password',
            'pass',
            'secret',
            'otp-secret',
            'token',
            'api-key',
            'key',
        ];

        $clean = [];

        foreach ($row as $key => $value) {
            $keyString = (string) $key;
            $lower = strtolower($keyString);

            $hide = false;

            foreach ($hiddenKeys as $hiddenKey) {
                if ($lower === $hiddenKey || str_contains($lower, $hiddenKey)) {
                    $hide = true;
                    break;
                }
            }

            $clean[$keyString] = $hide ? '<hidden>' : (string) $value;
        }

        return $clean;
    }

    private function sanitizeCustomer(array $customer): array
    {
        return [
            'id' => (int) ($customer['id'] ?? 0),
            'username' => (string) ($customer['username'] ?? ''),
            'full_name' => (string) ($customer['full_name'] ?? $customer['display_name'] ?? ''),
            'phone' => (string) ($customer['phone'] ?? ''),
            'payment_status' => (string) ($customer['payment_status'] ?? ''),
            'package_id' => (int) ($customer['package_id'] ?? 0),
            'created_at' => (string) ($customer['created_at'] ?? ''),
            'updated_at' => (string) ($customer['updated_at'] ?? ''),
        ];
    }

    private function sanitizePackage(array $package): array
    {
        return [
            'id' => (int) ($package['id'] ?? 0),
            'name' => (string) ($package['name'] ?? ''),
            'source_type' => (string) ($package['source_type'] ?? ''),
            'source_profile' => (string) ($package['source_profile'] ?? ''),
            'access_type' => (string) ($package['access_type'] ?? ''),
            'rate_limit' => (string) ($package['rate_limit'] ?? ''),
            'duration_days' => (int) ($package['duration_days'] ?? 0),
            'quota_gb' => (float) ($package['quota_gb'] ?? 0),
            'price' => (float) ($package['price'] ?? 0),
            'currency' => (string) ($package['currency'] ?? 'SYP'),
        ];
    }

    private function validateUsername(string $username): void
    {
        if (strlen($username) > 128) {
            throw new \RuntimeException('اسم المستخدم طويل جداً.');
        }

        if (preg_match('/[\r\n\t]/', $username)) {
            throw new \RuntimeException('اسم المستخدم يحتوي رموز غير مسموحة.');
        }
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}
