<?php

declare(strict_types=1);

namespace GreenNet\Services;

use DateTimeImmutable;
use DateTimeZone;
use GreenNet\Core\Config;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\ServicePackage;

class SubscriptionOverviewService
{
    public function getSummary(): array
    {
        $customers = CustomerLocal::all();
        $rows = [];

        foreach ($customers as $customer) {
            $rows[] = $this->buildRow($customer);
        }

        return [
            'rows' => $rows,

            'total_count' => count($rows),
            'active_count' => count(array_filter($rows, fn ($row) => $row['subscription_status'] === 'active')),
            'expired_count' => count(array_filter($rows, fn ($row) => $row['subscription_status'] === 'expired')),
            'soon_3_count' => count(array_filter($rows, fn ($row) => $row['subscription_status'] === 'soon_3')),
            'soon_7_count' => count(array_filter($rows, fn ($row) => in_array($row['subscription_status'], ['soon_3', 'soon_7'], true))),
            'no_renewal_count' => count(array_filter($rows, fn ($row) => $row['subscription_status'] === 'no_renewal')),
            'no_package_count' => count(array_filter($rows, fn ($row) => $row['subscription_status'] === 'no_package')),
        ];
    }

    private function buildRow(array $customer): array
    {
        $username = (string) ($customer['username'] ?? '');
        $packageId = (int) ($customer['package_id'] ?? 0);

        $package = $packageId > 0 ? ServicePackage::find($packageId) : null;
        $latestRenewal = Payment::latestRenewalForUser($username);

        $subscription = $this->detectSubscriptionStatus($package, $latestRenewal);

        return [
            'username' => $username,
            'display_name' => $customer['display_name'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'access_type' => $customer['access_type'] ?? '-',

            'payment_status' => $customer['payment_status'] ?? 'unknown',
            'payment_label' => $this->paymentLabel($customer['payment_status'] ?? 'unknown'),

            'package_found' => $package !== null,
            'package_id' => $package['id'] ?? 0,
            'package_name' => $package['name'] ?? '-',
            'package_price' => $package['price'] ?? 0,
            'package_currency' => $package['currency'] ?? 'SYP',
            'package_duration_days' => $package['duration_days'] ?? 0,
            'package_quota_gb' => $package['quota_gb'] ?? 0,
            'package_rate_limit' => $package['rate_limit'] ?? '-',

            'latest_renewal_found' => $latestRenewal !== null,
            'latest_renewal' => $latestRenewal,
            'starts_at' => $latestRenewal['starts_at'] ?? '',
            'expires_at' => $latestRenewal['expires_at'] ?? '',

            'subscription_status' => $subscription['status'],
            'subscription_label' => $subscription['label'],
            'days_left' => $subscription['days_left'],
            'days_left_label' => $subscription['days_left_label'],
        ];
    }

    private function detectSubscriptionStatus(?array $package, ?array $latestRenewal): array
    {
        if ($package === null) {
            return [
                'status' => 'no_package',
                'label' => 'بلا باقة',
                'days_left' => null,
                'days_left_label' => '-',
            ];
        }

        if ($latestRenewal === null) {
            return [
                'status' => 'no_renewal',
                'label' => 'بلا تجديد',
                'days_left' => null,
                'days_left_label' => '-',
            ];
        }

        $expiresAt = trim((string) ($latestRenewal['expires_at'] ?? ''));

        if ($expiresAt === '') {
            return [
                'status' => 'unknown',
                'label' => 'غير محدد',
                'days_left' => null,
                'days_left_label' => 'غير محدد',
            ];
        }

        $timezone = new DateTimeZone((string) Config::get('TZ', 'Asia/Damascus'));
        $now = new DateTimeImmutable('now', $timezone);
        $expires = new DateTimeImmutable($expiresAt, $timezone);

        if ($expires < $now) {
            return [
                'status' => 'expired',
                'label' => 'منتهي',
                'days_left' => 0,
                'days_left_label' => 'منتهي',
            ];
        }

        $daysLeft = (int) $now->diff($expires)->format('%a');

        if ($daysLeft <= 3) {
            return [
                'status' => 'soon_3',
                'label' => 'سينتهي خلال 3 أيام',
                'days_left' => $daysLeft,
                'days_left_label' => $daysLeft . ' يوم',
            ];
        }

        if ($daysLeft <= 7) {
            return [
                'status' => 'soon_7',
                'label' => 'سينتهي خلال 7 أيام',
                'days_left' => $daysLeft,
                'days_left_label' => $daysLeft . ' يوم',
            ];
        }

        return [
            'status' => 'active',
            'label' => 'فعال',
            'days_left' => $daysLeft,
            'days_left_label' => $daysLeft . ' يوم',
        ];
    }

    private function paymentLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'مدفوع',
            'due' => 'عليه دفع',
            'pending' => 'مؤجل',
            'unknown' => 'غير معروف',
            default => 'غير معروف',
        };
    }
}