<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\ServicePackage;
use GreenNet\Services\Access\AccessServiceFactory;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use PDO;
use Throwable;

class CustomerDashboardService
{
    public function getDashboardData(string $username): array
    {
        $username = trim($username);

        if ($username === '') {
            $username = 'guest';
        }

        $connectionData = $this->safeConnectionData($username);

        $customer = CustomerLocal::findByUsername($username);

        $package = null;

        if ($customer !== null) {
            $packageId = (int) ($customer['package_id'] ?? 0);

            if ($packageId > 0) {
                $package = ServicePackage::find($packageId);
            }
        }

        $latestRenewal = Payment::latestRenewalForUser($username);
        $renewalService = new CustomerRenewalService();
        $subscription = $renewalService->subscriptionStatus($latestRenewal);

        $data = array_merge($connectionData, [
            'username' => $username,

            'crm_found' => $customer !== null,
            'customer_display_name' => $customer['display_name'] ?? '',
            'customer_phone' => $customer['phone'] ?? '',
            'customer_access_type' => $customer['access_type'] ?? '',
            'payment_status' => $customer['payment_status'] ?? 'not_registered',
            'payment_label' => $this->paymentLabel($customer['payment_status'] ?? 'not_registered'),
            'customer_notes' => $customer['notes'] ?? '',

            'package_found' => $package !== null,
            'package_id' => $package['id'] ?? 0,
            'package_name' => $package['name'] ?? '',
            'package_price' => $package['price'] ?? 0,
            'package_currency' => $package['currency'] ?? 'SYP',
            'package_duration_days' => $package['duration_days'] ?? 0,
            'package_quota_gb' => $package['quota_gb'] ?? 0,
            'package_rate_limit' => $package['rate_limit'] ?? '-',
            'package_source_type' => $package['source_type'] ?? '',
            'package_source_profile' => $package['source_profile'] ?? '',
            'package_notes' => $package['notes'] ?? '',

            'latest_renewal' => $latestRenewal,
            'subscription' => $subscription,
            'subscription_found' => $latestRenewal !== null,
            'subscription_status' => $subscription['status'] ?? 'none',
            'subscription_label' => $subscription['label'] ?? 'لا يوجد تجديد',
            'subscription_days_left_label' => $subscription['days_left_label'] ?? 'غير محدد',
            'subscription_starts_at' => $latestRenewal['starts_at'] ?? '',
            'subscription_expires_at' => $latestRenewal['expires_at'] ?? '',

            /*
             * Baseline fields are always present so views do not break.
             */
            'usage_source' => (string) ($connectionData['usage_source'] ?? 'access_service'),
            'usage_backend' => (string) ($connectionData['usage_backend'] ?? ''),
            'greennet_baseline_enabled' => false,
            'greennet_baseline_found' => false,
            'greennet_baseline_id' => 0,
            'greennet_baseline_at' => '',
            'monitor_total' => '',
            'monitor_total_bytes' => 0,
            'monitor_download' => '',
            'monitor_download_bytes' => 0,
            'monitor_upload' => '',
            'monitor_upload_bytes' => 0,
            'monitor_uptime' => '',
            'monitor_uptime_seconds' => 0,
            'baseline_total' => '',
            'baseline_total_bytes' => 0,
            'used_since_baseline' => '',
            'used_since_baseline_bytes' => 0,
            'download_since_baseline' => '',
            'download_since_baseline_bytes' => 0,
            'upload_since_baseline' => '',
            'upload_since_baseline_bytes' => 0,
            'quota_bytes' => 0,
            'remaining_bytes' => 0,
            'counter_reset_detected' => false,
        ]);

        $quotaGb = 0.0;

        if ($package !== null) {
            $data['package'] = (string) ($package['name'] ?? 'باقة GreenNet');
            $data['speed'] = (string) ($package['rate_limit'] ?? '-');

            $quotaGb = (float) ($package['quota_gb'] ?? 0);

            if ($quotaGb > 0) {
                $data['package_quota_label'] = $this->formatQuota($quotaGb);
            } else {
                $data['package_quota_label'] = 'غير محدد';
            }

            if (($subscription['days_left_label'] ?? '') !== '') {
                $data['days_left'] = (string) $subscription['days_left_label'];
            } else {
                $data['days_left'] = $this->formatDuration((int) ($package['duration_days'] ?? 0));
            }

            if (($connectionData['routeros_found'] ?? false) !== true) {
                $data['used'] = '-';
                $data['remaining'] = $quotaGb > 0 ? $this->formatQuota($quotaGb) : '-';
                $data['used_percent'] = 0;
            }
        } else {
            $data['package_quota_label'] = 'غير محدد';
        }

        /*
         * S10.2H:
         * If this username is a User Manager user and Monitor is readable,
         * override the displayed usage with:
         *
         * Used = User Manager Monitor Total - Latest GreenNet Baseline
         *
         * This does NOT modify MikroTik.
         */
        $baselineUsage = $this->resolveUserManagerBaselineUsage($username, $quotaGb);

        $data['greennet_baseline_usage'] = $baselineUsage;

        if (!empty($baselineUsage['ok'])) {
            $this->applyBaselineUsageToDashboard($data, $baselineUsage);
        }

        $usageKnown = !empty($data['routeros_found']) && array_key_exists('used_bytes', $data);
        $lifecycle = (new SubscriptionLifecycleService())->evaluate(
            $username,
            $usageKnown ? (int) $data['used_bytes'] : null,
            false
        );
        $data['lifecycle'] = $lifecycle;
        $data['subscription_status'] = (string) ($lifecycle['state'] ?? $data['subscription_status']);
        $data['subscription_label'] = (string) ($lifecycle['label'] ?? $data['subscription_label']);

        return $data;
    }

