<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
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
        $this->ensureBaselineTables();

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
        $this->ensureBaselineTables();

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

            $plan = $this->buildPlan($action, $username);

            $auditId = $guard->recordDryRun([
                'action' => $action,
                'dataset' => (string) ($plan['dataset'] ?? 'user_manager_monitor'),
                'username' => $username,
                'command' => (string) ($plan['command'] ?? ''),
                'params' => $plan,
                'router_response' => $this->auditResponseFromPlan($plan),
            ]);

            $plan['audit_id'] = $auditId;

            $_SESSION['mikrotik_dry_run_result'] = $plan;

            AppLog::info('GreenNet Baseline Dry Run created', [
                'username' => $username,
                'audit_id' => $auditId,
                'monitor_ok' => !empty($plan['monitor_summary']['ok']),
                'baseline_id' => (int) ($plan['latest_baseline']['id'] ?? 0),
            ]);

            $this->flash('تم إنشاء Dry Run بنجاح. لم يتم تنفيذ أي أمر على MikroTik.', 'success');
        } catch (Throwable $e) {
            $_SESSION['mikrotik_dry_run_result'] = [
                'error' => $e->getMessage(),
                'executed' => false,
            ];

            AppLog::error('GreenNet Baseline Dry Run failed', [
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
        $this->ensureBaselineTables();

        $method = trim((string) ($_POST['method'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $confirm = trim((string) ($_POST['confirm_baseline'] ?? ''));

        try {
            if ($method !== 'create_greennet_baseline') {
                throw new RuntimeException('في S10.2G التنفيذ المسموح فقط هو إنشاء GreenNet Baseline داخل قاعدة البيانات.');
            }

            if ($username === '') {
                throw new RuntimeException('اسم المستخدم مطلوب.');
            }

            $this->validateUsername($username);

            if ($confirm !== 'BASELINE') {
                throw new RuntimeException('لإنشاء Baseline اكتب BASELINE في خانة التأكيد.');
            }

            $lastPlan = $this->requireSuccessfulMonitorDryRun($username);

            /*
             * Fresh read before creating baseline.
             * Do not trust old monitor totals.
             */
            $freshPlan = $this->buildPlan('hotspot_reset_counters', $username);
            $monitor = is_array($freshPlan['monitor_summary'] ?? null) ? $freshPlan['monitor_summary'] : [];

            if (empty($monitor['ok'])) {
                throw new RuntimeException('فشل قراءة User Manager Monitor. لا يمكن إنشاء Baseline بدون أرقام مؤكدة.');
            }

            if ((string) ($freshPlan['recommended_backend'] ?? '') !== 'user-manager') {
                throw new RuntimeException('المستخدم ليس User Manager حسب القراءة الجديدة.');
            }

            $baselineId = $this->createBaseline($username, $freshPlan, $monitor, [
                'reason' => 'manual_admin_baseline',
                'dry_run_audit_id' => (int) ($lastPlan['audit_id'] ?? 0),
            ]);

            $afterPlan = $this->buildPlan('hotspot_reset_counters', $username);
            $afterPlan['baseline_created'] = true;
            $afterPlan['executed'] = false;
            $afterPlan['real_execution'] = false;
            $afterPlan['baseline_result'] = [
                'baseline_id' => $baselineId,
                'username' => $username,
                'baseline_total_human' => (string) ($monitor['total_human'] ?? '0 B'),
                'baseline_total_bytes' => (int) ($monitor['total_bytes'] ?? 0),
                'baseline_download_human' => (string) ($monitor['total_download_human'] ?? '0 B'),
                'baseline_upload_human' => (string) ($monitor['total_upload_human'] ?? '0 B'),
                'baseline_uptime_human' => (string) ($monitor['total_uptime_human'] ?? '0s'),
                'created_at' => date('Y-m-d H:i:s'),
                'note' => 'تم إنشاء Baseline داخل GreenNet فقط. لم يتم تعديل MikroTik.',
            ];
            $afterPlan['notes'][] = 'تم إنشاء Baseline جديد داخل GreenNet.';
            $afterPlan['notes'][] = 'استهلاك المشترك بعد الـ Baseline يجب أن يصبح 0 B داخل GreenNet.';

            $_SESSION['mikrotik_dry_run_result'] = $afterPlan;

            AppLog::info('GreenNet usage baseline created', [
                'username' => $username,
                'baseline_id' => $baselineId,
                'baseline_total_bytes' => (int) ($monitor['total_bytes'] ?? 0),
                'dry_run_audit_id' => (int) ($lastPlan['audit_id'] ?? 0),
            ]);

            $this->flash('تم إنشاء GreenNet Baseline بنجاح. لم يتم تعديل MikroTik.', 'success');
        } catch (Throwable $e) {
            $previous = $_SESSION['mikrotik_dry_run_result'] ?? [];

            if (!is_array($previous)) {
                $previous = [];
            }

            $previous['execute_error'] = $e->getMessage();
            $previous['executed'] = false;
            $previous['real_execution'] = false;

            $_SESSION['mikrotik_dry_run_result'] = $previous;

            AppLog::error('GreenNet usage baseline failed', [
                'username' => $username,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            $this->flash('فشل إنشاء Baseline: ' . $e->getMessage(), 'warning');
        }

        header('Location: /admin/mikrotik-dry-run');
        exit;
    }

    private function buildPlan(string $action, string $username): array
    {
        if ($action !== 'hotspot_reset_counters') {
            return [
                'title' => 'Read Only Dry Run',
                'dataset' => 'mikrotik',
                'action' => $action,
                'username' => $username,
                'command' => 'read-only-placeholder',
                'expected_command' => 'No write command in S10.2G.',
                'router_status' => 'not_checked',
                'recommended_backend' => 'not_checked',
                'executed' => false,
                'can_execute_later' => false,
                'notes' => [
                    'باقي العمليات مؤجلة حالياً.',
                    'S10.2G مخصصة لبناء GreenNet Baseline فقط.',
                ],
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        return $this->buildUserManagerBaselinePlan($username);
    }

    private function buildUserManagerBaselinePlan(string $username): array
    {
        $plan = [
            'title' => 'GreenNet Baseline Usage Engine',
            'dataset' => 'greennet_usage_baseline',
            'action' => 'hotspot_reset_counters',
            'username' => $username,
            'command' => '/user-manager/user/monitor + GreenNet baseline',
            'expected_command' => 'Read only from MikroTik. Optional SQLite baseline only.',
            'router_status' => 'checking',
            'recommended_backend' => 'unknown',
            'executed' => false,
            'can_execute_later' => false,
            'backend_lookup' => [
                'hotspot_local' => $this->emptyBackend('Hotspot Local Users', '/ip/hotspot/user/print'),
                'user_manager' => $this->emptyBackend('User Manager Users', '/user-manager/user/print'),
                'hotspot_active' => $this->emptyBackend('Hotspot Active Sessions', '/ip/hotspot/active/print'),
            ],
            'user_manager_user' => null,
            'monitor_summary' => null,
            'session_summary' => null,
            'latest_baseline' => null,
            'baseline_usage' => null,
            'comparison' => null,
            'reset_policy' => null,
            'notes' => [
                'هذه المرحلة لا تكتب على MikroTik.',
                'User Manager Monitor هو مصدر العدادات الأساسي.',
                'GreenNet Baseline يحسب الاستهلاك الجديد بدون حذف sessions وبدون تصفير MikroTik.',
            ],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $hotspot = $this->lookupRouterBackend(
            'Hotspot Local Users',
            '/ip/hotspot/user/print',
            ['?name' => $username],
            $username,
            ['name']
        );

        $userManager = $this->lookupRouterBackend(
            'User Manager Users',
            '/user-manager/user/print',
            ['?name' => $username],
            $username,
            ['name', 'username']
        );

        $active = $this->lookupRouterBackend(
            'Hotspot Active Sessions',
            '/ip/hotspot/active/print',
            ['?user' => $username],
            $username,
            ['user', 'name']
        );

        $plan['backend_lookup'] = [
            'hotspot_local' => $hotspot,
            'user_manager' => $userManager,
            'hotspot_active' => $active,
        ];

        $foundHotspot = (bool) ($hotspot['found'] ?? false);
        $foundUserManager = (bool) ($userManager['found'] ?? false);
        $foundActive = (bool) ($active['found'] ?? false);

        $plan['found_hotspot_local'] = $foundHotspot;
        $plan['found_user_manager'] = $foundUserManager;
        $plan['found_hotspot_active'] = $foundActive;
        $plan['recommended_backend'] = $this->recommendedBackend($foundHotspot, $foundUserManager, $foundActive);
        $plan['router_status'] = $this->routerStatusFromBackends($plan['backend_lookup']);

        if ($foundUserManager) {
            $umRow = is_array($userManager['matched_raw_row'] ?? null) ? $userManager['matched_raw_row'] : [];
            $umId = trim((string) ($umRow['.id'] ?? ''));

            $plan['user_manager_resolved_id'] = $umId;
            $plan['user_manager_user'] = $this->cleanUserManagerRow($umRow);

            $monitor = $this->readUserManagerMonitor($username, $umId);
            $sessions = $this->buildUserManagerSessionSummary($username);
            $latestBaseline = $this->latestBaseline($username);

            $plan['monitor_summary'] = $monitor;
            $plan['session_summary'] = $sessions;
            $plan['latest_baseline'] = $latestBaseline;
            $plan['baseline_usage'] = $this->buildBaselineUsage($username, $monitor, $latestBaseline);
            $plan['comparison'] = $this->buildUsageComparison($monitor, $sessions, $latestBaseline);
            $plan['reset_policy'] = $this->buildResetPolicy($username, $monitor, $latestBaseline);
            $plan['can_execute_later'] = !empty($monitor['ok']);

            if (!empty($monitor['ok'])) {
                $plan['notes'][] = 'تمت قراءة Monitor بنجاح، ويمكن إنشاء Baseline من الأرقام الحالية.';
            } else {
                $plan['notes'][] = 'لم تنجح قراءة Monitor، لا تنشئ Baseline قبل حل المشكلة.';
            }

            if (!empty($latestBaseline)) {
                $plan['notes'][] = 'يوجد Baseline سابق لهذا المستخدم.';
            } else {
                $plan['notes'][] = 'لا يوجد Baseline سابق. الاستهلاك الحالي داخل GreenNet يساوي Monitor Total.';
            }
        }

        if ($plan['recommended_backend'] === 'hotspot-local') {
            $plan['notes'][] = 'المستخدم Hotspot Local وليس User Manager. Baseline الحالي مخصص لـ User Manager Monitor.';
        } elseif ($plan['recommended_backend'] === 'user-manager') {
            $plan['notes'][] = 'المستخدم User Manager. سيتم حساب الاستهلاك من Monitor - Baseline.';
        } elseif ($plan['recommended_backend'] === 'ambiguous') {
            $plan['notes'][] = 'المستخدم موجود في أكثر من مصدر. يجب تحديد Backend قبل أي اعتماد نهائي.';
        } elseif ($plan['recommended_backend'] === 'not-found') {
            $plan['notes'][] = 'لم يتم العثور على المستخدم.';
        }

        return $plan;
    }

    private function requireSuccessfulMonitorDryRun(string $username): array
    {
        $plan = $_SESSION['mikrotik_dry_run_result'] ?? null;

        if (!is_array($plan)) {
            throw new RuntimeException('نفّذ Dry Run ناجح قبل إنشاء Baseline.');
        }

        if ((string) ($plan['username'] ?? '') !== $username) {
            throw new RuntimeException('اسم المستخدم لا يطابق آخر Dry Run.');
        }

        if ((string) ($plan['recommended_backend'] ?? '') !== 'user-manager') {
            throw new RuntimeException('آخر Dry Run لا يثبت أن المستخدم User Manager.');
        }

        $monitor = is_array($plan['monitor_summary'] ?? null) ? $plan['monitor_summary'] : [];

        if (empty($monitor['ok'])) {
            throw new RuntimeException('آخر Dry Run لم يقرأ Monitor بنجاح.');
        }

        return $plan;
    }

    private function ensureBaselineTables(): void
    {
        $pdo = $this->pdo();

        $pdo->exec("
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

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_greennet_usage_baselines_username
            ON greennet_usage_baselines(username, id)
        ");
    }

    private function latestBaseline(string $username): array
    {
        $stmt = $this->pdo()->prepare("
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
        $row['baseline_uptime_human'] = $this->formatDuration((int) ($row['baseline_uptime_seconds'] ?? 0));

        return $row;
    }

    private function createBaseline(string $username, array $plan, array $monitor, array $options = []): int
    {
        $stmt = $this->pdo()->prepare("
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

        $now = date('Y-m-d H:i:s');

        $stmt->execute([
            ':username' => $username,
            ':backend' => 'user-manager',
            ':router_user_id' => (string) ($plan['user_manager_resolved_id'] ?? ''),
            ':baseline_download_bytes' => (int) ($monitor['total_download_bytes'] ?? 0),
            ':baseline_upload_bytes' => (int) ($monitor['total_upload_bytes'] ?? 0),
            ':baseline_total_bytes' => (int) ($monitor['total_bytes'] ?? 0),
            ':baseline_uptime_seconds' => (int) ($monitor['total_uptime_seconds'] ?? 0),
            ':baseline_monitor_raw' => $this->jsonString($monitor['raw_row'] ?? []),
            ':baseline_at' => $now,
            ':reason' => (string) ($options['reason'] ?? 'manual_baseline'),
            ':dry_run_audit_id' => (int) ($options['dry_run_audit_id'] ?? 0),
            ':created_by' => (string) ($_SESSION['admin_username'] ?? 'admin'),
            ':created_at' => $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function buildBaselineUsage(string $username, array $monitor, array $baseline): array
    {
        $monitorOk = !empty($monitor['ok']);

        $currentDownload = (int) ($monitor['total_download_bytes'] ?? 0);
        $currentUpload = (int) ($monitor['total_upload_bytes'] ?? 0);
        $currentTotal = (int) ($monitor['total_bytes'] ?? 0);
        $currentUptime = (int) ($monitor['total_uptime_seconds'] ?? 0);

        $baseDownload = (int) ($baseline['baseline_download_bytes'] ?? 0);
        $baseUpload = (int) ($baseline['baseline_upload_bytes'] ?? 0);
        $baseTotal = (int) ($baseline['baseline_total_bytes'] ?? 0);
        $baseUptime = (int) ($baseline['baseline_uptime_seconds'] ?? 0);

        $downloadDelta = max(0, $currentDownload - $baseDownload);
        $uploadDelta = max(0, $currentUpload - $baseUpload);
        $totalDelta = max(0, $currentTotal - $baseTotal);
        $uptimeDelta = max(0, $currentUptime - $baseUptime);

        return [
            'username' => $username,
            'monitor_ok' => $monitorOk,
            'has_baseline' => !empty($baseline),
            'baseline_id' => (int) ($baseline['id'] ?? 0),
            'baseline_at' => (string) ($baseline['baseline_at'] ?? ''),
            'current_total_bytes' => $currentTotal,
            'current_total_human' => $this->formatBytes($currentTotal),
            'baseline_total_bytes' => $baseTotal,
            'baseline_total_human' => $this->formatBytes($baseTotal),
            'used_since_baseline_bytes' => !empty($baseline) ? $totalDelta : $currentTotal,
            'used_since_baseline_human' => !empty($baseline) ? $this->formatBytes($totalDelta) : $this->formatBytes($currentTotal),
            'download_since_baseline_bytes' => !empty($baseline) ? $downloadDelta : $currentDownload,
            'download_since_baseline_human' => !empty($baseline) ? $this->formatBytes($downloadDelta) : $this->formatBytes($currentDownload),
            'upload_since_baseline_bytes' => !empty($baseline) ? $uploadDelta : $currentUpload,
            'upload_since_baseline_human' => !empty($baseline) ? $this->formatBytes($uploadDelta) : $this->formatBytes($currentUpload),
            'uptime_since_baseline_seconds' => !empty($baseline) ? $uptimeDelta : $currentUptime,
            'uptime_since_baseline_human' => !empty($baseline) ? $this->formatDuration($uptimeDelta) : $this->formatDuration($currentUptime),
            'counter_reset_detected' => !empty($baseline) && $currentTotal < $baseTotal,
            'formula' => !empty($baseline)
                ? 'used = max(0, monitor_total - baseline_total)'
                : 'no baseline yet: used = monitor_total',
        ];
    }

    private function readUserManagerMonitor(string $username, string $userManagerId): array
    {
        $summary = [
            'ok' => false,
            'status' => 'not_checked',
            'command' => '/user-manager/user/monitor',
            'username' => $username,
            'user_manager_id' => $userManagerId,
            'attempts' => [],
            'rows_count' => 0,
            'raw_row' => null,
            'error' => '',
            'total_uptime_raw' => '',
            'total_uptime_seconds' => 0,
            'total_uptime_human' => '0s',
            'total_download_raw' => '',
            'total_download_bytes' => 0,
            'total_download_human' => '0 B',
            'total_upload_raw' => '',
            'total_upload_bytes' => 0,
            'total_upload_human' => '0 B',
            'total_bytes' => 0,
            'total_human' => '0 B',
            'active_sessions_raw' => '',
            'active_sessions' => 0,
        ];

        $attempts = [];

        if ($userManagerId !== '') {
            $attempts[] = [
                'label' => 'monitor by .id',
                'params' => [
                    'numbers' => $userManagerId,
                    'once' => '',
                ],
            ];
        }

        $attempts[] = [
            'label' => 'monitor by username',
            'params' => [
                'numbers' => $username,
                'once' => '',
            ],
        ];

        $lastError = '';

        foreach ($attempts as $attempt) {
            $label = (string) ($attempt['label'] ?? 'monitor');
            $params = is_array($attempt['params'] ?? null) ? $attempt['params'] : [];

            try {
                $client = new RouterOSApiClient([
                    'timeout' => 5,
                ]);

                try {
                    $rows = $client->comm('/user-manager/user/monitor', $params);
                } finally {
                    $client->disconnect();
                }

                $rows = $this->normalizeRows($rows);

                $summary['attempts'][] = [
                    'label' => $label,
                    'params' => $params,
                    'ok' => true,
                    'rows_count' => count($rows),
                    'error' => '',
                ];

                if (count($rows) === 0) {
                    continue;
                }

                $row = $this->pickMonitorRow($rows, $username);

                $uptimeRaw = $this->firstExistingValue($row, [
                    'total-uptime',
                    'uptime',
                    'total-time',
                ]);

                $downloadRaw = $this->firstExistingValue($row, [
                    'total-download',
                    'download',
                    'download-used',
                    'total-bytes-out',
                    'bytes-out',
                ]);

                $uploadRaw = $this->firstExistingValue($row, [
                    'total-upload',
                    'upload',
                    'upload-used',
                    'total-bytes-in',
                    'bytes-in',
                ]);

                $activeRaw = $this->firstExistingValue($row, [
                    'active-sessions',
                    'active-session',
                    'sessions',
                ]);

                $downloadBytes = $this->parseBytesToInt($downloadRaw);
                $uploadBytes = $this->parseBytesToInt($uploadRaw);
                $uptimeSeconds = $this->parseDurationToSeconds($uptimeRaw);

                $summary['ok'] = true;
                $summary['status'] = 'has_rows';
                $summary['rows_count'] = count($rows);
                $summary['raw_row'] = $this->sanitizeRowForDisplay($row);
                $summary['total_uptime_raw'] = $uptimeRaw;
                $summary['total_uptime_seconds'] = $uptimeSeconds;
                $summary['total_uptime_human'] = $this->formatDuration($uptimeSeconds);
                $summary['total_download_raw'] = $downloadRaw;
                $summary['total_download_bytes'] = $downloadBytes;
                $summary['total_download_human'] = $this->formatBytes($downloadBytes);
                $summary['total_upload_raw'] = $uploadRaw;
                $summary['total_upload_bytes'] = $uploadBytes;
                $summary['total_upload_human'] = $this->formatBytes($uploadBytes);
                $summary['total_bytes'] = $downloadBytes + $uploadBytes;
                $summary['total_human'] = $this->formatBytes($downloadBytes + $uploadBytes);
                $summary['active_sessions_raw'] = $activeRaw;
                $summary['active_sessions'] = $this->parseInteger($activeRaw);

                return $summary;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();

                $summary['attempts'][] = [
                    'label' => $label,
                    'params' => $params,
                    'ok' => false,
                    'rows_count' => 0,
                    'error' => $lastError,
                ];
            }
        }

        $summary['ok'] = false;
        $summary['status'] = $lastError !== '' ? $this->backendErrorStatus($lastError) : 'empty';
        $summary['error'] = $lastError !== '' ? $lastError : 'No monitor rows returned.';

        return $summary;
    }

    private function buildUserManagerSessionSummary(string $username): array
    {
        $summary = [
            'ok' => false,
            'status' => 'not_checked',
            'command' => '/user-manager/session/print',
            'username' => $username,
            'error' => '',
            'sessions_count' => 0,
            'active_sessions' => 0,
            'closed_sessions' => 0,
            'total_download_bytes' => 0,
            'total_download_human' => '0 B',
            'total_upload_bytes' => 0,
            'total_upload_human' => '0 B',
            'total_bytes' => 0,
            'total_human' => '0 B',
            'total_uptime_seconds' => 0,
            'total_uptime_human' => '0s',
            'sessions_preview' => [],
        ];

        try {
            $client = new RouterOSApiClient([
                'timeout' => 4,
            ]);

            try {
                $rows = $client->comm('/user-manager/session/print', [
                    '?user' => $username,
                ]);
            } finally {
                $client->disconnect();
            }

            $rows = $this->normalizeRows($rows);

            $download = 0;
            $upload = 0;
            $uptime = 0;
            $active = 0;

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $download += $this->numericField($row, [
                    'download',
                    'download-used',
                    'bytes-out',
                    'acct-output-octets',
                    'output-octets',
                ]);

                $upload += $this->numericField($row, [
                    'upload',
                    'upload-used',
                    'bytes-in',
                    'acct-input-octets',
                    'input-octets',
                ]);

                $uptime += $this->durationFieldSeconds($row, [
                    'uptime',
                    'session-time',
                    'acct-session-time',
                ]);

                if ($this->isActiveSession($row)) {
                    $active++;
                }
            }

            $summary['ok'] = true;
            $summary['status'] = count($rows) > 0 ? 'has_rows' : 'empty';
            $summary['sessions_count'] = count($rows);
            $summary['active_sessions'] = $active;
            $summary['closed_sessions'] = max(0, count($rows) - $active);
            $summary['total_download_bytes'] = $download;
            $summary['total_download_human'] = $this->formatBytes($download);
            $summary['total_upload_bytes'] = $upload;
            $summary['total_upload_human'] = $this->formatBytes($upload);
            $summary['total_bytes'] = $download + $upload;
            $summary['total_human'] = $this->formatBytes($download + $upload);
            $summary['total_uptime_seconds'] = $uptime;
            $summary['total_uptime_human'] = $this->formatDuration($uptime);
            $summary['sessions_preview'] = $this->sanitizeRowsPreview($rows, 10);

            return $summary;
        } catch (Throwable $e) {
            $summary['ok'] = false;
            $summary['status'] = $this->backendErrorStatus($e->getMessage());
            $summary['error'] = $e->getMessage();

            return $summary;
        }
    }

    private function buildUsageComparison(array $monitor, array $sessions, array $baseline): array
    {
        $monitorBytes = (int) ($monitor['total_bytes'] ?? 0);
        $sessionBytes = (int) ($sessions['total_bytes'] ?? 0);
        $baselineBytes = (int) ($baseline['baseline_total_bytes'] ?? 0);

        return [
            'source_of_truth' => !empty($monitor['ok']) ? 'user_manager_monitor' : 'unknown',
            'monitor_ok' => !empty($monitor['ok']),
            'sessions_ok' => !empty($sessions['ok']),
            'has_baseline' => !empty($baseline),
            'monitor_total_bytes' => $monitorBytes,
            'monitor_total_human' => $this->formatBytes($monitorBytes),
            'sessions_total_bytes' => $sessionBytes,
            'sessions_total_human' => $this->formatBytes($sessionBytes),
            'baseline_total_bytes' => $baselineBytes,
            'baseline_total_human' => $this->formatBytes($baselineBytes),
            'monitor_minus_sessions_bytes' => max(0, $monitorBytes - $sessionBytes),
            'monitor_minus_sessions_human' => $this->formatBytes(max(0, $monitorBytes - $sessionBytes)),
            'decision' => !empty($monitor['ok'])
                ? 'اعتمد Monitor كمصدر الحقيقة، واحسب الاستهلاك داخل GreenNet من Monitor - Baseline.'
                : 'لا تعتمد الحسابات قبل نجاح Monitor.',
        ];
    }

    private function buildResetPolicy(string $username, array $monitor, array $baseline): array
    {
        return [
            'username' => $username,
            'read_only' => true,
            'mikrotik_write_required' => false,
            'recommended_strategy' => 'greennet_baseline',
            'usage_source' => !empty($monitor['ok']) ? 'user_manager_monitor' : 'unknown',
            'baseline_status' => !empty($baseline) ? 'exists' : 'missing',
            'why' => [
                'Monitor يقرأ نفس أرقام Winbox Users.',
                'Sessions تفاصيل فقط ولا تكفي للتصفير.',
                'Baseline يحافظ على بيانات MikroTik كاملة ويعطي تصفير منطقي داخل GreenNet.',
            ],
            'next_step' => 'ربط هذا الحساب مع لوحة المشترك والتجديدات ليتم إنشاء Baseline عند التجديد.',
        ];
    }

    private function lookupRouterBackend(
        string $label,
        string $command,
        array $params,
        string $username,
        array $matchKeys
    ): array {
        $result = $this->emptyBackend($label, $command);
        $result['params'] = $params;

        try {
            $client = new RouterOSApiClient([
                'timeout' => 4,
            ]);

            try {
                $rows = $client->comm($command, $params);
            } finally {
                $client->disconnect();
            }

            $rows = $this->normalizeRows($rows);
            $matched = $this->findMatchingRow($rows, $username, $matchKeys);

            $result['ok'] = true;
            $result['available'] = true;
            $result['status'] = $matched !== null ? 'found' : 'not_found';
            $result['found'] = $matched !== null;
            $result['rows_count'] = count($rows);
            $result['matched_raw_row'] = $matched ?? null;
            $result['matched_row'] = is_array($matched) ? $this->sanitizeRowForDisplay($matched) : null;
            $result['matched_id'] = is_array($matched) ? (string) ($matched['.id'] ?? '') : '';
            $result['error'] = '';

            return $result;
        } catch (Throwable $e) {
            $message = $e->getMessage();

            $result['ok'] = false;
            $result['available'] = false;
            $result['status'] = $this->backendErrorStatus($message);
            $result['found'] = false;
            $result['rows_count'] = 0;
            $result['matched_raw_row'] = null;
            $result['matched_row'] = null;
            $result['matched_id'] = '';
            $result['error'] = $message;

            return $result;
        }
    }

    private function emptyBackend(string $label, string $command): array
    {
        return [
            'label' => $label,
            'command' => $command,
            'params' => [],
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

    private function recommendedBackend(bool $foundHotspot, bool $foundUserManager, bool $foundActive): string
    {
        if ($foundHotspot && !$foundUserManager) {
            return 'hotspot-local';
        }

        if (!$foundHotspot && $foundUserManager) {
            return 'user-manager';
        }

        if ($foundHotspot && $foundUserManager) {
            return 'ambiguous';
        }

        if (!$foundHotspot && !$foundUserManager && $foundActive) {
            return 'active-only';
        }

        return 'not-found';
    }

    private function routerStatusFromBackends(array $backends): string
    {
        $found = false;
        $checked = false;
        $unreachable = true;

        foreach ($backends as $backend) {
            if (!is_array($backend)) {
                continue;
            }

            $status = (string) ($backend['status'] ?? 'not_checked');

            if ($status !== 'not_checked') {
                $checked = true;
            }

            if (!in_array($status, ['unreachable', 'not_checked'], true)) {
                $unreachable = false;
            }

            if (!empty($backend['found'])) {
                $found = true;
            }
        }

        if ($found) {
            return 'found';
        }

        if ($checked && $unreachable) {
            return 'unreachable';
        }

        if ($checked) {
            return 'not_found';
        }

        return 'not_checked';
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

    private function findMatchingRow(array $rows, string $username, array $keys): ?array
    {
        foreach ($rows as $row) {
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

    private function firstExistingValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return (string) $row[$key];
            }
        }

        return '';
    }

    private function cleanUserManagerRow(array $row): array
    {
        $allowed = [
            '.id',
            'name',
            'username',
            'actual-profile',
            'group',
            'disabled',
            'comment',
            'shared-users',
            'attributes',
            'customer',
        ];

        $clean = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $row)) {
                $clean[$key] = (string) $row[$key];
            }
        }

        return $clean;
    }

    private function sanitizeRowsPreview(array $rows, int $limit = 12): array
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

    private function numericField(array $row, array $keys): int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            return $this->parseBytesToInt((string) $row[$key]);
        }

        return 0;
    }

    private function durationFieldSeconds(array $row, array $keys): int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            return $this->parseDurationToSeconds((string) $row[$key]);
        }

        return 0;
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

    private function isActiveSession(array $row): bool
    {
        $active = strtolower((string) ($row['active'] ?? ''));

        if (in_array($active, ['true', 'yes', '1'], true)) {
            return true;
        }

        if (in_array($active, ['false', 'no', '0'], true)) {
            return false;
        }

        $status = strtolower((string) ($row['status'] ?? ''));

        if ($status === '') {
            return false;
        }

        if (str_contains($status, 'stop') || str_contains($status, 'close') || str_contains($status, 'ended')) {
            return false;
        }

        return str_contains($status, 'start') || str_contains($status, 'active');
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

    private function formatDuration(int $seconds): string
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
        $backend = (string) ($plan['recommended_backend'] ?? '');
        $monitor = is_array($plan['monitor_summary'] ?? null) ? $plan['monitor_summary'] : [];
        $usage = is_array($plan['baseline_usage'] ?? null) ? $plan['baseline_usage'] : [];

        return 'Dry Run only. S10.2G GreenNet Baseline. Backend: '
            . $backend
            . '. Monitor: '
            . (!empty($monitor['ok']) ? 'OK' : 'FAILED')
            . '. Used since baseline: '
            . (string) ($usage['used_since_baseline_human'] ?? 'unknown');
    }

    private function pdo(): PDO
    {
        foreach (['pdo', 'connection', 'getConnection', 'getPdo'] as $method) {
            if (method_exists(Database::class, $method)) {
                $pdo = Database::$method();

                if ($pdo instanceof PDO) {
                    return $pdo;
                }
            }
        }

        throw new RuntimeException('لم أستطع الوصول إلى PDO من GreenNet\\Core\\Database.');
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