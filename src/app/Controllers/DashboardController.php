<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Models\Announcement;
use GreenNet\Services\CustomerDashboardService;

class DashboardController
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
        $subscriberData = $dashboardService->getDashboardData($username);

        return View::render('dashboard', array_merge([
            'title' => 'لوحة المشترك',
            'app_name' => Config::appName(),
            'support_phone' => Config::supportPhone(),
            'support_whatsapp' => Config::supportWhatsapp(),
            'announcements' => Announcement::active(),
            'is_admin_preview' => $this->isAdminPreview(),
            'subscriber_auth_source' => $_SESSION['subscriber_auth_source'] ?? '',
        ], $subscriberData));
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