<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\View;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Services\CustomerRenewalService;
use GreenNet\Models\AppLog;
use PDO;
use Throwable;

class AdminCustomerRenewalController
{
    public function show(): string
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_GET['username'] ?? ''));

        if ($username === '') {
            header('Location: /admin/customers');
            exit;
        }

        $service = new CustomerRenewalService();
        $preview = $service->preview($username);
        $requestId = (int) ($_GET['request_id'] ?? 0);

        return View::render('admin/customer_renew', [
            'title' => 'تجديد الاشتراك',
            'app_name' => Config::appName(),
            'preview' => $preview,
            'renewal_request' => $this->findRenewalRequest($requestId, $username),
        ]);
    }

    public function renew(): void
    {
        Database::migrate();

        $this->requireLogin();

        $username = trim((string) ($_POST['username'] ?? ''));
        $amount = (int) ($_POST['amount'] ?? 0);
        $currency = trim((string) ($_POST['currency'] ?? 'SYP'));
        $note = trim((string) ($_POST['note'] ?? ''));
        $requestId = (int) ($_POST['renewal_request_id'] ?? 0);

        if ($username === '') {
            header('Location: /admin/customers');
            exit;
        }

        $service = new CustomerRenewalService();
        $result = $service->renew($username, $amount, $currency, $note);

        if (!empty($result['ok']) && $requestId > 0) {
            $this->completeRenewalRequest($requestId, $username);
        }

        header('Location: /admin/customers/profile?username=' . urlencode($username));
        exit;
    }

    private function findRenewalRequest(int $id, string $username): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = Database::connection()->prepare("
                SELECT *
                FROM renewal_requests
                WHERE id = :id AND lower(username) = lower(:username)
                LIMIT 1
            ");
            $stmt->execute(['id' => $id, 'username' => $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function completeRenewalRequest(int $id, string $username): void
    {
        try {
            $stmt = Database::connection()->prepare("
                UPDATE renewal_requests
                SET status = 'completed', updated_at = :updated_at
                WHERE id = :id
                  AND lower(username) = lower(:username)
                  AND status <> 'completed'
            ");
            $stmt->execute([
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
                'username' => $username,
            ]);

            if ($stmt->rowCount() > 0) {
                AppLog::info('Renewal request completed with customer renewal', [
                    'request_id' => $id,
                    'username' => $username,
                ]);
            }
        } catch (Throwable $e) {
            AppLog::warning('Renewal succeeded but request status update failed', [
                'request_id' => $id,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}
