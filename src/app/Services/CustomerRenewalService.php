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
use Throwable;

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

    public function renew(string $username, int $amount, string $currency, string $note, int $packageId = 0): array
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
        $package = $packageId > 0 ? ServicePackage::find($packageId) : $preview['package'];
        if (!is_array($package)) {
            return ['ok' => false, 'message' => 'الباقة المحددة غير موجودة.'];
        }
        if ((int) ($customer['package_id'] ?? 0) !== (int) $package['id']) {
            CustomerLocal::updatePackage((string) $customer['username'], (int) $package['id']);
        }

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
        \GreenNet\Core\Database::connection()->prepare("
            UPDATE customers_local SET service_status = 'sync_pending', updated_at = CURRENT_TIMESTAMP
            WHERE username = :username
        ")->execute(['username' => (string) $customer['username']]);

        $baseline = $this->createRenewalBaseline((string) $customer['username']);
        (new SubscriptionLifecycleService())->resetAfterRenewal((string) $customer['username']);

        AppLog::info('تم تجديد اشتراك', [
            'username' => (string) $customer['username'],
            'package_id' => (int) $package['id'],
            'package_name' => (string) $package['name'],
            'amount' => $amount,
            'currency' => $currency,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAtText,
            'baseline_ok' => !empty($baseline['ok']),
            'baseline_id' => (int) ($baseline['baseline_id'] ?? 0),
            'baseline_message' => (string) ($baseline['message'] ?? ''),
        ]);

        $message = 'تم تسجيل الدفعة وتجديد الاشتراك.';

        if (!empty($baseline['ok'])) {
            $message .= ' تم إنشاء Baseline جديد، وسيبدأ الاستهلاك من 0 داخل GreenNet.';
        } else {
            $message .= ' تنبيه: لم يتم إنشاء Baseline تلقائي. السبب: ' . (string) ($baseline['message'] ?? 'غير معروف');
        }

        return [
            'ok' => true,
            'message' => $message,
            'username' => (string) $customer['username'],
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAtText,
            'baseline' => $baseline,
            'baseline_ok' => !empty($baseline['ok']),
            'baseline_id' => (int) ($baseline['baseline_id'] ?? 0),
            'mikrotik_write' => false,
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

    private function createRenewalBaseline(string $username): array
    {
        try {
            $baselineService = new GreenNetUsageBaselineService();

            return $baselineService->createForUser($username, 'auto_renewal', 0);
        } catch (Throwable $e) {
            AppLog::warning('فشل إنشاء Baseline تلقائي بعد التجديد', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason' => 'exception',
                'message' => $e->getMessage(),
            ];
        }
    }
}
