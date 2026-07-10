<?php

declare(strict_types=1);

namespace GreenNet\Services;

use DateTimeImmutable;
use DateTimeZone;
use GreenNet\Core\Config;
use GreenNet\Models\AppLog;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\ServicePackage;

class CustomerRenewalService
{
    public function preview(string $username): array
    {
        $username = trim($username);
        $customer = CustomerLocal::findByUsername($username);

        if ($customer === null) {
            return [
                'ok' => false,
                'message' => 'المشترك غير موجود في CRM.',
                'customer' => null,
                'package' => null,
                'latest_renewal' => null,
                'subscription' => null,
            ];
        }

        $packageId = (int) ($customer['package_id'] ?? 0);
        $package = $packageId > 0 ? ServicePackage::find($packageId) : null;
        $latestRenewal = Payment::latestRenewalForUser($username);

        return [
            'ok' => $package !== null,
            'message' => $package !== null ? 'جاهز للتجديد.' : 'لا توجد باقة مرتبطة بهذا المشترك.',
            'customer' => $customer,
            'package' => $package,
            'latest_renewal' => $latestRenewal,
            'subscription' => $this->subscriptionStatus($latestRenewal),
        ];
    }

    public function renew(string $username, int $amount, string $currency, string $note): array
    {
        $preview = $this->preview($username);

        if (($preview['ok'] ?? false) !== true) {
            AppLog::warning('فشل تجديد اشتراك', [
                'username' => $username,
                'reason' => $preview['message'] ?? 'unknown',
            ]);

            return [
                'ok' => false,
                'message' => $preview['message'] ?? 'لا يمكن التجديد.',
            ];
        }

        $customer = $preview['customer'];
        $package = $preview['package'];

        $timezone = new DateTimeZone((string) Config::get('TZ', 'Asia/Damascus'));
        $startsAt = new DateTimeImmutable('now', $timezone);

        $durationDays = (int) ($package['duration_days'] ?? 0);

        if ($durationDays > 0) {
            $expiresAt = $startsAt->modify('+' . $durationDays . ' days');
            $expiresAtText = $expiresAt->format('Y-m-d H:i:s');
        } else {
            $expiresAtText = null;
        }

        if ($amount < 0) {
            $amount = 0;
        }

        if (trim($currency) === '') {
            $currency = 'SYP';
        }

        if (trim($note) === '') {
            $note = 'تجديد اشتراك باقة ' . ($package['name'] ?? '-');
        }

        Payment::create(
            (string) $customer['username'],
            $amount,
            $currency,
            'paid',
            $note,
            (int) $package['id'],
            (string) $package['name'],
            $durationDays,
            (float) ($package['quota_gb'] ?? 0),
            $startsAt->format('Y-m-d H:i:s'),
            $expiresAtText
        );

        CustomerLocal::updatePaymentStatus((string) $customer['username'], 'paid');

        AppLog::info('تم تجديد اشتراك', [
            'username' => (string) $customer['username'],
            'package_id' => (int) $package['id'],
            'package_name' => (string) $package['name'],
            'amount' => $amount,
            'currency' => $currency,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAtText,
        ]);

        return [
            'ok' => true,
            'message' => 'تم تسجيل الدفعة وتجديد الاشتراك.',
            'username' => (string) $customer['username'],
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAtText,
        ];
    }

    public function subscriptionStatus(?array $renewal): ?array
    {
        if ($renewal === null) {
            return null;
        }

        $expiresAt = trim((string) ($renewal['expires_at'] ?? ''));

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

        $diff = $now->diff($expires);
        $daysLeft = (int) $diff->format('%a');

        return [
            'status' => 'active',
            'label' => 'فعال',
            'days_left' => $daysLeft,
            'days_left_label' => $daysLeft . ' يوم',
        ];
    }
}