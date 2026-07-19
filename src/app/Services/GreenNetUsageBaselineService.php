<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Core\Database;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use PDO;
use Throwable;

class GreenNetUsageBaselineService
{
    public function createForUser(string $username, string $reason = 'manual', int $dryRunAuditId = 0): array
    {
        $username = trim($username);

        if ($username === '') {
            return [
                'ok' => false,
                'reason' => 'empty_username',
                'message' => 'اسم المستخدم فارغ.',
            ];
        }

        try {
            $this->ensureTables();

            $monitor = $this->readUserManagerMonitor($username);

            if (empty($monitor['ok'])) {
                return [
                    'ok' => false,
                    'reason' => 'monitor_failed',
                    'message' => (string) ($monitor['error'] ?? 'فشل قراءة User Manager Monitor.'),
                    'monitor' => $monitor,
                ];
            }

            $baselineId = $this->insertBaseline($username, $monitor, $reason, $dryRunAuditId);

            return [
                'ok' => true,
                'reason' => 'baseline_created',
                'message' => 'تم إنشاء GreenNet Baseline بنجاح.',
                'baseline_id' => $baselineId,
                'username' => $username,
                'backend' => 'user-manager',
                'router_user_id' => (string) ($monitor['user_manager_id'] ?? ''),
                'baseline_total_bytes' => (int) ($monitor['total_bytes'] ?? 0),
                'baseline_total_human' => $this->formatBytes((int) ($monitor['total_bytes'] ?? 0)),
                'baseline_download_bytes' => (int) ($monitor['total_download_bytes'] ?? 0),
                'baseline_download_human' => $this->formatBytes((int) ($monitor['total_download_bytes'] ?? 0)),
                'baseline_upload_bytes' => (int) ($monitor['total_upload_bytes'] ?? 0),
                'baseline_upload_human' => $this->formatBytes((int) ($monitor['total_upload_bytes'] ?? 0)),
                'baseline_uptime_seconds' => (int) ($monitor['total_uptime_seconds'] ?? 0),
                'baseline_uptime_human' => $this->formatDurationSeconds((int) ($monitor['total_uptime_seconds'] ?? 0)),
                'baseline_at' => date('Y-m-d H:i:s'),
                'mikrotik_write' => false,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'reason' => 'exception',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function latestBaseline(string $username): array
    {
        $username = trim($username);

        if ($username === '') {
            return [];
        }

        try {
            $this->ensureTables();

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
        } catch (Throwable) {
            return [];
        }
    }

    public function ensureTables(): void
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

    private function insertBaseline(string $username, array $monitor, string $reason, int $dryRunAuditId): int
    {
        $now = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare("
            INSERT INTO greennet_usage_baselines (
                username,
                backend,
                router_user_id,
                baseline_download_bytes,
                baseline_upload_bytes,
                baseline_total_bytes,
                baseline_uptime_seconds,
                baseline_monitor_raw,
                baseline_at,
                reason,
                dry_run_audit_id,
                created_by,
                created_at
            ) VALUES (
                :username,
                :backend,
                :router_user_id,
                :baseline_download_bytes,
                :baseline_upload_bytes,
                :baseline_total_bytes,
                :baseline_uptime_seconds,
                :baseline_monitor_raw,
                :baseline_at,
                :reason,
                :dry_run_audit_id,
                :created_by,
                :created_at
            )
        ");

        $stmt->execute([
            ':username' => $username,
            ':backend' => 'user-manager',
            ':router_user_id' => (string) ($monitor['user_manager_id'] ?? ''),
            ':baseline_download_bytes' => (int) ($monitor['total_download_bytes'] ?? 0),
            ':baseline_upload_bytes' => (int) ($monitor['total_upload_bytes'] ?? 0),
            ':baseline_total_bytes' => (int) ($monitor['total_bytes'] ?? 0),
            ':baseline_uptime_seconds' => (int) ($monitor['total_uptime_seconds'] ?? 0),
            ':baseline_monitor_raw' => $this->jsonString($monitor['raw_row'] ?? []),
            ':baseline_at' => $now,
            ':reason' => $reason,
            ':dry_run_audit_id' => $dryRunAuditId,
            ':created_by' => (string) ($_SESSION['admin_username'] ?? 'system'),
            ':created_at' => $now,
        ]);

        return (int) Database::connection()->lastInsertId();
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

        $client = new RouterOSApiClient([
            'timeout' => 5,
        ]);

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

    private function jsonString(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }
}