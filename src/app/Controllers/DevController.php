<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Database;
use GreenNet\Core\Config;
use GreenNet\Models\Setting;
use GreenNet\Models\Admin;
use GreenNet\Models\QosProfile;
use GreenNet\Models\Announcement;
use GreenNet\Models\Payment;

class DevController
{
    public function database(): string
    {
        if (!Config::isDevelopment()) {
            http_response_code(403);

            return View::render('errors/403', [
                'title' => 'غير مسموح',
                'message' => 'صفحات التطوير غير متاحة في وضع التشغيل الحالي.'
            ]);
        }

        Database::migrate();

        return View::render('dev/database', [
            'title' => 'فحص قاعدة البيانات',
            'app_name' => Config::appName(),
            'settings' => Setting::all(),
            'admin_count' => Admin::count(),
            'qos_profiles' => QosProfile::all(),
            'announcements' => Announcement::all(),
            'payments' => Payment::latest(5),
        ]);
    }
}