<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Contracts\RouterOSReadGatewayInterface;
use GreenNet\Core\Database;
use GreenNet\Services\RouterOS\RouterOSReadGatewayFactory;
use PDO;
use Throwable;

final class UnifiedSubscriberService
{
    public function __construct(
        private ?PDO $database = null,
        private ?RouterOSReadGatewayInterface $readGateway = null,
        private readonly ?int $now = null
    ) {
    }

    public function summary(string $username): array
    {
        $customer = $this->customer($username);
        if ($customer === []) {
            return [];
        }

        $package = $this->package($customer);
        $payment = $this->latestPayment($username);
        $router = $this->routerState($username, (string) ($customer['access_type'] ?? 'hybrid'));
        $expiresAt = $this->firstDate([
            $payment['expires_at'] ?? null,
            $customer['expires_at'] ?? null,
        ]);
        $startsAt = $this->firstDate([
            $payment['starts_at'] ?? null,
            $payment['paid_at'] ?? null,
            $customer['created_at'] ?? null,
        ]);
        $quotaBytes = $this->quotaBytes($package['quota_gb'] ?? null);
        $totalUsage = $this->nullableSum($router['download_bytes'], $router['upload_bytes']);
        $remainingQuota = $quotaBytes !== null && $totalUsage !== null
            ? max(0, $quotaBytes - $totalUsage)
            : null;
        $remainingDays = $this->remainingDays($expiresAt);
        $enabled = $router['enabled'];
        $status = $this->accountStatus($enabled, $expiresAt);
        if (in_array(strtolower((string) ($customer['service_status'] ?? '')), ['suspended', 'disabled'], true)) {
            $status = 'suspended';
        } elseif ($quotaBytes !== null && $totalUsage !== null && $totalUsage >= $quotaBytes) {
            $status = 'quota_exhausted';
        }

        return [
            'portal_customer_id' => isset($customer['id']) ? (int) $customer['id'] : null,
            'router_username' => (string) ($customer['username'] ?? $username),
            'display_name' => (string) ($customer['full_name'] ?? $customer['display_name'] ?? $username),
            'phone' => $this->nullableString($customer['phone'] ?? null),
            'access_type' => $router['access_type'],
            'router_backend' => $router['backend'],
            'status' => $status,
            'enabled' => $enabled,
            'online' => $router['online'],
            'package' => [
                'id' => isset($package['id']) ? (int) $package['id'] : null,
                'name' => $this->nullableString($package['name'] ?? null),
                'profile' => $this->nullableString($package['source_profile'] ?? $router['profile']),
                'speed' => $this->nullableString($package['rate_limit'] ?? null),
                'quota_bytes' => $quotaBytes,
                'duration_days' => isset($package['duration_days']) ? (int) $package['duration_days'] : null,
                'price' => isset($package['price']) ? (float) $package['price'] : null,
                'currency' => $this->nullableString($package['currency'] ?? null),
            ],
            'start_date' => $startsAt,
            'expiration_date' => $expiresAt,
            'remaining_days' => $remainingDays,
            'usage' => [
                'download_bytes' => $router['download_bytes'],
                'upload_bytes' => $router['upload_bytes'],
                'total_bytes' => $totalUsage,
                'remaining_quota_bytes' => $remainingQuota,
            ],
            'active_session' => $router['session'],
            'latest_renewal' => $this->latestRenewal($username),
            'latest_payment' => $this->paymentProjection($payment),
        ];
    }