    private function safeConnectionData(string $username): array
    {
        try {
            $customer = CustomerLocal::findByUsername($username);
            $accessService = AccessServiceFactory::make(
                RouterConnectionResolver::settingsForCustomer($username, ['timeout' => 4]),
                (string) ($customer['access_type'] ?? '')
            );
            $data = $accessService->getSubscriberStatus($username);

            return is_array($data) ? $data : $this->emptyConnectionData();
        } catch (Throwable $e) {
            return array_merge($this->emptyConnectionData(), [
                'routeros_error' => $e->getMessage(),
            ]);
        }
    }

    private function emptyConnectionData(): array
    {
        return [
            'routeros_found' => false,
            'connection_status' => 'unknown',
            'connection_label' => 'غير معروف',
            'used' => '-',
            'remaining' => '-',
            'used_percent' => 0,
            'speed' => '-',
            'package' => 'GreenNet',
        ];
    }

    private function resolveUserManagerBaselineUsage(string $username, float $quotaGb): array
    {
        $result = [
            'ok' => false,
            'reason' => '',
            'username' => $username,
            'backend' => 'user-manager',
            'source' => 'user_manager_monitor',
            'routeros_found' => false,

            'user_manager_id' => '',
            'monitor_ok' => false,
            'monitor_error' => '',
            'monitor_raw' => [],

            'monitor_total_bytes' => 0,
            'monitor_total_human' => '0 B',
            'monitor_download_bytes' => 0,
            'monitor_download_human' => '0 B',
            'monitor_upload_bytes' => 0,
            'monitor_upload_human' => '0 B',
            'monitor_uptime_seconds' => 0,
            'monitor_uptime_human' => '0s',
            'monitor_active_sessions' => 0,

            'baseline_found' => false,
            'baseline_id' => 0,
            'baseline_at' => '',
            'baseline_total_bytes' => 0,
            'baseline_total_human' => '0 B',
            'baseline_download_bytes' => 0,
            'baseline_download_human' => '0 B',
            'baseline_upload_bytes' => 0,
            'baseline_upload_human' => '0 B',
            'baseline_uptime_seconds' => 0,
            'baseline_uptime_human' => '0s',

            'used_since_baseline_bytes' => 0,
            'used_since_baseline_human' => '0 B',
            'download_since_baseline_bytes' => 0,
            'download_since_baseline_human' => '0 B',
            'upload_since_baseline_bytes' => 0,
            'upload_since_baseline_human' => '0 B',
            'uptime_since_baseline_seconds' => 0,
            'uptime_since_baseline_human' => '0s',

            'quota_gb' => $quotaGb,
            'quota_bytes' => $this->quotaGbToBytes($quotaGb),
            'quota_human' => $quotaGb > 0 ? $this->formatQuota($quotaGb) : 'غير محدد',
            'remaining_bytes' => 0,
            'remaining_human' => '-',
            'used_percent' => 0,

            'counter_reset_detected' => false,
            'formula' => 'used = max(0, monitor_total - baseline_total)',
        ];

        try {
            $this->ensureBaselineTables();

            $monitor = $this->readUserManagerMonitor($username);

            if (empty($monitor['ok'])) {
                $result['reason'] = 'monitor_failed';
                $result['monitor_error'] = (string) ($monitor['error'] ?? 'Monitor failed.');

                return $result;
            }

            $baseline = $this->latestBaseline($username);

            $monitorTotal = (int) ($monitor['total_bytes'] ?? 0);
            $monitorDownload = (int) ($monitor['total_download_bytes'] ?? 0);
            $monitorUpload = (int) ($monitor['total_upload_bytes'] ?? 0);
            $monitorUptime = (int) ($monitor['total_uptime_seconds'] ?? 0);

            $baselineFound = !empty($baseline);
            $baselineTotal = $baselineFound ? (int) ($baseline['baseline_total_bytes'] ?? 0) : 0;
            $baselineDownload = $baselineFound ? (int) ($baseline['baseline_download_bytes'] ?? 0) : 0;
            $baselineUpload = $baselineFound ? (int) ($baseline['baseline_upload_bytes'] ?? 0) : 0;
            $baselineUptime = $baselineFound ? (int) ($baseline['baseline_uptime_seconds'] ?? 0) : 0;

            $counterResetDetected = $baselineFound && $monitorTotal < $baselineTotal;

            $usedTotal = $baselineFound ? max(0, $monitorTotal - $baselineTotal) : $monitorTotal;
            $usedDownload = $baselineFound ? max(0, $monitorDownload - $baselineDownload) : $monitorDownload;
            $usedUpload = $baselineFound ? max(0, $monitorUpload - $baselineUpload) : $monitorUpload;
            $usedUptime = $baselineFound ? max(0, $monitorUptime - $baselineUptime) : $monitorUptime;

            $quotaBytes = $this->quotaGbToBytes($quotaGb);
            $remainingBytes = $quotaBytes > 0 ? max(0, $quotaBytes - $usedTotal) : 0;
            $usedPercent = $quotaBytes > 0 ? min(100, (int) floor(($usedTotal / $quotaBytes) * 100)) : 0;

            return array_merge($result, [
                'ok' => true,
                'reason' => 'monitor_ok',
                'routeros_found' => true,
                'user_manager_id' => (string) ($monitor['user_manager_id'] ?? ''),

                'monitor_ok' => true,
                'monitor_raw' => $monitor['raw_row'] ?? [],
                'monitor_total_bytes' => $monitorTotal,
                'monitor_total_human' => $this->formatBytes($monitorTotal),
                'monitor_download_bytes' => $monitorDownload,
                'monitor_download_human' => $this->formatBytes($monitorDownload),
                'monitor_upload_bytes' => $monitorUpload,
                'monitor_upload_human' => $this->formatBytes($monitorUpload),
                'monitor_uptime_seconds' => $monitorUptime,
                'monitor_uptime_human' => $this->formatDurationSeconds($monitorUptime),
                'monitor_active_sessions' => (int) ($monitor['active_sessions'] ?? 0),

                'baseline_found' => $baselineFound,
                'baseline_id' => (int) ($baseline['id'] ?? 0),
                'baseline_at' => (string) ($baseline['baseline_at'] ?? ''),
                'baseline_total_bytes' => $baselineTotal,
                'baseline_total_human' => $this->formatBytes($baselineTotal),
                'baseline_download_bytes' => $baselineDownload,
                'baseline_download_human' => $this->formatBytes($baselineDownload),
                'baseline_upload_bytes' => $baselineUpload,
                'baseline_upload_human' => $this->formatBytes($baselineUpload),
                'baseline_uptime_seconds' => $baselineUptime,
                'baseline_uptime_human' => $this->formatDurationSeconds($baselineUptime),

                'used_since_baseline_bytes' => $usedTotal,
                'used_since_baseline_human' => $this->formatBytes($usedTotal),
                'download_since_baseline_bytes' => $usedDownload,
                'download_since_baseline_human' => $this->formatBytes($usedDownload),
                'upload_since_baseline_bytes' => $usedUpload,
                'upload_since_baseline_human' => $this->formatBytes($usedUpload),
                'uptime_since_baseline_seconds' => $usedUptime,
                'uptime_since_baseline_human' => $this->formatDurationSeconds($usedUptime),

                'quota_bytes' => $quotaBytes,
                'remaining_bytes' => $remainingBytes,
                'remaining_human' => $quotaBytes > 0 ? $this->formatBytes($remainingBytes) : '-',
                'used_percent' => $usedPercent,

                'counter_reset_detected' => $counterResetDetected,
            ]);
        } catch (Throwable $e) {
            $result['reason'] = 'exception';
            $result['monitor_error'] = $e->getMessage();

            return $result;
        }
    }

