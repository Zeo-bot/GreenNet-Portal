<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use PDO;
use Throwable;

class AdminCustomerTimelineController
{
    private string $notesTable = 'customer_timeline_notes';

    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensureNotesTable();

        $username = trim((string) ($_GET['username'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));

        if ($username === '') {
            return View::render('admin/customer_timeline', [
                'title' => 'Timeline المشترك',
                'mode' => 'search',
                'q' => $q,
                'customers' => $this->customers($q),
                'message' => $this->consumeFlash('message'),
                'message_type' => $this->consumeFlash('type', 'success'),
            ]);
        }

        $customer = $this->customer($username);
        $events = $this->timeline($username, $customer);

        return View::render('admin/customer_timeline', [
            'title' => 'Timeline المشترك',
            'mode' => 'detail',
            'username' => $username,
            'customer' => $customer,
            'events' => $events,
            'stats' => $this->stats($events),
            'message' => $this->consumeFlash('message'),
            'message_type' => $this->consumeFlash('type', 'success'),
        ]);
    }

    public function storeNote(): void
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensureNotesTable();

        $username = trim((string) ($_POST['username'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        $severity = $this->allowedSeverity((string) ($_POST['severity'] ?? 'info'));

        if ($username === '') {
            $this->flash('اسم المستخدم مطلوب.', 'warning');
            $this->redirect('/admin/customers/timeline');
        }

        if ($note === '') {
            $this->flash('الملاحظة فارغة.', 'warning');
            $this->redirect('/admin/customers/timeline?username=' . urlencode($username));
        }

        if ($title === '') {
            $title = 'ملاحظة إدارية';
        }

        $now = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare("
            INSERT INTO {$this->notesTable} (
                username,
                title,
                note,
                severity,
                admin_username,
                created_at,
                updated_at
            )
            VALUES (
                :username,
                :title,
                :note,
                :severity,
                :admin_username,
                :created_at,
                :updated_at
            )
        ");

        $stmt->execute([
            'username' => $username,
            'title' => $title,
            'note' => $note,
            'severity' => $severity,
            'admin_username' => (string) ($_SESSION['admin_username'] ?? 'admin'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        AppLog::info('تمت إضافة ملاحظة على Timeline المشترك', [
            'username' => $username,
            'admin' => (string) ($_SESSION['admin_username'] ?? 'admin'),
            'severity' => $severity,
        ]);

        $this->flash('تمت إضافة الملاحظة إلى Timeline.', 'success');
        $this->redirect('/admin/customers/timeline?username=' . urlencode($username));
    }

    private function customers(string $q): array
    {
        if (!$this->tableExists('customers_local')) {
            return [];
        }

        $columns = $this->columns('customers_local');
        $where = '';
        $params = [];

        if ($q !== '') {
            $searchColumns = array_values(array_intersect(
                ['username', 'full_name', 'phone', 'payment_status'],
                $columns
            ));

            if (count($searchColumns) > 0) {
                $parts = [];

                foreach ($searchColumns as $column) {
                    $parts[] = "lower({$column}) LIKE lower(:q)";
                }

                $where = 'WHERE ' . implode(' OR ', $parts);
                $params['q'] = '%' . $q . '%';
            }
        }

        $orderBy = in_array('id', $columns, true) ? 'id DESC' : 'username ASC';

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM customers_local
                {$where}
                ORDER BY {$orderBy}
                LIMIT 80
            ");

            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            AppLog::error('فشل تحميل زبائن Timeline', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function customer(string $username): ?array
    {
        if (!$this->tableExists('customers_local')) {
            return null;
        }

        if (!in_array('username', $this->columns('customers_local'), true)) {
            return null;
        }

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM customers_local
                WHERE lower(username) = lower(:username)
                LIMIT 1
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function timeline(string $username, ?array $customer): array
    {
        $events = [];

        $this->collectCustomerEvents($events, $username, $customer);
        $this->collectPaymentEvents($events, $username);
        $this->collectRenewalEvents($events, $username);
        $this->collectNotificationEvents($events, $username);
        $this->collectManualNotes($events, $username);
        $this->collectLogEvents($events, $username);

        usort($events, static function (array $a, array $b): int {
            return (int) ($b['_sort'] ?? 0) <=> (int) ($a['_sort'] ?? 0);
        });

        return $events;
    }

    private function collectCustomerEvents(array &$events, string $username, ?array $customer): void
    {
        if (!$customer) {
            $this->addEvent($events, [
                'type' => 'customer',
                'severity' => 'warning',
                'title' => 'المشترك غير موجود داخل CRM',
                'message' => 'لا يوجد سجل محلي لهذا المستخدم داخل customers_local، لكن قد توجد له أحداث أخرى في النظام.',
                'time' => date('Y-m-d H:i:s'),
                'source' => 'customers_local',
                'link_url' => '/admin/customers/table?q=' . urlencode($username),
            ]);

            return;
        }

        $createdAt = $this->rowValue($customer, ['created_at', 'createdAt'], '');
        $updatedAt = $this->rowValue($customer, ['updated_at', 'updatedAt'], '');

        if ($createdAt !== '') {
            $this->addEvent($events, [
                'type' => 'customer',
                'severity' => 'success',
                'title' => 'تم إنشاء المشترك داخل GreenNet',
                'message' => $this->customerSummary($customer),
                'time' => $createdAt,
                'source' => 'customers_local',
                'link_url' => '/admin/customers/profile?username=' . urlencode($username),
            ]);
        }

        if ($updatedAt !== '' && $updatedAt !== $createdAt) {
            $this->addEvent($events, [
                'type' => 'customer',
                'severity' => 'info',
                'title' => 'تم تحديث بيانات المشترك',
                'message' => $this->customerSummary($customer),
                'time' => $updatedAt,
                'source' => 'customers_local',
                'link_url' => '/admin/customers/profile?username=' . urlencode($username),
            ]);
        }

        $packageId = (int) ($customer['package_id'] ?? 0);

        if ($packageId > 0) {
            $package = $this->package($packageId);
            $packageName = $package ? (string) ($package['name'] ?? ('Package #' . $packageId)) : ('Package #' . $packageId);

            $message = 'الباقة الحالية: ' . $packageName;

            if ($package && (($package['rate_limit'] ?? '') !== '')) {
                $message .= "\n" . 'السرعة: ' . (string) $package['rate_limit'];
            }

            if ($package && (($package['price'] ?? '') !== '')) {
                $message .= "\n" . 'السعر: ' . (string) $package['price'] . ' ' . (string) ($package['currency'] ?? '');
            }

            $this->addEvent($events, [
                'type' => 'package',
                'severity' => 'info',
                'title' => 'باقة مرتبطة بالمشترك',
                'message' => $message,
                'time' => $updatedAt !== '' ? $updatedAt : ($createdAt !== '' ? $createdAt : date('Y-m-d H:i:s')),
                'source' => 'service_packages',
                'link_url' => '/admin/customers/package?username=' . urlencode($username),
            ]);
        }

        $passwordChangedAt = $this->rowValue($customer, ['password_changed_at'], '');

        if ($passwordChangedAt !== '') {
            $this->addEvent($events, [
                'type' => 'security',
                'severity' => 'success',
                'title' => 'تم تعيين أو تغيير PIN / كلمة مرور المشترك',
                'message' => 'تم تحديث كلمة مرور دخول المشترك إلى تطبيق GreenNet.',
                'time' => $passwordChangedAt,
                'source' => 'customers_local',
                'link_url' => '/admin/customers/password?username=' . urlencode($username),
            ]);
        }

        $lastLoginAt = $this->rowValue($customer, ['last_login_at'], '');

        if ($lastLoginAt !== '') {
            $this->addEvent($events, [
                'type' => 'security',
                'severity' => 'success',
                'title' => 'آخر تسجيل دخول للمشترك',
                'message' => 'تم تسجيل دخول المشترك إلى تطبيق GreenNet.',
                'time' => $lastLoginAt,
                'source' => 'customers_local',
                'link_url' => '/admin/customers/profile?username=' . urlencode($username),
            ]);
        }

        $failedAttempts = (int) ($customer['failed_login_attempts'] ?? 0);

        if ($failedAttempts > 0) {
            $this->addEvent($events, [
                'type' => 'security',
                'severity' => 'warning',
                'title' => 'محاولات دخول فاشلة',
                'message' => 'عدد المحاولات الفاشلة الحالية: ' . $failedAttempts,
                'time' => $updatedAt !== '' ? $updatedAt : date('Y-m-d H:i:s'),
                'source' => 'customers_local',
                'link_url' => '/admin/customers/password?username=' . urlencode($username),
            ]);
        }

        $lockedUntil = $this->rowValue($customer, ['locked_until'], '');

        if ($lockedUntil !== '') {
            $this->addEvent($events, [
                'type' => 'security',
                'severity' => 'danger',
                'title' => 'حساب المشترك مقفل مؤقتاً',
                'message' => 'القفل مستمر حتى: ' . $lockedUntil,
                'time' => $lockedUntil,
                'source' => 'customers_local',
                'link_url' => '/admin/customers/password?username=' . urlencode($username),
            ]);
        }
    }

    private function collectPaymentEvents(array &$events, string $username): void
    {
        if (!$this->tableExists('payments')) {
            return;
        }

        if (!in_array('username', $this->columns('payments'), true)) {
            return;
        }

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM payments
                WHERE lower(username) = lower(:username)
                ORDER BY id DESC
                LIMIT 120
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $amount = $this->rowValue($row, ['amount'], '0');
                $currency = $this->rowValue($row, ['currency'], '');
                $packageName = $this->rowValue($row, ['package_name', 'package'], '');
                $createdAt = $this->rowValue($row, ['created_at', 'paid_at', 'date'], date('Y-m-d H:i:s'));

                $message = 'المبلغ: ' . $amount . ' ' . $currency;

                if ($packageName !== '') {
                    $message .= "\n" . 'الباقة: ' . $packageName;
                }

                $startsAt = $this->rowValue($row, ['starts_at', 'start_at'], '');
                $expiresAt = $this->rowValue($row, ['expires_at', 'expire_at'], '');

                if ($startsAt !== '') {
                    $message .= "\n" . 'بداية الاشتراك: ' . $startsAt;
                }

                if ($expiresAt !== '') {
                    $message .= "\n" . 'نهاية الاشتراك: ' . $expiresAt;
                }

                $this->addEvent($events, [
                    'type' => 'payment',
                    'severity' => 'success',
                    'title' => 'تم تسجيل دفعة',
                    'message' => $message,
                    'time' => $createdAt,
                    'source' => 'payments',
                    'source_id' => (int) ($row['id'] ?? 0),
                    'link_url' => '/admin/payments?username=' . urlencode($username),
                ]);
            }
        } catch (Throwable $e) {
            AppLog::error('فشل جمع أحداث الدفعات للـ Timeline', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function collectRenewalEvents(array &$events, string $username): void
    {
        if (!$this->tableExists('renewal_requests')) {
            return;
        }

        if (!in_array('username', $this->columns('renewal_requests'), true)) {
            return;
        }

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM renewal_requests
                WHERE lower(username) = lower(:username)
                ORDER BY id DESC
                LIMIT 120
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $status = (string) ($row['status'] ?? 'pending');
                $packageName = $this->rowValue($row, ['package_name'], '');
                $message = 'الحالة: ' . $this->statusLabel($status);

                if ($packageName !== '') {
                    $message .= "\n" . 'الباقة المطلوبة: ' . $packageName;
                }

                if (($row['message'] ?? '') !== '') {
                    $message .= "\n" . 'رسالة المشترك: ' . (string) $row['message'];
                }

                if (($row['admin_note'] ?? '') !== '') {
                    $message .= "\n" . 'ملاحظة المدير: ' . (string) $row['admin_note'];
                }

                $this->addEvent($events, [
                    'type' => 'renewal',
                    'severity' => $status === 'completed' ? 'success' : ($status === 'rejected' ? 'warning' : 'info'),
                    'title' => 'طلب تجديد',
                    'message' => $message,
                    'time' => $this->rowValue($row, ['created_at'], date('Y-m-d H:i:s')),
                    'source' => 'renewal_requests',
                    'source_id' => (int) ($row['id'] ?? 0),
                    'link_url' => '/admin/renewal-requests?q=' . urlencode($username),
                ]);
            }
        } catch (Throwable $e) {
            AppLog::error('فشل جمع أحداث التجديد للـ Timeline', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function collectNotificationEvents(array &$events, string $username): void
    {
        if (!$this->tableExists('admin_notifications')) {
            return;
        }

        if (!in_array('related_username', $this->columns('admin_notifications'), true)) {
            return;
        }

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM admin_notifications
                WHERE lower(related_username) = lower(:username)
                ORDER BY id DESC
                LIMIT 120
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->addEvent($events, [
                    'type' => 'notification',
                    'severity' => (string) ($row['severity'] ?? 'info'),
                    'title' => 'إشعار: ' . (string) (($row['title'] ?? '') !== '' ? $row['title'] : 'إشعار'),
                    'message' => (string) ($row['message'] ?? ''),
                    'time' => $this->rowValue($row, ['created_at'], date('Y-m-d H:i:s')),
                    'source' => 'admin_notifications',
                    'source_id' => (int) ($row['id'] ?? 0),
                    'link_url' => (string) (($row['link_url'] ?? '') !== '' ? $row['link_url'] : '/admin/notifications?q=' . urlencode($username)),
                ]);
            }
        } catch (Throwable $e) {
            AppLog::error('فشل جمع إشعارات Timeline', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function collectManualNotes(array &$events, string $username): void
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM {$this->notesTable}
                WHERE lower(username) = lower(:username)
                ORDER BY id DESC
                LIMIT 120
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $admin = $this->rowValue($row, ['admin_username'], 'admin');
                $message = (string) ($row['note'] ?? '');

                if ($admin !== '') {
                    $message .= "\n" . 'بواسطة: ' . $admin;
                }

                $this->addEvent($events, [
                    'type' => 'note',
                    'severity' => (string) ($row['severity'] ?? 'info'),
                    'title' => (string) (($row['title'] ?? '') !== '' ? $row['title'] : 'ملاحظة إدارية'),
                    'message' => $message,
                    'time' => $this->rowValue($row, ['created_at'], date('Y-m-d H:i:s')),
                    'source' => $this->notesTable,
                    'source_id' => (int) ($row['id'] ?? 0),
                    'link_url' => '',
                ]);
            }
        } catch (Throwable $e) {
            AppLog::error('فشل جمع ملاحظات Timeline', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function collectLogEvents(array &$events, string $username): void
    {
        if (!$this->tableExists('app_logs')) {
            return;
        }

        try {
            $stmt = Database::connection()->query("
                SELECT *
                FROM app_logs
                ORDER BY id DESC
                LIMIT 700
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!$this->logBelongsToUsername($row, $username)) {
                    continue;
                }

                $level = strtolower($this->rowValue($row, ['level', 'type', 'severity'], 'info'));
                $message = $this->rowValue($row, ['message', 'title', 'event', 'action'], 'حدث في النظام');

                $this->addEvent($events, [
                    'type' => 'log',
                    'severity' => $this->severityFromLog($level, $message),
                    'title' => 'Log: ' . $message,
                    'message' => $this->logDetails($row),
                    'time' => $this->rowValue($row, ['created_at', 'time'], date('Y-m-d H:i:s')),
                    'source' => 'app_logs',
                    'source_id' => (int) ($row['id'] ?? 0),
                    'link_url' => '/admin/logs',
                ]);
            }
        } catch (Throwable $e) {
            AppLog::error('فشل جمع Logs للـ Timeline', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function addEvent(array &$events, array $event): void
    {
        $time = (string) (($event['time'] ?? '') !== '' ? $event['time'] : date('Y-m-d H:i:s'));
        $sort = strtotime($time);

        if ($sort === false) {
            $sort = 0;
        }

        $severity = $this->allowedSeverity((string) ($event['severity'] ?? 'info'));

        $events[] = [
            'type' => (string) ($event['type'] ?? 'system'),
            'severity' => $severity,
            'severity_label' => $this->severityLabel($severity),
            'severity_badge' => $this->severityBadge($severity),
            'icon' => $this->iconForType((string) ($event['type'] ?? 'system')),
            'title' => (string) ($event['title'] ?? 'حدث'),
            'message' => (string) ($event['message'] ?? ''),
            'time' => $time,
            'source' => (string) ($event['source'] ?? ''),
            'source_id' => (int) ($event['source_id'] ?? 0),
            'link_url' => (string) ($event['link_url'] ?? ''),
            '_sort' => $sort,
        ];
    }

    private function stats(array $events): array
    {
        $stats = [
            'total' => count($events),
            'payment' => 0,
            'renewal' => 0,
            'security' => 0,
            'notification' => 0,
            'note' => 0,
            'warning' => 0,
            'danger' => 0,
        ];

        foreach ($events as $event) {
            $type = (string) ($event['type'] ?? '');
            $severity = (string) ($event['severity'] ?? '');

            if (array_key_exists($type, $stats)) {
                $stats[$type]++;
            }

            if (array_key_exists($severity, $stats)) {
                $stats[$severity]++;
            }
        }

        return $stats;
    }

    private function package(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('service_packages')) {
            return null;
        }

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM service_packages
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                'id' => $id,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function customerSummary(array $customer): string
    {
        $lines = [];

        $fullName = $this->rowValue($customer, ['full_name', 'name'], '');

        if ($fullName !== '') {
            $lines[] = 'الاسم: ' . $fullName;
        }

        $phone = $this->rowValue($customer, ['phone'], '');

        if ($phone !== '') {
            $lines[] = 'الهاتف: ' . $phone;
        }

        $paymentStatus = $this->rowValue($customer, ['payment_status'], '');

        if ($paymentStatus !== '') {
            $lines[] = 'حالة الدفع: ' . $paymentStatus;
        }

        return count($lines) > 0 ? implode("\n", $lines) : 'تم العثور على سجل المشترك داخل GreenNet.';
    }

    private function logBelongsToUsername(array $row, string $username): bool
    {
        $usernameLower = mb_strtolower($username);

        foreach (['username', 'user', 'related_username', 'customer_username'] as $key) {
            if (!empty($row[$key]) && mb_strtolower((string) $row[$key]) === $usernameLower) {
                return true;
            }
        }

        $text = '';

        foreach ($row as $value) {
            if (is_scalar($value)) {
                $text .= ' ' . (string) $value;
            }
        }

        return str_contains(mb_strtolower($text), $usernameLower);
    }

    private function logDetails(array $row): string
    {
        $message = $this->rowValue($row, ['message', 'title', 'event', 'action'], '');
        $context = $this->rowValue($row, ['context', 'context_json', 'metadata', 'data'], '');

        if ($context !== '') {
            return $message . "\n" . $context;
        }

        return $message !== '' ? $message : 'حدث مسجل في app_logs.';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'جديد',
            'in_review' => 'قيد المراجعة',
            'completed' => 'مكتمل',
            'rejected' => 'مرفوض',
            default => $status,
        };
    }

    private function severityFromLog(string $level, string $message): string
    {
        $text = mb_strtolower($level . ' ' . $message);

        if (str_contains($text, 'error') || str_contains($text, 'danger') || str_contains($text, 'فشل') || str_contains($text, 'خطأ')) {
            return 'danger';
        }

        if (str_contains($text, 'warning') || str_contains($text, 'warn') || str_contains($text, 'قفل') || str_contains($text, 'خاطئة')) {
            return 'warning';
        }

        if (str_contains($text, 'success') || str_contains($text, 'نجاح') || str_contains($text, 'ناجح')) {
            return 'success';
        }

        return 'info';
    }

    private function allowedSeverity(string $severity): string
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

    private function iconForType(string $type): string
    {
        return match ($type) {
            'customer' => '👤',
            'payment' => '💳',
            'renewal' => '🔁',
            'security' => '🔐',
            'notification' => '🔔',
            'note' => '📝',
            'log' => '🧾',
            'package' => '📦',
            default => '•',
        };
    }

    private function ensureNotesTable(): void
    {
        Database::connection()->exec("
            CREATE TABLE IF NOT EXISTS {$this->notesTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                title TEXT DEFAULT '',
                note TEXT DEFAULT '',
                severity TEXT DEFAULT 'info',
                admin_username TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->ensureColumn($this->notesTable, 'username', "TEXT DEFAULT ''");
        $this->ensureColumn($this->notesTable, 'title', "TEXT DEFAULT ''");
        $this->ensureColumn($this->notesTable, 'note', "TEXT DEFAULT ''");
        $this->ensureColumn($this->notesTable, 'severity', "TEXT DEFAULT 'info'");
        $this->ensureColumn($this->notesTable, 'admin_username', "TEXT DEFAULT ''");
        $this->ensureColumn($this->notesTable, 'created_at', "TEXT DEFAULT CURRENT_TIMESTAMP");
        $this->ensureColumn($this->notesTable, 'updated_at', "TEXT DEFAULT CURRENT_TIMESTAMP");

        try {
            Database::connection()->exec("
                CREATE INDEX IF NOT EXISTS idx_customer_timeline_notes_username
                ON {$this->notesTable}(username)
            ");
        } catch (Throwable) {
            // تجاهل أخطاء الفهارس
        }
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if ($table !== $this->notesTable) {
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

    private function columns(string $table): array
    {
        try {
            $stmt = Database::connection()->query("PRAGMA table_info({$table})");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_values(array_filter(array_map(
                static fn (array $row): string => (string) ($row['name'] ?? ''),
                $columns
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

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['admin_customer_timeline_flash_message'] = $message;
        $_SESSION['admin_customer_timeline_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'admin_customer_timeline_flash_' . $key;
        $value = (string) ($_SESSION[$sessionKey] ?? $default);

        unset($_SESSION[$sessionKey]);

        return $value;
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}