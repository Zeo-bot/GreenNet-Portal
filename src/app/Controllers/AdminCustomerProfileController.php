<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\ServicePackage;
use GreenNet\Services\CustomerDashboardService;
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
        ]);
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}