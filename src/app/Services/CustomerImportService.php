<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Models\CustomerLocal;

class CustomerImportService
{
    public function importFromMikroTik(array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));

        if ($username === '') {
            return [
                'ok' => false,
                'message' => 'اسم المستخدم فارغ.',
                'username' => '',
                'created' => false,
            ];
        }

        $existing = CustomerLocal::findByUsername($username);

        if ($existing !== null) {
            return [
                'ok' => true,
                'message' => 'المستخدم موجود مسبقاً داخل CRM.',
                'username' => $username,
                'created' => false,
            ];
        }

        $displayName = trim((string) ($data['display_name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $accessType = $this->normalizeAccessType((string) ($data['access_type'] ?? 'hybrid'));
        $paymentStatus = $this->normalizePaymentStatus((string) ($data['payment_status'] ?? 'unknown'));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($notes === '') {
            $notes = $this->buildDefaultNotes($data);
        }

        CustomerLocal::create(
            $username,
            $displayName,
            $phone,
            $accessType,
            $paymentStatus,
            $notes
        );

        return [
            'ok' => true,
            'message' => 'تمت إضافة المستخدم إلى CRM.',
            'username' => $username,
            'created' => true,
        ];
    }

    private function normalizeAccessType(string $accessType): string
    {
        $accessType = strtolower(trim($accessType));

        return match ($accessType) {
            'hotspot' => 'hotspot',
            'ppp', 'pppoe' => 'ppp',
            'hybrid' => 'hybrid',
            default => 'hybrid',
        };
    }

    private function normalizePaymentStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'paid' => 'paid',
            'due' => 'due',
            'pending' => 'pending',
            'unknown' => 'unknown',
            default => 'unknown',
        };
    }

    private function buildDefaultNotes(array $data): string
    {
        $source = trim((string) ($data['source'] ?? 'MikroTik'));
        $profile = trim((string) ($data['profile'] ?? ''));
        $comment = trim((string) ($data['comment'] ?? ''));

        $parts = [
            'مستورد من ' . ($source !== '' ? $source : 'MikroTik'),
        ];

        if ($profile !== '' && $profile !== '-') {
            $parts[] = 'Profile: ' . $profile;
        }

        if ($comment !== '' && $comment !== '-') {
            $parts[] = 'Comment: ' . $comment;
        }

        return implode(' - ', $parts);
    }
}