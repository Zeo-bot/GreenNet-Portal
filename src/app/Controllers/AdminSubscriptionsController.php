<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use PDO;

class AdminSubscriptionsController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $q = trim((string) ($_GET['q'] ?? ''));
        $statusFilter = trim((string) ($_GET['status'] ?? 'all'));

        $customers = $this->customers();
        $latestPayments = $this->latestPaymentsByUsername();

        $rows = [];
        $stats = [
            'total' => 0,
            'active' => 0,
            'soon_3' => 0,
            'soon_7' => 0,
            'expired' => 0,
            'no_renewal' => 0,
            'no_package' => 0,
        ];

        foreach ($customers as $customer) {
            $username = (string) ($customer['username'] ?? '');
            $payment = $latestPayments[$username] ?? null;

            $row = $this->buildRow($customer, $payment);

            if ($q !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $row['username'],
                    $row['display_name'],
                    $row['phone'],
                    $row['package_name'],
                    $row['status_label'],
                ]));

                if (!str_contains($haystack, mb_strtolower($q))) {
                    continue;
                }
            }

            if ($statusFilter !== 'all' && $row['status'] !== $statusFilter) {
                continue;
            }

            $rows[] = $row;

            $stats['total']++;

            if (isset($stats[$row['status']])) {
                $stats[$row['status']]++;
            }
        }

        return View::render('admin/subscriptions', [
            'title' => 'إدارة الاشتراكات',
            'rows' => $rows,
            'stats' => $stats,
            'filters' => [
                'q' => $q,
                'status' => $statusFilter,
            ],
        ]);
    }

    private function buildRow(array $customer, ?array $payment): array
    {
        $username = (string) ($customer['username'] ?? '');

        $displayName = (string) (
            $customer['display_name']
            ?? $customer['full_name']
            ?? $customer['name']
            ?? $username
        );

        $phone = (string) (
            $customer['phone']
            ?? $customer['mobile']
            ?? $customer['phone_number']
            ?? '-'
        );

        $packageId = (int) ($customer['package_id'] ?? 0);

        $packageName = $packageId > 0
            ? (string) ($customer['package_name'] ?? '-')
            : 'بلا باقة';

        $expiresAt = (string) ($payment['expires_at'] ?? '');
        $startsAt = (string) ($payment['starts_at'] ?? '');

        $status = 'no_renewal';
        $statusLabel = 'بلا تجديد';
        $daysLeftLabel = '-';
        $daysLeft = null;

        if ($packageId <= 0) {
            $status = 'no_package';
            $statusLabel = 'بلا باقة';
        } elseif ($expiresAt === '') {
            $status = 'no_renewal';
            $statusLabel = 'بلا تجديد';
        } else {
            $expiresTimestamp = strtotime($expiresAt);
            $now = time();

            if ($expiresTimestamp === false) {
                $status = 'no_renewal';
                $statusLabel = 'تاريخ غير صالح';
            } elseif ($expiresTimestamp < $now) {
                $status = 'expired';
                $statusLabel = 'منتهي';
                $daysLeft = (int) floor(($expiresTimestamp - $now) / 86400);
                $daysLeftLabel = 'منتهي منذ ' . abs($daysLeft) . ' يوم';
            } else {
                $daysLeft = (int) ceil(($expiresTimestamp - $now) / 86400);
                $daysLeftLabel = 'متبقي ' . $daysLeft . ' يوم';

                if ($daysLeft <= 3) {
                    $status = 'soon_3';
                    $statusLabel = 'ينتهي خلال 3 أيام';
                } elseif ($daysLeft <= 7) {
                    $status = 'soon_7';
                    $statusLabel = 'ينتهي خلال 7 أيام';
                } else {
                    $status = 'active';
                    $statusLabel = 'فعال';
                }
            }
        }

        return [
            'username' => $username,
            'display_name' => $displayName,
            'phone' => $phone,
            'package_id' => $packageId,
            'package_name' => $packageName,
            'package_profile' => (string) ($customer['package_source_profile'] ?? '-'),
            'rate_limit' => (string) ($customer['package_rate_limit'] ?? '-'),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => $status,
            'status_label' => $statusLabel,
            'days_left' => $daysLeft,
            'days_left_label' => $daysLeftLabel,
            'last_payment_amount' => (string) ($payment['amount'] ?? '0'),
            'last_payment_currency' => (string) ($payment['currency'] ?? $customer['package_currency'] ?? 'SYP'),
            'last_payment_date' => (string) ($payment['paid_at'] ?? $payment['created_at'] ?? '-'),
        ];
    }

    private function customers(): array
    {
        $stmt = Database::connection()->query("
            SELECT
                c.*,
                sp.name AS package_name,
                sp.source_profile AS package_source_profile,
                sp.rate_limit AS package_rate_limit,
                sp.currency AS package_currency
            FROM customers_local c
            LEFT JOIN service_packages sp ON sp.id = c.package_id
            ORDER BY c.username ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function latestPaymentsByUsername(): array
    {
        $stmt = Database::connection()->query("
            SELECT p.*
            FROM payments p
            INNER JOIN (
                SELECT username, MAX(id) AS latest_id
                FROM payments
                GROUP BY username
            ) latest ON latest.latest_id = p.id
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(string) ($row['username'] ?? '')] = $row;
        }

        return $indexed;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}