<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Core\Database;
use GreenNet\Models\AppLog;
use PDO;
use Throwable;

class NotificationService
{
    private string $table = 'admin_notifications';

    public function ensureSchemaAndTriggers(): void
    {
        $this->ensureTable();
        $this->ensureTriggers();
    }

    public function backfillRecentEvents(): array
    {
        $this->ensureSchemaAndTriggers();

        return [
            'renewal_created' => $this->backfillRenewalRequests(),
            'logs_created' => $this->backfillImportantLogs(),
        ];
    }

    public function create(array $data): bool
    {
        $this->ensureTable();

        $eventKey = trim((string) ($data['event_key'] ?? ''));

        if ($eventKey === '') {
            return false;
        }

        if ($this->exists($eventKey)) {
            return false;
        }

        try {
            $createdAt = (string) (($data['created_at'] ?? '') !== '' ? $data['created_at'] : date('Y-m-d H:i:s'));

            $stmt = Database::connection()->prepare("
                INSERT INTO {$this->table} (
                    event_key,
                    category,
                    severity,
                    title,
                    message,
                    link_url,
                    related_username,
                    source_table,
                    source_id,
                    is_read,
                    is_archived,
                    read_at,
                    created_at,
                    updated_at
                )
                VALUES (
                    :event_key,
                    :category,
                    :severity,
                    :title,
                    :message,
                    :link_url,
                    :related_username,
                    :source_table,
                    :source_id,
                    0,
                    0,
                    '',
                    :created_at,
                    :updated_at
                )
            ");

            $stmt->execute([
                'event_key' => $eventKey,
                'category' => (string) ($data['category'] ?? 'system'),
                'severity' => $this->normalizeSeverity((string) ($data['severity'] ?? 'info')),
                'title' => (string) ($data['title'] ?? 'إشعار جديد'),
                'message' => (string) ($data['message'] ?? ''),
                'link_url' => (string) ($data['link_url'] ?? ''),
                'related_username' => (string) ($data['related_username'] ?? ''),
                'source_table' => (string) ($data['source_table'] ?? ''),
                'source_id' => (int) ($data['source_id'] ?? 0),
                'created_at' => $createdAt,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (Throwable $e) {
            AppLog::error('فشل إنشاء إشعار إداري', [
                'event_key' => $eventKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function tableName(): string
    {
        return $this->table;
    }

    private function ensureTable(): void
    {
        Database::connection()->exec("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_key TEXT NOT NULL UNIQUE,
                category TEXT DEFAULT 'system',
                severity TEXT DEFAULT 'info',
                title TEXT DEFAULT '',
                message TEXT DEFAULT '',
                link_url TEXT DEFAULT '',
                related_username TEXT DEFAULT '',
                source_table TEXT DEFAULT '',
                source_id INTEGER DEFAULT 0,
                is_read INTEGER DEFAULT 0,
                is_archived INTEGER DEFAULT 0,
                read_at TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->ensureColumn($this->table, 'event_key', "TEXT DEFAULT ''");
        $this->ensureColumn($this->table, 'category', "TEXT DEFAULT 'system'");
        $this->ensureColumn($this->table, 'severity', "TEXT DEFAULT 'info'");
        $this->ensureColumn($this->table, 'title', "TEXT DEFAULT ''");
        $this->ensureColumn($this->table, 'message', "TEXT DEFAULT ''");
        $this->ensureColumn($this->table, 'link_url', "TEXT DEFAULT ''");
        $this->ensureColumn($this->table, 'related_username', "TEXT DEFAULT ''");
        $this->ensureColumn($this->table, 'source_table', "TEXT DEFAULT ''");
        $this->ensureColumn($this->table, 'source_id', "INTEGER DEFAULT 0");
        $this->ensureColumn($this->table, 'is_read', "INTEGER DEFAULT 0");
        $this->ensureColumn($this->table, 'is_archived', "INTEGER DEFAULT 0");
        $this->ensureColumn($this->table, 'read_at', "TEXT DEFAULT ''");
        $this->ensureColumn($this->table, 'created_at', "TEXT DEFAULT CURRENT_TIMESTAMP");
        $this->ensureColumn($this->table, 'updated_at', "TEXT DEFAULT CURRENT_TIMESTAMP");

        try {
            Database::connection()->exec("
                CREATE UNIQUE INDEX IF NOT EXISTS idx_admin_notifications_event_key
                ON {$this->table}(event_key)
            ");

            Database::connection()->exec("
                CREATE INDEX IF NOT EXISTS idx_admin_notifications_category
                ON {$this->table}(category)
            ");

            Database::connection()->exec("
                CREATE INDEX IF NOT EXISTS idx_admin_notifications_read_archived
                ON {$this->table}(is_read, is_archived)
            ");
        } catch (Throwable) {
            // ignore index errors
        }
    }

    private function ensureTriggers(): void
    {
        $this->ensureRenewalInsertTrigger();
        $this->ensureRenewalStatusTrigger();
        $this->ensurePinChangeTrigger();
    }

    private function ensureRenewalInsertTrigger(): void
    {
        if (!$this->tableExists('renewal_requests')) {
            return;
        }

        try {
            Database::connection()->exec("
                CREATE TRIGGER IF NOT EXISTS trg_admin_notifications_renewal_insert
                AFTER INSERT ON renewal_requests
                BEGIN
                    INSERT OR IGNORE INTO {$this->table} (
                        event_key,
                        category,
                        severity,
                        title,
                        message,
                        link_url,
                        related_username,
                        source_table,
                        source_id,
                        is_read,
                        is_archived,
                        read_at,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        'renewal_request:' || NEW.id,
                        'renewal',
                        'info',
                        'طلب تجديد جديد',
                        'المشترك: ' || COALESCE(NEW.username, '-') ||
                            char(10) || 'الهاتف: ' || COALESCE(NEW.phone, '-') ||
                            char(10) || 'الباقة: ' || COALESCE(NEW.package_name, '-') ||
                            char(10) || 'رسالة المشترك: ' || COALESCE(NEW.message, ''),
                        '/admin/renewal-requests?q=' || COALESCE(NEW.username, ''),
                        COALESCE(NEW.username, ''),
                        'renewal_requests',
                        NEW.id,
                        0,
                        0,
                        '',
                        COALESCE(NEW.created_at, datetime('now', 'localtime')),
                        datetime('now', 'localtime')
                    );
                END
            ");
        } catch (Throwable $e) {
            AppLog::error('فشل إنشاء Trigger طلبات التجديد', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function ensureRenewalStatusTrigger(): void
    {
        if (!$this->tableExists('renewal_requests')) {
            return;
        }

        try {
            Database::connection()->exec("
                CREATE TRIGGER IF NOT EXISTS trg_admin_notifications_renewal_status_update
                AFTER UPDATE OF status ON renewal_requests
                WHEN OLD.status IS NOT NEW.status
                BEGIN
                    INSERT OR IGNORE INTO {$this->table} (
                        event_key,
                        category,
                        severity,
                        title,
                        message,
                        link_url,
                        related_username,
                        source_table,
                        source_id,
                        is_read,
                        is_archived,
                        read_at,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        'renewal_request_status:' || NEW.id || ':' || COALESCE(NEW.status, '') || ':' || COALESCE(NEW.updated_at, datetime('now', 'localtime')),
                        'renewal',
                        CASE
                            WHEN NEW.status = 'completed' THEN 'success'
                            WHEN NEW.status = 'rejected' THEN 'warning'
                            ELSE 'info'
                        END,
                        'تحديث حالة طلب تجديد',
                        'المشترك: ' || COALESCE(NEW.username, '-') ||
                            char(10) || 'الحالة السابقة: ' || COALESCE(OLD.status, '-') ||
                            char(10) || 'الحالة الجديدة: ' || COALESCE(NEW.status, '-'),
                        '/admin/renewal-requests?q=' || COALESCE(NEW.username, ''),
                        COALESCE(NEW.username, ''),
                        'renewal_requests',
                        NEW.id,
                        0,
                        0,
                        '',
                        COALESCE(NEW.updated_at, datetime('now', 'localtime')),
                        datetime('now', 'localtime')
                    );
                END
            ");
        } catch (Throwable $e) {
            AppLog::error('فشل إنشاء Trigger تحديث طلبات التجديد', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function ensurePinChangeTrigger(): void
    {
        if (!$this->tableExists('customers_local')) {
            return;
        }

        $columns = $this->columns('customers_local');

        if (
            !in_array('username', $columns, true)
            || !in_array('subscriber_password_hash', $columns, true)
            || !in_array('password_changed_at', $columns, true)
        ) {
            return;
        }

        try {
            Database::connection()->exec("
                CREATE TRIGGER IF NOT EXISTS trg_admin_notifications_customer_pin_update
                AFTER UPDATE OF subscriber_password_hash, password_changed_at ON customers_local
                WHEN NEW.subscriber_password_hash IS NOT NULL
                  AND NEW.subscriber_password_hash != ''
                  AND (
                        OLD.subscriber_password_hash IS NULL
                        OR OLD.subscriber_password_hash != NEW.subscriber_password_hash
                        OR OLD.password_changed_at IS NOT NEW.password_changed_at
                  )
                BEGIN
                    INSERT OR IGNORE INTO {$this->table} (
                        event_key,
                        category,
                        severity,
                        title,
                        message,
                        link_url,
                        related_username,
                        source_table,
                        source_id,
                        is_read,
                        is_archived,
                        read_at,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        'subscriber_pin_changed:' || COALESCE(NEW.username, '') || ':' || COALESCE(NEW.password_changed_at, datetime('now', 'localtime')),
                        'security',
                        'success',
                        'تغيير PIN / كلمة مرور مشترك',
                        'تم تعيين أو تغيير PIN للمشترك: ' || COALESCE(NEW.username, '-'),
                        '/admin/customers/password?username=' || COALESCE(NEW.username, ''),
                        COALESCE(NEW.username, ''),
                        'customers_local',
                        COALESCE(NEW.id, 0),
                        0,
                        0,
                        '',
                        COALESCE(NEW.password_changed_at, datetime('now', 'localtime')),
                        datetime('now', 'localtime')
                    );
                END
            ");
        } catch (Throwable $e) {
            AppLog::error('فشل إنشاء Trigger تغيير PIN', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function backfillRenewalRequests(): int
    {
        if (!$this->tableExists('renewal_requests')) {
            return 0;
        }

        $created = 0;

        try {
            $stmt = Database::connection()->query("
                SELECT *
                FROM renewal_requests
                ORDER BY id DESC
                LIMIT 300
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) ($row['id'] ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $status = (string) ($row['status'] ?? 'pending');
                $username = (string) ($row['username'] ?? '');
                $packageName = (string) ($row['package_name'] ?? '');

                $title = match ($status) {
                    'completed' => 'طلب تجديد مكتمل',
                    'rejected' => 'طلب تجديد مرفوض',
                    'in_review' => 'طلب تجديد قيد المراجعة',
                    default => 'طلب تجديد جديد',
                };

                $severity = match ($status) {
                    'completed' => 'success',
                    'rejected' => 'warning',
                    default => 'info',
                };

                $message = 'المشترك: ' . ($username !== '' ? $username : '-');

                if (($row['phone'] ?? '') !== '') {
                    $message .= "\n" . 'الهاتف: ' . (string) $row['phone'];
                }

                if ($packageName !== '') {
                    $message .= "\n" . 'الباقة: ' . $packageName;
                }

                if (($row['message'] ?? '') !== '') {
                    $message .= "\n" . 'رسالة المشترك: ' . (string) $row['message'];
                }

                $ok = $this->create([
                    'event_key' => 'renewal_request:' . $id,
                    'category' => 'renewal',
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'link_url' => '/admin/renewal-requests?q=' . urlencode($username),
                    'related_username' => $username,
                    'source_table' => 'renewal_requests',
                    'source_id' => $id,
                    'created_at' => (string) (($row['created_at'] ?? '') !== '' ? $row['created_at'] : date('Y-m-d H:i:s')),
                ]);

                if ($ok) {
                    $created++;
                }
            }
        } catch (Throwable $e) {
            AppLog::error('فشل Backfill طلبات التجديد للإشعارات', [
                'error' => $e->getMessage(),
            ]);
        }

        return $created;
    }

    private function backfillImportantLogs(): int
    {
        if (!$this->tableExists('app_logs')) {
            return 0;
        }

        $created = 0;

        try {
            $stmt = Database::connection()->query("
                SELECT *
                FROM app_logs
                ORDER BY id DESC
                LIMIT 300
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) ($row['id'] ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $message = $this->rowValue($row, ['message', 'title', 'event', 'action'], '');
                $level = strtolower($this->rowValue($row, ['level', 'type', 'severity'], 'info'));

                if (!$this->isImportantLog($message, $level)) {
                    continue;
                }

                $category = $this->categoryFromLog($message, $level);
                $severity = $this->severityFromText($level . ' ' . $message);
                $username = $this->extractUsername($row);

                $ok = $this->create([
                    'event_key' => 'app_log:' . $id,
                    'category' => $category,
                    'severity' => $severity,
                    'title' => $this->titleFromLog($message, $category, $severity),
                    'message' => $message !== '' ? $message : 'حدث مهم في النظام.',
                    'link_url' => $this->linkFromLog($category, $username),
                    'related_username' => $username,
                    'source_table' => 'app_logs',
                    'source_id' => $id,
                    'created_at' => $this->rowValue($row, ['created_at', 'time'], date('Y-m-d H:i:s')),
                ]);

                if ($ok) {
                    $created++;
                }
            }
        } catch (Throwable $e) {
            AppLog::error('فشل Backfill Logs للإشعارات', [
                'error' => $e->getMessage(),
            ]);
        }

        return $created;
    }

    private function exists(string $eventKey): bool
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT id
                FROM {$this->table}
                WHERE event_key = :event_key
                LIMIT 1
            ");

            $stmt->execute([
                'event_key' => $eventKey,
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if ($table !== $this->table) {
            return;
        }

        $stmt = Database::connection()->query("PRAGMA table_info({$table})");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $existingColumn) {
            if ((string) ($existingColumn['name'] ?? '') === $column) {
                return;
            }
        }

        Database::connection()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
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

    private function rowValue(array $row, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return $default;
    }

    private function isImportantLog(string $message, string $level): bool
    {
        if (in_array($level, ['error', 'warning', 'warn', 'danger'], true)) {
            return true;
        }

        $text = mb_strtolower($message);

        $keywords = [
            'دخول',
            'فاشلة',
            'قفل',
            'كلمة مرور',
            'pin',
            'password',
            'api',
            'backup',
            'restore',
            'تجديد',
            'renewal',
            'router',
            'mikrotik',
            'auto match',
            'استيراد',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function categoryFromLog(string $message, string $level): string
    {
        $text = mb_strtolower($message . ' ' . $level);

        if (str_contains($text, 'تجديد') || str_contains($text, 'renewal')) {
            return 'renewal';
        }

        if (
            str_contains($text, 'دخول')
            || str_contains($text, 'كلمة مرور')
            || str_contains($text, 'pin')
            || str_contains($text, 'password')
            || str_contains($text, 'قفل')
        ) {
            return 'security';
        }

        if (str_contains($text, 'api')) {
            return 'api';
        }

        if (str_contains($text, 'router') || str_contains($text, 'mikrotik')) {
            return 'router';
        }

        if (
            str_contains($text, 'backup')
            || str_contains($text, 'restore')
            || str_contains($text, 'نسخ')
            || str_contains($text, 'استعادة')
        ) {
            return 'backup';
        }

        if (
            str_contains($text, 'مشترك')
            || str_contains($text, 'زبون')
            || str_contains($text, 'customer')
            || str_contains($text, 'استيراد')
        ) {
            return 'customer';
        }

        return 'system';
    }

    private function severityFromText(string $text): string
    {
        $text = mb_strtolower($text);

        if (
            str_contains($text, 'error')
            || str_contains($text, 'danger')
            || str_contains($text, 'فشل')
            || str_contains($text, 'خطأ')
        ) {
            return 'danger';
        }

        if (
            str_contains($text, 'warning')
            || str_contains($text, 'warn')
            || str_contains($text, 'قفل')
            || str_contains($text, 'خاطئة')
        ) {
            return 'warning';
        }

        if (
            str_contains($text, 'success')
            || str_contains($text, 'نجاح')
            || str_contains($text, 'ناجح')
        ) {
            return 'success';
        }

        return 'info';
    }

    private function titleFromLog(string $message, string $category, string $severity): string
    {
        $text = mb_strtolower($message);

        if ($category === 'security') {
            if (str_contains($text, 'فاشلة') || str_contains($text, 'خاطئة')) {
                return 'محاولة دخول فاشلة';
            }

            if (str_contains($text, 'قفل')) {
                return 'قفل حساب مؤقتاً';
            }

            if (str_contains($text, 'كلمة مرور') || str_contains($text, 'pin')) {
                return 'تغيير كلمة مرور مشترك';
            }

            return 'حدث أمني';
        }

        if ($category === 'renewal') {
            return 'حدث متعلق بالتجديد';
        }

        if ($category === 'api') {
            return $severity === 'danger' ? 'مشكلة في API' : 'حدث API';
        }

        if ($category === 'backup') {
            return 'حدث Backup / Restore';
        }

        if ($category === 'router') {
            return 'حدث Router / MikroTik';
        }

        if ($category === 'customer') {
            return 'حدث متعلق بالمشتركين';
        }

        return 'إشعار نظام';
    }

    private function linkFromLog(string $category, string $username): string
    {
        if ($category === 'renewal') {
            return '/admin/renewal-requests';
        }

        if ($category === 'security') {
            return $username !== ''
                ? '/admin/customers/password?username=' . urlencode($username)
                : '/admin/customers/password';
        }

        if ($category === 'api') {
            return '/admin/api/diagnostics';
        }

        if ($category === 'backup') {
            return '/admin/backup';
        }

        if ($category === 'router') {
            return '/admin/health';
        }

        if ($category === 'customer') {
            return $username !== ''
                ? '/admin/customers/profile?username=' . urlencode($username)
                : '/admin/customers/table';
        }

        return '/admin/logs';
    }

    private function extractUsername(array $row): string
    {
        foreach (['username', 'user', 'related_username', 'customer_username'] as $key) {
            if (!empty($row[$key])) {
                return (string) $row[$key];
            }
        }

        foreach (['context', 'context_json', 'metadata', 'data'] as $key) {
            if (empty($row[$key]) || !is_string($row[$key])) {
                continue;
            }

            $decoded = json_decode((string) $row[$key], true);

            if (!is_array($decoded)) {
                continue;
            }

            foreach (['username', 'user', 'customer', 'customer_username'] as $decodedKey) {
                if (!empty($decoded[$decodedKey])) {
                    return (string) $decoded[$decodedKey];
                }
            }
        }

        return '';
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
}