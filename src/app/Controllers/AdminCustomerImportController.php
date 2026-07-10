<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Services\CustomerImportService;

class AdminCustomerImportController
{
    public function fromMikroTik(): void
    {
        Database::migrate();

        $this->requireLogin();

        $service = new CustomerImportService();

        $result = $service->importFromMikroTik([
            'username' => $_POST['username'] ?? '',
            'display_name' => $_POST['display_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'access_type' => $_POST['access_type'] ?? 'hybrid',
            'payment_status' => $_POST['payment_status'] ?? 'unknown',
            'notes' => $_POST['notes'] ?? '',
            'source' => $_POST['source'] ?? 'MikroTik',
            'profile' => $_POST['profile'] ?? '',
            'comment' => $_POST['comment'] ?? '',
        ]);

        $username = (string) ($result['username'] ?? '');

        if ($username !== '') {
            header('Location: /admin/customers/profile?username=' . urlencode($username));
            exit;
        }

        header('Location: /admin/search');
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