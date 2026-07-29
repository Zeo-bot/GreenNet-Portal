<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\Router;
use GreenNet\Models\RouterPackageProfile;
use GreenNet\Models\ServicePackage;
use GreenNet\Services\RouterOS\RouterOSApiClient;
use RuntimeException;
use Throwable;

final class AdminRoutersController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_GET['id'] ?? 0);
        $message = (string) ($_SESSION['routers_message'] ?? '');
        $messageType = (string) ($_SESSION['routers_message_type'] ?? 'success');
        unset($_SESSION['routers_message'], $_SESSION['routers_message_type']);

        return View::render('admin/routers', [
            'title' => 'إدارة الراوترات',
            'routers' => Router::allWithCustomerCounts(),
            'editing' => $id > 0 ? Router::find($id) : null,
            'packages' => ServicePackage::all(),
            'mappings' => $id > 0 ? RouterPackageProfile::forRouter($id) : [],
            'message' => $message,
            'message_type' => $messageType,
        ]);
    }

    public function save(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            if (trim((string) ($_POST['name'] ?? '')) === '' || trim((string) ($_POST['host'] ?? '')) === '') {
                throw new RuntimeException('اسم الراوتر وعنوانه مطلوبان.');
            }
            $id = Router::save($_POST);
            $this->flash('تم حفظ الراوتر. تغيير التعيين لا ينقل أي مستخدم بعيد تلقائياً.');
            $this->redirect($id);
        } catch (Throwable $e) {
            $this->flash($e->getMessage(), 'warning');
            $this->redirect((int) ($_POST['id'] ?? 0));
        }
    }

    public function test(): void
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_POST['id'] ?? 0);
        $router = Router::find($id);

        if ($router === null || empty($router['enabled'])) {
            $this->flash('الراوتر غير موجود أو معطّل.', 'warning');
            $this->redirect($id);
        }

        try {
            $client = new RouterOSApiClient(Router::connectionSettings($router) + ['timeout' => 4]);
            $identityRows = $client->comm('/system/identity/print');
            $resourceRows = $client->comm('/system/resource/print');
            $result = [
                'ok' => true,
                'message' => 'Connected',
                'identity' => (string) ($identityRows[0]['name'] ?? ''),
                'routeros_version' => (string) ($resourceRows[0]['version'] ?? ''),
            ];
        } catch (Throwable $e) {
            $result = [
                'ok' => false,
                'message' => $e->getMessage(),
                'identity' => '',
                'routeros_version' => '',
            ];
        }
        Router::updateStatus($id, $result);
        $this->flash(
            !empty($result['ok']) ? 'تم الاتصال بالراوتر بنجاح.' : 'فشل الاتصال بهذا الراوتر فقط: ' . (string) ($result['message'] ?? ''),
            !empty($result['ok']) ? 'success' : 'warning'
        );
        $this->redirect($id);
    }

    public function saveMapping(): void
    {
        Database::migrate();
        $this->requireLogin();
        $routerId = (int) ($_POST['router_id'] ?? 0);
        $packageId = (int) ($_POST['package_id'] ?? 0);
        $profileName = trim((string) ($_POST['profile_name'] ?? ''));

        if (Router::find($routerId) === null || ServicePackage::find($packageId) === null || $profileName === '') {
            $this->flash('اختر راوتراً وباقة وأدخل اسم ملف صالحاً.', 'warning');
        } else {
            RouterPackageProfile::save($routerId, $packageId, $profileName, (string) ($_POST['profile_id'] ?? ''));
            $this->flash('تم حفظ ربط الباقة لهذا الراوتر.');
        }

        $this->redirect($routerId);
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['routers_message'] = $message;
        $_SESSION['routers_message_type'] = $type;
    }

    private function redirect(int $id = 0): never
    {
        header('Location: /admin/routers' . ($id > 0 ? '?id=' . $id : ''));
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
