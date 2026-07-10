<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Services\CustomerDashboardService;
use GreenNet\Services\SiteSettingsService;

class SupportController
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
        $data = $dashboardService->getDashboardData($username);

        $settings = SiteSettingsService::all();

        $message = SiteSettingsService::renderTemplate(
            (string) ($settings['whatsapp_renew_message'] ?? ''),
            [
                'username' => $username,
                'package' => ($data['package_name'] ?? '') !== '' ? $data['package_name'] : '-',
                'expires_at' => ($data['subscription_expires_at'] ?? '') !== '' ? $data['subscription_expires_at'] : '-',
            ]
        );

        if (trim($message) === '') {
            $message = "مرحبا، أريد مساعدة بخصوص اشتراك " . ($settings['app_name'] ?? 'GreenNet') . "\n";
            $message .= "اسم المستخدم: " . $username . "\n";
        }

        return View::render('support', array_merge([
            'title' => 'الدعم',
            'app_name' => Config::appName(),
            'username' => $username,
            'support_phone' => Config::supportPhone(),
            'support_whatsapp' => Config::supportWhatsapp(),
            'whatsapp_message' => $message,
            'is_admin_preview' => $this->isAdminPreview(),
        ], $data));
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