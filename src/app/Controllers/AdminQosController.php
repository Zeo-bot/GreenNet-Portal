<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Models\QosProfile;

class AdminQosController
{
    public function index(): string
    {
        Database::migrate();

        $this->requireLogin();

        return View::render('admin/qos', [
            'title' => 'إدارة Smart QoS',
            'app_name' => Config::appName(),
            'qos_profiles' => QosProfile::all(),
            'active_count' => QosProfile::countActive(),
        ]);
    }

    public function edit(): string
    {
        Database::migrate();

        $this->requireLogin();

        $id = (int) ($_GET['id'] ?? 0);
        $profile = QosProfile::find($id);

        if (!$profile) {
            header('Location: /admin/qos');
            exit;
        }

        return View::render('admin/edit_qos', [
            'title' => 'تعديل QoS Profile',
            'app_name' => Config::appName(),
            'profile' => $profile,
        ]);
    }

    public function update(): void
    {
        Database::migrate();

        $this->requireLogin();

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $mode = trim($_POST['mode'] ?? 'smart');
        $priority = (int) ($_POST['priority'] ?? 5);
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $allowedModes = ['smart', 'normal', 'calls', 'stability', 'limited', 'vip'];

        if (!in_array($mode, $allowedModes, true)) {
            $mode = 'smart';
        }

        if ($priority < 1) {
            $priority = 1;
        }

        if ($priority > 8) {
            $priority = 8;
        }

        if ($id > 0 && $name !== '') {
            QosProfile::update(
                $id,
                $name,
                $mode,
                $priority,
                $description,
                $isActive
            );
        }

        header('Location: /admin/qos');
        exit;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}