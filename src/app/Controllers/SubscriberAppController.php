<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\SubscriberSecurityService;
use GreenNet\Services\UnifiedSubscriberService;
use PDO;
use Throwable;

class SubscriberAppController
{
    public function __construct(private ?UnifiedSubscriberService $subscribers = null)
    {
    }

    public function home(): string
    {
        Database::migrate();

        $username = $this->requireSubscriberUsername();

        return View::render('subscriber/home', [
            'title' => 'تطبيق المشترك',
            'username' => $username,
            'customer' => $this->customer($username),
            'package' => $this->customerPackage($username),
            'latest_payment' => $this->latestPayment($username),
            'connection' => $this->connection($username),
            'subscriber' => $this->subscriber()->summary($username),
            'flash' => $this->consumeFlash(),
        ]);
    }

    public function usage(): string
    {
        Database::migrate();

        $username = $this->requireSubscriberUsername();

        return View::render('subscriber/usage', [
            'title' => 'استهلاكي',
            'username' => $username,
            'customer' => $this->customer($username),
            'package' => $this->customerPackage($username),
            'latest_payment' => $this->latestPayment($username),
            'payments' => $this->payments($username, 5),
            'connection' => $this->connection($username),
            'subscriber' => $this->subscriber()->summary($username),
            'flash' => $this->consumeFlash(),
        ]);
    }

    public function package(): string
    {
        Database::migrate();

        $username = $this->requireSubscriberUsername();

        return View::render('subscriber/package', [
            'title' => 'باقتي',
            'username' => $username,
            'customer' => $this->customer($username),
            'package' => $this->customerPackage($username),
            'latest_payment' => $this->latestPayment($username),
            'connection' => $this->connection($username),
            'subscriber' => $this->subscriber()->summary($username),
            'flash' => $this->consumeFlash(),
            'csrf_token' => SubscriberSecurityService::csrfToken(),
        ]);
    }

    public function renewForm(): string
    {
        Database::migrate();
        $this->ensureRenewalRequestsTable();

        $username = $this->requireSubscriberUsername();

        return View::render('subscriber/renew', [
            'title' => 'طلب تجديد',
            'username' => $username,
            'customer' => $this->customer($username),
            'package' => $this->customerPackage($username),
            'latest_payment' => $this->latestPayment($username),
            'pending_requests' => $this->pendingRenewalRequests($username),
            'subscriber' => $this->subscriber()->summary($username),
            'flash' => $this->consumeFlash(),
            'csrf_token' => SubscriberSecurityService::csrfToken(),
        ]);
    }

