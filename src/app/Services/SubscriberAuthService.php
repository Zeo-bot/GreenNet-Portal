<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Models\AppLog;
use GreenNet\Models\CustomerLocal;
use GreenNet\Services\RouterOS\MikroTikService;

class SubscriberAuthService
{
    public function attempt(string $username, string $phoneOrCode = ''): array
    {
        $username = trim($username);
        $phoneOrCode = trim($phoneOrCode);

        if ($username === '') {
            return [
                'ok' => false,
                'message' => 'يرجى إدخال اسم المستخدم.',
                'username' => '',
                'source' => 'none',
            ];
        }

        $customer = CustomerLocal::findByUsername($username);

        if ($customer !== null) {
            $storedPhone = $this->normalizePhone((string) ($customer['phone'] ?? ''));
            $providedPhone = $this->normalizePhone($phoneOrCode);

            if ($storedPhone !== '') {
                if ($providedPhone === '') {
                    AppLog::warning('فشل دخول مشترك بسبب عدم إدخال الهاتف', [
                        'username' => $username,
                    ]);

                    return [
                        'ok' => false,
                        'message' => 'يرجى إدخال رقم الهاتف المسجل لهذا الحساب.',
                        'username' => $username,
                        'source' => 'crm',
                    ];
                }

                if ($storedPhone !== $providedPhone) {
                    AppLog::warning('فشل دخول مشترك بسبب رقم هاتف غير مطابق', [
                        'username' => $username,
                    ]);

                    return [
                        'ok' => false,
                        'message' => 'رقم الهاتف غير مطابق للبيانات المسجلة.',
                        'username' => $username,
                        'source' => 'crm',
                    ];
                }
            }

            AppLog::info('تسجيل دخول مشترك من CRM', [
                'username' => $username,
            ]);

            return [
                'ok' => true,
                'message' => 'تم تسجيل الدخول بنجاح.',
                'username' => $username,
                'source' => 'crm',
            ];
        }

        if ($this->existsInMikroTik($username)) {
            AppLog::info('تسجيل دخول مشترك موجود في MikroTik وغير موجود في CRM', [
                'username' => $username,
            ]);

            return [
                'ok' => true,
                'message' => 'تم تسجيل الدخول عبر MikroTik.',
                'username' => $username,
                'source' => 'mikrotik',
            ];
        }

        AppLog::warning('فشل دخول مشترك غير موجود', [
            'username' => $username,
        ]);

        return [
            'ok' => false,
            'message' => 'اسم المستخدم غير موجود.',
            'username' => $username,
            'source' => 'none',
        ];
    }

    private function existsInMikroTik(string $username): bool
    {
        $mikrotik = new MikroTikService();

        $hotspotUsers = $mikrotik->readHotspotUsers();

        if (($hotspotUsers['ok'] ?? false) === true) {
            foreach ($hotspotUsers['rows'] as $row) {
                if ($this->rowUsername($row) === $username) {
                    return true;
                }
            }
        }

        $pppSecrets = $mikrotik->readPppSecrets();

        if (($pppSecrets['ok'] ?? false) === true) {
            foreach ($pppSecrets['rows'] as $row) {
                if ($this->rowUsername($row) === $username) {
                    return true;
                }
            }
        }

        $userManagerUsers = $mikrotik->readUserManagerUsers();

        if (($userManagerUsers['ok'] ?? false) === true) {
            foreach ($userManagerUsers['rows'] as $row) {
                if ($this->rowUsername($row) === $username) {
                    return true;
                }
            }
        }

        return false;
    }

    private function rowUsername(array $row): string
    {
        foreach (['name', 'user', 'username', 'login'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}