    private function applyBaselineUsageToDashboard(array &$data, array $usage): void
    {
        $isActive = (int) ($usage['monitor_active_sessions'] ?? 0) > 0;

        $data['routeros_found'] = true;
        $data['connection_status'] = $isActive ? 'online' : 'offline';
        $data['connection_label'] = $isActive ? 'متصل' : 'غير متصل حالياً';

        $data['usage_source'] = 'greennet_baseline';
        $data['usage_backend'] = 'user_manager_monitor';
        $data['greennet_baseline_enabled'] = true;
        $data['greennet_baseline_found'] = !empty($usage['baseline_found']);
        $data['greennet_baseline_id'] = (int) ($usage['baseline_id'] ?? 0);
        $data['greennet_baseline_at'] = (string) ($usage['baseline_at'] ?? '');

        $data['used'] = (string) ($usage['used_since_baseline_human'] ?? '0 B');
        $data['used_bytes'] = (int) ($usage['used_since_baseline_bytes'] ?? 0);
        $data['used_percent'] = (int) ($usage['used_percent'] ?? 0);

        $data['remaining'] = (string) ($usage['remaining_human'] ?? '-');
        $data['remaining_bytes'] = (int) ($usage['remaining_bytes'] ?? 0);

        $data['monitor_total'] = (string) ($usage['monitor_total_human'] ?? '0 B');
        $data['monitor_total_bytes'] = (int) ($usage['monitor_total_bytes'] ?? 0);
        $data['monitor_download'] = (string) ($usage['monitor_download_human'] ?? '0 B');
        $data['monitor_download_bytes'] = (int) ($usage['monitor_download_bytes'] ?? 0);
        $data['monitor_upload'] = (string) ($usage['monitor_upload_human'] ?? '0 B');
        $data['monitor_upload_bytes'] = (int) ($usage['monitor_upload_bytes'] ?? 0);
        $data['monitor_uptime'] = (string) ($usage['monitor_uptime_human'] ?? '0s');
        $data['monitor_uptime_seconds'] = (int) ($usage['monitor_uptime_seconds'] ?? 0);

        $data['baseline_total'] = (string) ($usage['baseline_total_human'] ?? '0 B');
        $data['baseline_total_bytes'] = (int) ($usage['baseline_total_bytes'] ?? 0);

        $data['used_since_baseline'] = (string) ($usage['used_since_baseline_human'] ?? '0 B');
        $data['used_since_baseline_bytes'] = (int) ($usage['used_since_baseline_bytes'] ?? 0);
        $data['download_since_baseline'] = (string) ($usage['download_since_baseline_human'] ?? '0 B');
        $data['download_since_baseline_bytes'] = (int) ($usage['download_since_baseline_bytes'] ?? 0);
        $data['upload_since_baseline'] = (string) ($usage['upload_since_baseline_human'] ?? '0 B');
        $data['upload_since_baseline_bytes'] = (int) ($usage['upload_since_baseline_bytes'] ?? 0);

        $data['quota_bytes'] = (int) ($usage['quota_bytes'] ?? 0);
        $data['counter_reset_detected'] = !empty($usage['counter_reset_detected']);
    }

