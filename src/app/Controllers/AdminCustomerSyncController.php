<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Services\MikroTikCrmSyncService;

class AdminCustomerSyncController
{
    public function index(): string
    {
        Database::migrate();

        $this->requireLogin();

        $service = new MikroTikCrmSyncService();
        $summary = $service->getSummary();

        return View::render('admin/customer_sync', [
            'title' => 'مزامنة MikroTik مع CRM',
            'app_name' => Config::appName(),
            'summary' => $summary,
            'import_result' => $_SESSION['sync_import_result'] ?? null,
        ]);
    }

    public function importSelected(): void
    {
        Database::migrate();

        $this->requireLogin();

        $selectedUsers = $_POST['selected_users'] ?? [];

        if (!is_array($selectedUsers)) {
            $selectedUsers = [];
        }

        $service = new MikroTikCrmSyncService();
        $result = $service->importSelected($selectedUsers);

        $_SESSION['sync_import_result'] = $result;

        header('Location: /admin/customers/sync');
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