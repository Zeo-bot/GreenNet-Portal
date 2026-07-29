<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Services\AutomationEngine;
use Throwable;

final class AdminAutomationController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();
        $engine = new AutomationEngine();
        $health = $engine->health();
        $health['pending_retries'] = (int) Database::connection()
            ->query("SELECT COUNT(*) FROM automation_retries WHERE next_retry_at IS NOT NULL")
            ->fetchColumn();
        return View::render('admin/automation', [
            'title' => 'الأتمتة',
            'registry' => $engine->registry(),
            'history' => $engine->history(),
            'enabled' => $engine->enabled(),
            'health' => $health,
            'message' => $this->consume(),
        ]);
    }

    public function run(): void
    {
        Database::migrate();
        $this->requireLogin();
        $job = trim((string) ($_POST['job'] ?? ''));
        try {
            $result = (new AutomationEngine())->run($job);
            $this->flash(!empty($result['ok']) ? 'تم تشغيل المهمة.' : (string) ($result['message'] ?? 'انتهت المهمة مع أخطاء.'));
        } catch (Throwable $e) {
            $this->flash('فشل التشغيل: ' . $e->getMessage());
        }
        header('Location: /admin/automation');
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
        $_SESSION['automation_message'] = $message;
    }

    private function consume(): string
    {
        $message = (string) ($_SESSION['automation_message'] ?? '');
        unset($_SESSION['automation_message']);
        return $message;
    }
}
