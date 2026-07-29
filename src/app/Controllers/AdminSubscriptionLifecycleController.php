<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Services\CustomerDashboardService;
use GreenNet\Services\SubscriptionLifecycleService;
use Throwable;

final class AdminSubscriptionLifecycleController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();
        $service = new SubscriptionLifecycleService();

        return View::render('admin/subscription_lifecycle', [
            'title' => 'إدارة دورة الاشتراك',
            'rows' => $service->previewAll(),
            'message' => $this->consume(),
        ]);
    }

    public function evaluate(): void
    {
        Database::migrate();
        $this->requireLogin();
        $username = trim((string) ($_POST['username'] ?? ''));
        $usedBytes = null;

        try {
            $dashboard = (new CustomerDashboardService())->getDashboardData($username);
            if (!empty($dashboard['routeros_found']) && array_key_exists('used_bytes', $dashboard)) {
                $usedBytes = (int) $dashboard['used_bytes'];
            }
            $state = (new SubscriptionLifecycleService())->evaluate($username, $usedBytes, true);
            $this->flash($state['label'] . ': ' . $state['reason']);
        } catch (Throwable $e) {
            (new SubscriptionLifecycleService())->evaluate($username, null, true);
            $this->flash('تم تقييم المدة محلياً، وتعذرت قراءة الاستهلاك: ' . $e->getMessage());
        }

        header('Location: /admin/lifecycle');
        exit;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }

    private function flash(string $message): void
    {
        $_SESSION['lifecycle_message'] = $message;
    }

    private function consume(): string
    {
        $message = (string) ($_SESSION['lifecycle_message'] ?? '');
        unset($_SESSION['lifecycle_message']);
        return $message;
    }
}
