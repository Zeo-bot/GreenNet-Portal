<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Contracts\RouterOSReadGatewayInterface;
use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\RouterOS\RouterOSReadGatewayFactory;
use PDO;
use Throwable;

class AdminUserManagerPackagesController
{
    public function __construct(
        private readonly ?RouterOSReadGatewayInterface $routerOSReadGateway = null
    ) {
    }

    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        return View::render('admin/user_manager_packages', [
            'title' => 'User Manager Packages',
            'result' => $this->discover(),
            'message' => $this->consumeFlash('message'),
            'message_type' => $this->consumeFlash('type', 'success'),
        ]);
    }

    public function import(): void
    {
        Database::migrate();
        $this->requireLogin();

        $mode = trim((string) ($_POST['mode'] ?? 'one'));

        try {
            $this->ensureServicePackagesTable();

            $discovery = $this->discover();
            $candidates = is_array($discovery['package_candidates'] ?? null) ? $discovery['package_candidates'] : [];

            if ($mode === 'all') {
                $imported = 0;
                $updated = 0;
                $skipped = 0;

                foreach ($candidates as $candidate) {
                    if (empty($candidate['can_import_to_greennet'])) {
                        $skipped++;
                        continue;
                    }

                    $result = $this->importCandidate($candidate);

                    if (($result['action'] ?? '') === 'created') {
                        $imported++;
                    } elseif (($result['action'] ?? '') === 'updated') {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }

                $this->flash(
                    'تم استيراد الباقات. جديد: ' . $imported . '، تحديث: ' . $updated . '، متروك: ' . $skipped,
                    'success'
                );

                AppLog::info('Imported all MikroTik User Manager packages to GreenNet', [
                    'created' => $imported,
                    'updated' => $updated,
                    'skipped' => $skipped,
                ]);

                header('Location: /admin/user-manager-packages');
                exit;
            }

            $profileId = trim((string) ($_POST['profile_id'] ?? ''));
            $profileName = trim((string) ($_POST['profile_name'] ?? ''));
            $limitationId = trim((string) ($_POST['limitation_id'] ?? ''));

            $candidate = $this->findCandidate($candidates, $profileId, $profileName, $limitationId);

            if ($candidate === null) {
                throw new \RuntimeException('لم أجد الباقة المطلوبة ضمن Discovery الحالي.');
            }

            if (empty($candidate['can_import_to_greennet'])) {
                throw new \RuntimeException('هذه الباقة لا يمكن استيرادها لأنها لا تحتوي Profile صالح.');
            }

            $result = $this->importCandidate($candidate);

            $this->flash(
                (($result['action'] ?? '') === 'created' ? 'تم استيراد الباقة: ' : 'تم تحديث الباقة: ')
                . (string) ($result['name'] ?? '-'),
                'success'
            );

            AppLog::info('Imported MikroTik User Manager package to GreenNet', $result);
        } catch (Throwable $e) {
            $this->flash('فشل الاستيراد: ' . $e->getMessage(), 'warning');

            AppLog::error('Failed importing MikroTik User Manager package', [
                'error' => $e->getMessage(),
            ]);
        }

        header('Location: /admin/user-manager-packages');
        exit;
    }

    private function discover(): array
    {
        $result = [
            'ok' => false,
            'read_only' => true,
            'mikrotik_write' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'profiles' => $this->emptyRead('/user-manager/profile/print'),
            'limitations' => $this->emptyRead('/user-manager/limitation/print'),
            'profile_links' => $this->emptyRead('profile-limitation discovery'),
            'link_attempts' => [],
            'package_candidates' => [],
            'summary' => [
                'profiles_count' => 0,
                'limitations_count' => 0,
                'links_count' => 0,
                'candidates_count' => 0,
                'linked_count' => 0,
                'name_match_count' => 0,
                'profile_only_count' => 0,
                'limitation_only_count' => 0,
                'importable_count' => 0,
            ],
            'notes' => [
                'هذه الصفحة تقرأ من MikroTik فقط.',
                'الاستيراد يكتب داخل GreenNet SQLite فقط.',
                'لا يتم إنشاء أو تعديل أي Profile أو Limitation على MikroTik.',
            ],
        ];

        try {
            $gateway = $this->routerOSReadGateway ?? RouterOSReadGatewayFactory::create([
                'timeout' => 6,
            ]);

            $profiles = $this->readRows($gateway, '/user-manager/profile/print');
            $limitations = $this->readRows($gateway, '/user-manager/limitation/print');

            $linkCommands = [
                '/user-manager/profile-limitation/print',
                '/user-manager/profile/limitation/print',
                '/user-manager/profile/limitations/print',
            ];

            $linkResult = $this->emptyRead('profile-limitation discovery');

            foreach ($linkCommands as $command) {
                $attempt = $this->readRows($gateway, $command);
                $result['link_attempts'][] = $attempt;

                if (!empty($attempt['ok'])) {
                    $linkResult = $attempt;

                    if ((int) ($attempt['rows_count'] ?? 0) > 0) {
                        break;
                    }
                }
            }

            $profileRows = is_array($profiles['rows'] ?? null) ? $profiles['rows'] : [];
            $limitationRows = is_array($limitations['rows'] ?? null) ? $limitations['rows'] : [];
            $linkRows = is_array($linkResult['rows'] ?? null) ? $linkResult['rows'] : [];

            $candidates = $this->buildPackageCandidates($profileRows, $limitationRows, $linkRows);

            $summary = [
                'profiles_count' => count($profileRows),
                'limitations_count' => count($limitationRows),
                'links_count' => count($linkRows),
                'candidates_count' => count($candidates),
                'linked_count' => 0,
                'name_match_count' => 0,
                'profile_only_count' => 0,
                'limitation_only_count' => 0,
                'importable_count' => 0,
            ];

            foreach ($candidates as $candidate) {
                $status = (string) ($candidate['match_status'] ?? '');

                if ($status === 'linked') {
                    $summary['linked_count']++;
                } elseif ($status === 'name_match') {
                    $summary['name_match_count']++;
                } elseif ($status === 'profile_only') {
                    $summary['profile_only_count']++;
                } elseif ($status === 'limitation_only') {
                    $summary['limitation_only_count']++;
                }

                if (!empty($candidate['can_import_to_greennet'])) {
                    $summary['importable_count']++;
                }
            }

            $result['ok'] = !empty($profiles['ok']) || !empty($limitations['ok']);
            $result['profiles'] = $profiles;
            $result['limitations'] = $limitations;
            $result['profile_links'] = $linkResult;
            $result['package_candidates'] = $candidates;
            $result['summary'] = $summary;

            if ($summary['links_count'] > 0) {
                $result['notes'][] = 'تم العثور على جدول ربط بين Profile و Limitation.';
            } else {
                $result['notes'][] = 'لم يظهر جدول ربط واضح. سيتم الاعتماد على مطابقة الاسم عندما تكون ممكنة.';
            }

            return $result;
        } catch (Throwable $e) {
            $result['ok'] = false;
            $result['error'] = $e->getMessage();

            return $result;
        }
    }

    private function importCandidate(array $candidate): array
    {
        $this->ensureServicePackagesTable();

        $sourceProfile = trim((string) ($candidate['suggested_source_profile'] ?? ''));
        $name = trim((string) ($candidate['suggested_greennet_name'] ?? ''));

        if ($sourceProfile === '') {
            throw new \RuntimeException('لا يمكن الاستيراد بدون source_profile.');
        }

        if ($name === '') {
            $name = $sourceProfile;
        }

        $quotaBytes = (int) ($candidate['quota_bytes'] ?? 0);
        $quotaGb = $quotaBytes > 0 ? round($quotaBytes / 1024 / 1024 / 1024, 4) : 0.0;

        $durationDays = $this->durationToDays(
            (string) ($candidate['profile_validity'] ?? ''),
            (string) ($candidate['uptime_limit'] ?? '')
        );

        $rateLimit = trim((string) ($candidate['rate_limit'] ?? ''));

        if ($rateLimit === '-') {
            $rateLimit = '';
        }

        $price = $this->numericPrice((string) ($candidate['profile_price'] ?? ''));

        $notes = implode("\n", [
            'Imported from MikroTik User Manager.',
            'Profile ID: ' . (string) ($candidate['profile_id'] ?? ''),
            'Profile Name: ' . (string) ($candidate['profile_name'] ?? ''),
            'Limitation ID: ' . (string) ($candidate['limitation_id'] ?? ''),
            'Limitation Name: ' . (string) ($candidate['limitation_name'] ?? ''),
            'Quota Raw: ' . (string) ($candidate['quota_raw'] ?? ''),
            'Rate Limit Raw: ' . (string) ($candidate['rate_limit'] ?? ''),
            'Validity Raw: ' . (string) ($candidate['profile_validity'] ?? ''),
            'Uptime Limit Raw: ' . (string) ($candidate['uptime_limit'] ?? ''),
            'Match Status: ' . (string) ($candidate['match_status'] ?? ''),
            'Imported At: ' . date('Y-m-d H:i:s'),
        ]);

        $pdo = Database::connection();

        $stmt = $pdo->prepare("
            SELECT id
            FROM service_packages
            WHERE source_type = 'user-manager'
              AND source_profile = :source_profile
            LIMIT 1
        ");

        $stmt->execute([
            ':source_profile' => $sourceProfile,
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            $id = (int) ($existing['id'] ?? 0);

            $update = $pdo->prepare("
                UPDATE service_packages
                SET
                    name = :name,
                    access_type = :access_type,
                    rate_limit = :rate_limit,
                    duration_days = :duration_days,
                    quota_gb = :quota_gb,
                    price = :price,
                    currency = :currency,
                    is_active = 1,
                    notes = :notes,
                    updated_at = :updated_at
                WHERE id = :id
            ");

            $update->execute([
                ':name' => $name,
                ':access_type' => 'hotspot',
                ':rate_limit' => $rateLimit,
                ':duration_days' => $durationDays,
                ':quota_gb' => $quotaGb,
                ':price' => $price,
                ':currency' => 'SYP',
                ':notes' => $notes,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);

            return [
                'ok' => true,
                'action' => 'updated',
                'id' => $id,
                'name' => $name,
                'source_type' => 'user-manager',
                'source_profile' => $sourceProfile,
            ];
        }

        $insert = $pdo->prepare("
            INSERT INTO service_packages (
                name,
                source_type,
                source_profile,
                access_type,
                rate_limit,
                duration_days,
                quota_gb,
                price,
                currency,
                is_active,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :source_type,
                :source_profile,
                :access_type,
                :rate_limit,
                :duration_days,
                :quota_gb,
                :price,
                :currency,
                :is_active,
                :notes,
                :created_at,
                :updated_at
            )
        ");

        $now = date('Y-m-d H:i:s');

        $insert->execute([
            ':name' => $name,
            ':source_type' => 'user-manager',
            ':source_profile' => $sourceProfile,
            ':access_type' => 'hotspot',
            ':rate_limit' => $rateLimit,
            ':duration_days' => $durationDays,
            ':quota_gb' => $quotaGb,
            ':price' => $price,
            ':currency' => 'SYP',
            ':is_active' => 1,
            ':notes' => $notes,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return [
            'ok' => true,
            'action' => 'created',
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
            'source_type' => 'user-manager',
            'source_profile' => $sourceProfile,
        ];
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

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_service_packages_source
            ON service_packages(source_type, source_profile)
        ");
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

    private function findCandidate(array $candidates, string $profileId, string $profileName, string $limitationId): ?array
    {
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $candidateProfileId = (string) ($candidate['profile_id'] ?? '');
            $candidateProfileName = (string) ($candidate['profile_name'] ?? '');
            $candidateLimitationId = (string) ($candidate['limitation_id'] ?? '');

            if ($profileId !== '' && $candidateProfileId === $profileId) {
                return $candidate;
            }

            if ($profileName !== '' && $candidateProfileName === $profileName) {
                return $candidate;
            }

            if ($limitationId !== '' && $candidateLimitationId === $limitationId && $candidateProfileName !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function readRows(RouterOSReadGatewayInterface $gateway, string $command): array
    {
        $result = $this->emptyRead($command);

        try {
            $rows = $this->normalizeRows($gateway->read($command));

            $result['ok'] = true;
            $result['status'] = 'ok';
            $result['rows'] = $rows;
            $result['rows_count'] = count($rows);
            $result['rows_preview'] = $this->sanitizeRowsPreview($rows, 80);
            $result['error'] = '';

            return $result;
        } catch (Throwable $e) {
            $result['ok'] = false;
            $result['status'] = $this->backendErrorStatus($e->getMessage());
            $result['rows'] = [];
            $result['rows_count'] = 0;
            $result['rows_preview'] = [];
            $result['error'] = $e->getMessage();

            return $result;
        }
    }

    private function emptyRead(string $command): array
    {
        return [
            'command' => $command,
            'ok' => false,
            'status' => 'not_checked',
            'rows' => [],
            'rows_count' => 0,
            'rows_preview' => [],
            'error' => '',
        ];
    }

    private function buildPackageCandidates(array $profiles, array $limitations, array $links): array
    {
        $candidates = [];
        $usedLimitations = [];

        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $profileId = trim((string) ($profile['.id'] ?? ''));
            $profileName = $this->field($profile, ['name', 'profile', 'profile-name']);
            $profileDisplayName = $this->field($profile, ['name-for-users', 'name', 'profile']);

            $linkedLimitation = $this->findLimitationByLink($profile, $limitations, $links);
            $matchStatus = 'profile_only';

            if ($linkedLimitation !== null) {
                $matchStatus = 'linked';
            } else {
                $linkedLimitation = $this->findLimitationByName($profileName, $limitations);

                if ($linkedLimitation !== null) {
                    $matchStatus = 'name_match';
                }
            }

            $limitationId = '';
            $limitationName = '';
            $quotaRaw = '';
            $quotaBytes = 0;
            $rateLimit = '';
            $uptimeLimit = '';

            if ($linkedLimitation !== null) {
                $limitationId = trim((string) ($linkedLimitation['.id'] ?? ''));
                $limitationName = $this->field($linkedLimitation, ['name', 'limitation', 'limitation-name']);
                $quotaRaw = $this->field($linkedLimitation, [
                    'transfer-limit',
                    'total-limit',
                    'download-limit',
                    'upload-limit',
                ]);
                $quotaBytes = $this->parseBytesToInt($quotaRaw);
                $rateLimit = $this->field($linkedLimitation, [
                    'rate-limit',
                    'rate',
                    'rx-rate',
                    'tx-rate',
                ]);
                $uptimeLimit = $this->field($linkedLimitation, [
                    'uptime-limit',
                    'time-limit',
                    'session-timeout',
                ]);

                if ($limitationId !== '') {
                    $usedLimitations[$limitationId] = true;
                }

                if ($limitationName !== '') {
                    $usedLimitations['name:' . strtolower($limitationName)] = true;
                }
            }

            $validity = $this->field($profile, ['validity', 'duration', 'expires-after']);
            $startsWhen = $this->field($profile, ['starts-when']);
            $price = $this->field($profile, ['price']);

            $candidates[] = [
                'match_status' => $matchStatus,
                'profile_id' => $profileId,
                'profile_name' => $profileName,
                'profile_display_name' => $profileDisplayName,
                'profile_validity' => $validity,
                'profile_starts_when' => $startsWhen,
                'profile_price' => $price,
                'limitation_id' => $limitationId,
                'limitation_name' => $limitationName,
                'quota_raw' => $quotaRaw,
                'quota_bytes' => $quotaBytes,
                'quota_human' => $quotaBytes > 0 ? $this->formatBytes($quotaBytes) : '-',
                'rate_limit' => $rateLimit !== '' ? $rateLimit : '-',
                'uptime_limit' => $uptimeLimit !== '' ? $uptimeLimit : '-',
                'suggested_greennet_name' => $profileDisplayName !== '' ? $profileDisplayName : $profileName,
                'suggested_source_type' => 'user-manager',
                'suggested_source_profile' => $profileName,
                'can_import_to_greennet' => $profileName !== '',
                'profile_raw' => $this->sanitizeRowForDisplay($profile),
                'limitation_raw' => is_array($linkedLimitation) ? $this->sanitizeRowForDisplay($linkedLimitation) : [],
            ];
        }

        foreach ($limitations as $limitation) {
            if (!is_array($limitation)) {
                continue;
            }

            $limitationId = trim((string) ($limitation['.id'] ?? ''));
            $limitationName = $this->field($limitation, ['name', 'limitation', 'limitation-name']);

            $isUsed = false;

            if ($limitationId !== '' && !empty($usedLimitations[$limitationId])) {
                $isUsed = true;
            }

            if ($limitationName !== '' && !empty($usedLimitations['name:' . strtolower($limitationName)])) {
                $isUsed = true;
            }

            if ($isUsed) {
                continue;
            }

            $quotaRaw = $this->field($limitation, [
                'transfer-limit',
                'total-limit',
                'download-limit',
                'upload-limit',
            ]);

            $quotaBytes = $this->parseBytesToInt($quotaRaw);

            $candidates[] = [
                'match_status' => 'limitation_only',
                'profile_id' => '',
                'profile_name' => '',
                'profile_display_name' => '',
                'profile_validity' => '',
                'profile_starts_when' => '',
                'profile_price' => '',
                'limitation_id' => $limitationId,
                'limitation_name' => $limitationName,
                'quota_raw' => $quotaRaw,
                'quota_bytes' => $quotaBytes,
                'quota_human' => $quotaBytes > 0 ? $this->formatBytes($quotaBytes) : '-',
                'rate_limit' => $this->field($limitation, ['rate-limit', 'rate', 'rx-rate', 'tx-rate']) ?: '-',
                'uptime_limit' => $this->field($limitation, ['uptime-limit', 'time-limit', 'session-timeout']) ?: '-',
                'suggested_greennet_name' => $limitationName,
                'suggested_source_type' => 'user-manager-limitation',
                'suggested_source_profile' => '',
                'can_import_to_greennet' => false,
                'profile_raw' => [],
                'limitation_raw' => $this->sanitizeRowForDisplay($limitation),
            ];
        }

        return $candidates;
    }

    private function findLimitationByLink(array $profile, array $limitations, array $links): ?array
    {
        $profileId = trim((string) ($profile['.id'] ?? ''));
        $profileName = $this->field($profile, ['name', 'profile', 'profile-name']);

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $profileRef = $this->field($link, [
                'profile',
                'profile-id',
                'profile-name',
                'profile-ref',
            ]);

            if (!$this->sameRef($profileRef, $profileId, $profileName)) {
                continue;
            }

            $limitationRef = $this->field($link, [
                'limitation',
                'limitation-id',
                'limitation-name',
                'limit',
                'limits',
            ]);

            if ($limitationRef === '') {
                continue;
            }

            $limitation = $this->findLimitationByRef($limitationRef, $limitations);

            if ($limitation !== null) {
                return $limitation;
            }
        }

        return null;
    }

    private function findLimitationByName(string $profileName, array $limitations): ?array
    {
        if ($profileName === '') {
            return null;
        }

        foreach ($limitations as $limitation) {
            if (!is_array($limitation)) {
                continue;
            }

            $limitationName = $this->field($limitation, ['name', 'limitation', 'limitation-name']);

            if (strtolower($limitationName) === strtolower($profileName)) {
                return $limitation;
            }
        }

        return null;
    }

    private function findLimitationByRef(string $ref, array $limitations): ?array
    {
        $ref = trim($ref);

        if ($ref === '') {
            return null;
        }

        foreach ($limitations as $limitation) {
            if (!is_array($limitation)) {
                continue;
            }

            $id = trim((string) ($limitation['.id'] ?? ''));
            $name = $this->field($limitation, ['name', 'limitation', 'limitation-name']);

            if ($this->sameRef($ref, $id, $name)) {
                return $limitation;
            }
        }

        return null;
    }

    private function sameRef(string $ref, string $id, string $name): bool
    {
        $ref = trim($ref);

        if ($ref === '') {
            return false;
        }

        if ($id !== '' && $ref === $id) {
            return true;
        }

        if ($name !== '' && strtolower($ref) === strtolower($name)) {
            return true;
        }

        return false;
    }

    private function field(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function durationToDays(string $validity, string $uptimeLimit): int
    {
        $seconds = max(
            $this->parseDurationToSeconds($validity),
            $this->parseDurationToSeconds($uptimeLimit)
        );

        if ($seconds <= 0) {
            return 0;
        }

        if ($seconds < 86400) {
            return 0;
        }

        return (int) ceil($seconds / 86400);
    }

    private function numericPrice(string $price): float
    {
        $price = trim($price);

        if ($price === '') {
            return 0.0;
        }

        $price = preg_replace('/[^0-9.]/', '', $price);

        return is_numeric($price) ? (float) $price : 0.0;
    }

    private function backendErrorStatus(string $message): string
    {
        $lower = strtolower($message);

        if (
            str_contains($lower, 'no such command')
            || str_contains($lower, 'no such item')
            || str_contains($lower, 'not found')
            || str_contains($lower, 'unknown')
        ) {
            return 'unsupported';
        }

        if (str_contains($lower, 'not allowed')) {
            return 'not_allowed';
        }

        if (
            str_contains($lower, 'unreachable')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'closed')
            || str_contains($lower, 'login failed')
            || str_contains($lower, 'host is empty')
        ) {
            return 'unreachable';
        }

        return 'error';
    }

    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        if (isset($rows['.id']) || isset($rows['name']) || isset($rows['profile']) || isset($rows['limitation'])) {
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

    private function sanitizeRowsPreview(array $rows, int $limit = 50): array
    {
        $preview = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            if (is_array($row)) {
                $preview[] = $this->sanitizeRowForDisplay($row);
            }
        }

        return $preview;
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

    private function parseDurationToSeconds(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
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

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['um_packages_flash_message'] = $message;
        $_SESSION['um_packages_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'um_packages_flash_' . $key;
        $value = (string) ($_SESSION[$sessionKey] ?? $default);

        unset($_SESSION[$sessionKey]);

        return $value;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}
