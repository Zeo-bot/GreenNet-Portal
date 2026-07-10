<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\RouterOS\MikroTikService;
use PDO;
use Throwable;

class SubscriberAppController
{
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
            'flash' => $this->consumeFlash(),
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
            'flash' => $this->consumeFlash(),
        ]);
    }

    public function renewSubmit(): void
    {
        Database::migrate();
        $this->ensureRenewalRequestsTable();

        $username = $this->requireSubscriberUsername();

        $customer = $this->customer($username);
        $package = $this->customerPackage($username);

        $phone = trim((string) ($_POST['phone'] ?? ($customer['phone'] ?? '')));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($message === '') {
            $message = 'أريد تجديد اشتراكي.';
        }

        $stmt = Database::connection()->prepare("
            INSERT INTO renewal_requests (
                username,
                full_name,
                phone,
                package_id,
                package_name,
                message,
                status,
                admin_note,
                created_at,
                updated_at
            )
            VALUES (
                :username,
                :full_name,
                :phone,
                :package_id,
                :package_name,
                :message,
                'pending',
                '',
                :created_at,
                :updated_at
            )
        ");

        $now = date('Y-m-d H:i:s');

        $stmt->execute([
            'username' => $username,
            'full_name' => (string) ($customer['full_name'] ?? $username),
            'phone' => $phone,
            'package_id' => (int) ($package['id'] ?? 0),
            'package_name' => (string) ($package['name'] ?? ''),
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        AppLog::info('طلب تجديد جديد من المشترك', [
            'username' => $username,
            'package' => (string) ($package['name'] ?? ''),
            'phone' => $phone,
        ]);

        $_SESSION['subscriber_flash'] = [
            'type' => 'success',
            'message' => 'تم إرسال طلب التجديد بنجاح. سنتواصل معك قريباً.',
        ];

        $query = $this->previewQuery();

        header('Location: /my/renew' . $query);
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
        $default = [
            'online' => false,
            'source' => 'غير متصل',
            'ip' => '',
            'uptime' => '',
            'bytes_in' => 0,
            'bytes_out' => 0,
            'bytes_total' => 0,
            'bytes_in_human' => '0 B',
            'bytes_out_human' => '0 B',
            'bytes_total_human' => '0 B',
            'raw' => [],
            'error' => '',
        ];

        try {
            $service = new MikroTikService();

            foreach ($service->hotspotActiveUsers() as $row) {
                $user = (string) ($row['user'] ?? $row['name'] ?? '');

                if ($user === $username) {
                    return $this->connectionFromRow($row, 'Hotspot');
                }
            }

            foreach ($service->pppActiveUsers() as $row) {
                $user = (string) ($row['name'] ?? $row['user'] ?? '');

                if ($user === $username) {
                    return $this->connectionFromRow($row, 'PPP');
                }
            }

            return $default;
        } catch (Throwable $e) {
            $default['error'] = $e->getMessage();

            return $default;
        }
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
            'raw' => $row,
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