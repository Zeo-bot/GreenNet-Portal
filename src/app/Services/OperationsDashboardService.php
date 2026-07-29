<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Core\Database;
use GreenNet\Models\Payment;
use GreenNet\Models\Router;
use PDO;
use Throwable;

final class OperationsDashboardService
{
    public function getData(): array
    {
        $subscription = (new SubscriptionOverviewService())->getSummary();
        $customers = $this->customers();
        $routers = Router::allWithCustomerCounts();

        $customersByUsername = [];
        foreach ($customers as $customer) {
            $customersByUsername[(string) ($customer['username'] ?? '')] = $customer;
        }

        $rows = [];
        foreach (($subscription['rows'] ?? []) as $row) {
            $customer = $customersByUsername[(string) ($row['username'] ?? '')] ?? [];
            $rows[] = $row + [
                'router_id' => $customer['router_id'] ?? null,
                'router_name' => $customer['router_name'] ?? '',
                'service_backend' => $customer['service_backend'] ?? 'user-manager',
                'service_status' => $customer['service_status'] ?? 'active',
                'mapping_found' => !empty($customer['mapping_found']),
            ];
        }

        $backendCounts = ['user-manager' => 0, 'native-hotspot' => 0, 'native-pppoe' => 0];
        $suspended = 0;
        $unassigned = 0;
        $mappingMissing = 0;
        $syncPending = 0;
        $syncFailed = 0;

        foreach ($rows as $row) {
            $backend = (string) ($row['service_backend'] ?? 'user-manager');
            $backendCounts[$backend] = ($backendCounts[$backend] ?? 0) + 1;
            $status = strtolower((string) ($row['service_status'] ?? 'active'));
            $suspended += in_array($status, ['suspended', 'disabled'], true) ? 1 : 0;
            $unassigned += (int) ($row['router_id'] ?? 0) <= 0 ? 1 : 0;
            $mappingMissing += (int) ($row['package_id'] ?? 0) > 0
                && (int) ($row['router_id'] ?? 0) > 0
                && empty($row['mapping_found']) ? 1 : 0;
            $syncPending += in_array($status, ['pending', 'sync_pending'], true) ? 1 : 0;
            $syncFailed += in_array($status, ['failed', 'sync_failed', 'missing'], true) ? 1 : 0;
        }

        $routerSummary = [
            'total' => count($routers),
            'enabled' => count(array_filter($routers, static fn (array $r): bool => (int) ($r['enabled'] ?? 0) === 1)),
            'available' => count(array_filter($routers, static fn (array $r): bool => ($r['last_status'] ?? '') === 'available')),
            'unavailable' => count(array_filter($routers, static fn (array $r): bool => ($r['last_status'] ?? '') === 'unreachable')),
        ];

        return [
            'subscription' => $subscription,
            'rows' => $rows,
            'expiring' => array_slice(array_values(array_filter(
                $rows,
                static fn (array $row): bool => in_array($row['subscription_status'] ?? '', ['soon_3', 'soon_7'], true)
            )), 0, 12),
            'routers' => $routers,
            'router_summary' => $routerSummary,
            'backend_counts' => $backendCounts,
            'suspended_count' => $suspended,
            'unassigned_count' => $unassigned,
            'mapping_missing_count' => $mappingMissing,
            'sync_pending_count' => $syncPending,
            'sync_failed_count' => $syncFailed,
            'lifecycle' => (new SubscriptionLifecycleService())->counts(),
            'renewals' => $this->renewals(),
            'recent_payments' => Payment::latest(6),
            'sessions' => [
                'hotspot' => null,
                'pppoe' => null,
                'total' => null,
                'message' => 'بيانات الجلسات متاحة عند الطلب من صفحة الجلسات ولا يتم استطلاع الموجّهات عند فتح اللوحة.',
            ],
        ];
    }

    private function customers(): array
    {
        return Database::connection()->query("
            SELECT c.*, r.name AS router_name,
                CASE WHEN rbpp.id IS NULL THEN 0 ELSE 1 END AS mapping_found
            FROM customers_local c
            LEFT JOIN routers r ON r.id = c.router_id
            LEFT JOIN router_backend_package_profiles rbpp
              ON rbpp.router_id = c.router_id
             AND rbpp.package_id = c.package_id
             AND rbpp.backend = c.service_backend
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function renewals(): array
    {
        $result = ['pending' => 0, 'completed' => 0, 'rejected' => 0, 'recent' => []];

        try {
            $rows = Database::connection()->query("
                SELECT id, username, package_name, status, created_at, updated_at
                FROM renewal_requests
                ORDER BY id DESC
                LIMIT 8
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $counts = Database::connection()->query("
                SELECT lower(status) AS status, COUNT(*) AS total
                FROM renewal_requests
                GROUP BY lower(status)
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($counts as $count) {
                $status = (string) ($count['status'] ?? 'pending');
                $total = (int) ($count['total'] ?? 0);
                if (in_array($status, ['pending', 'processing', 'review', 'in_review'], true)) {
                    $result['pending'] += $total;
                } elseif (in_array($status, ['approved', 'done', 'completed', 'paid'], true)) {
                    $result['completed'] += $total;
                } elseif (in_array($status, ['denied', 'rejected', 'failed', 'cancelled'], true)) {
                    $result['rejected'] += $total;
                }
            }
            $result['recent'] = $rows;
        } catch (Throwable) {
            // The dashboard remains useful when the optional renewal table is unavailable.
        }

        return $result;
    }
}
