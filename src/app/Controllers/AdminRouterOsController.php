<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Services\RouterOS\MikroTikService;
use GreenNet\Services\RouterOS\ActiveUsersCrmService;
use GreenNet\Services\RouterOS\RouterOSProfilesService;
use GreenNet\Services\MikroTikCrmSyncService;

class AdminRouterOsController
{
    public function index(): string
    {
        Database::migrate();

        $this->requireLogin();

        $mikrotik = new MikroTikService();
        $status = $mikrotik->status();

        return View::render('admin/routeros', [
            'title' => 'MikroTik API',
            'app_name' => Config::appName(),

            'host' => Config::mikrotikHost(),
            'port' => Config::mikrotikApiPort(),
            'username' => Config::mikrotikUsername(),

            'status' => $status,
        ]);
    }

    public function activeUsers(): string
    {
        Database::migrate();

        $this->requireLogin();

        $service = new ActiveUsersCrmService();
        $summary = $service->getMergedActiveUsers();

        return View::render('admin/routeros_active_users', [
            'title' => 'المتصلون الآن',
            'app_name' => Config::appName(),

            'hotspot_users' => $summary['hotspot_users'],
            'ppp_users' => $summary['ppp_users'],
            'all_users' => $summary['all_users'],

            'hotspot_count' => $summary['hotspot_count'],
            'ppp_count' => $summary['ppp_count'],
            'total_count' => $summary['total_count'],

            'crm_found_count' => $summary['crm_found_count'],
            'crm_missing_count' => $summary['crm_missing_count'],
            'due_count' => $summary['due_count'],
            'pending_count' => $summary['pending_count'],
        ]);
    }

    public function discovery(): string
    {
        Database::migrate();

        $this->requireLogin();

        $mikrotik = new MikroTikService();
        $discovery = $mikrotik->dataDiscovery();

        return View::render('admin/routeros_discovery', [
            'title' => 'RouterOS Data Discovery',
            'app_name' => Config::appName(),
            'discovery' => $discovery,
        ]);
    }

    public function users(): string
    {
        Database::migrate();

        $this->requireLogin();

        $syncService = new MikroTikCrmSyncService();
        $summary = $syncService->getSummary();

        $allUsers = $summary['all_users'] ?? [];

        $disabledUsers = array_values(array_filter($allUsers, function (array $user): bool {
            $disabled = strtolower((string) ($user['disabled'] ?? ''));

            return str_contains($disabled, 'true') || $disabled === 'yes';
        }));

        $enabledUsers = array_values(array_filter($allUsers, function (array $user): bool {
            $disabled = strtolower((string) ($user['disabled'] ?? ''));

            return !str_contains($disabled, 'true') && $disabled !== 'yes';
        }));

        return View::render('admin/routeros_users', [
            'title' => 'مستخدمو MikroTik',
            'app_name' => Config::appName(),

            'summary' => $summary,
            'all_users' => $allUsers,
            'missing_users' => $summary['missing_users'] ?? [],
            'existing_users' => $summary['existing_users'] ?? [],
            'disabled_users' => $disabledUsers,
            'enabled_users' => $enabledUsers,

            'enabled_count' => count($enabledUsers),
            'disabled_count' => count($disabledUsers),
        ]);
    }

    public function profiles(): string
    {
        Database::migrate();

        $this->requireLogin();

        $service = new RouterOSProfilesService();
        $summary = $service->getSummary();

        return View::render('admin/routeros_profiles', [
            'title' => 'بروفايلات MikroTik',
            'app_name' => Config::appName(),
            'summary' => $summary,
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