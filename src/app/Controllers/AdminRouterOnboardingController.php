<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\Router;
use GreenNet\Services\RouterOnboardingService;
use RuntimeException;
use Throwable;

final class AdminRouterOnboardingController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_GET['id'] ?? 0);
        $router = $id > 0 ? Router::find($id) : null;
        $service = new RouterOnboardingService();

        return View::render('admin/router_onboarding', [
            'title' => 'Router Onboarding',
            'router' => $router,
            'routers' => Router::allWithCustomerCounts(),
            'roles' => $router ? $service->roles($router) : [],
            'capabilities' => $router ? $service->capabilities($router) : [],
            'readiness' => $router ? $service->readiness($router) : ['status' => 'registered', 'checks' => []],
            'missing_mappings' => $router ? $service->mappingsMissing($id) : [],
            'installation_methods' => $router ? $service->installationMethods($router) : [],
            'deployment' => $router ? $this->deployment($id, $service, $router) : [],
            'deployment_warnings' => $router ? $service->deploymentWarnings($router, $this->deployment($id, $service, $router)) : [],
            'message' => $this->consume(),
        ]);
    }

    public function save(): void
    {
        Database::migrate();
        $this->requireLogin();
        try {
            $name = trim((string) ($_POST['name'] ?? ''));
            $host = trim((string) ($_POST['host'] ?? ''));
            if ($name === '' || $host === '') {
                throw new RuntimeException('Router name and host are required.');
            }
            $id = Router::save([
                'id' => (int) ($_POST['id'] ?? 0),
                'name' => $name,
                'host' => $host,
                'api_port' => (int) ($_POST['api_port'] ?? 8728),
                'username' => trim((string) ($_POST['username'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
                'enabled' => !empty($_POST['enabled']),
                'is_default' => !empty($_POST['is_default']),
                'access_mode' => 'hybrid',
                'auth_backend' => 'multi',
                'location' => trim((string) ($_POST['location'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
            ]);
            (new RouterOnboardingService())->saveRolesAndState(
                $id,
                is_array($_POST['roles'] ?? null) ? $_POST['roles'] : [],
                (string) ($_POST['onboarding_mode'] ?? 'existing')
            );
            $this->flash('تم تسجيل الموجّه. لم يتم الاتصال به أو تغيير إعداده.');
            $this->redirect($id);
        } catch (Throwable $e) {
            $this->flash($e->getMessage());
            $this->redirect((int) ($_POST['id'] ?? 0));
        }
    }

    public function detect(): void
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            (new RouterOnboardingService())->detect($id);
            $this->flash('تم فحص الاتصال والقدرات بالقراءة فقط.');
        } catch (Throwable $e) {
            if ($id > 0) {
                (new RouterOnboardingService())->markDetectionFailure($id);
                Router::updateStatus($id, ['ok' => false, 'message' => $e->getMessage()]);
            }
            $this->flash('تعذر فحص الموجّه: ' . $e->getMessage());
        }
        $this->redirect($id);
    }

    public function finish(): void
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_POST['id'] ?? 0);
        (new RouterOnboardingService())->finish($id);
        $this->flash('تم حفظ نتيجة الجاهزية. أكمل ربط الباقات قبل إسناد المشتركين.');
        $this->redirect($id);
    }

    public function artifact(): void
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_GET['id'] ?? 0);
        $name = (string) ($_GET['file'] ?? '');
        $router = Router::find($id);
        if ($router === null) {
            http_response_code(404);
            return;
        }
        $service = new RouterOnboardingService();
        $artifacts = $service->artifacts($router, $this->deployment($id, $service, $router));
        if (!array_key_exists($name, $artifacts)) {
            http_response_code(404);
            return;
        }
        $type = str_ends_with($name, '.yml') ? 'application/yaml' : 'text/plain';
        header('Content-Type: ' . $type . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        echo $artifacts[$name];
    }

    public function prepare(): void
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $router = Router::find($id);
            if ($router === null) {
                throw new RuntimeException('Router not found.');
            }
            $service = new RouterOnboardingService();
            $deployment = $service->deployment($router, $_POST);
            $_SESSION['router_onboarding_deployment'][$id] = $deployment;
            $service->markInstallationStatus($id, 'artifact_ready');
            $this->flash('تم تجهيز الملخص. راجع التحذيرات ثم أدخل كلمة مرور المدير مرة واحدة عند تنزيل الملف.');
        } catch (Throwable $e) {
            $this->flash($e->getMessage());
        }
        $this->redirect($id);
    }

    public function generate(): void
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_POST['id'] ?? 0);
        $router = Router::find($id);
        if ($router === null) {
            http_response_code(404);
            return;
        }
        try {
            $service = new RouterOnboardingService();
            $deployment = $this->deployment($id, $service, $router);
            $password = (string) ($_POST['admin_password'] ?? '');
            if (strlen($password) < 12 || in_array(strtolower($password), ['admin', 'password', 'change_me_admin_password'], true)) {
                throw new RuntimeException('استخدم كلمة مرور مدير مقصودة بطول 12 محرفًا على الأقل.');
            }
            $method = (string) ($deployment['method'] ?? '');
            $name = $method === 'apps' ? 'greennet-app.yml' : 'bootstrap.rsc';
            $artifacts = $service->artifacts($router, $deployment, ['admin_password' => $password]);
            $service->markInstallationStatus($id, 'installation_pending');
            $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', $deployment['app_name'] . '-' . $deployment['router_name']) ?: 'greennet';
            header('Content-Type: ' . ($method === 'apps' ? 'application/yaml' : 'text/plain') . '; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '-' . $name . '"');
            header('Cache-Control: no-store, private');
            header('X-GreenNet-Sensitive-Artifact: true');
            echo $artifacts[$name];
        } catch (Throwable $e) {
            $this->flash($e->getMessage());
            $this->redirect($id);
        }
    }

    public function installationStatus(): void
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            (new RouterOnboardingService())->markInstallationStatus($id, (string) ($_POST['status'] ?? 'needs_attention'));
            $this->flash('تم تحديث حالة التثبيت وفق إفادة المشغّل.');
        } catch (Throwable $e) {
            $this->flash($e->getMessage());
        }
        $this->redirect($id);
    }

    public function checkGreenNet(): void
    {
        Database::migrate();
        $this->requireLogin();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $router = Router::find($id);
            if ($router === null) {
                throw new RuntimeException('Router not found.');
            }
            $service = new RouterOnboardingService();
            $deployment = $this->deployment($id, $service, $router);
            $socket = @fsockopen($deployment['container_ip'], (int) $deployment['http_port'], $errorCode, $errorMessage, 3);
            if (!is_resource($socket)) {
                $service->markInstallationStatus($id, 'needs_attention');
                throw new RuntimeException('لم يستجب عنوان GreenNet المتوقع.');
            }
            fclose($socket);
            $service->markInstallationStatus($id, 'greennet_reachable');
            $this->flash('GreenNet قابل للوصول على العنوان والمنفذ المتوقعين.');
        } catch (Throwable $e) {
            $this->flash($e->getMessage());
        }
        $this->redirect($id);
    }

    private function redirect(int $id): never
    {
        header('Location: /admin/router-onboarding' . ($id > 0 ? '?id=' . $id : ''));
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
        $_SESSION['router_onboarding_message'] = $message;
    }

    private function consume(): string
    {
        $message = (string) ($_SESSION['router_onboarding_message'] ?? '');
        unset($_SESSION['router_onboarding_message']);
        return $message;
    }

    private function deployment(int $id, RouterOnboardingService $service, array $router): array
    {
        $saved = $_SESSION['router_onboarding_deployment'][$id] ?? [];
        return $service->deployment($router, is_array($saved) ? $saved : []);
    }
}
