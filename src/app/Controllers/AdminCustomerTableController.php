<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use PDO;

class AdminCustomerTableController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $q = trim((string) ($_GET['q'] ?? ''));
        $paymentStatus = trim((string) ($_GET['payment_status'] ?? 'all'));
        $accessType = trim((string) ($_GET['access_type'] ?? 'all'));
        $packageId = (int) ($_GET['package_id'] ?? 0);

        $customerColumns = $this->columns('customers_local');

        $where = [];
        $params = [];

        if ($q !== '') {
            $searchColumns = ['username', 'display_name', 'full_name', 'name', 'phone', 'mobile', 'phone_number', 'notes'];
            $parts = [];

            foreach ($searchColumns as $column) {
                if (in_array($column, $customerColumns, true)) {
                    $parts[] = "c.{$column} LIKE :q";
                }
            }

            if (count($parts) > 0) {
                $where[] = '(' . implode(' OR ', $parts) . ')';
                $params['q'] = '%' . $q . '%';
            }
        }

        if ($paymentStatus !== 'all' && in_array('payment_status', $customerColumns, true)) {
            $where[] = 'c.payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }

        if ($accessType !== 'all' && in_array('access_type', $customerColumns, true)) {
            $where[] = 'c.access_type = :access_type';
            $params['access_type'] = $accessType;
        }

        if ($packageId > 0 && in_array('package_id', $customerColumns, true)) {
            $where[] = 'c.package_id = :package_id';
            $params['package_id'] = $packageId;
        }

        $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderColumn = 'username';

        if (in_array('updated_at', $customerColumns, true)) {
            $orderColumn = 'updated_at';
        } elseif (in_array('created_at', $customerColumns, true)) {
            $orderColumn = 'created_at';
        }

        $sql = "
            SELECT
                c.*,
                sp.name AS package_name,
                sp.source_type AS package_source_type,
                sp.source_profile AS package_source_profile,
                sp.rate_limit AS package_rate_limit,
                sp.price AS package_price,
                sp.currency AS package_currency
            FROM customers_local c
            LEFT JOIN service_packages sp ON sp.id = c.package_id
            {$whereSql}
            ORDER BY c.{$orderColumn} DESC
            LIMIT 500
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $packages = $this->packages();

        $stats = [
            'total' => count($customers),
            'paid' => 0,
            'due' => 0,
            'pending' => 0,
            'unknown' => 0,
            'no_package' => 0,
        ];

        foreach ($customers as $customer) {
            $status = (string) ($customer['payment_status'] ?? 'unknown');

            if (isset($stats[$status])) {
                $stats[$status]++;
            } else {
                $stats['unknown']++;
            }

            if ((int) ($customer['package_id'] ?? 0) <= 0) {
                $stats['no_package']++;
            }
        }

        return View::render('admin/customers_table', [
            'title' => 'جدول الزبائن',
            'customers' => $customers,
            'packages' => $packages,
            'stats' => $stats,
            'filters' => [
                'q' => $q,
                'payment_status' => $paymentStatus,
                'access_type' => $accessType,
                'package_id' => $packageId,
            ],
        ]);
    }

    private function packages(): array
    {
        $stmt = Database::connection()->query("
            SELECT id, name, source_type, source_profile
            FROM service_packages
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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