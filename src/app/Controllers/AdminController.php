<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Models\Admin;
use GreenNet\Models\Setting;
use GreenNet\Models\Payment;
use GreenNet\Models\Announcement;
use GreenNet\Models\QosProfile;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Router;
use GreenNet\Services\CustomerRenewalService;

class AdminController
{
    public function loginForm(): string
    {
        return View::render('admin/login', [
            'title' => 'دخول المدير',
            'app_name' => Config::appName(),
            'error' => $_SESSION['admin_error'] ?? null,
        ]);
    }

    public function login(): void
    {
        Database::migrate();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));

        if (Admin::verifyPassword($username, $password)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            unset($_SESSION['admin_error']);

            header('Location: /admin');
            exit;
        }

        $_SESSION['admin_error'] = 'اسم المستخدم أو كلمة المرور غير صحيحة';

        header('Location: /admin/login');
        exit;
    }

    public function dashboard(): string
    {
        Database::migrate();

        $this->requireLogin();

        return View::render('admin/dashboard', [
            'title' => 'لوحة المدير',
            'app_name' => Config::appName(),
            'admin_username' => $_SESSION['admin_username'] ?? 'admin',

            'settings' => Setting::all(),
            'admin_count' => Admin::count(),
            'qos_profiles' => QosProfile::all(),
            'announcements' => Announcement::all(),
            'announcements_active' => Announcement::countActive(),
            'payments' => Payment::latest(5),

            'customers_count' => CustomerLocal::count(),
            'customers_paid' => CustomerLocal::countByPaymentStatus('paid'),
            'customers_due' => CustomerLocal::countByPaymentStatus('due'),
            'customers_pending' => CustomerLocal::countByPaymentStatus('pending'),
            'customers_unknown' => CustomerLocal::countByPaymentStatus('unknown'),
            'customers_unpaid' => CustomerLocal::unpaidCount(),

            'total_paid' => Payment::totalPaid(),
        ]);
    }

    public function customers(): string
    {
        Database::migrate();

        $this->requireLogin();

        return View::render('admin/customers', [
            'title' => 'إدارة الزبائن',
            'app_name' => Config::appName(),
            'customers' => CustomerLocal::all(),
            'routers' => Router::enabled(),
        ]);
    }

    public function editCustomer(): string
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_GET['username'] ?? ''));
        $customer = CustomerLocal::findByUsername($username);

        if (!$customer) {
            header('Location: /admin/customers');
            exit;
        }

        return View::render('admin/edit_customer', [
            'title' => 'تعديل الزبون',
            'app_name' => Config::appName(),
            'customer' => $customer,
            'routers' => Router::enabled(),
        ]);
    }

    public function updateCustomer(): void
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_POST['username'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $accessType = trim((string) ($_POST['access_type'] ?? 'hybrid'));
        $paymentStatus = trim((string) ($_POST['payment_status'] ?? 'unknown'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $routerId = (int) ($_POST['router_id'] ?? 0);
        $serviceBackend = $this->serviceBackend((string) ($_POST['service_backend'] ?? 'user-manager'));

        $allowedAccessTypes = ['hotspot', 'ppp', 'hybrid'];
        $allowedStatuses = ['paid', 'due', 'pending', 'unknown'];

        if (!in_array($accessType, $allowedAccessTypes, true)) {
            $accessType = 'hybrid';
        }

        if (!in_array($paymentStatus, $allowedStatuses, true)) {
            $paymentStatus = 'unknown';
        }

        if ($username !== '') {
            CustomerLocal::updateDetails(
                $username,
                $displayName,
                $phone,
                $accessType,
                $paymentStatus,
                $notes,
                $routerId > 0 ? $routerId : null,
                $serviceBackend
            );
        }

        header('Location: /admin/customers');
        exit;
    }

    public function confirmDeleteCustomer(): string
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_GET['username'] ?? ''));
        $customer = CustomerLocal::findByUsername($username);

        if (!$customer) {
            header('Location: /admin/customers');
            exit;
        }

        return View::render('admin/delete_customer', [
            'title' => 'تأكيد حذف الزبون',
            'app_name' => Config::appName(),
            'customer' => $customer,
        ]);
    }

    public function deleteCustomer(): void
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_POST['username'] ?? ''));
        $confirm = trim((string) ($_POST['confirm'] ?? ''));

        if ($username !== '' && $confirm === 'DELETE') {
            CustomerLocal::deleteByUsername($username);
        }

        header('Location: /admin/customers');
        exit;
    }

    public function dueCustomers(): string
    {
        Database::migrate();

        $this->requireLogin();

        return View::render('admin/due_customers', [
            'title' => 'الزبائن غير المدفوعين',
            'app_name' => Config::appName(),
            'customers' => CustomerLocal::unpaid(),
            'due_count' => CustomerLocal::countByPaymentStatus('due'),
            'pending_count' => CustomerLocal::countByPaymentStatus('pending'),
            'unknown_count' => CustomerLocal::countByPaymentStatus('unknown'),
            'unpaid_count' => CustomerLocal::unpaidCount(),
        ]);
    }

    public function storeCustomer(): void
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_POST['username'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($_POST['full_name'] ?? ''));
        }
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $accessType = trim((string) ($_POST['access_type'] ?? 'hybrid'));
        $paymentStatus = trim((string) ($_POST['payment_status'] ?? 'unknown'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $packageId = (int) ($_POST['package_id'] ?? 0);
        $routerId = (int) ($_POST['router_id'] ?? 0);
        $serviceBackend = $this->serviceBackend((string) ($_POST['service_backend'] ?? 'user-manager'));

        if ($username !== '') {
            CustomerLocal::create(
                $username,
                $displayName,
                $phone,
                $accessType,
                $paymentStatus,
                $notes,
                $packageId,
                $routerId > 0 ? $routerId : null,
                $serviceBackend
            );
        }

        header('Location: /admin/customers');
        exit;
    }

    public function updateCustomerStatus(): void
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_POST['username'] ?? ''));
        $paymentStatus = trim((string) ($_POST['payment_status'] ?? 'unknown'));
        $redirectTo = trim((string) ($_POST['redirect_to'] ?? '/admin/customers'));

        $allowedStatuses = ['paid', 'due', 'pending', 'unknown'];

        if ($username !== '' && in_array($paymentStatus, $allowedStatuses, true)) {
            CustomerLocal::updatePaymentStatus($username, $paymentStatus);
        }

        $allowedRedirects = [
            '/admin/customers',
            '/admin/customers/due',
            '/admin',
        ];

        if (!in_array($redirectTo, $allowedRedirects, true)) {
            $redirectTo = '/admin/customers';
        }

        header('Location: ' . $redirectTo);
        exit;
    }

    private function serviceBackend(string $backend): string
    {
        return in_array($backend, ['user-manager', 'native-hotspot', 'native-pppoe'], true)
            ? $backend
            : 'user-manager';
    }

    public function payments(): string
    {
        Database::migrate();

        $this->requireLogin();

        return View::render('admin/payments', [
            'title' => 'إدارة الدفعات',
            'app_name' => Config::appName(),
            'customers' => CustomerLocal::all(),
            'payments' => Payment::latest(20),
            'total_paid' => Payment::totalPaid(),
        ]);
    }

    public function storePayment(): void
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_POST['username'] ?? ''));
        $amount = (int) ($_POST['amount'] ?? 0);
        $currency = trim((string) ($_POST['currency'] ?? 'SYP'));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($currency === '') {
            $currency = 'SYP';
        }

        if ($username === '' || $amount <= 0) {
            $_SESSION['admin_payments_flash_message'] = 'لم يتم تسجيل الدفعة. تأكد من اختيار مشترك وإدخال مبلغ صحيح.';
            $_SESSION['admin_payments_flash_type'] = 'warning';

            header('Location: /admin/payments');
            exit;
        }

        /*
         * S10.2J:
         * Do not create payments directly here.
         * All subscription payments must pass through CustomerRenewalService::renew()
         * so a GreenNet usage baseline is created automatically.
         */
        $renewalService = new CustomerRenewalService();
        $result = $renewalService->renew($username, $amount, $currency, $note);

        $_SESSION['admin_payments_flash_message'] = (string) ($result['message'] ?? '');
        $_SESSION['admin_payments_flash_type'] = !empty($result['ok']) ? 'success' : 'warning';

        header('Location: /admin/payments');
        exit;
    }

    public function announcements(): string
    {
        Database::migrate();

        $this->requireLogin();

        return View::render('admin/announcements', [
            'title' => 'إدارة الإعلانات',
            'app_name' => Config::appName(),
            'announcements' => Announcement::all(),
        ]);
    }

    public function storeAnnouncement(): void
    {
        Database::migrate();

        $this->requireLogin();

        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title !== '' && $body !== '') {
            Announcement::create($title, $body, $isActive);
        }

        header('Location: /admin/announcements');
        exit;
    }

    public function editAnnouncement(): string
    {
        Database::migrate();

        $this->requireLogin();

        $id = (int) ($_GET['id'] ?? 0);
        $announcement = Announcement::find($id);

        if (!$announcement) {
            header('Location: /admin/announcements');
            exit;
        }

        return View::render('admin/edit_announcement', [
            'title' => 'تعديل الإعلان',
            'app_name' => Config::appName(),
            'announcement' => $announcement,
        ]);
    }

    public function updateAnnouncement(): void
    {
        Database::migrate();

        $this->requireLogin();

        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0 && $title !== '' && $body !== '') {
            Announcement::update($id, $title, $body, $isActive);
        }

        header('Location: /admin/announcements');
        exit;
    }

    public function deleteAnnouncement(): void
    {
        Database::migrate();

        $this->requireLogin();

        $id = (int) ($_POST['id'] ?? 0);
        $confirm = trim((string) ($_POST['confirm'] ?? ''));

        if ($id > 0 && $confirm === 'DELETE') {
            Announcement::delete($id);
        }

        header('Location: /admin/announcements');
        exit;
    }

    public function logout(): void
    {
        unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['admin_error']);

        header('Location: /admin/login');
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
