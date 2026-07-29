<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Services\UnifiedSubscriberService;

class SubscriberPaymentsController
{
    public function __construct(private ?UnifiedSubscriberService $subscribers = null)
    {
    }

    public function index(): string
    {
        Database::migrate();

        $username = $this->resolveUsername();

        if ($username === '') {
            header('Location: /login');
            exit;
        }

        $summary = $this->service()->summary($username);
        $payments = $this->service()->payments($username);

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
            'package_name' => (string) ($summary['package']['name'] ?? ''),
            'subscription_expires_at' => (string) ($summary['expiration_date'] ?? ''),
        ]));
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

    private function service(): UnifiedSubscriberService
    {
        return $this->subscribers ??= new UnifiedSubscriberService();
    }
}