    private function readUserManagerMonitor(string $username): array
    {
        $summary = [
            'ok' => false,
            'error' => '',
            'username' => $username,
            'user_manager_id' => '',
            'raw_row' => [],
            'total_uptime_seconds' => 0,
            'total_download_bytes' => 0,
            'total_upload_bytes' => 0,
            'total_bytes' => 0,
            'active_sessions' => 0,
        ];

        $client = new RouterOSApiClient(
            RouterConnectionResolver::settingsForCustomer($username, ['timeout' => 4])
        );

        try {
            $users = $this->normalizeRows($client->comm('/user-manager/user/print', [
                '?name' => $username,
            ]));

            $userRow = $this->findMatchingRow($users, $username, ['name', 'username']);

            if ($userRow === null) {
                $summary['error'] = 'User Manager user not found.';

                return $summary;
            }

            $userManagerId = trim((string) ($userRow['.id'] ?? ''));

            $attempts = [];

            if ($userManagerId !== '') {
                $attempts[] = [
                    'numbers' => $userManagerId,
                    'once' => '',
                ];
            }

            $attempts[] = [
                'numbers' => $username,
                'once' => '',
            ];

            $lastError = '';

            foreach ($attempts as $params) {
                try {
                    $rows = $this->normalizeRows($client->comm('/user-manager/user/monitor', $params));

                    if (count($rows) === 0) {
                        continue;
                    }

                    $monitorRow = $this->pickMonitorRow($rows, $username);

                    $downloadRaw = $this->firstExistingValue($monitorRow, [
                        'total-download',
                        'download',
                        'download-used',
                        'total-bytes-out',
                        'bytes-out',
                    ]);

                    $uploadRaw = $this->firstExistingValue($monitorRow, [
                        'total-upload',
                        'upload',
                        'upload-used',
                        'total-bytes-in',
                        'bytes-in',
                    ]);

                    $uptimeRaw = $this->firstExistingValue($monitorRow, [
                        'total-uptime',
                        'uptime',
                        'total-time',
                    ]);

                    $activeRaw = $this->firstExistingValue($monitorRow, [
                        'active-sessions',
                        'active-session',
                        'sessions',
                    ]);

                    $downloadBytes = $this->parseBytesToInt($downloadRaw);
                    $uploadBytes = $this->parseBytesToInt($uploadRaw);
                    $uptimeSeconds = $this->parseDurationToSeconds($uptimeRaw);

                    return [
                        'ok' => true,
                        'error' => '',
                        'username' => $username,
                        'user_manager_id' => $userManagerId,
                        'raw_row' => $this->sanitizeRowForDisplay($monitorRow),
                        'total_uptime_seconds' => $uptimeSeconds,
                        'total_download_bytes' => $downloadBytes,
                        'total_upload_bytes' => $uploadBytes,
                        'total_bytes' => $downloadBytes + $uploadBytes,
                        'active_sessions' => $this->parseInteger($activeRaw),
                    ];
                } catch (Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }

            $summary['user_manager_id'] = $userManagerId;
            $summary['error'] = $lastError !== '' ? $lastError : 'No monitor rows returned.';

            return $summary;
        } catch (Throwable $e) {
            $summary['error'] = $e->getMessage();

            return $summary;
        } finally {
            $client->disconnect();
        }
    }

    private function ensureBaselineTables(): void
    {
        Database::connection()->exec("
            CREATE TABLE IF NOT EXISTS greennet_usage_baselines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                backend TEXT NOT NULL DEFAULT 'user-manager',
                router_user_id TEXT,
                baseline_download_bytes INTEGER NOT NULL DEFAULT 0,
                baseline_upload_bytes INTEGER NOT NULL DEFAULT 0,
                baseline_total_bytes INTEGER NOT NULL DEFAULT 0,
                baseline_uptime_seconds INTEGER NOT NULL DEFAULT 0,
                baseline_monitor_raw TEXT,
                baseline_at TEXT NOT NULL,
                reason TEXT,
                dry_run_audit_id INTEGER DEFAULT 0,
                created_by TEXT,
                created_at TEXT NOT NULL
            )
        ");

        Database::connection()->exec("
            CREATE INDEX IF NOT EXISTS idx_greennet_usage_baselines_username
            ON greennet_usage_baselines(username, id)
        ");
    }

    private function latestBaseline(string $username): array
    {
        $stmt = Database::connection()->prepare("
            SELECT *
            FROM greennet_usage_baselines
            WHERE username = :username
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['user'])) {
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

    private function findMatchingRow(array $rows, string $username, array $keys): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($keys as $key) {
                if (isset($row[$key]) && (string) $row[$key] === $username) {
                    return $row;
                }
            }
        }

        if (count($rows) === 1 && is_array($rows[0] ?? null)) {
            return $rows[0];
        }

        return null;
    }

    private function pickMonitorRow(array $rows, string $username): array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (
                (isset($row['name']) && (string) $row['name'] === $username)
                || (isset($row['user']) && (string) $row['user'] === $username)
                || (isset($row['username']) && (string) $row['username'] === $username)
            ) {
                return $row;
            }
        }

