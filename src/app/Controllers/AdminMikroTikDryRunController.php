<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\GreenNetUsageBaselineService;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use GreenNet\Services\WriteSafetyGuard;
use PDO;
use RuntimeException;
use Throwable;

class AdminMikroTikDryRunController
{
    public function index(): string
    {
        Database::migrate();

        $this->requireLogin();

        $guard = new WriteSafetyGuard();
        $guard->ensureTables();

        return View::render('admin/mikrotik_dry_run', [
            'title' => 'MikroTik Dry Run',
            'preflight' => $guard->preflight(),
            'result' => $_SESSION['mikrotik_dry_run_result'] ?? null,
            'message' => $this->consumeFlash('message'),
            'message_type' => $this->consumeFlash('type', 'success'),
        ]);
    }

    public function preview(): void
    {
        Database::migrate();

        $this->requireLogin();

        $guard = new WriteSafetyGuard();
        $guard->ensureTables();

        try {
            $guard->assertDryRunAllowed();

            $action = trim((string) ($_POST['action'] ?? 'hotspot_reset_counters'));
            $username = trim((string) ($_POST['username'] ?? ''));

            if ($username === '') {
                throw new RuntimeException('اسم المستخدم مطلوب.');
            }

            $this->validateUsername($username);

            $plan = match ($action) {
                'hotspot_reset_counters' => $this->buildBaselinePlan($username),
                'um_disable_user' => $this->buildSetDisabledPlan($username, true),
                'um_enable_user' => $this->buildSetDisabledPlan($username, false),
                default => throw new RuntimeException('عملية غير معروفة.'),
            };

            $auditId = $guard->recordDryRun([
                'action' => $action,
                'dataset' => (string) ($plan['dataset'] ?? 'mikrotik'),
                'username' => $username,
                'command' => (string) ($plan['command'] ?? ''),
                'params' => $plan,
                'router_response' => $this->auditResponseFromPlan($plan),
            ]);

            $plan['audit_id'] = $auditId;

            $_SESSION['mikrotik_dry_run_result'] = $plan;

            AppLog::info('MikroTik Dry Run created', [
                'action' => $action,
                'username' => $username,
                'audit_id' => $auditId,
                'can_execute_later' => !empty($plan['can_execute_later']),
            ]);

            $this->flash('تم إنشاء Dry Run بنجاح. لم يتم تنفيذ أي أمر بعد.', 'success');
        } catch (Throwable $e) {
            $_SESSION['mikrotik_dry_run_result'] = [
                'error' => $e->getMessage(),
                'executed' => false,
                'real_execution' => false,
            ];

            AppLog::error('MikroTik Dry Run failed', [
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل Dry Run: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/mikrotik-dry-run');
        exit;
    }

    public function execute(): void
    {
        Database::migrate();

        $this->requireLogin();

        $method = trim((string) ($_POST['method'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));

        try {
            if ($username === '') {
                throw new RuntimeException('اسم المستخدم مطلوب.');
            }

            $this->validateUsername($username);

            if ($method === 'create_greennet_baseline') {
                $this->executeCreateBaseline($username);
            } elseif ($method === 'um_set_disabled') {
                $this->executeSetDisabled($username);
            } else {
                throw new RuntimeException('طريقة تنفيذ غير معروفة.');
            }
        } catch (Throwable $e) {
            $previous = $_SESSION['mikrotik_dry_run_result'] ?? [];

            if (!is_array($previous)) {
                $previous = [];
            }

            $previous['execute_error'] = $e->getMessage();
            $previous['executed'] = false;

            $_SESSION['mikrotik_dry_run_result'] = $previous;

            AppLog::error('MikroTik execution failed', [
                'username' => $username,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل التنفيذ: ' . $e->getMessage(), 'warning');

            header('Location: /admin/mikrotik-dry-run');
            exit;
        }
    }

    private function executeCreateBaseline(string $username): void
    {
        $confirm = trim((string) ($_POST['confirm_baseline'] ?? ''));

        if ($confirm !== 'BASELINE') {
            throw new RuntimeException('لإنشاء Baseline اكتب BASELINE في خانة التأكيد.');
        }

        $lastPlan = $this->requireLastPlan($username, 'hotspot_reset_counters');

        if ((string) ($lastPlan['recommended_backend'] ?? '') !== 'user-manager') {
            throw new RuntimeException('آخر Dry Run لا يثبت أن المستخدم User Manager.');
        }

        $baselineService = new GreenNetUsageBaselineService();
        $baseline = $baselineService->createForUser($username, 'manual_admin_baseline', (int) ($lastPlan['audit_id'] ?? 0));

        if (empty($baseline['ok'])) {
            throw new RuntimeException((string) ($baseline['message'] ?? 'فشل إنشاء Baseline.'));
        }

        $afterPlan = $this->buildBaselinePlan($username);
        $afterPlan['baseline_created'] = true;
        $afterPlan['baseline_result'] = $baseline;
        $afterPlan['executed'] = false;
        $afterPlan['real_execution'] = false;
        $afterPlan['notes'][] = 'تم إنشاء Baseline داخل GreenNet فقط، بدون أي تعديل على MikroTik.';

        $_SESSION['mikrotik_dry_run_result'] = $afterPlan;

        AppLog::info('GreenNet usage baseline created from Dry Run', [
            'username' => $username,
            'baseline_id' => (int) ($baseline['baseline_id'] ?? 0),
        ]);

        $this->flash('تم إنشاء GreenNet Baseline بنجاح. لم يتم تعديل MikroTik.', 'success');

        header('Location: /admin/mikrotik-dry-run');
        exit;
    }

    private function executeSetDisabled(string $username): void
    {
        $action = trim((string) ($_POST['action'] ?? ''));
        $confirm = trim((string) ($_POST['confirm_execute'] ?? ''));

        if (!in_array($action, ['um_disable_user', 'um_enable_user'], true)) {
            throw new RuntimeException('عملية User Manager غير صحيحة.');
        }

        $desiredDisabled = $action === 'um_disable_user';
        $requiredConfirm = $desiredDisabled ? 'DISABLE' : 'ENABLE';

        if ($confirm !== $requiredConfirm) {
            throw new RuntimeException('للتنفيذ اكتب ' . $requiredConfirm . ' في خانة التأكيد.');
        }

        $guard = new WriteSafetyGuard();
        $guard->ensureTables();
        $guard->assertRealWriteAllowed([
            'confirmed' => true,
        ]);

        $lastPlan = $this->requireLastPlan($username, $action);

        if (empty($lastPlan['can_execute_later'])) {
            throw new RuntimeException('آخر Dry Run لا يسمح بالتنفيذ.');
        }

        $freshPlan = $this->buildSetDisabledPlan($username, $desiredDisabled);

        if (empty($freshPlan['can_execute_later'])) {
            throw new RuntimeException((string) ($freshPlan['block_reason'] ?? 'العملية لم تعد قابلة للتنفيذ.'));
        }

        $userId = trim((string) ($freshPlan['user_manager_id'] ?? ''));

        if ($userId === '') {
            throw new RuntimeException('لم يتم تحديد .id للمستخدم داخل User Manager.');
        }

        $command = '/user-manager/user/set';
        $params = [
            'numbers' => $userId,
            'disabled' => $desiredDisabled ? 'yes' : 'no',
        ];

        $client = new RouterOSApiClient([
            'timeout' => 5,
        ]);

        try {
            $routerResponse = $client->comm($command, $params);
        } finally {
            $client->disconnect();
        }

        $afterPlan = $this->buildSetDisabledPlan($username, $desiredDisabled);
        $afterDisabled = (bool) ($afterPlan['current_disabled_bool'] ?? !$desiredDisabled);
        $success = $afterDisabled === $desiredDisabled;

        $guard->recordRealAttempt([
            'action' => $desiredDisabled ? 'user_manager_disable_user' : 'user_manager_enable_user',
            'dataset' => 'user_manager_user',
            'username' => $username,
            'command' => $command,
            'params' => [
                'username' => $username,
                'numbers' => $userId,
                'disabled' => $params['disabled'],
                'dry_run_audit_id' => (int) ($lastPlan['audit_id'] ?? 0),
                'confirmed' => true,
            ],
            'executed' => 1,
            'success' => $success ? 1 : 0,
            'router_response' => $this->jsonString([
                'response' => $routerResponse,
                'verified_disabled' => $afterDisabled,
                'desired_disabled' => $desiredDisabled,
            ]),
        ]);

        $afterPlan['real_result'] = [
            'success' => $success,
            'command' => $command,
            'params' => $params,
            'username' => $username,
            'user_manager_id' => $userId,
            'desired_disabled' => $desiredDisabled,
            'verified_disabled' => $afterDisabled,
            'router_response' => $routerResponse,
            'executed_at' => date('Y-m-d H:i:s'),
        ];

        $afterPlan['executed'] = true;
        $afterPlan['real_execution'] = true;

        $_SESSION['mikrotik_dry_run_result'] = $afterPlan;

        if (!$success) {
            throw new RuntimeException('تم إرسال الأمر لكن التحقق بعد التنفيذ لم يطابق الحالة المطلوبة.');
        }

        $this->flash($desiredDisabled ? 'تم تعطيل المستخدم بنجاح.' : 'تم تفعيل المستخدم بنجاح.', 'success');

        header('Location: /admin/mikrotik-dry-run');
        exit;
    }

    private function buildBaselinePlan(string $username): array
    {
        $lookup = $this->lookupUserManagerUser($username);
        $monitor = [];
        $latestBaseline = [];
        $baselineUsage = [];

        if (!empty($lookup['found'])) {
            $monitor = $this->readUserManagerMonitor($username, (string) ($lookup['matched_id'] ?? ''));
            $latestBaseline = $this->latestBaseline($username);
            $baselineUsage = $this->buildBaselineUsage($username, $monitor, $latestBaseline);
        }

        return [
            'title' => 'GreenNet Baseline Usage Engine',
            'dataset' => 'greennet_usage_baseline',
            'action' => 'hotspot_reset_counters',
            'username' => $username,
            'command' => '/user-manager/user/monitor + GreenNet baseline',
            'expected_command' => 'Read only from MikroTik. Optional SQLite baseline only.',
            'router_status' => !empty($lookup['found']) ? 'found' : (string) ($lookup['status'] ?? 'not_found'),
            'recommended_backend' => !empty($lookup['found']) ? 'user-manager' : 'not-found',
            'backend_lookup' => [
                'user_manager' => $lookup,
            ],
            'found_user_manager' => !empty($lookup['found']),
            'user_manager_id' => (string) ($lookup['matched_id'] ?? ''),
            'user_manager_user' => is_array($lookup['matched_row'] ?? null) ? $lookup['matched_row'] : [],
            'monitor_summary' => $monitor,
            'latest_baseline' => $latestBaseline,
            'baseline_usage' => $baselineUsage,
            'executed' => false,
            'real_execution' => false,
            'can_execute_later' => !empty($monitor['ok']),
            'notes' => [
                'هذه العملية لا تكتب على MikroTik.',
                'الاستهلاك داخل GreenNet = Monitor Total - Latest Baseline.',
                'استخدم Create Baseline عند التجديد أو بداية باقة جديدة.',
            ],
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function buildSetDisabledPlan(string $username, bool $desiredDisabled): array
    {
        $lookup = $this->lookupUserManagerUser($username);
        $found = !empty($lookup['found']);

        $currentDisabled = false;
        $currentDisabledRaw = '';
        $userId = '';

        if ($found) {
            $row = is_array($lookup['matched_raw_row'] ?? null) ? $lookup['matched_raw_row'] : [];
            $userId = trim((string) ($row['.id'] ?? ''));
            $currentDisabledRaw = (string) ($row['disabled'] ?? 'false');
            $currentDisabled = $this->routerBool($currentDisabledRaw);
        }

        $alreadyDesired = $found && $currentDisabled === $desiredDisabled;
        $canExecute = $found && $userId !== '' && !$alreadyDesired;

        $blockReason = '';

        if (!$found) {
            $blockReason = 'المستخدم غير موجود في User Manager.';
        } elseif ($userId === '') {
            $blockReason = 'لم يتم العثور على .id للمستخدم.';
        } elseif ($alreadyDesired) {
            $blockReason = $desiredDisabled ? 'المستخدم معطّل مسبقاً.' : 'المستخدم مفعّل مسبقاً.';
        }

        return [
            'title' => $desiredDisabled ? 'Disable User Manager User' : 'Enable User Manager User',
            'dataset' => 'user_manager_user',
            'action' => $desiredDisabled ? 'um_disable_user' : 'um_enable_user',
            'username' => $username,
            'command' => '/user-manager/user/set',
            'expected_command' => '/user-manager/user/set numbers=' . ($userId !== '' ? $userId : '<id>') . ' disabled=' . ($desiredDisabled ? 'yes' : 'no'),
            'expected_params' => [
                'numbers' => $userId,
                'disabled' => $desiredDisabled ? 'yes' : 'no',
            ],
            'router_status' => $found ? 'found' : (string) ($lookup['status'] ?? 'not_found'),
            'recommended_backend' => $found ? 'user-manager' : 'not-found',
            'backend_lookup' => [
                'user_manager' => $lookup,
            ],
            'found_user_manager' => $found,
            'user_manager_id' => $userId,
            'user_manager_user' => is_array($lookup['matched_row'] ?? null) ? $lookup['matched_row'] : [],
            'current_disabled_raw' => $currentDisabledRaw,
            'current_disabled_bool' => $currentDisabled,
            'desired_disabled_bool' => $desiredDisabled,
            'desired_disabled_label' => $desiredDisabled ? 'disabled' : 'enabled',
            'already_desired' => $alreadyDesired,
            'block_reason' => $blockReason,
            'executed' => false,
            'real_execution' => false,
            'can_execute_later' => $canExecute,
            'confirm_word' => $desiredDisabled ? 'DISABLE' : 'ENABLE',
            'notes' => [
                'هذه العملية تكتب على MikroTik عند الضغط على Execute.',
                'الأمر الحقيقي يستخدم /user-manager/user/set.',
                'لن يتم تنفيذ شيء بدون Dry Run و WriteSafetyGuard وكلمة تأكيد.',
            ],
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function lookupUserManagerUser(string $username): array
    {
        $result = [
            'label' => 'User Manager Users',
            'command' => '/user-manager/user/print',
            'params' => [
                '?name' => $username,
            ],
            'ok' => false,
            'available' => false,
            'status' => 'not_checked',
            'found' => false,
            'rows_count' => 0,
            'matched_id' => '',
            'matched_row' => null,
            'matched_raw_row' => null,
            'error' => '',
        ];

        try {
            $client = new RouterOSApiClient([
                'timeout' => 4,
            ]);

            try {
                $rows = $this->normalizeRows($client->comm('/user-manager/user/print', [
                    '?name' => $username,
                ]));
            } finally {
                $client->disconnect();
            }

            $matched = $this->findMatchingRow($rows, $username, ['name', 'username']);

            $result['ok'] = true;
            $result['available'] = true;
            $result['status'] = $matched !== null ? 'found' : 'not_found';
            $result['found'] = $matched !== null;
            $result['rows_count'] = count($rows);
            $result['matched_raw_row'] = $matched;
            $result['matched_row'] = is_array($matched) ? $this->sanitizeRowForDisplay($matched) : null;
            $result['matched_id'] = is_array($matched) ? (string) ($matched['.id'] ?? '') : '';

            return $result;
        } catch (Throwable $e) {
            $result['ok'] = false;
            $result['available'] = false;
            $result['status'] = $this->backendErrorStatus($e->getMessage());
            $result['error'] = $e->getMessage();

            return $result;
        }
    }

    private function readUserManagerMonitor(string $username, string $userManagerId): array
    {
        $summary = [
            'ok' => false,
            'error' => '',
            'username' => $username,
            'user_manager_id' => $userManagerId,
            'raw_row' => [],
            'total_uptime_seconds' => 0,
            'total_uptime_human' => '0s',
            'total_download_bytes' => 0,
            'total_download_human' => '0 B',
            'total_upload_bytes' => 0,
            'total_upload_human' => '0 B',
            'total_bytes' => 0,
            'total_human' => '0 B',
            'active_sessions' => 0,
        ];

        $client = new RouterOSApiClient([
            'timeout' => 5,
        ]);

        try {
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

                    $row = $this->pickMonitorRow($rows, $username);

                    $downloadBytes = $this->parseBytesToInt($this->firstExistingValue($row, [
                        'total-download',
                        'download',
                        'download-used',
                        'total-bytes-out',
                        'bytes-out',
                    ]));

                    $uploadBytes = $this->parseBytesToInt($this->firstExistingValue($row, [
                        'total-upload',
                        'upload',
                        'upload-used',
                        'total-bytes-in',
                        'bytes-in',
                    ]));

                    $uptimeSeconds = $this->parseDurationToSeconds($this->firstExistingValue($row, [
                        'total-uptime',
                        'uptime',
                        'total-time',
                    ]));

                    $activeSessions = $this->parseInteger($this->firstExistingValue($row, [
                        'active-sessions',
                        'active-session',
                        'sessions',
                    ]));

                    return [
                        'ok' => true,
                        'error' => '',
                        'username' => $username,
                        'user_manager_id' => $userManagerId,
                        'raw_row' => $this->sanitizeRowForDisplay($row),
                        'total_uptime_seconds' => $uptimeSeconds,
                        'total_uptime_human' => $this->formatDurationSeconds($uptimeSeconds),
                        'total_download_bytes' => $downloadBytes,
                        'total_download_human' => $this->formatBytes($downloadBytes),
                        'total_upload_bytes' => $uploadBytes,
                        'total_upload_human' => $this->formatBytes($uploadBytes),
                        'total_bytes' => $downloadBytes + $uploadBytes,
                        'total_human' => $this->formatBytes($downloadBytes + $uploadBytes),
                        'active_sessions' => $activeSessions,
                    ];
                } catch (Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }

            $summary['error'] = $lastError !== '' ? $lastError : 'No monitor rows returned.';

            return $summary;
        } catch (Throwable $e) {
            $summary['error'] = $e->getMessage();

            return $summary;
        } finally {
            $client->disconnect();
        }
    }

    private function latestBaseline(string $username): array
    {
        try {
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

            if (!is_array($row)) {
                return [];
            }

            $row['baseline_total_human'] = $this->formatBytes((int) ($row['baseline_total_bytes'] ?? 0));
            $row['baseline_download_human'] = $this->formatBytes((int) ($row['baseline_download_bytes'] ?? 0));
            $row['baseline_upload_human'] = $this->formatBytes((int) ($row['baseline_upload_bytes'] ?? 0));
            $row['baseline_uptime_human'] = $this->formatDurationSeconds((int) ($row['baseline_uptime_seconds'] ?? 0));

            return $row;
        } catch (Throwable) {
            return [];
        }
    }

    private function buildBaselineUsage(string $username, array $monitor, array $baseline): array
    {
        $monitorTotal = (int) ($monitor['total_bytes'] ?? 0);
        $monitorDownload = (int) ($monitor['total_download_bytes'] ?? 0);
        $monitorUpload = (int) ($monitor['total_upload_bytes'] ?? 0);
        $monitorUptime = (int) ($monitor['total_uptime_seconds'] ?? 0);

        $hasBaseline = !empty($baseline);
        $baseTotal = $hasBaseline ? (int) ($baseline['baseline_total_bytes'] ?? 0) : 0;
        $baseDownload = $hasBaseline ? (int) ($baseline['baseline_download_bytes'] ?? 0) : 0;
        $baseUpload = $hasBaseline ? (int) ($baseline['baseline_upload_bytes'] ?? 0) : 0;
        $baseUptime = $hasBaseline ? (int) ($baseline['baseline_uptime_seconds'] ?? 0) : 0;

        return [
            'username' => $username,
            'monitor_ok' => !empty($monitor['ok']),
            'has_baseline' => $hasBaseline,
            'baseline_id' => (int) ($baseline['id'] ?? 0),
            'baseline_at' => (string) ($baseline['baseline_at'] ?? ''),
            'current_total_bytes' => $monitorTotal,
            'current_total_human' => $this->formatBytes($monitorTotal),
            'baseline_total_bytes' => $baseTotal,
            'baseline_total_human' => $this->formatBytes($baseTotal),
            'used_since_baseline_bytes' => $hasBaseline ? max(0, $monitorTotal - $baseTotal) : $monitorTotal,
            'used_since_baseline_human' => $hasBaseline ? $this->formatBytes(max(0, $monitorTotal - $baseTotal)) : $this->formatBytes($monitorTotal),
            'download_since_baseline_bytes' => $hasBaseline ? max(0, $monitorDownload - $baseDownload) : $monitorDownload,
            'download_since_baseline_human' => $hasBaseline ? $this->formatBytes(max(0, $monitorDownload - $baseDownload)) : $this->formatBytes($monitorDownload),
            'upload_since_baseline_bytes' => $hasBaseline ? max(0, $monitorUpload - $baseUpload) : $monitorUpload,
            'upload_since_baseline_human' => $hasBaseline ? $this->formatBytes(max(0, $monitorUpload - $baseUpload)) : $this->formatBytes($monitorUpload),
            'uptime_since_baseline_seconds' => $hasBaseline ? max(0, $monitorUptime - $baseUptime) : $monitorUptime,
            'uptime_since_baseline_human' => $hasBaseline ? $this->formatDurationSeconds(max(0, $monitorUptime - $baseUptime)) : $this->formatDurationSeconds($monitorUptime),
            'counter_reset_detected' => $hasBaseline && $monitorTotal < $baseTotal,
            'formula' => $hasBaseline ? 'used = max(0, monitor_total - baseline_total)' : 'no baseline yet: used = monitor_total',
        ];
    }

    private function requireLastPlan(string $username, string $action): array
    {
        $plan = $_SESSION['mikrotik_dry_run_result'] ?? null;

        if (!is_array($plan)) {
            throw new RuntimeException('نفّذ Dry Run ناجح قبل التنفيذ.');
        }

        if ((string) ($plan['username'] ?? '') !== $username) {
            throw new RuntimeException('اسم المستخدم لا يطابق آخر Dry Run.');
        }

        if ((string) ($plan['action'] ?? '') !== $action) {
            throw new RuntimeException('نوع العملية لا يطابق آخر Dry Run.');
        }

        if (!empty($plan['executed'])) {
            throw new RuntimeException('آخر Dry Run تم تنفيذه مسبقاً. أعد إنشاء Dry Run جديد.');
        }

        return $plan;
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

    private function routerBool(string $value): bool
    {
        $value = strtolower(trim($value));

        return in_array($value, ['true', 'yes', '1', 'on'], true);
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

    private function validateUsername(string $username): void
    {
        if ($username === '') {
            return;
        }

        if (strlen($username) > 128) {
            throw new RuntimeException('اسم المستخدم طويل جداً.');
        }

        if (preg_match('/[\r\n\t]/', $username)) {
            throw new RuntimeException('اسم المستخدم يحتوي رموز غير مسموحة.');
        }
    }

    private function auditResponseFromPlan(array $plan): string
    {
        return 'Dry Run. Action: '
            . (string) ($plan['action'] ?? '')
            . '. Backend: '
            . (string) ($plan['recommended_backend'] ?? '')
            . '. Can execute later: '
            . (!empty($plan['can_execute_later']) ? 'YES' : 'NO');
    }

    private function jsonString(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['mikrotik_dry_run_flash_message'] = $message;
        $_SESSION['mikrotik_dry_run_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'mikrotik_dry_run_flash_' . $key;
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