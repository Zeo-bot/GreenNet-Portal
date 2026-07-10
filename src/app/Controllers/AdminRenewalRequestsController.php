<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Models\Setting;
use PDO;
use Throwable;

class AdminRenewalRequestsController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensureRenewalRequestsTable();
        $this->ensureCustomerPackageColumn();

        $status = trim((string) ($_GET['status'] ?? 'all'));
        $q = trim((string) ($_GET['q'] ?? ''));

        return View::render('admin/renewal_requests', [
            'title' => 'طلبات التجديد',
            'requests' => $this->requests($status, $q),
            'counts' => $this->counts(),
            'status' => $status,
            'q' => $q,
            'message' => $this->consumeFlash('message'),
            'message_type' => $this->consumeFlash('type', 'success'),
        ]);
    }

    public function update(): void
    {
        Database::migrate();
        $this->requireLogin();
        $this->ensureRenewalRequestsTable();
        $this->ensureCustomerPackageColumn();

        $id = (int) ($_POST['id'] ?? 0);
        $status = $this->allowedStatus((string) ($_POST['status'] ?? 'pending'));
        $adminNote = trim((string) ($_POST['admin_note'] ?? ''));

        $request = $this->findRequest($id);

        if (!$request) {
            $this->flash('لم يتم العثور على الطلب.', 'warning');
            $this->redirectBack();
        }

        $stmt = Database::connection()->prepare("
            UPDATE renewal_requests
            SET
                status = :status,
                admin_note = :admin_note,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            'status' => $status,
            'admin_note' => $adminNote,
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $id,
        ]);

        AppLog::info('تم تحديث حالة طلب تجديد', [
            'request_id' => $id,
            'username' => (string) ($request['username'] ?? ''),
            'old_status' => (string) ($request['status'] ?? ''),
            'new_status' => $status,
        ]);

        $this->flash('تم تحديث طلب التجديد بنجاح.', 'success');
        $this->redirectBack();
    }

    private function requests(string $status, string $q): array
    {
        $where = [];
        $params = [];

        if ($status !== 'all' && $status !== '') {
            $where[] = 'rr.status = :status';
            $params['status'] = $this->allowedStatus($status);
        }

        if ($q !== '') {
            $where[] = '(
                lower(rr.username) LIKE lower(:q)
                OR lower(rr.full_name) LIKE lower(:q)
                OR lower(rr.phone) LIKE lower(:q)
                OR lower(rr.package_name) LIKE lower(:q)
                OR lower(rr.message) LIKE lower(:q)
            )';

            $params['q'] = '%' . $q . '%';
        }

        $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $stmt = Database::connection()->prepare("
                SELECT
                    rr.*,
                    c.payment_status,
                    c.package_id AS customer_package_id,
                    sp.name AS current_package_name,
                    sp.price AS current_package_price,
                    sp.currency AS current_package_currency
                FROM renewal_requests rr
                LEFT JOIN customers_local c ON lower(c.username) = lower(rr.username)
                LEFT JOIN service_packages sp ON sp.id = c.package_id
                {$whereSql}
                ORDER BY rr.id DESC
                LIMIT 200
            ");

            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['status_label'] = $this->statusLabel((string) ($row['status'] ?? 'pending'));
                $row['status_badge'] = $this->statusBadge((string) ($row['status'] ?? 'pending'));
                $row['whatsapp_url'] = $this->whatsappUrl($row);
            }

            return $rows;
        } catch (Throwable $e) {
            AppLog::error('فشل تحميل طلبات التجديد', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function counts(): array
    {
        $counts = [
            'all' => 0,
            'pending' => 0,
            'in_review' => 0,
            'completed' => 0,
            'rejected' => 0,
        ];

        try {
            $stmt = Database::connection()->query("
                SELECT status, COUNT(*) AS total
                FROM renewal_requests
                GROUP BY status
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? 'pending');
                $total = (int) ($row['total'] ?? 0);

                if (!array_key_exists($status, $counts)) {
                    $status = 'pending';
                }

                $counts[$status] += $total;
                $counts['all'] += $total;
            }
        } catch (Throwable) {
            return $counts;
        }

        return $counts;
    }

    private function findRequest(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM renewal_requests
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

    private function whatsappUrl(array $row): string
    {
        $phone = $this->normalizePhone((string) ($row['phone'] ?? ''));

        if ($phone === '') {
            return '';
        }

        $username = (string) ($row['username'] ?? '');
        $package = (string) (($row['package_name'] ?? '') !== '' ? $row['package_name'] : ($row['current_package_name'] ?? ''));

        $text = 'مرحبا، بخصوص طلب تجديد اشتراك GreenNet'
            . "\n"
            . 'المستخدم: ' . $username
            . "\n"
            . 'الباقة: ' . ($package !== '' ? $package : '-');

        return 'https://wa.me/' . $phone . '?text=' . urlencode($text);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '00')) {
            return substr($phone, 2);
        }

        if (str_starts_with($phone, '0')) {
            $countryCode = preg_replace('/[^0-9]/', '', Setting::get('support_country_code', '963')) ?? '963';

            return $countryCode . ltrim($phone, '0');
        }

        return $phone;
    }

    private function allowedStatus(string $status): string
    {
        return match ($status) {
            'pending',
            'in_review',
            'completed',
            'rejected' => $status,
            default => 'pending',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'جديد',
            'in_review' => 'قيد المراجعة',
            'completed' => 'مكتمل',
            'rejected' => 'مرفوض',
            default => 'جديد',
        };
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            'pending' => 'admin-badge admin-badge-warning',
            'in_review' => 'admin-badge',
            'completed' => 'admin-badge admin-badge-success',
            'rejected' => 'admin-badge admin-badge-danger',
            default => 'admin-badge',
        };
    }

    private function ensureRenewalRequestsTable(): void
    {
        Database::connection()->exec("
            CREATE TABLE IF NOT EXISTS renewal_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                full_name TEXT DEFAULT '',
                phone TEXT DEFAULT '',
                package_id INTEGER DEFAULT 0,
                package_name TEXT DEFAULT '',
                message TEXT DEFAULT '',
                status TEXT DEFAULT 'pending',
                admin_note TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->ensureColumn('renewal_requests', 'admin_note', "TEXT DEFAULT ''");
        $this->ensureColumn('renewal_requests', 'updated_at', "TEXT DEFAULT ''");
    }

    private function ensureCustomerPackageColumn(): void
    {
        $this->ensureColumn('customers_local', 'package_id', "INTEGER DEFAULT 0");
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $allowedTables = [
            'renewal_requests',
            'customers_local',
        ];

        if (!in_array($table, $allowedTables, true)) {
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

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['admin_renewal_requests_flash_message'] = $message;
        $_SESSION['admin_renewal_requests_flash_type'] = $type;
    }

    private function consumeFlash(string $key, string $default = ''): string
    {
        $sessionKey = 'admin_renewal_requests_flash_' . $key;
        $value = (string) ($_SESSION[$sessionKey] ?? $default);

        unset($_SESSION[$sessionKey]);

        return $value;
    }

    private function redirectBack(): void
    {
        $status = trim((string) ($_POST['current_status'] ?? 'all'));
        $q = trim((string) ($_POST['current_q'] ?? ''));

        $params = [];

        if ($status !== '') {
            $params['status'] = $status;
        }

        if ($q !== '') {
            $params['q'] = $q;
        }

        $query = count($params) > 0 ? '?' . http_build_query($params) : '';

        header('Location: /admin/renewal-requests' . $query);
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