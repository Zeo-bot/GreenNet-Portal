<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Services\CustomerSearchService;

class AdminSearchController
{
    public function index(): string
    {
        Database::migrate();

        $this->requireLogin();

        $query = trim($_GET['q'] ?? '');

        $service = new CustomerSearchService();
        $search = $service->search($query);

        return View::render('admin/search', [
            'title' => 'البحث عن مشترك',
            'app_name' => Config::appName(),
            'query' => $query,
            'search' => $search,
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