<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\RouterSettingsService;

class AdminRouterSetupController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        return View::render('admin/router_setup', [
            'title' => 'Router Setup Wizard',
            'settings' => RouterSettingsService::current(),
            'test_result' => null,
            'detect_result' => null,
            'saved' => false,
        ]);
    }

    public function submit(): string
    {
        Database::migrate();
        $this->requireLogin();

        $action = trim((string) ($_POST['action'] ?? 'save'));

        RouterSettingsService::saveConnection($_POST);

        $testResult = null;
        $detectResult = null;

        if ($action === 'test') {
            $testResult = RouterSettingsService::testConnection();

            AppLog::info('تم اختبار اتصال MikroTik من Router Setup Wizard', [
                'ok' => $testResult['ok'] ?? false,
                'message' => $testResult['message'] ?? '',
            ]);
        }

        if ($action === 'detect') {
            $testResult = RouterSettingsService::testConnection();
            $detectResult = RouterSettingsService::detectServices();

            AppLog::info('تم تشغيل Detect Services من Router Setup Wizard', [
                'test_ok' => $testResult['ok'] ?? false,
                'detect_ok' => $detectResult['ok'] ?? false,
                'summary' => $detectResult['summary'] ?? [],
            ]);
        }

        if ($action === 'save') {
            AppLog::info('تم حفظ إعدادات MikroTik من Router Setup Wizard');
        }

        return View::render('admin/router_setup', [
            'title' => 'Router Setup Wizard',
            'settings' => RouterSettingsService::current(),
            'test_result' => $testResult,
            'detect_result' => $detectResult,
            'saved' => true,
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