<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Database;
use GreenNet\Core\Config;

class InstallController
{
    public function index(): string
    {
        if (!Config::isDevelopment()) {
            http_response_code(403);

            return View::render('errors/403', [
                'title' => 'غير مسموح',
                'message' => 'صفحة التثبيت غير متاحة في وضع التشغيل الحالي.'
            ]);
        }

        Database::migrate();

        return View::render('install', [
            'title' => 'تثبيت GreenNet Portal',
            'app_name' => Config::appName(),
            'version' => Config::appVersion(),
            'database' => Config::get('DB_DATABASE'),
        ]);
    }
}