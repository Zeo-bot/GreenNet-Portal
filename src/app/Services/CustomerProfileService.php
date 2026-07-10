<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\ServicePackage;

class CustomerProfileService
{
    public function getProfile(string $username): array
    {
        $username = trim($username);

        $dashboardService = new CustomerDashboardService();
        $connection = $dashboardService->getDashboardData($username);

        $customer = CustomerLocal::findByUsername($username);
        $payments = Payment::forUser($username);

        $package = null;

        if ($customer !== null) {
            $packageId = (int) ($customer['package_id'] ?? 0);

            if ($packageId > 0) {
                $package = ServicePackage::find($packageId);
            }
        }

        $latestRenewal = Payment::latestRenewalForUser($username);
        $renewalService = new CustomerRenewalService();
        $subscription = $renewalService->subscriptionStatus($latestRenewal);

        $totalPaid = 0;

        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') === 'paid') {
                $totalPaid += (int) ($payment['amount'] ?? 0);
            }
        }

        $paymentStatus = $customer['payment_status'] ?? 'not_registered';

        return [
            'username' => $username,

            'crm_found' => $customer !== null,
            'customer' => $customer,

            'package_found' => $package !== null,
            'package' => $package,

            'latest_renewal' => $latestRenewal,
            'subscription' => $subscription,

            'payment_status' => $paymentStatus,
            'payment_label' => $this->paymentLabel($paymentStatus),

            'payments' => $payments,
            'payments_count' => count($payments),
            'total_paid' => $totalPaid,

            'connection' => $connection,
            'is_online' => (bool) ($connection['routeros_found'] ?? false),
        ];
    }

    private function paymentLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'مدفوع',
            'due' => 'عليه دفع',
            'pending' => 'مؤجل',
            'unknown' => 'غير معروف',
            'not_registered' => 'غير موجود في CRM',
            default => 'غير معروف',
        };
    }
}