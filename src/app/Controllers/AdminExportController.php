<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Models\AppLog;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Services\SubscriptionOverviewService;

class AdminExportController
{
    public function customers(): void
    {
        Database::migrate();

        $this->requireLogin();

        $rows = CustomerLocal::all();

        AppLog::info('تم تصدير الزبائن CSV', [
            'count' => count($rows),
        ]);

        $this->downloadCsv('greennet-customers.csv', [
            'username',
            'display_name',
            'phone',
            'access_type',
            'payment_status',
            'package_id',
            'notes',
            'created_at',
            'updated_at',
        ], $rows);
    }

    public function payments(): void
    {
        Database::migrate();

        $this->requireLogin();

        $rows = Database::connection()->query("
            SELECT *
            FROM payments
            ORDER BY paid_at DESC, id DESC
        ")->fetchAll();

        AppLog::info('تم تصدير الدفعات CSV', [
            'count' => count($rows),
        ]);

        $this->downloadCsv('greennet-payments.csv', [
            'id',
            'username',
            'amount',
            'currency',
            'status',
            'note',
            'package_id',
            'package_name',
            'duration_days',
            'quota_gb',
            'starts_at',
            'expires_at',
            'paid_at',
            'created_at',
        ], $rows);
    }

    public function subscriptions(): void
    {
        Database::migrate();

        $this->requireLogin();

        $service = new SubscriptionOverviewService();
        $summary = $service->getSummary();
        $rows = $summary['rows'] ?? [];

        AppLog::info('تم تصدير الاشتراكات CSV', [
            'count' => count($rows),
        ]);

        $this->downloadCsv('greennet-subscriptions.csv', [
            'username',
            'display_name',
            'phone',
            'access_type',
            'payment_status',
            'payment_label',
            'package_name',
            'package_price',
            'package_currency',
            'package_duration_days',
            'package_quota_gb',
            'package_rate_limit',
            'starts_at',
            'expires_at',
            'subscription_status',
            'subscription_label',
            'days_left_label',
        ], $rows);
    }

    public function logs(): void
    {
        Database::migrate();

        $this->requireLogin();

        $rows = Database::connection()->query("
            SELECT *
            FROM app_logs
            ORDER BY created_at DESC, id DESC
        ")->fetchAll();

        AppLog::info('تم تصدير سجل العمليات CSV', [
            'count' => count($rows),
        ]);

        $this->downloadCsv('greennet-logs.csv', [
            'id',
            'level',
            'message',
            'context',
            'created_at',
        ], $rows);
    }

    private function downloadCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $output = fopen('php://output', 'w');

        echo "\xEF\xBB\xBF";

        fputcsv($output, $headers);

        foreach ($rows as $row) {
            $line = [];

            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }

            fputcsv($output, $line);
        }

        fclose($output);
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