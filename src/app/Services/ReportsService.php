<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;

class ReportsService
{
    public function getSummary(): array
    {
        return [
            'total_paid' => Payment::totalPaid(),
            'today_paid' => $this->sumPaidToday(),
            'month_paid' => $this->sumPaidThisMonth(),

            'total_payments_count' => $this->countPayments(),
            'today_payments_count' => $this->countPaymentsToday(),
            'month_payments_count' => $this->countPaymentsThisMonth(),

            'renewals_count' => $this->countRenewals(),
            'today_renewals_count' => $this->countRenewalsToday(),
            'month_renewals_count' => $this->countRenewalsThisMonth(),

            'customers_count' => CustomerLocal::count(),
            'paid_customers_count' => CustomerLocal::countByPaymentStatus('paid'),
            'due_customers_count' => CustomerLocal::countByPaymentStatus('due'),
            'pending_customers_count' => CustomerLocal::countByPaymentStatus('pending'),
            'unknown_customers_count' => CustomerLocal::countByPaymentStatus('unknown'),

            'package_sales' => $this->packageSales(),
            'latest_payments' => Payment::latest(15),
        ];
    }

    private function sumPaidToday(): int
    {
        $stmt = Database::connection()->query("
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE status = 'paid'
              AND date(paid_at) = date('now')
        ");

        return (int) $stmt->fetchColumn();
    }

    private function sumPaidThisMonth(): int
    {
        $stmt = Database::connection()->query("
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE status = 'paid'
              AND strftime('%Y-%m', paid_at) = strftime('%Y-%m', 'now')
        ");

        return (int) $stmt->fetchColumn();
    }

    private function countPayments(): int
    {
        return (int) Database::connection()->query("
            SELECT COUNT(*)
            FROM payments
        ")->fetchColumn();
    }

    private function countPaymentsToday(): int
    {
        return (int) Database::connection()->query("
            SELECT COUNT(*)
            FROM payments
            WHERE date(paid_at) = date('now')
        ")->fetchColumn();
    }

    private function countPaymentsThisMonth(): int
    {
        return (int) Database::connection()->query("
            SELECT COUNT(*)
            FROM payments
            WHERE strftime('%Y-%m', paid_at) = strftime('%Y-%m', 'now')
        ")->fetchColumn();
    }

    private function countRenewals(): int
    {
        return (int) Database::connection()->query("
            SELECT COUNT(*)
            FROM payments
            WHERE package_id > 0
        ")->fetchColumn();
    }

    private function countRenewalsToday(): int
    {
        return (int) Database::connection()->query("
            SELECT COUNT(*)
            FROM payments
            WHERE package_id > 0
              AND date(paid_at) = date('now')
        ")->fetchColumn();
    }

    private function countRenewalsThisMonth(): int
    {
        return (int) Database::connection()->query("
            SELECT COUNT(*)
            FROM payments
            WHERE package_id > 0
              AND strftime('%Y-%m', paid_at) = strftime('%Y-%m', 'now')
        ")->fetchColumn();
    }

    private function packageSales(): array
    {
        $stmt = Database::connection()->query("
            SELECT
                package_id,
                package_name,
                COUNT(*) AS renewals_count,
                COALESCE(SUM(amount), 0) AS total_amount
            FROM payments
            WHERE status = 'paid'
              AND package_id > 0
            GROUP BY package_id, package_name
            ORDER BY total_amount DESC, renewals_count DESC
        ");

        return $stmt->fetchAll();
    }
}