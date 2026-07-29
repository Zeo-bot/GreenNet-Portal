<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\ServicePackage;
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use GreenNet\Services\RouterOS\NativeSubscriberRecordResolver;
use GreenNet\Services\CustomerDashboardService;
use PDO;
use Throwable;

class AdminCustomerProfileController
{
    public function show(): string
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_GET['username'] ?? ''));

        if ($username === '') {
            return View::render('admin/customer_profile', [
                'title' => 'ملف المشترك',
                'error' => 'لم يتم تحديد اسم المستخدم.',
                'username' => '',
                'customer' => [],
                'package' => [],
                'payments' => [],
                'total_paid' => 0,
                'payments_count' => 0,
                'dashboard' => [],
            ]);
        }

        $customer = CustomerLocal::findByUsername($username);

        if (!is_array($customer)) {
            $customer = [];
        }

        $payments = Payment::forUser($username);

        $totalPaid = 0.0;

        foreach ($payments as $payment) {
            $totalPaid += (float) ($payment['amount'] ?? 0);
        }

        $package = [];

        $packageId = (int) ($customer['package_id'] ?? 0);

        if ($packageId > 0) {
            $foundPackage = ServicePackage::find($packageId);

            if (is_array($foundPackage)) {
                $package = $foundPackage;
            }
        }

        $dashboard = [];

        try {
            $dashboardService = new CustomerDashboardService();
            $dashboard = $dashboardService->getDashboardData($username);
        } catch (Throwable $e) {
            $dashboard = [
                'routeros_found' => false,
                'connection_status' => 'تعذر قراءة حالة MikroTik',
                'dashboard_error' => $e->getMessage(),
            ];
        }

        return View::render('admin/customer_profile', [
            'title' => 'ملف المشترك: ' . $username,
            'error' => '',
            'username' => $username,
            'customer' => $customer,
            'package' => $package,
            'payments' => $payments,
            'total_paid' => $totalPaid,
            'payments_count' => count($payments),
            'dashboard' => $dashboard,
            'renewal_requests' => $this->renewalRequests($username),
            'assigned_router' => $this->assignedRouter($username),
            'native_record_state' => $this->nativeRecordState($customer, $username),
        ]);
    }

    private function nativeRecordState(array $customer, string $username): array
    {
        $backend = (string) ($customer['service_backend'] ?? 'user-manager');
        if (!in_array($backend, ['native-hotspot', 'native-pppoe'], true)) {
            return [];
        }
        try {
            $bundle = RouterConnectionResolver::gatewayBundleForCustomer($username, ['timeout' => 4]);
            $record = (new NativeSubscriberRecordResolver())->resolve($bundle->read, $username, $backend);
            return [
                'status' => $record === null ? 'missing' : 'found',
                'record_id' => (string) ($record['.id'] ?? ''),
                'disabled' => (string) ($record['disabled'] ?? 'false'),
            ];
        } catch (Throwable $e) {
            return ['status' => 'unavailable', 'message' => $e->getMessage()];
        }
    }

    private function assignedRouter(string $username): array
    {
        try {
            return RouterConnectionResolver::routerForCustomer($username);
        } catch (Throwable $e) {
            return [
                'name' => 'الراوتر المعيّن غير متاح',
                'host' => '',
                'enabled' => 0,
                'last_status' => 'disabled',
                'resolution_error' => $e->getMessage(),
            ];
        }
    }

    private function renewalRequests(string $username): array
    {
        try {
            $stmt = Database::connection()->prepare("
                SELECT id, package_name, message, status, admin_note, created_at, updated_at
                FROM renewal_requests
                WHERE lower(username) = lower(:username)
                ORDER BY id DESC
                LIMIT 10
            ");
            $stmt->execute(['username' => $username]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}
