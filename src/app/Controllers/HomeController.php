<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;

class HomeController
{
    public function index(): string
    {
        return View::render('home', [
            'title' => Config::appTitle(),
            'app_name' => Config::appName(),
            'version' => Config::appVersion(),
            'support_phone' => Config::supportPhone(),
            'support_whatsapp' => Config::supportWhatsapp(),
            'access_mode' => Config::accessMode(),
            'auth_backend' => Config::authBackend(),
            'qos_enabled' => Config::qosEnabled(),
        ]);
    }
}