<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\ServicePackage;
use GreenNet\Services\Access\AccessServiceFactory;

class CustomerDashboardService
{
    public function getDashboardData(string $username): array
    {
        $username = trim($username);

        if ($username === '') {
            $username = 'guest';
        }

        $accessService = AccessServiceFactory::make();
        $connectionData = $accessService->getSubscriberStatus($username);

        $customer = CustomerLocal::findByUsername($username);

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

        $data = array_merge($connectionData, [
            'username' => $username,

            'crm_found' => $customer !== null,
            'customer_display_name' => $customer['display_name'] ?? '',
            'customer_phone' => $customer['phone'] ?? '',
            'customer_access_type' => $customer['access_type'] ?? '',
            'payment_status' => $customer['payment_status'] ?? 'not_registered',
            'payment_label' => $this->paymentLabel($customer['payment_status'] ?? 'not_registered'),
            'customer_notes' => $customer['notes'] ?? '',

            'package_found' => $package !== null,
            'package_id' => $package['id'] ?? 0,
            'package_name' => $package['name'] ?? '',
            'package_price' => $package['price'] ?? 0,
            'package_currency' => $package['currency'] ?? 'SYP',
            'package_duration_days' => $package['duration_days'] ?? 0,
            'package_quota_gb' => $package['quota_gb'] ?? 0,
            'package_rate_limit' => $package['rate_limit'] ?? '-',
            'package_source_type' => $package['source_type'] ?? '',
            'package_source_profile' => $package['source_profile'] ?? '',
            'package_notes' => $package['notes'] ?? '',

            'latest_renewal' => $latestRenewal,
            'subscription' => $subscription,
            'subscription_found' => $latestRenewal !== null,
            'subscription_status' => $subscription['status'] ?? 'none',
            'subscription_label' => $subscription['label'] ?? 'لا يوجد تجديد',
            'subscription_days_left_label' => $subscription['days_left_label'] ?? 'غير محدد',
            'subscription_starts_at' => $latestRenewal['starts_at'] ?? '',
            'subscription_expires_at' => $latestRenewal['expires_at'] ?? '',
        ]);

        if ($package !== null) {
            $data['package'] = (string) ($package['name'] ?? 'باقة GreenNet');
            $data['speed'] = (string) ($package['rate_limit'] ?? '-');

            $quotaGb = (float) ($package['quota_gb'] ?? 0);

            if ($quotaGb > 0) {
                $data['package_quota_label'] = $this->formatQuota($quotaGb);
            } else {
                $data['package_quota_label'] = 'غير محدد';
            }

            if (($subscription['days_left_label'] ?? '') !== '') {
                $data['days_left'] = (string) $subscription['days_left_label'];
            } else {
                $data['days_left'] = $this->formatDuration((int) ($package['duration_days'] ?? 0));
            }

            if (($connectionData['routeros_found'] ?? false) !== true) {
                $data['used'] = '-';
                $data['remaining'] = $quotaGb > 0 ? $this->formatQuota($quotaGb) : '-';
                $data['used_percent'] = 0;
            }
        } else {
            $data['package_quota_label'] = 'غير محدد';
        }

        return $data;
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

    private function formatDuration(int $days): string
    {
        if ($days <= 0) {
            return 'غير محدد';
        }

        return $days . ' يوم';
    }

    private function formatQuota(float $quotaGb): string
    {
        if ($quotaGb <= 0) {
            return 'غير محدد';
        }

        if ((int) $quotaGb === $quotaGb) {
            return (string) ((int) $quotaGb) . ' GB';
        }

        return rtrim(rtrim((string) $quotaGb, '0'), '.') . ' GB';
    }
}