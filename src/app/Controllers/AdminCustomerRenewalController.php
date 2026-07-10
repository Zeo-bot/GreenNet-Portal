<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Services\CustomerRenewalService;

class AdminCustomerRenewalController
{
    public function show(): string
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_GET['username'] ?? ''));

        if ($username === '') {
            header('Location: /admin/customers');
            exit;
        }

        $service = new CustomerRenewalService();
        $preview = $service->preview($username);

        return View::render('admin/customer_renew', [
            'title' => 'تجديد الاشتراك',
            'app_name' => Config::appName(),
            'preview' => $preview,
        ]);
    }

    public function renew(): void
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_POST['username'] ?? ''));
        $amount = (int) ($_POST['amount'] ?? 0);
        $currency = trim((string) ($_POST['currency'] ?? 'SYP'));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($username === '') {
            header('Location: /admin/customers');
            exit;
        }

        $service = new CustomerRenewalService();
        $service->renew($username, $amount, $currency, $note);

        header('Location: /admin/customers/profile?username=' . urlencode($username));
        exit;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}