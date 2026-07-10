<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\NotificationService;
use PDO;
use Throwable;

class AdminNotificationsController
{
    private string $table = 'admin_notifications';

    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $service = new NotificationService();
        $syncResult = $service->backfillRecentEvents();

        $status = trim((string) ($_GET['status'] ?? 'all'));
        $category = trim((string) ($_GET['category'] ?? 'all'));
        $q = trim((string) ($_GET['q'] ?? ''));

        return View::render('admin/notifications', [
            'title' => 'مركز الإشعارات',
            'notifications' => $this->notifications($status, $category, $q),
            'counts' => $this->counts(),
            'status' => $status,
            'category' => $category,
            'q' => $q,
            'message' => $this->consumeFlash('message'),
            'message_type' => $this->consumeFlash('type', 'success'),
            'sync_result' => $syncResult,
        ]);
    }

    public function markRead(): void
    {
        Database::migrate();
        $this->requireLogin();
        (new NotificationService())->ensureSchemaAndTriggers();

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->flash('الإشعار غير صالح.', 'warning');
            $this->redirectBack();
        }

        $now = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare("
            UPDATE {$this->table}
            SET
                is_read = 1,
                read_at = :read_at,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'read_at' => $now,
            'updated_at' => $now,
        ]);

        AppLog::info('تم تعليم إشعار كمقروء', [
            'notification_id' => $id,
            'admin' => (string) ($_SESSION['admin_username'] ?? 'admin'),
        ]);

        $this->flash('تم تعليم الإشعار كمقروء.', 'success');
        $this->redirectBack();
    }

    public function archive(): void
    {
        Database::migrate();
        $this->requireLogin();
        (new NotificationService())->ensureSchemaAndTriggers();

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->flash('الإشعار غير صالح.', 'warning');
            $this->redirectBack();
        }

        $now = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare("
            UPDATE {$this->table}
            SET
                is_archived = 1,
                is_read = 1,
                read_at = CASE WHEN read_at = '' OR read_at IS NULL THEN :read_at ELSE read_at END,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'read_at' => $now,
            'updated_at' => $now,
        ]);

        AppLog::info('تم أرشفة إشعار', [
            'notification_id' => $id,
            'admin' => (string) ($_SESSION['admin_username'] ?? 'admin'),
        ]);

        $this->flash('تمت أرشفة الإشعار.', 'success');
        $this->redirectBack();
    }

    public function markAllRead(): void
    {
        Database::migrate();
        $this->requireLogin();
        (new NotificationService())->ensureSchemaAndTriggers();

        $now = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare("
            UPDATE {$this->table}
            SET
                is_read = 1,
                read_at = CASE WHEN read_at = '' OR read_at IS NULL THEN :read_at ELSE read_at END,
                updated_at = :updated_at
            WHERE is_archived = 0
              AND is_read = 0
        ");

        $stmt->execute([
            'read_at' => $now,
            'updated_at' => $now,
        ]);

        AppLog::info('تم تعليم كل الإشعارات كمقروءة', [
            'admin' => (string) ($_SESSION['admin_username'] ?? 'admin'),
        ]);

        $this->flash('تم تعليم كل الإشعارات كمقروءة.', 'success');
        $this->redirectBack();
    }

    private function notifications(string $status, string $category, string $q): array
    {
        $where = [];
        $params = [];

        if ($status === 'unread') {
            $where[] = 'is_archived = 0';
            $where[] = 'is_read = 0';
        } elseif ($status === 'read') {
            $where[] = 'is_archived = 0';
            $where[] = 'is_read = 1';
        } elseif ($status === 'archived') {
            $where[] = 'is_archived = 1';
        } else {
            $where[] = 'is_archived = 0';
        }

        if ($category !== '' && $category !== 'all') {
            $where[] = 'category = :category';
            $params['category'] = $category;
        }

        if ($q !== '') {
            $where[] = '(
                lower(title) LIKE lower(:q)
                OR lower(message) LIKE lower(:q)
                OR lower(related_username) LIKE lower(:q)
                OR lower(category) LIKE lower(:q)
                OR lower(severity) LIKE lower(:q)
            )';

            $params['q'] = '%' . $q . '%';
        }

        $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM {$this->table}
                {$whereSql}
                ORDER BY
                    is_read ASC,
                    id DESC
                LIMIT 250
            ");

            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['severity_badge'] = $this->severityBadge((string) ($row['severity'] ?? 'info'));
                $row['severity_label'] = $this->severityLabel((string) ($row['severity'] ?? 'info'));
                $row['category_label'] = $this->categoryLabel((string) ($row['category'] ?? 'system'));
            }

            return $rows;
        } catch (Throwable $e) {
            AppLog::error('فشل تحميل الإشعارات', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function counts(): array
    {
        $counts = [
            'all' => 0,
            'unread' => 0,
            'read' => 0,
            'archived' => 0,
            'renewal' => 0,
            'security' => 0,
            'api' => 0,
            'backup' => 0,
            'router' => 0,
            'customer' => 0,
            'system' => 0,
        ];

        try {
            $row = Database::connection()
                ->query("
                    SELECT
                        SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) AS all_count,
                        SUM(CASE WHEN is_archived = 0 AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count,
                        SUM(CASE WHEN is_archived = 0 AND is_read = 1 THEN 1 ELSE 0 END) AS read_count,
                        SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) AS archived_count
                    FROM {$this->table}
                ")
                ->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $counts['all'] = (int) ($row['all_count'] ?? 0);
                $counts['unread'] = (int) ($row['unread_count'] ?? 0);
                $counts['read'] = (int) ($row['read_count'] ?? 0);
                $counts['archived'] = (int) ($row['archived_count'] ?? 0);
            }

            $stmt = Database::connection()->query("
                SELECT category, COUNT(*) AS total
                FROM {$this->table}
                WHERE is_archived = 0
                GROUP BY category
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $categoryRow) {
                $category = (string) ($categoryRow['category'] ?? 'system');

                if (!array_key_exists($category, $counts)) {
                    $category = 'system';
                }

                $counts[$category] += (int) ($categoryRow['total'] ?? 0);
            }
        } catch (Throwable) {
            return $counts;
        }

        return $counts;
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

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'success' => 'نجاح',
            'warning' => 'تنبيه',
            'danger' => 'خطير',
            default => 'معلومة',
        };
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'renewal' => 'تجديد',
            'security' => 'أمان',
            'api' => 'API',
            'backup' => 'Backup',
            'router' => 'Router',
            'customer' => 'مشتركين',
            default => 'نظام',
        };
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['admin_notifications_flash_message'] = $message;
        $_SESSION['admin_notifications_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'admin_notifications_flash_' . $key;
        $value = (string) ($_SESSION[$sessionKey] ?? $default);

        unset($_SESSION[$sessionKey]);

        return $value;
    }

    private function redirectBack(): void
    {
        $status = trim((string) ($_POST['current_status'] ?? 'all'));
        $category = trim((string) ($_POST['current_category'] ?? 'all'));
        $q = trim((string) ($_POST['current_q'] ?? ''));

        $params = [];

        if ($status !== '') {
            $params['status'] = $status;
        }

        if ($category !== '') {
            $params['category'] = $category;
        }

        if ($q !== '') {
            $params['q'] = $q;
        }

        $query = count($params) > 0 ? '?' . http_build_query($params) : '';

        header('Location: /admin/notifications' . $query);
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