<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use PDO;

class AdminReportsController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $filters = [
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'username' => trim((string) ($_GET['username'] ?? '')),
            'package_id' => (int) ($_GET['package_id'] ?? 0),
        ];

        $payments = $this->payments($filters);
        $customers = $this->customers();
        $packages = $this->packages();

        $paymentStats = $this->paymentStats($payments);
        $packageSales = $this->packageSales($payments);
        $customerStats = $this->customerStats($customers);
        $subscriptionStats = $this->subscriptionStats($customers, $payments);

        return View::render('admin/reports', [
            'title' => 'التقارير',
            'filters' => $filters,
            'payments' => array_slice($payments, 0, 30),
            'packages' => $packages,
            'payment_stats' => $paymentStats,
            'package_sales' => $packageSales,
            'customer_stats' => $customerStats,
            'subscription_stats' => $subscriptionStats,
        ]);
    }

    private function payments(array $filters): array
    {
        $columns = $this->columns('payments');

        $where = [];
        $params = [];

        $dateColumn = in_array('paid_at', $columns, true) ? 'paid_at' : 'created_at';

        if (($filters['date_from'] ?? '') !== '') {
            $where[] = "DATE({$dateColumn}) >= DATE(:date_from)";
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $where[] = "DATE({$dateColumn}) <= DATE(:date_to)";
            $params['date_to'] = $filters['date_to'];
        }

        if (($filters['username'] ?? '') !== '' && in_array('username', $columns, true)) {
            $where[] = 'username LIKE :username';
            $params['username'] = '%' . $filters['username'] . '%';
        }

        if ((int) ($filters['package_id'] ?? 0) > 0 && in_array('package_id', $columns, true)) {
            $where[] = 'package_id = :package_id';
            $params['package_id'] = (int) $filters['package_id'];
        }

        $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = Database::connection()->prepare("
            SELECT *
            FROM payments
            {$whereSql}
            ORDER BY id DESC
            LIMIT 1000
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function customers(): array
    {
        $stmt = Database::connection()->query("
            SELECT
                c.*,
                sp.name AS package_name,
                sp.source_profile AS package_source_profile
            FROM customers_local c
            LEFT JOIN service_packages sp ON sp.id = c.package_id
            ORDER BY c.username ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function packages(): array
    {
        $stmt = Database::connection()->query("
            SELECT id, name, source_type, source_profile, price, currency
            FROM service_packages
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function paymentStats(array $payments): array
    {
        $total = 0.0;
        $users = [];
        $currencies = [];

        foreach ($payments as $payment) {
            $amount = (float) ($payment['amount'] ?? 0);
            $currency = (string) ($payment['currency'] ?? 'SYP');
            $username = (string) ($payment['username'] ?? '');

            $total += $amount;

            if ($username !== '') {
                $users[$username] = true;
            }

            if (!isset($currencies[$currency])) {
                $currencies[$currency] = 0.0;
            }

            $currencies[$currency] += $amount;
        }

        $count = count($payments);

        return [
            'total' => $total,
            'count' => $count,
            'average' => $count > 0 ? round($total / $count, 2) : 0,
            'unique_users' => count($users),
            'currencies' => $currencies,
        ];
    }

    private function packageSales(array $payments): array
    {
        $packages = [];

        foreach ($payments as $payment) {
            $key = (string) ($payment['package_name'] ?? '');

            if ($key === '') {
                $key = 'غير محدد';
            }

            if (!isset($packages[$key])) {
                $packages[$key] = [
                    'package_name' => $key,
                    'count' => 0,
                    'total' => 0.0,
                    'currency' => (string) ($payment['currency'] ?? 'SYP'),
                ];
            }

            $packages[$key]['count']++;
            $packages[$key]['total'] += (float) ($payment['amount'] ?? 0);
        }

        usort($packages, function (array $a, array $b): int {
            return $b['total'] <=> $a['total'];
        });

        return array_slice($packages, 0, 12);
    }

    private function customerStats(array $customers): array
    {
        $stats = [
            'total' => count($customers),
            'paid' => 0,
            'due' => 0,
            'pending' => 0,
            'unknown' => 0,
            'no_package' => 0,
            'hotspot' => 0,
            'ppp' => 0,
            'pppoe' => 0,
            'hybrid' => 0,
            'other_access' => 0,
        ];

        foreach ($customers as $customer) {
            $paymentStatus = (string) ($customer['payment_status'] ?? 'unknown');
            $accessType = (string) ($customer['access_type'] ?? '');

            if (isset($stats[$paymentStatus])) {
                $stats[$paymentStatus]++;
            } else {
                $stats['unknown']++;
            }

            if ((int) ($customer['package_id'] ?? 0) <= 0) {
                $stats['no_package']++;
            }

            if (isset($stats[$accessType])) {
                $stats[$accessType]++;
            } else {
                $stats['other_access']++;
            }
        }

        return $stats;
    }

    private function subscriptionStats(array $customers, array $payments): array
    {
        $latestPaymentByUser = [];

        foreach ($payments as $payment) {
            $username = (string) ($payment['username'] ?? '');

            if ($username === '') {
                continue;
            }

            if (!isset($latestPaymentByUser[$username])) {
                $latestPaymentByUser[$username] = $payment;
            }
        }

        $stats = [
            'active' => 0,
            'soon' => 0,
            'expired' => 0,
            'no_renewal' => 0,
            'no_package' => 0,
        ];

        foreach ($customers as $customer) {
            $username = (string) ($customer['username'] ?? '');
            $packageId = (int) ($customer['package_id'] ?? 0);

            if ($packageId <= 0) {
                $stats['no_package']++;
                continue;
            }

            $payment = $latestPaymentByUser[$username] ?? null;

            if ($payment === null || (string) ($payment['expires_at'] ?? '') === '') {
                $stats['no_renewal']++;
                continue;
            }

            $expiresTimestamp = strtotime((string) $payment['expires_at']);

            if ($expiresTimestamp === false) {
                $stats['no_renewal']++;
                continue;
            }

            $now = time();

            if ($expiresTimestamp < $now) {
                $stats['expired']++;
                continue;
            }

            $daysLeft = (int) ceil(($expiresTimestamp - $now) / 86400);

            if ($daysLeft <= 7) {
                $stats['soon']++;
            } else {
                $stats['active']++;
            }
        }

        return $stats;
    }

    private function columns(string $table): array
    {
        $stmt = Database::connection()->query("PRAGMA table_info({$table})");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columns = [];

        foreach ($rows as $row) {
            $columns[] = (string) ($row['name'] ?? '');
        }

        return $columns;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}