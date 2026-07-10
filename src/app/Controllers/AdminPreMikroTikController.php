<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use PDO;
use Throwable;

class AdminPreMikroTikController
{
    private string $safetyTable = 'greennet_write_safety_settings';
    private string $queueTable = 'mikrotik_transaction_queue';
    private string $apiAuditTable = 'api_audit_logs';

    public function audit(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensurePreMikroTikTables();

        $q = trim((string) ($_GET['q'] ?? ''));
        $source = trim((string) ($_GET['source'] ?? 'all'));
        $severity = trim((string) ($_GET['severity'] ?? 'all'));

        return View::render('admin/audit_center', [
            'title' => 'Audit Center',
            'events' => $this->auditEvents($q, $source, $severity),
            'counts' => $this->auditCounts(),
            'q' => $q,
            'source' => $source,
            'severity' => $severity,
        ]);
    }

    public function globalSearch(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensurePreMikroTikTables();

        $q = trim((string) ($_GET['q'] ?? ''));

        return View::render('admin/global_search', [
            'title' => 'Global Search',
            'q' => $q,
            'groups' => $q !== '' ? $this->globalSearchResults($q) : [],
        ]);
    }

    public function dashboardWidgets(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensurePreMikroTikTables();

        return View::render('admin/dashboard_widgets', [
            'title' => 'Dashboard Widgets',
            'widgets' => $this->dashboardData(),
        ]);
    }

    public function setupWizard(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensurePreMikroTikTables();

        return View::render('admin/setup_wizard', [
            'title' => 'Setup Wizard النهائي',
            'steps' => $this->setupSteps(),
        ]);
    }

    public function writeSafety(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensurePreMikroTikTables();

        return View::render('admin/write_safety', [
            'title' => 'Write Safety',
            'settings' => $this->safetySettings(),
            'queue' => $this->queueItems(),
            'api_audits' => $this->apiAuditItems(),
            'message' => $this->consumeFlash('message'),
            'message_type' => $this->consumeFlash('type', 'success'),
        ]);
    }

    public function saveWriteSafety(): void
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensurePreMikroTikTables();

        $settings = [
            'mikrotik_write_enabled' => isset($_POST['mikrotik_write_enabled']) ? 'true' : 'false',
            'greennet_safe_mode' => isset($_POST['greennet_safe_mode']) ? 'true' : 'false',
            'backup_guard_enabled' => isset($_POST['backup_guard_enabled']) ? 'true' : 'false',
            'dry_run_required' => isset($_POST['dry_run_required']) ? 'true' : 'false',
            'confirm_required' => isset($_POST['confirm_required']) ? 'true' : 'false',
            'transaction_queue_enabled' => isset($_POST['transaction_queue_enabled']) ? 'true' : 'false',
        ];

        foreach ($settings as $key => $value) {
            $this->saveSafetySetting($key, $value);
        }

        AppLog::info('تم تحديث إعدادات Write Safety', [
            'admin' => (string) ($_SESSION['admin_username'] ?? 'admin'),
            'settings' => $settings,
        ]);

