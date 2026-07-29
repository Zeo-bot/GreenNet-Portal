<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Contracts\AuthorizedRouterOSWriterInterface;
use GreenNet\Contracts\RouterOSReadGatewayInterface;
use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\RouterPackageProfile;
use GreenNet\Models\ServicePackage;
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use GreenNet\Services\RouterOS\NativeSubscriberRecordResolver;
use GreenNet\Services\WriteSafetyGuard;
use GreenNet\Services\SubscriptionLifecycleService;
use RuntimeException;
use Throwable;

final class AdminNativeSubscriberController
{
    private ?string $customerRedirect = null;

    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();
        $username = trim((string) ($_GET['username'] ?? ''));
        $customer = CustomerLocal::findByUsername($username);

        return View::render('admin/native_subscriber_operation', [
            'title' => 'Native RouterOS Operation',
            'customer' => $customer,
            'router' => $customer ? RouterConnectionResolver::routerForCustomer($username) : [],
            'package' => $this->package($customer),
            'action' => $this->action((string) ($_GET['action'] ?? 'package')),
            'result' => $_SESSION['native_subscriber_result'] ?? null,
            'preflight' => (new WriteSafetyGuard())->preflight(),
            'message' => $this->consume('message'),
            'message_type' => $this->consume('type', 'success'),
        ]);
    }

    public function preview(): void
    {
        Database::migrate();
        $this->requireLogin();

        try {
            $username = trim((string) ($_POST['username'] ?? ''));
            $action = $this->action((string) ($_POST['action'] ?? ''));
            $plan = $this->buildPlan($username, $action);
            $guard = new WriteSafetyGuard();
            $guard->ensureTables();
            $guard->assertDryRunAllowed();
            $plan['audit_id'] = $guard->recordDryRun([
                'action' => 'native_' . $action,
                'dataset' => (string) $plan['backend'],
                'username' => $username,
                'command' => (string) $plan['command'],
                'params' => $plan,
                'router_response' => 'Dry Run only. No RouterOS write.',
            ]);
            $_SESSION['native_subscriber_result'] = $plan;
            $this->flash('تم إنشاء المعاينة. لم يتم تنفيذ أي تعديل.');
        } catch (Throwable $e) {
            $_SESSION['native_subscriber_result'] = ['error' => $e->getMessage()];
            $this->flash($e->getMessage(), 'warning');
        }

        $this->redirect((string) ($_POST['username'] ?? ''), (string) ($_POST['action'] ?? 'package'));
    }

    public function execute(): void
    {
        Database::migrate();
        $this->requireLogin();
        $username = trim((string) ($_POST['username'] ?? ''));
        $action = $this->action((string) ($_POST['action'] ?? ''));

        try {
            $confirm = trim((string) ($_POST['confirm'] ?? ''));
            if ($confirm !== strtoupper($action)) {
                throw new RuntimeException('Confirmation text is incorrect.');
            }
            $last = $_SESSION['native_subscriber_result'] ?? null;
            if (!is_array($last)
                || (string) ($last['username'] ?? '') !== $username
                || (string) ($last['action'] ?? '') !== $action
                || !empty($last['executed'])) {
                throw new RuntimeException('Create a fresh matching preview first.');
            }

            $fresh = $this->buildPlan($username, $action);
            if ((string) ($fresh['backend'] ?? '') !== (string) ($last['backend'] ?? '')
                || (string) ($fresh['record_id'] ?? '') !== (string) ($last['record_id'] ?? '')
                || (string) ($fresh['profile_name'] ?? '') !== (string) ($last['profile_name'] ?? '')) {
                throw new RuntimeException('Router state changed after preview.');
            }

            $password = (string) ($_POST['password'] ?? '');
            if (in_array($action, ['create', 'password'], true) && $password === '') {
                throw new RuntimeException('Password is required for execution.');
            }
            if (preg_match('/[\r\n\t]/', $password)) {
                throw new RuntimeException('Password contains unsupported control characters.');
            }

            $params = $this->writeParams($fresh, $password);
            $bundle = RouterConnectionResolver::gatewayBundleForCustomer($username, ['timeout' => 6]);
            $result = $bundle->write->execute(
                new WriteExecutionRequest(
                    'native_' . $action,
                    (string) $fresh['backend'],
                    $username,
                    (string) $fresh['command'],
                    array_diff_key($params, ['password' => true]),
                    (int) ($last['audit_id'] ?? 0),
                    true
                ),
                function (AuthorizedRouterOSWriterInterface $writer) use ($fresh, $params, $username, $action, $bundle): array {
                    $writer->execute(new RouterOSWriteCommand((string) $fresh['command'], $params));
                    $after = $this->resolveRecord($bundle->read, $username, (string) $fresh['backend']);

                    if ($action === 'delete') {
                        if ($after !== null) {
                            throw new RuntimeException('Native record still exists after deletion.');
                        }
                    } else {
                        if ($after === null) {
                            throw new RuntimeException('Native record is missing after write.');
                        }
                        if ($action === 'package' && (string) ($after['profile'] ?? '') !== (string) $fresh['profile_name']) {
                            throw new RuntimeException('Profile verification failed.');
                        }
                        if (in_array($action, ['disable', 'enable'], true)) {
                            $expected = $action === 'disable' ? 'true' : 'false';
                            $actual = $this->boolText((string) ($after['disabled'] ?? 'false'));
                            if ($actual !== $expected) {
                                throw new RuntimeException('Disabled-state verification failed.');
                            }
                        }
                    }

                    return ['verified' => true, 'record_id' => (string) ($after['.id'] ?? '')];
                }
            );

            if (!$result->ok) {
                throw new RuntimeException($result->safeError !== '' ? $result->safeError : 'Native operation failed.');
            }
            if ($action === 'disable' || $action === 'enable') {
                $this->updateLocalStatus($username, $action === 'disable' ? 'suspended' : 'active');
                if ($action === 'disable' && Database::hasConnection()) {
                    (new SubscriptionLifecycleService())->markEnforcement($username, 'enforced', 'Native account disabled and verified.');
                }
            }
            $fresh['executed'] = true;
            $fresh['execution_ok'] = true;
            $_SESSION['native_subscriber_result'] = $fresh;
            $this->flash('تم تنفيذ العملية والتحقق من النتيجة.');
        } catch (Throwable $e) {
            if ($action === 'disable' && $username !== '' && Database::hasConnection()) {
                (new SubscriptionLifecycleService())->markEnforcement($username, 'failed', $e->getMessage());
            }
            $previous = is_array($_SESSION['native_subscriber_result'] ?? null) ? $_SESSION['native_subscriber_result'] : [];
            $previous['execute_error'] = $e->getMessage();
            $_SESSION['native_subscriber_result'] = $previous;
            $this->flash($e->getMessage(), 'warning');
        }

        $this->redirect($username, $action);
    }

    public function customerAction(): void
    {
        Database::migrate();
        $this->requireLogin();
        $username = trim((string) ($_POST['username'] ?? ''));
        $action = $this->action((string) ($_POST['action'] ?? ''));
        $this->customerRedirect = '/admin/customers/profile?username=' . rawurlencode($username);

        try {
            if (!in_array($action, ['package', 'disable', 'enable', 'delete'], true)) {
                throw new RuntimeException('هذه العملية تحتاج إدخال بيانات إضافية.');
            }
            $plan = $this->buildPlan($username, $action);
            $guard = new WriteSafetyGuard();
            $guard->ensureTables();
            $guard->assertDryRunAllowed();
            $plan['audit_id'] = $guard->recordDryRun([
                'action' => 'native_' . $action,
                'dataset' => (string) $plan['backend'],
                'username' => $username,
                'command' => (string) $plan['command'],
                'params' => $plan,
                'router_response' => 'Internal production preflight completed.',
            ]);
            $_SESSION['native_subscriber_result'] = $plan;
            $_POST['confirm'] = strtoupper($action);
            $this->execute();
        } catch (Throwable $e) {
            $this->flash('تعذر تنفيذ العملية: ' . $e->getMessage(), 'warning');
            $this->redirect($username, $action);
        }
    }

    private function buildPlan(string $username, string $action): array
    {
        $customer = CustomerLocal::findByUsername($username);
        if ($customer === null) {
            throw new RuntimeException('Customer not found.');
        }
        $backend = (string) ($customer['service_backend'] ?? 'user-manager');
        if (!in_array($backend, ['native-hotspot', 'native-pppoe'], true)) {
            throw new RuntimeException('This customer is controlled by User Manager.');
        }
        $router = RouterConnectionResolver::routerForCustomer($username);
        $bundle = RouterConnectionResolver::gatewayBundleForCustomer($username, ['timeout' => 6]);
        $record = $this->resolveRecord($bundle->read, $username, $backend);
        $package = $this->package($customer);
        $profile = '';
        if (in_array($action, ['create', 'package'], true)) {
            if ($package === null) {
                throw new RuntimeException('Assign a GreenNet package first.');
            }
            $profile = RouterPackageProfile::profileName(
                (int) ($router['id'] ?? 0),
                (int) ($package['id'] ?? 0),
                '',
                $backend
            );
            if ($profile === '') {
                throw new RuntimeException('No backend-specific package profile mapping exists for this router.');
            }
        }
        if ($action === 'create' && $record !== null) {
            throw new RuntimeException('A unique native account already exists.');
        }
        if ($action !== 'create' && $record === null) {
            throw new RuntimeException('Native account record was not found.');
        }

        $commandBase = $backend === 'native-hotspot' ? '/ip/hotspot/user' : '/ppp/secret';
        $command = $commandBase . match ($action) {
            'create' => '/add',
            'delete' => '/remove',
            default => '/set',
        };

        return [
            'ok' => true,
            'username' => $username,
            'action' => $action,
            'backend' => $backend,
            'router_id' => (int) ($router['id'] ?? 0),
            'router_name' => (string) ($router['name'] ?? ''),
            'record_id' => (string) ($record['.id'] ?? ''),
            'profile_name' => $profile,
            'command' => $command,
            'executed' => false,
        ];
    }

    private function resolveRecord(RouterOSReadGatewayInterface $read, string $username, string $backend): ?array
    {
        return (new NativeSubscriberRecordResolver())->resolve($read, $username, $backend);
    }

    private function writeParams(array $plan, string $password): array
    {
        $action = (string) $plan['action'];
        if ($action === 'create') {
            $params = [
                'name' => (string) $plan['username'],
                'password' => $password,
                'profile' => (string) $plan['profile_name'],
                'disabled' => 'no',
                'comment' => 'GreenNet managed subscriber',
            ];
            if ($plan['backend'] === 'native-pppoe') {
                $params['service'] = 'pppoe';
            }
            return $params;
        }
        if ($action === 'delete') {
            return ['numbers' => (string) $plan['record_id']];
        }
        $params = ['numbers' => (string) $plan['record_id']];
        $params[match ($action) {
            'package' => 'profile',
            'password' => 'password',
            default => 'disabled',
        }] = match ($action) {
            'package' => (string) $plan['profile_name'],
            'password' => $password,
            'disable' => 'yes',
            'enable' => 'no',
            default => throw new RuntimeException('Unsupported native action.'),
        };
        return $params;
    }

    private function package(?array $customer): ?array
    {
        $id = (int) ($customer['package_id'] ?? 0);
        return $id > 0 ? ServicePackage::find($id) : null;
    }

    private function updateLocalStatus(string $username, string $status): void
    {
        $stmt = Database::connection()->prepare("
            UPDATE customers_local SET service_status = :status, updated_at = CURRENT_TIMESTAMP
            WHERE username = :username
        ");
        $stmt->execute(['status' => $status, 'username' => $username]);
    }

    private function action(string $action): string
    {
        return in_array($action, ['create', 'package', 'password', 'disable', 'enable', 'delete'], true)
            ? $action
            : 'package';
    }

    private function boolText(string $value): string
    {
        return in_array(strtolower($value), ['yes', 'true', '1'], true) ? 'true' : 'false';
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['native_operation_message'] = $message;
        $_SESSION['native_operation_type'] = $type;
    }

    private function consume(string $key, string $default = ''): string
    {
        $sessionKey = $key === 'message' ? 'native_operation_message' : 'native_operation_type';
        $value = (string) ($_SESSION[$sessionKey] ?? $default);
        unset($_SESSION[$sessionKey]);
        return $value;
    }

    private function redirect(string $username, string $action): never
    {
        header('Location: ' . ($this->customerRedirect
            ?? '/admin/native-subscriber?username=' . rawurlencode($username) . '&action=' . rawurlencode($action)));
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
