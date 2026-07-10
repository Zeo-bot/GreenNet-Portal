<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\Announcement;
use GreenNet\Models\QosProfile;

class AdminSystemController
{
    public function index(): string
    {
        Database::migrate();

        $this->requireLogin();

        return View::render('admin/system', [
            'title' => 'حالة النظام',
            'app_name' => Config::appName(),

            'app_env' => Config::appEnv(),
            'app_version' => Config::appVersion(),

            'access_mode' => Config::accessMode(),
            'auth_backend' => Config::authBackend(),

            'mikrotik_host' => Config::mikrotikHost(),
            'mikrotik_api_port' => Config::mikrotikApiPort(),
            'mikrotik_username' => Config::mikrotikUsername(),

            'qos_enabled' => Config::qosEnabled() ? 'true' : 'false',
            'qos_mode' => Config::qosMode(),
            'qos_backend' => Config::qosBackend(),

            'database_path' => Config::get('DB_DATABASE', '/var/www/database/database.sqlite'),

            'customers_count' => CustomerLocal::count(),
            'payments_total' => Payment::totalPaid(),
            'announcements_count' => count(Announcement::all()),
            'qos_profiles_count' => count(QosProfile::all()),
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