<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\SubscriberRouterMigration;
use GreenNet\Services\SubscriberRouterMigrationService;
use Throwable;

final class AdminCustomerMigrationController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();
        $username = trim((string) ($_GET['username'] ?? ''));
        $customer = CustomerLocal::findByUsername($username);
        $service = new SubscriberRouterMigrationService();
        $targets = $customer !== null ? $service->usableTargets((int) ($customer['router_id'] ?? 0)) : [];
        $targetRouterId = (int) ($_GET['target_router_id'] ?? 0);
        $targetBackend = trim((string) ($_GET['target_backend'] ?? ''));
        $readiness = null;
        if ($customer !== null && $targetRouterId > 0 && $targetBackend !== '') {
            try {
                $readiness = $service->readiness($username, $targetRouterId, $targetBackend);
            } catch (Throwable $e) {
                $readiness = ['state' => 'blocked', 'blocks' => [$e->getMessage()], 'warnings' => []];
            }
        }

        return View::render('admin/customer_migration', [
            'title' => 'نقل المشترك إلى راوتر آخر',
            'customer' => $customer ?? [],
            'targets' => $targets,
            'target_router_id' => $targetRouterId,
            'target_backend' => $targetBackend,
            'readiness' => $readiness,
            'migration' => $customer !== null
                ? SubscriberRouterMigration::latestForCustomer((int) $customer['id'])
                : null,
            'message' => $this->consume('message'),
            'message_type' => $this->consume('type', 'success'),
        ]);
    }

    public function migrate(): void
    {
        Database::migrate();
        $this->requireLogin();
        $username = trim((string) ($_POST['username'] ?? ''));
        try {
            (new SubscriberRouterMigrationService())->migrate(
                $username,
                (int) ($_POST['target_router_id'] ?? 0),
                trim((string) ($_POST['target_backend'] ?? '')),
                (string) ($_POST['password'] ?? ''),
                trim((string) ($_POST['usage_decision'] ?? '')),
                trim((string) ($_POST['cleanup_action'] ?? 'leave'))
            );
            $this->flash('تم إنشاء الحساب الهدف والتحقق منه ثم نقل تعيين المشترك بنجاح.');
            header('Location: /admin/customers/profile?username=' . rawurlencode($username));
            exit;
        } catch (Throwable $e) {
            $this->flash('تعذر إكمال النقل: ' . $e->getMessage(), 'warning');
            $this->redirect($username);
        }
    }

    public function cleanup(): void
    {
        Database::migrate();
        $this->requireLogin();
        $username = trim((string) ($_POST['username'] ?? ''));
        try {
            (new SubscriberRouterMigrationService())->cleanupSource(
                (int) ($_POST['migration_id'] ?? 0),
                trim((string) ($_POST['cleanup_action'] ?? ''))
            );
            $this->flash('تم تنفيذ معالجة الحساب المصدر والتحقق منها.');
        } catch (Throwable $e) {
            $this->flash('بقي تنظيف المصدر معلقاً: ' . $e->getMessage(), 'warning');
        }
        header('Location: /admin/customers/profile?username=' . rawurlencode($username));
        exit;
    }

    private function redirect(string $username): never
    {
        $query = http_build_query([
            'username' => $username,
            'target_router_id' => (int) ($_POST['target_router_id'] ?? 0),
            'target_backend' => (string) ($_POST['target_backend'] ?? ''),
        ]);
        header('Location: /admin/customers/migrate?' . $query);
        exit;
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['customer_migration_message'] = $message;
        $_SESSION['customer_migration_type'] = $type;
    }

    private function consume(string $key, string $default = ''): string
    {
        $sessionKey = $key === 'message' ? 'customer_migration_message' : 'customer_migration_type';
        $value = (string) ($_SESSION[$sessionKey] ?? $default);
        unset($_SESSION[$sessionKey]);
        return $value;
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}
