<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Services\RouterOS\MikroTikService;

class AdminRouterOsController
{
    public function index(): string
    {
        Database::migrate();

        $this->requireLogin();

        $mikrotik = new MikroTikService();
        $status = $mikrotik->status();

        return View::render('admin/routeros', [
            'title' => 'MikroTik API',
            'app_name' => Config::appName(),

            'host' => Config::mikrotikHost(),
            'port' => Config::mikrotikApiPort(),
            'username' => Config::mikrotikUsername(),

            'status' => $status,
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