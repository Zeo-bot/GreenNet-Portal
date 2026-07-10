<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\ServicePackage;

class AdminCustomerPackageController
{
    public function edit(): string
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_GET['username'] ?? ''));

        if ($username === '') {
            header('Location: /admin/customers');
            exit;
        }

        $customer = CustomerLocal::findByUsername($username);

        if (!$customer) {
            header('Location: /admin/customers');
            exit;
        }

        return View::render('admin/customer_package', [
            'title' => 'تعيين باقة المشترك',
            'app_name' => Config::appName(),
            'customer' => $customer,
            'packages' => ServicePackage::active(),
        ]);
    }

    public function update(): void
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_POST['username'] ?? ''));
        $packageId = (int) ($_POST['package_id'] ?? 0);

        if ($username === '') {
            header('Location: /admin/customers');
            exit;
        }

        CustomerLocal::updatePackage($username, $packageId);

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