        return is_array($rows[0] ?? null) ? $rows[0] : [];
    }

    private function firstExistingValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return (string) $row[$key];
            }
        }

        return '';
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

    private function parseBytesToInt(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }

        $normalized = str_replace(',', '', $value);

        if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(B|KiB|MiB|GiB|TiB|KB|MB|GB|TB)?/i', $normalized, $m)) {
            $number = (float) $m[1];
            $unit = strtolower((string) ($m[2] ?? 'b'));

            $factor = match ($unit) {
                'kib', 'kb' => 1024,
                'mib', 'mb' => 1024 ** 2,
                'gib', 'gb' => 1024 ** 3,
                'tib', 'tb' => 1024 ** 4,
                default => 1,
            };

            return (int) round($number * $factor);
        }

        return 0;
    }

    private function parseInteger(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $clean = preg_replace('/[^0-9]/', '', $value);

        return $clean === '' ? 0 : (int) $clean;
    }

    private function parseDurationToSeconds(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        if (preg_match('/^\d+$/', $value)) {
            return (int) $value;
        }

        if (preg_match('/^(\d+):(\d+):(\d+)$/', $value, $m)) {
            return ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3];
        }

        $seconds = 0;

        if (preg_match_all('/(\d+)\s*(w|d|h|m|s)/i', $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $number = (int) $match[1];
                $unit = strtolower((string) $match[2]);

                $seconds += match ($unit) {
                    'w' => $number * 604800,
                    'd' => $number * 86400,
                    'h' => $number * 3600,
                    'm' => $number * 60,
                    's' => $number,
                    default => 0,
                };
            }
        }

        return $seconds;
    }

    private function quotaGbToBytes(float $quotaGb): int
    {
        if ($quotaGb <= 0) {
            return 0;
        }

        return (int) round($quotaGb * 1024 * 1024 * 1024);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes / 1024;
        $unit = 'KB';

        foreach ($units as $currentUnit) {
            $unit = $currentUnit;

            if ($value < 1024 || $currentUnit === 'TB') {
                break;
            }

            $value /= 1024;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' ' . $unit;
    }

    private function paymentLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'مدفوع',
            'due' => 'عليه دفع',
            'pending' => 'مؤجل',
            'unknown' => 'غير معروف',
            'not_registered' => 'غير موجود في CRM',
            default => 'غير معروف',
        };
    }

    private function formatDuration(int $days): string
    {
        if ($days <= 0) {
            return 'غير محدد';
        }

        return $days . ' يوم';
    }

    private function formatDurationSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;

        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;

        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . 'd';
        }

        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }

        if ($minutes > 0) {
            $parts[] = $minutes . 'm';
        }

        if ($seconds > 0 || count($parts) === 0) {
            $parts[] = $seconds . 's';
        }

        return implode(' ', $parts);
    }

    private function formatQuota(float $quotaGb): string
    {
        if ($quotaGb <= 0) {
            return 'غير محدد';
        }

        if (abs($quotaGb - round($quotaGb)) < 0.000001) {
            return (string) ((int) round($quotaGb)) . ' GB';
        }

        return rtrim(rtrim(number_format($quotaGb, 2, '.', ''), '0'), '.') . ' GB';
    }
}
