<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Models\ServicePackage;
use GreenNet\Services\PackageSyncService;
use GreenNet\Services\RouterOS\RouterOSErrorNormalizer;
use Throwable;

class AdminPackagesController
{
    public function index(): string
    {
        Database::migrate();

        $this->requireLogin();

        $packages = ServicePackage::all();

        return View::render('admin/packages', [
            'title' => 'إدارة الباقات',
            'app_name' => Config::appName(),
            'packages' => $packages,
            'packages_count' => ServicePackage::count(),
            'active_count' => ServicePackage::activeCount(),
            'hotspot_count' => ServicePackage::countByAccessType('hotspot'),
            'ppp_count' => ServicePackage::countByAccessType('ppp'),
            'hybrid_count' => ServicePackage::countByAccessType('hybrid'),
            'sync_result' => $_SESSION['packages_sync_result'] ?? null,
        ]);
    }

    public function syncFromRouterOS(): void
    {
        Database::migrate();

        $this->requireLogin();

        try {
            $service = new PackageSyncService();
            $result = ['ok' => true] + $service->syncFromRouterOSProfiles();
        } catch (Throwable $error) {
            $normalized = (new RouterOSErrorNormalizer())->normalize($error);
            $result = [
                'ok' => false,
                'code' => $normalized['code'],
                'message' => $normalized['message'],
            ];
        }

        $_SESSION['packages_sync_result'] = $result;

        header('Location: /admin/packages');
        exit;
    }

    public function edit(): string
    {
        Database::migrate();

        $this->requireLogin();

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            header('Location: /admin/packages');
            exit;
        }

        $package = ServicePackage::find($id);

        if (!$package) {
            header('Location: /admin/packages');
            exit;
        }

        return View::render('admin/package_edit', [
            'title' => 'تعديل الباقة',
            'app_name' => Config::appName(),
            'package' => $package,
        ]);
    }

    public function update(): void
    {
        Database::migrate();

        $this->requireLogin();

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            header('Location: /admin/packages');
            exit;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $price = (int) ($_POST['price'] ?? 0);
        $currency = trim((string) ($_POST['currency'] ?? 'SYP'));
        $durationDays = (int) ($_POST['duration_days'] ?? 0);
        $quotaGb = (float) ($_POST['quota_gb'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 0);
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($name === '') {
            $name = 'Package ' . $id;
        }

        ServicePackage::updateDetails(
            $id,
            $name,
            $price,
            $currency,
            $durationDays,
            $quotaGb,
            $isActive,
            $notes
        );

        header('Location: /admin/packages');
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