    public function payments(string $username, int $limit = 50): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT id, package_name, amount, currency, status, paid_at, created_at, starts_at, expires_at
             FROM payments WHERE lower(username) = lower(:username)
             ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue(':username', $username);
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): array => $this->paymentProjection($row), $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function notifications(string $username): array
    {
        try {
            $statement = $this->pdo()->prepare(
                "SELECT id, title, body, type, is_read, created_at
                 FROM notifications
                 WHERE username IS NULL OR trim(username) = '' OR lower(username) = lower(:username)
                 ORDER BY id DESC LIMIT 50"
            );
            $statement->execute(['username' => $username]);
            return array_map(static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
                'type' => (string) ($row['type'] ?? 'info'),
                'read' => (bool) ($row['is_read'] ?? false),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ], $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable) {
            return [];
        }
    }

    public function renewalHistory(string $username): array
    {
        $this->ensureRenewalTable();
        $statement = $this->pdo()->prepare(
            'SELECT id, package_id, package_name, status, admin_note, created_at, updated_at
             FROM renewal_requests WHERE lower(username) = lower(:username)
             ORDER BY id DESC LIMIT 50'
        );
        $statement->execute(['username' => $username]);

        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'package_id' => isset($row['package_id']) ? (int) $row['package_id'] : null,
            'package_name' => (string) ($row['package_name'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'admin_note' => (string) ($row['admin_note'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ], $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function createRenewal(string $username, string $phone, string $message): array
    {
        $this->ensureRenewalTable();
        $pending = $this->pdo()->prepare(
            "SELECT id FROM renewal_requests
             WHERE lower(username) = lower(:username) AND status = 'pending'
             ORDER BY id DESC LIMIT 1"
        );
        $pending->execute(['username' => $username]);
        $existing = $pending->fetchColumn();
        if ($existing !== false) {
            return ['created' => false, 'duplicate' => true, 'id' => (int) $existing];
        }

        $summary = $this->summary($username);
        if ($summary === []) {
            return ['created' => false, 'duplicate' => false, 'id' => null];
        }

        $statement = $this->pdo()->prepare(
            "INSERT INTO renewal_requests
             (username, full_name, phone, package_id, package_name, message, status, admin_note, created_at, updated_at)
             VALUES (:username, :full_name, :phone, :package_id, :package_name, :message, 'pending', '', :created_at, :updated_at)"
        );
        $now = date('Y-m-d H:i:s', $this->clock());
        $statement->execute([
            'username' => $username,
            'full_name' => (string) ($summary['display_name'] ?? $username),
            'phone' => $phone,
            'package_id' => $summary['package']['id'],
            'package_name' => (string) ($summary['package']['name'] ?? ''),
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['created' => true, 'duplicate' => false, 'id' => (int) $this->pdo()->lastInsertId()];
    }

    public function support(): array
    {
        $settings = SiteSettingsService::all();
        $phone = preg_replace('/[^0-9+]/', '', (string) ($settings['support_phone'] ?? '')) ?? '';
        $whatsapp = preg_replace('/\D+/', '', (string) ($settings['support_whatsapp'] ?? '')) ?? '';

        return [
            'phone' => $phone !== '' ? $phone : null,
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'hours' => $this->nullableString($settings['support_hours'] ?? null),
        ];
    }

    private function routerState(string $username, string $preferredAccess): array
    {
        $state = [
            'access_type' => $this->normalizeAccessType($preferredAccess),
            'backend' => 'unknown',
            'enabled' => null,
            'online' => false,
            'profile' => null,
            'download_bytes' => null,
            'upload_bytes' => null,
            'session' => null,
        ];

        try {
            $hotspotUsers = $this->exactRows('/ip/hotspot/user/print', ['?name' => $username], $username);
            $pppSecrets = $this->exactRows('/ppp/secret/print', ['?name' => $username], $username);
            $umUsers = $this->exactRows('/user-manager/user/print', ['?name' => $username], $username);
            $hotspotActive = $this->exactRows('/ip/hotspot/active/print', ['?user' => $username], $username);
            $pppActive = $this->exactRows('/ppp/active/print', ['?name' => $username], $username);

            $account = $hotspotUsers[0] ?? $pppSecrets[0] ?? $umUsers[0] ?? null;
            if (is_array($account)) {
                $state['backend'] = isset($hotspotUsers[0]) ? 'hotspot' : (isset($pppSecrets[0]) ? 'pppoe' : 'user-manager');
                $state['access_type'] = $state['backend'] === 'pppoe' ? 'pppoe' : ($state['backend'] === 'hotspot' ? 'hotspot' : $state['access_type']);
                $state['enabled'] = $this->enabled($account['disabled'] ?? null);
                $state['profile'] = $this->nullableString($account['profile'] ?? $account['actual-profile'] ?? null);
            }

            $session = $hotspotActive[0] ?? $pppActive[0] ?? null;
            if (is_array($session)) {
                $source = isset($hotspotActive[0]) ? 'hotspot' : 'pppoe';
                $state['online'] = true;
                $state['access_type'] = $source;
                $state['backend'] = $source;
                $state['download_bytes'] = $this->bytes($session['bytes-out'] ?? null);
                $state['upload_bytes'] = $this->bytes($session['bytes-in'] ?? null);
                $state['session'] = [
                    'source' => $source,
                    'id' => $this->nullableString($session['.id'] ?? null),
                    'address' => $this->nullableString($session['address'] ?? $session['remote-address'] ?? null),
                    'uptime' => $this->nullableString($session['uptime'] ?? null),
                ];
            } elseif (isset($umUsers[0]['.id'])) {
                $monitor = $this->rows($this->read()->read('/user-manager/user/monitor', [
                    'numbers' => (string) $umUsers[0]['.id'],
                    'once' => '',
                ]));
                if (isset($monitor[0])) {
                    $state['download_bytes'] = $this->bytes($monitor[0]['download-used'] ?? $monitor[0]['download'] ?? null);
                    $state['upload_bytes'] = $this->bytes($monitor[0]['upload-used'] ?? $monitor[0]['upload'] ?? null);
                }
            }
        } catch (Throwable) {
            // Router state remains explicitly unknown/offline; no raw error is exposed.
        }

        return $state;
    }

    private function exactRows(string $command, array $params, string $username): array
    {
        return array_values(array_filter($this->rows($this->read()->read($command, $params)), static function (array $row) use ($username): bool {
            foreach (['name', 'user', 'username', 'login'] as $key) {
                if (isset($row[$key]) && (string) $row[$key] === $username) {
                    return true;
                }
            }
            return false;
        }));
    }

    private function customer(string $username): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM customers_local WHERE lower(username) = lower(:username) LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    private function package(array $customer): array
    {
        $id = (int) ($customer['package_id'] ?? 0);
        if ($id <= 0) {
            return [];
        }
        $statement = $this->pdo()->prepare('SELECT * FROM service_packages WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    private function latestPayment(string $username): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT * FROM payments WHERE lower(username) = lower(:username) ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    private function latestRenewal(string $username): ?array
    {
        $history = $this->renewalHistory($username);
        return $history[0] ?? null;
    }

    private function paymentProjection(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'package_name' => $this->nullableString($row['package_name'] ?? null),
            'amount' => isset($row['amount']) ? (float) $row['amount'] : null,
            'currency' => $this->nullableString($row['currency'] ?? null),
            'status' => $this->nullableString($row['status'] ?? null),
            'paid_at' => $this->firstDate([$row['paid_at'] ?? null, $row['created_at'] ?? null]),
            'starts_at' => $this->firstDate([$row['starts_at'] ?? null]),
            'expires_at' => $this->firstDate([$row['expires_at'] ?? null]),
        ];
    }

    private function ensureRenewalTable(): void
    {
        $this->pdo()->exec(
            "CREATE TABLE IF NOT EXISTS renewal_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                full_name TEXT DEFAULT '',
                phone TEXT DEFAULT '',
                package_id INTEGER DEFAULT 0,
                package_name TEXT DEFAULT '',
                message TEXT DEFAULT '',
                status TEXT DEFAULT 'pending',
                admin_note TEXT DEFAULT '',
                created_at TEXT,
                updated_at TEXT
            )"
        );
    }

    private function pdo(): PDO
    {
        return $this->database ??= Database::connection();
    }

    private function read(): RouterOSReadGatewayInterface
    {
        return $this->readGateway ??= RouterOSReadGatewayFactory::create(['timeout' => 5]);
    }

    private function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['user'])) {
            return [$rows];
        }
        return array_values(array_filter($rows, 'is_array'));
    }

    private function enabled(mixed $disabled): ?bool
    {
        if ($disabled === null || $disabled === '') {
            return null;
        }
        return !in_array(strtolower((string) $disabled), ['yes', 'true', '1'], true);
    }

    private function accountStatus(?bool $enabled, ?string $expiresAt): string
    {
        if ($enabled === false) {
            return 'disabled';
        }
        if ($expiresAt !== null && strtotime($expiresAt) !== false && strtotime($expiresAt) < $this->clock()) {
            return 'expired';
        }
        return $enabled === true ? 'enabled' : 'unknown';
    }

    private function normalizeAccessType(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'ppp', 'pppoe' => 'pppoe',
            'hotspot' => 'hotspot',
            default => 'unknown',
        };
    }

    private function bytes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function quotaBytes(mixed $quotaGb): ?int
    {
        if ($quotaGb === null || $quotaGb === '') {
            return null;
        }
        $quota = (float) $quotaGb;
        return $quota > 0 ? (int) round($quota * 1024 ** 3) : null;
    }

    private function nullableSum(?int $a, ?int $b): ?int
    {
        return $a === null && $b === null ? null : ($a ?? 0) + ($b ?? 0);
    }

    private function remainingDays(?string $expiresAt): ?int
    {
        if ($expiresAt === null || strtotime($expiresAt) === false) {
            return null;
        }
        return max(0, (int) floor((strtotime($expiresAt) - $this->clock()) / 86400));
    }

    private function firstDate(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && strtotime($value) !== false) {
                return $value;
            }
        }
        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function clock(): int
    {
        return $this->now ?? time();
    }
}