        $this->flash('تم حفظ إعدادات Write Safety. لا يوجد أي تنفيذ على MikroTik حتى الآن.', 'success');
        header('Location: /admin/write-safety');
        exit;
    }

    private function auditEvents(string $q, string $source, string $severity): array
    {
        $events = [];

        $this->collectAppLogsAudit($events, $q, $source, $severity);
        $this->collectNotificationsAudit($events, $q, $source, $severity);
        $this->collectRenewalAudit($events, $q, $source, $severity);
        $this->collectPaymentsAudit($events, $q, $source, $severity);
        $this->collectTimelineNotesAudit($events, $q, $source, $severity);
        $this->collectApiAudit($events, $q, $source, $severity);
        $this->collectQueueAudit($events, $q, $source, $severity);

        usort($events, static function (array $a, array $b): int {
            return (int) ($b['_sort'] ?? 0) <=> (int) ($a['_sort'] ?? 0);
        });

        return array_slice($events, 0, 300);
    }

    private function collectAppLogsAudit(array &$events, string $q, string $source, string $severity): void
    {
        if (!$this->tableExists('app_logs') || ($source !== 'all' && $source !== 'app_logs')) {
            return;
        }

        foreach ($this->fetchRecent('app_logs', 250) as $row) {
            $message = $this->rowValue($row, ['message', 'title', 'event', 'action'], 'Log event');
            $level = strtolower($this->rowValue($row, ['level', 'type', 'severity'], 'info'));
            $eventSeverity = $this->severityFromText($level . ' ' . $message);

            if (!$this->matchesAuditFilter($row, $message, 'app_logs', $eventSeverity, $q, $severity)) {
                continue;
            }

            $this->addAuditEvent($events, [
                'source' => 'app_logs',
                'severity' => $eventSeverity,
                'title' => $message,
                'message' => $this->rowValue($row, ['context', 'context_json', 'metadata', 'data'], ''),
                'username' => $this->extractUsername($row),
                'time' => $this->rowValue($row, ['created_at', 'time'], date('Y-m-d H:i:s')),
                'link_url' => '/admin/logs',
                'source_id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    private function collectNotificationsAudit(array &$events, string $q, string $source, string $severity): void
    {
        if (!$this->tableExists('admin_notifications') || ($source !== 'all' && $source !== 'admin_notifications')) {
            return;
        }

        foreach ($this->fetchRecent('admin_notifications', 250) as $row) {
            $eventSeverity = $this->normalizeSeverity((string) ($row['severity'] ?? 'info'));
            $title = $this->rowValue($row, ['title'], 'Notification');

            if (!$this->matchesAuditFilter($row, $title, 'admin_notifications', $eventSeverity, $q, $severity)) {
                continue;
            }

            $this->addAuditEvent($events, [
                'source' => 'admin_notifications',
                'severity' => $eventSeverity,
                'title' => $title,
                'message' => $this->rowValue($row, ['message'], ''),
                'username' => $this->rowValue($row, ['related_username'], ''),
                'time' => $this->rowValue($row, ['created_at'], date('Y-m-d H:i:s')),
                'link_url' => $this->rowValue($row, ['link_url'], '/admin/notifications'),
                'source_id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    private function collectRenewalAudit(array &$events, string $q, string $source, string $severity): void
    {
        if (!$this->tableExists('renewal_requests') || ($source !== 'all' && $source !== 'renewal_requests')) {
            return;
        }

        foreach ($this->fetchRecent('renewal_requests', 250) as $row) {
            $status = (string) ($row['status'] ?? 'pending');
            $eventSeverity = match ($status) {
                'completed' => 'success',
                'rejected' => 'warning',
                default => 'info',
            };

            $username = $this->rowValue($row, ['username'], '');
            $title = 'طلب تجديد - ' . $this->renewalStatusLabel($status);
            $message = 'المشترك: ' . $username;

            if (($row['package_name'] ?? '') !== '') {
                $message .= "\n" . 'الباقة: ' . (string) $row['package_name'];
            }

            if (($row['message'] ?? '') !== '') {
                $message .= "\n" . 'رسالة: ' . (string) $row['message'];
            }

            if (!$this->matchesAuditFilter($row, $title . ' ' . $message, 'renewal_requests', $eventSeverity, $q, $severity)) {
                continue;
            }

            $this->addAuditEvent($events, [
                'source' => 'renewal_requests',
                'severity' => $eventSeverity,
                'title' => $title,
                'message' => $message,
                'username' => $username,
                'time' => $this->rowValue($row, ['created_at'], date('Y-m-d H:i:s')),
                'link_url' => '/admin/renewal-requests?q=' . urlencode($username),
                'source_id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    private function collectPaymentsAudit(array &$events, string $q, string $source, string $severity): void
    {
        if (!$this->tableExists('payments') || ($source !== 'all' && $source !== 'payments')) {
            return;
        }

        foreach ($this->fetchRecent('payments', 250) as $row) {
            $username = $this->rowValue($row, ['username'], '');
            $amount = $this->rowValue($row, ['amount'], '0');
            $currency = $this->rowValue($row, ['currency'], '');
            $packageName = $this->rowValue($row, ['package_name', 'package'], '');

            $message = 'المبلغ: ' . $amount . ' ' . $currency;

            if ($packageName !== '') {
                $message .= "\n" . 'الباقة: ' . $packageName;
            }

            if (!$this->matchesAuditFilter($row, $message, 'payments', 'success', $q, $severity)) {
                continue;
            }

            $this->addAuditEvent($events, [
                'source' => 'payments',
                'severity' => 'success',
                'title' => 'دفعة مسجلة',
                'message' => $message,
                'username' => $username,
                'time' => $this->rowValue($row, ['created_at', 'paid_at', 'date'], date('Y-m-d H:i:s')),
                'link_url' => '/admin/payments',
                'source_id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    private function collectTimelineNotesAudit(array &$events, string $q, string $source, string $severity): void
    {
        if (!$this->tableExists('customer_timeline_notes') || ($source !== 'all' && $source !== 'customer_timeline_notes')) {
            return;
        }

        foreach ($this->fetchRecent('customer_timeline_notes', 250) as $row) {
            $eventSeverity = $this->normalizeSeverity((string) ($row['severity'] ?? 'info'));
            $username = $this->rowValue($row, ['username'], '');
            $title = $this->rowValue($row, ['title'], 'ملاحظة إدارية');
            $message = $this->rowValue($row, ['note'], '');

            if (!$this->matchesAuditFilter($row, $title . ' ' . $message, 'customer_timeline_notes', $eventSeverity, $q, $severity)) {
                continue;
            }

            $this->addAuditEvent($events, [
                'source' => 'customer_timeline_notes',
                'severity' => $eventSeverity,
                'title' => $title,
                'message' => $message . "\n" . 'بواسطة: ' . $this->rowValue($row, ['admin_username'], 'admin'),
                'username' => $username,
                'time' => $this->rowValue($row, ['created_at'], date('Y-m-d H:i:s')),
                'link_url' => '/admin/customers/timeline?username=' . urlencode($username),
                'source_id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    private function collectApiAudit(array &$events, string $q, string $source, string $severity): void
    {
        if (!$this->tableExists($this->apiAuditTable) || ($source !== 'all' && $source !== $this->apiAuditTable)) {
            return;
        }

        foreach ($this->fetchRecent($this->apiAuditTable, 250) as $row) {
            $success = (int) ($row['success'] ?? 0) === 1;
            $dryRun = (int) ($row['dry_run'] ?? 1) === 1;
            $eventSeverity = $success ? 'success' : 'danger';

            $title = ($dryRun ? 'Dry Run: ' : 'API Execute: ') . $this->rowValue($row, ['action', 'command'], 'MikroTik API');

            if (!$this->matchesAuditFilter($row, $title, $this->apiAuditTable, $eventSeverity, $q, $severity)) {
                continue;
            }

            $this->addAuditEvent($events, [
                'source' => $this->apiAuditTable,
                'severity' => $eventSeverity,
                'title' => $title,
                'message' => $this->rowValue($row, ['params', 'router_response'], ''),
                'username' => $this->rowValue($row, ['username'], ''),
                'time' => $this->rowValue($row, ['created_at'], date('Y-m-d H:i:s')),
                'link_url' => '/admin/write-safety',
                'source_id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    private function collectQueueAudit(array &$events, string $q, string $source, string $severity): void
    {
        if (!$this->tableExists($this->queueTable) || ($source !== 'all' && $source !== $this->queueTable)) {
            return;
        }

        foreach ($this->fetchRecent($this->queueTable, 250) as $row) {
            $status = strtolower($this->rowValue($row, ['status'], 'pending'));

            $eventSeverity = match ($status) {
                'success', 'done' => 'success',
                'failed', 'error' => 'danger',
                'retry' => 'warning',
                default => 'info',
            };

            $title = 'Queue: ' . $this->rowValue($row, ['action'], 'pending action');

            if (!$this->matchesAuditFilter($row, $title, $this->queueTable, $eventSeverity, $q, $severity)) {
                continue;
            }

            $this->addAuditEvent($events, [
                'source' => $this->queueTable,
                'severity' => $eventSeverity,
                'title' => $title,
                'message' => $this->rowValue($row, ['payload', 'last_error'], ''),
                'username' => $this->rowValue($row, ['username'], ''),
                'time' => $this->rowValue($row, ['created_at'], date('Y-m-d H:i:s')),
                'link_url' => '/admin/write-safety',
                'source_id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    private function addAuditEvent(array &$events, array $event): void
    {
        $time = (string) (($event['time'] ?? '') !== '' ? $event['time'] : date('Y-m-d H:i:s'));
        $sort = strtotime($time);

        $severity = $this->normalizeSeverity((string) ($event['severity'] ?? 'info'));

        $events[] = [
            'source' => (string) ($event['source'] ?? ''),
            'source_id' => (int) ($event['source_id'] ?? 0),
            'severity' => $severity,
            'severity_label' => $this->severityLabel($severity),
            'severity_badge' => $this->severityBadge($severity),
            'title' => (string) ($event['title'] ?? 'Audit Event'),
            'message' => (string) ($event['message'] ?? ''),
            'username' => (string) ($event['username'] ?? ''),
            'time' => $time,
            'link_url' => (string) ($event['link_url'] ?? ''),
            '_sort' => $sort !== false ? $sort : 0,
        ];
    }

    private function matchesAuditFilter(array $row, string $text, string $source, string $eventSeverity, string $q, string $severity): bool
    {
        if ($severity !== 'all' && $severity !== '' && $eventSeverity !== $severity) {
            return false;
        }

        if ($q === '') {
            return true;
        }

        $haystack = mb_strtolower($text . ' ' . $source . ' ' . implode(' ', array_map(static function ($value): string {
            return is_scalar($value) ? (string) $value : '';
        }, $row)));

        return str_contains($haystack, mb_strtolower($q));
    }

    private function auditCounts(): array
    {
        return [
            'app_logs' => $this->countRows('app_logs'),
            'admin_notifications' => $this->countRows('admin_notifications'),
            'renewal_requests' => $this->countRows('renewal_requests'),
            'payments' => $this->countRows('payments'),
            'customer_timeline_notes' => $this->countRows('customer_timeline_notes'),
            'api_audit_logs' => $this->countRows($this->apiAuditTable),
            'queue' => $this->countRows($this->queueTable),
        ];
    }

    private function globalSearchResults(string $q): array
    {
        return [
            'customers' => $this->searchTable('customers_local', $q, ['username', 'full_name', 'phone', 'payment_status'], '/admin/customers/profile?username={username}'),
            'renewal_requests' => $this->searchTable('renewal_requests', $q, ['username', 'full_name', 'phone', 'package_name', 'message', 'status'], '/admin/renewal-requests?q={username}'),
            'payments' => $this->searchTable('payments', $q, ['username', 'amount', 'currency', 'package_name'], '/admin/payments'),
            'packages' => $this->searchTable('service_packages', $q, ['name', 'source_profile', 'rate_limit', 'notes'], '/admin/packages'),
            'notifications' => $this->searchTable('admin_notifications', $q, ['title', 'message', 'related_username', 'category'], '/admin/notifications?q={related_username}'),
            'logs' => $this->searchTable('app_logs', $q, ['message', 'level', 'context', 'context_json', 'data'], '/admin/logs'),
            'timeline_notes' => $this->searchTable('customer_timeline_notes', $q, ['username', 'title', 'note', 'admin_username'], '/admin/customers/timeline?username={username}'),
        ];
    }

    private function searchTable(string $table, string $q, array $candidateColumns, string $linkTemplate): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $columns = $this->columns($table);
        $searchColumns = array_values(array_intersect($candidateColumns, $columns));

        if (count($searchColumns) === 0) {
            return [];
        }

        $where = [];

        foreach ($searchColumns as $column) {
            $where[] = "lower({$column}) LIKE lower(:q)";
        }

        $orderBy = in_array('id', $columns, true) ? 'id DESC' : $searchColumns[0] . ' ASC';

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM {$table}
                WHERE " . implode(' OR ', $where) . "
                ORDER BY {$orderBy}
                LIMIT 25
            ");

            $stmt->execute([
                'q' => '%' . $q . '%',
            ]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['_link'] = $this->renderLinkTemplate($linkTemplate, $row);
                $row['_title'] = $this->resultTitle($table, $row);
                $row['_subtitle'] = $this->resultSubtitle($row);
            }

            return $rows;
        } catch (Throwable $e) {
            AppLog::error('فشل البحث العام في جدول', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function renderLinkTemplate(string $template, array $row): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($row): string {
            $key = $matches[1] ?? '';

            return urlencode((string) ($row[$key] ?? ''));
        }, $template) ?? $template;
    }

    private function resultTitle(string $table, array $row): string
    {
        if ($table === 'customers_local') {
            return (string) (($row['username'] ?? '') !== '' ? $row['username'] : 'Customer');
        }

        if ($table === 'service_packages') {
            return (string) (($row['name'] ?? '') !== '' ? $row['name'] : 'Package');
        }

        if ($table === 'admin_notifications') {
            return (string) (($row['title'] ?? '') !== '' ? $row['title'] : 'Notification');
        }

        if ($table === 'app_logs') {
            return (string) (($row['message'] ?? '') !== '' ? $row['message'] : 'Log');
        }

        if ($table === 'renewal_requests') {
            return 'Renewal: ' . (string) ($row['username'] ?? '');
        }

        if ($table === 'payments') {
            return 'Payment: ' . (string) ($row['username'] ?? '');
        }

        return $table . ' #' . (string) ($row['id'] ?? '');
    }

    private function resultSubtitle(array $row): string
    {
        $parts = [];

        foreach (['full_name', 'phone', 'status', 'payment_status', 'package_name', 'amount', 'currency', 'created_at'] as $key) {
            if (($row[$key] ?? '') !== '') {
                $parts[] = $key . ': ' . (string) $row[$key];
            }
        }

        return implode(' — ', array_slice($parts, 0, 4));
    }

    private function dashboardData(): array
    {
        return [
            'customers_total' => $this->countRows('customers_local'),
            'customers_with_pin' => $this->countWhere('customers_local', "subscriber_password_hash IS NOT NULL AND subscriber_password_hash != ''"),
            'customers_without_pin' => $this->countWhere('customers_local', "(subscriber_password_hash IS NULL OR subscriber_password_hash = '')"),
            'renewal_pending' => $this->countWhere('renewal_requests', "status = 'pending'"),
            'renewal_all' => $this->countRows('renewal_requests'),
            'notifications_unread' => $this->countWhere('admin_notifications', "is_archived = 0 AND is_read = 0"),
            'payments_today' => $this->sumPayments('today'),
            'payments_month' => $this->sumPayments('month'),
            'packages_total' => $this->countRows('service_packages'),
            'api_audits' => $this->countRows($this->apiAuditTable),
            'queue_pending' => $this->countWhere($this->queueTable, "status = 'pending'"),
            'queue_failed' => $this->countWhere($this->queueTable, "status = 'failed'"),
            'backup' => $this->backupInfo(),
            'safety' => $this->safetySettings(),
        ];
    }

    private function sumPayments(string $period): float
    {
        if (!$this->tableExists('payments')) {
            return 0.0;
        }

        $columns = $this->columns('payments');

        if (!in_array('amount', $columns, true)) {
            return 0.0;
        }

        $dateColumn = in_array('created_at', $columns, true) ? 'created_at' : '';

        if ($dateColumn === '') {
            return 0.0;
        }

        $where = $period === 'today'
            ? "date({$dateColumn}) = date('now', 'localtime')"
            : "strftime('%Y-%m', {$dateColumn}) = strftime('%Y-%m', 'now', 'localtime')";

        try {
            $row = Database::connection()->query("
                SELECT SUM(CAST(amount AS REAL)) AS total
                FROM payments
                WHERE {$where}
            ")->fetch(PDO::FETCH_ASSOC);

            return (float) ($row['total'] ?? 0);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function backupInfo(): array
    {
        $dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/backups';

        $files = [];

        if (is_dir($dir)) {
            $items = glob($dir . '/*') ?: [];

            foreach ($items as $item) {
                if (is_file($item)) {
                    $files[] = [
                        'name' => basename($item),
                        'size' => filesize($item) ?: 0,
                        'time' => filemtime($item) ?: 0,
                    ];
                }
            }
        }

        usort($files, static fn (array $a, array $b): int => (int) $b['time'] <=> (int) $a['time']);

        return [
            'count' => count($files),
            'latest' => $files[0] ?? null,
        ];
    }

    private function setupSteps(): array
    {
        $safety = $this->safetySettings();
        $backup = $this->backupInfo();

        return [
            [
                'title' => '1. Welcome',
                'status' => 'done',
                'message' => 'GreenNet Portal يعمل محلياً عبر Docker.',
                'link' => '/admin',
            ],
            [
                'title' => '2. Router Connection',
                'status' => $this->countRows('settings') > 0 ? 'done' : 'warning',
                'message' => 'إعدادات Router موجودة في النظام أو من .env.',
                'link' => '/admin/router-setup',
            ],
            [
                'title' => '3. API Diagnostics',
                'status' => 'manual',
                'message' => 'افتح API Diagnostics وتأكد من نجاح الاتصال قبل أي كتابة مستقبلية.',
                'link' => '/admin/api/diagnostics',
            ],
            [
                'title' => '4. Service Detection',
                'status' => 'manual',
                'message' => 'تأكد من Hotspot / PPP / User Manager من Router Setup وHealth.',
                'link' => '/admin/health',
            ],
            [
                'title' => '5. Import Profiles',
                'status' => $this->countRows('service_packages') > 0 ? 'done' : 'warning',
                'message' => 'عدد الباقات المحلية: ' . $this->countRows('service_packages'),
                'link' => '/admin/auto-match',
            ],
            [
                'title' => '6. Import Users',
                'status' => $this->countRows('customers_local') > 0 ? 'done' : 'warning',
                'message' => 'عدد الزبائن المحليين: ' . $this->countRows('customers_local'),
                'link' => '/admin/customers/table',
            ],
            [
                'title' => '7. Auto Match',
                'status' => 'manual',
                'message' => 'نفذ Auto Match وتأكد من ربط الباقات والمستخدمين.',
                'link' => '/admin/auto-match',
            ],
            [
                'title' => '8. Readiness',
                'status' => 'manual',
                'message' => 'افتح Readiness Check قبل الدخول إلى Sprint 10.',
                'link' => '/admin/readiness',
            ],
            [
                'title' => '9. Backup',
                'status' => ($backup['count'] ?? 0) > 0 ? 'done' : 'warning',
                'message' => 'عدد النسخ الاحتياطية: ' . (string) ($backup['count'] ?? 0),
                'link' => '/admin/backup',
            ],
            [
                'title' => '10. Write Safety',
                'status' => ($safety['greennet_safe_mode'] ?? 'true') === 'true' ? 'done' : 'warning',
                'message' => 'Safe Mode يجب أن يبقى مفعلاً قبل Sprint 10.',
                'link' => '/admin/write-safety',
            ],
        ];
    }

    private function queueItems(): array
    {
        return $this->fetchRecent($this->queueTable, 100);
    }

    private function apiAuditItems(): array
    {
        return $this->fetchRecent($this->apiAuditTable, 100);
    }

    private function ensurePreMikroTikTables(): void
    {
        Database::connection()->exec("
            CREATE TABLE IF NOT EXISTS {$this->safetyTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        Database::connection()->exec("
            CREATE TABLE IF NOT EXISTS {$this->queueTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action TEXT DEFAULT '',
                username TEXT DEFAULT '',
                payload TEXT DEFAULT '',
                status TEXT DEFAULT 'pending',
                attempts INTEGER DEFAULT 0,
                last_error TEXT DEFAULT '',
                created_by TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        Database::connection()->exec("
            CREATE TABLE IF NOT EXISTS {$this->apiAuditTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_username TEXT DEFAULT '',
                action TEXT DEFAULT '',
                dataset TEXT DEFAULT '',
                username TEXT DEFAULT '',
                command TEXT DEFAULT '',
                params TEXT DEFAULT '',
                dry_run INTEGER DEFAULT 1,
                executed INTEGER DEFAULT 0,
                success INTEGER DEFAULT 0,
                router_response TEXT DEFAULT '',
                ip_address TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        foreach ($this->defaultSafetySettings() as $key => $value) {
            if ($this->safetySetting($key, null) === null) {
                $this->saveSafetySetting($key, $value);
            }
        }

        try {
            Database::connection()->exec("CREATE INDEX IF NOT EXISTS idx_mikrotik_queue_status ON {$this->queueTable}(status)");
            Database::connection()->exec("CREATE INDEX IF NOT EXISTS idx_api_audit_created ON {$this->apiAuditTable}(created_at)");
            Database::connection()->exec("CREATE INDEX IF NOT EXISTS idx_safety_key ON {$this->safetyTable}(setting_key)");
        } catch (Throwable) {
            // ignore
        }
    }

    private function defaultSafetySettings(): array
    {
        return [
            'mikrotik_write_enabled' => 'false',
            'greennet_safe_mode' => 'true',
            'backup_guard_enabled' => 'true',
            'dry_run_required' => 'true',
            'confirm_required' => 'true',
            'transaction_queue_enabled' => 'true',
        ];
    }

    private function safetySettings(): array
    {
        $settings = $this->defaultSafetySettings();

        try {
            $stmt = Database::connection()->query("
                SELECT setting_key, setting_value
                FROM {$this->safetyTable}
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = (string) ($row['setting_key'] ?? '');

                if ($key !== '') {
                    $settings[$key] = (string) ($row['setting_value'] ?? '');
                }
            }
        } catch (Throwable) {
            return $settings;
        }

        return $settings;
    }

    private function safetySetting(string $key, ?string $default = ''): ?string
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT setting_value
                FROM {$this->safetyTable}
                WHERE setting_key = :key
                LIMIT 1
            ");

            $stmt->execute([
                'key' => $key,
            ]);

            $value = $stmt->fetchColumn();

            return $value === false ? $default : (string) $value;
        } catch (Throwable) {
            return $default;
        }
    }

    private function saveSafetySetting(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare("
            INSERT INTO {$this->safetyTable} (
                setting_key,
                setting_value,
                created_at,
                updated_at
            )
            VALUES (
                :key,
                :value,
                :created_at,
                :updated_at
            )
            ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = excluded.setting_value,
                updated_at = excluded.updated_at
        ");

        $stmt->execute([
            'key' => $key,
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function fetchRecent(string $table, int $limit): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $columns = $this->columns($table);
        $order = in_array('id', $columns, true) ? 'id DESC' : (in_array('created_at', $columns, true) ? 'created_at DESC' : 'rowid DESC');

        try {
            $stmt = Database::connection()->query("
                SELECT *
                FROM {$table}
                ORDER BY {$order}
                LIMIT {$limit}
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function countRows(string $table): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        try {
            return (int) Database::connection()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countWhere(string $table, string $where): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        try {
            return (int) Database::connection()->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function columns(string $table): array
    {
        try {
            $stmt = Database::connection()->query("PRAGMA table_info({$table})");

            return array_values(array_filter(array_map(
                static fn (array $row): string => (string) ($row['name'] ?? ''),
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            )));
        } catch (Throwable) {
            return [];
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT name
                FROM sqlite_master
                WHERE type = 'table'
                  AND name = :name
                LIMIT 1
            ");

            $stmt->execute([
                'name' => $table,
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function rowValue(array $row, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return $default;
    }

    private function extractUsername(array $row): string
    {
        foreach (['username', 'user', 'related_username', 'customer_username'] as $key) {
            if (($row[$key] ?? '') !== '') {
                return (string) $row[$key];
            }
        }

        return '';
    }

    private function severityFromText(string $text): string
    {
        $text = mb_strtolower($text);

        if (str_contains($text, 'error') || str_contains($text, 'danger') || str_contains($text, 'فشل') || str_contains($text, 'خطأ')) {
            return 'danger';
        }

        if (str_contains($text, 'warning') || str_contains($text, 'warn') || str_contains($text, 'تنبيه') || str_contains($text, 'قفل')) {
            return 'warning';
        }

        if (str_contains($text, 'success') || str_contains($text, 'نجاح') || str_contains($text, 'ناجح')) {
            return 'success';
        }

        return 'info';
    }

    private function normalizeSeverity(string $severity): string
    {
        return match ($severity) {
            'success',
            'warning',
            'danger',
            'info' => $severity,
            default => 'info',
        };
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'success' => 'نجاح',
            'warning' => 'تنبيه',
            'danger' => 'خطير',
            default => 'معلومة',
        };
    }

    private function severityBadge(string $severity): string
    {
        return match ($severity) {
            'success' => 'admin-badge admin-badge-success',
            'warning' => 'admin-badge admin-badge-warning',
            'danger' => 'admin-badge admin-badge-danger',
            default => 'admin-badge',
        };
    }

    private function renewalStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'جديد',
            'in_review' => 'قيد المراجعة',
            'completed' => 'مكتمل',
            'rejected' => 'مرفوض',
            default => $status,
        };
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['pre_mikrotik_flash_message'] = $message;
        $_SESSION['pre_mikrotik_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'pre_mikrotik_flash_' . $key;
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