    public function renewSubmit(): void
    {
        Database::migrate();
        $this->ensureRenewalRequestsTable();

        $username = $this->requireSubscriberUsername();

        if (!SubscriberSecurityService::validateCsrf((string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(419);
            $_SESSION['subscriber_flash'] = ['type' => 'warning', 'message' => 'تعذر التحقق من الطلب.'];
            header('Location: /my/renew');
            exit;
        }

        $renewal = $this->subscriber()->createRenewal(
            $username,
            trim((string) ($_POST['phone'] ?? '')),
            trim((string) ($_POST['message'] ?? 'أرغب في تجديد اشتراكي.'))
        );
        $_SESSION['subscriber_flash'] = !empty($renewal['duplicate'])
            ? ['type' => 'warning', 'message' => 'يوجد طلب تجديد قيد المراجعة بالفعل.']
            : ['type' => 'success', 'message' => 'تم إرسال طلب التجديد بنجاح.'];
        header('Location: /my/renew');
        exit;
    }

    public function announcements(): string
    {
        Database::migrate();

        $username = $this->requireSubscriberUsername();

        return View::render('subscriber/announcements', [
            'title' => 'الإعلانات',
            'username' => $username,
            'customer' => $this->customer($username),
            'announcements' => $this->announcementsList(),
            'flash' => $this->consumeFlash(),
        ]);
    }

    public function notifications(): string
    {
        Database::migrate();
        $username = $this->requireSubscriberUsername();
        return View::render('subscriber/notifications', [
            'title' => 'الإشعارات',
            'username' => $username,
            'notifications' => $this->subscriber()->notifications($username),
        ]);
    }

    public function account(): string
    {
        Database::migrate();
        $username = $this->requireSubscriberUsername();
        return View::render('subscriber/account', [
            'title' => 'حسابي',
            'username' => $username,
            'subscriber' => $this->subscriber()->summary($username),
            'csrf_token' => SubscriberSecurityService::csrfToken(),
        ]);
    }

    private function customer(string $username): array
    {
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

            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function customerPackage(string $username): array
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT sp.*
                FROM customers_local c
                INNER JOIN service_packages sp ON sp.id = c.package_id
                WHERE lower(c.username) = lower(:username)
                LIMIT 1
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function latestPayment(string $username): array
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM payments
                WHERE lower(username) = lower(:username)
                ORDER BY id DESC
                LIMIT 1
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function payments(string $username, int $limit = 5): array
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM payments
                WHERE lower(username) = lower(:username)
                ORDER BY id DESC
                LIMIT :limit
            ");

            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function connection(string $username): array
    {
        $summary = $this->subscriber()->summary($username);
        $usage = is_array($summary['usage'] ?? null) ? $summary['usage'] : [];
        $session = is_array($summary['active_session'] ?? null) ? $summary['active_session'] : [];
        $download = isset($usage['download_bytes']) ? (int) $usage['download_bytes'] : null;
        $upload = isset($usage['upload_bytes']) ? (int) $usage['upload_bytes'] : null;
        $total = isset($usage['total_bytes']) ? (int) $usage['total_bytes'] : null;

        return [
            'online' => (bool) ($summary['online'] ?? false),
            'source' => (string) ($summary['access_type'] ?? 'unknown'),
            'ip' => (string) ($session['address'] ?? ''),
            'uptime' => (string) ($session['uptime'] ?? ''),
            'bytes_in' => $upload,
            'bytes_out' => $download,
            'bytes_total' => $total,
            'bytes_in_human' => $upload !== null ? $this->formatBytes($upload) : 'غير متاح',
            'bytes_out_human' => $download !== null ? $this->formatBytes($download) : 'غير متاح',
            'bytes_total_human' => $total !== null ? $this->formatBytes($total) : 'غير متاح',
            'error' => '',
        ];
    }

    private function connectionFromRow(array $row, string $source): array
    {
        $bytesIn = $this->parseBytes((string) ($row['bytes-in'] ?? $row['bytes_in'] ?? '0'));
        $bytesOut = $this->parseBytes((string) ($row['bytes-out'] ?? $row['bytes_out'] ?? '0'));
        $total = $bytesIn + $bytesOut;

        return [
            'online' => true,
            'source' => $source,
            'ip' => (string) ($row['address'] ?? $row['caller-id'] ?? $row['remote-address'] ?? ''),
            'uptime' => (string) ($row['uptime'] ?? ''),
            'bytes_in' => $bytesIn,
            'bytes_out' => $bytesOut,
            'bytes_total' => $total,
            'bytes_in_human' => $this->formatBytes($bytesIn),
            'bytes_out_human' => $this->formatBytes($bytesOut),
            'bytes_total_human' => $this->formatBytes($total),
            'error' => '',
        ];
    }

    private function pendingRenewalRequests(string $username): array
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM renewal_requests
                WHERE lower(username) = lower(:username)
                ORDER BY id DESC
                LIMIT 5
            ");

            $stmt->execute([
                'username' => $username,
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function announcementsList(): array
    {
        try {
            $stmt = Database::connection()->query("
                SELECT *
                FROM announcements
                ORDER BY id DESC
                LIMIT 20
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filtered = [];

            foreach ($rows as $row) {
                if (isset($row['is_active']) && (int) $row['is_active'] === 0) {
                    continue;
                }

                $filtered[] = $row;
            }

            return $filtered;
        } catch (Throwable) {
            return [];
        }
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
    }

    private function requireSubscriberUsername(): string
    {
        if (($_SESSION['subscriber_logged_in'] ?? false) === true) {
            $username = trim((string) ($_SESSION['subscriber_username'] ?? ''));

            if ($username !== '') {
                return $username;
            }
        }

        if (($_SESSION['admin_logged_in'] ?? false) === true) {
            $previewUsername = trim((string) ($_GET['username'] ?? $_POST['username'] ?? ''));

            if ($previewUsername !== '') {
                return $previewUsername;
            }
        }

        header('Location: /login');
        exit;
    }

    private function previewQuery(): string
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            return '';
        }

        $username = trim((string) ($_GET['username'] ?? $_POST['username'] ?? ''));

        if ($username === '') {
            return '';
        }

        return '?username=' . urlencode($username);
    }

    private function consumeFlash(): array
    {
        $flash = $_SESSION['subscriber_flash'] ?? null;
        unset($_SESSION['subscriber_flash']);

        if (!is_array($flash)) {
            return [
                'type' => '',
                'message' => '',
            ];
        }

        return [
            'type' => (string) ($flash['type'] ?? ''),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }

    private function subscriber(): UnifiedSubscriberService
    {
        return $this->subscribers ??= new UnifiedSubscriberService();
    }

    private function parseBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $normalized = strtolower($value);
        $number = (float) preg_replace('/[^0-9.]/', '', $normalized);

        if (str_contains($normalized, 'g')) {
            return (int) round($number * 1024 * 1024 * 1024);
        }

        if (str_contains($normalized, 'm')) {
            return (int) round($number * 1024 * 1024);
        }

        if (str_contains($normalized, 'k')) {
            return (int) round($number * 1024);
        }

        return (int) round($number);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
