<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Services\SiteSettingsService;
use GreenNet\Services\UnifiedSubscriberService;

class SupportController
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
        $support = $this->service()->support();
        $settings = SiteSettingsService::all();

        $message = SiteSettingsService::renderTemplate(
            (string) ($settings['whatsapp_renew_message'] ?? ''),
            [
                'username' => $username,
                'package' => ($summary['package']['name'] ?? '') !== '' ? $summary['package']['name'] : '-',
                'expires_at' => ($summary['expiration_date'] ?? '') !== '' ? $summary['expiration_date'] : '-',
            ]
        );

        if (trim($message) === '') {
            $message = "مرحبا، أريد مساعدة بخصوص اشتراك " . ($settings['app_name'] ?? 'GreenNet') . "\n";
            $message .= "اسم المستخدم: " . $username . "\n";
        }

        return View::render('support', [
            'title' => 'الدعم',
            'app_name' => Config::appName(),
            'username' => $username,
            'support_phone' => $support['phone'],
            'support_whatsapp' => $support['whatsapp'],
            'whatsapp_message' => $message,
            'is_admin_preview' => $this->isAdminPreview(),
            'package_name' => (string) ($summary['package']['name'] ?? ''),
            'subscription_label' => (string) ($summary['status'] ?? 'unknown'),
        ]);
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
