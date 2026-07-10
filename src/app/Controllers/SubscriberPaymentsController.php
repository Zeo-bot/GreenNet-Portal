<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\Payment;
use GreenNet\Services\CustomerDashboardService;

class SubscriberPaymentsController
{
    public function index(): string
    {
        Database::migrate();

        $username = $this->resolveUsername();

        if ($username === '') {
            header('Location: /login');
            exit;
        }

        $dashboardService = new CustomerDashboardService();
        $dashboardData = $dashboardService->getDashboardData($username);

        $payments = Payment::forUser($username);

        $totalPaid = 0.0;

        foreach ($payments as $payment) {
            $totalPaid += (float) ($payment['amount'] ?? 0);
        }

        return View::render('subscriber/payments', array_merge([
            'title' => 'دفعاتي',
            'username' => $username,
            'payments' => $payments,
            'payments_count' => count($payments),
            'total_paid' => $totalPaid,
            'is_admin_preview' => $this->isAdminPreview(),
        ], $dashboardData));
    }

    private function resolveUsername(): string
    {
        $queryUsername = trim((string) ($_GET['username'] ?? ''));

        if ($this->isAdminPreview() && $queryUsername !== '') {
            return $queryUsername;
        }

        if (($_SESSION['subscriber_logged_in'] ?? false) === true) {
            return trim((string) ($_SESSION['subscriber_username'] ?? ''));
        }

        return '';
    }

    private function isAdminPreview(): bool
    {
        return ($_SESSION['admin_logged_in'] ?? false) === true;
    }
}