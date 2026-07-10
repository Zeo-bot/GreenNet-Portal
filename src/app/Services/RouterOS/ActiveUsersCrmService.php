<?php

declare(strict_types=1);

namespace GreenNet\Services\RouterOS;

use GreenNet\Models\CustomerLocal;

class ActiveUsersCrmService
{
    public function getMergedActiveUsers(): array
    {
        $mikrotik = new MikroTikService();
        $summary = $mikrotik->activeUsersSummary();

        $hotspotUsers = [];

        foreach ($summary['hotspot'] as $user) {
            $username = (string) ($user['user'] ?? '');
            $customer = CustomerLocal::findByUsername($username);

            $bytesInRaw = (int) ($user['bytes-in'] ?? 0);
            $bytesOutRaw = (int) ($user['bytes-out'] ?? 0);

            $hotspotUsers[] = [
                'type' => 'Hotspot',
                'username' => $username,
                'ip_address' => $user['address'] ?? '-',
                'mac_or_caller' => $user['mac-address'] ?? '-',
                'uptime' => $user['uptime'] ?? '-',
                'bytes_in' => MikroTikService::humanBytes($bytesInRaw),
                'bytes_out' => MikroTikService::humanBytes($bytesOutRaw),
                'service' => 'Hotspot',

                'crm_found' => $customer !== null,
                'display_name' => $customer['display_name'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'payment_status' => $customer['payment_status'] ?? 'not_registered',
                'payment_label' => $this->paymentLabel($customer['payment_status'] ?? 'not_registered'),
                'notes' => $customer['notes'] ?? '',
            ];
        }

        $pppUsers = [];

        foreach ($summary['ppp'] as $user) {
            $username = (string) ($user['name'] ?? '');
            $customer = CustomerLocal::findByUsername($username);

            $pppUsers[] = [
                'type' => 'PPP',
                'username' => $username,
                'ip_address' => $user['address'] ?? '-',
                'mac_or_caller' => $user['caller-id'] ?? '-',
                'uptime' => $user['uptime'] ?? '-',
                'bytes_in' => '-',
                'bytes_out' => '-',
                'service' => $user['service'] ?? 'PPP',

                'crm_found' => $customer !== null,
                'display_name' => $customer['display_name'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'payment_status' => $customer['payment_status'] ?? 'not_registered',
                'payment_label' => $this->paymentLabel($customer['payment_status'] ?? 'not_registered'),
                'notes' => $customer['notes'] ?? '',
            ];
        }

        $allUsers = array_merge($hotspotUsers, $pppUsers);

        return [
            'hotspot_users' => $hotspotUsers,
            'ppp_users' => $pppUsers,
            'all_users' => $allUsers,

            'hotspot_count' => count($hotspotUsers),
            'ppp_count' => count($pppUsers),
            'total_count' => count($allUsers),

            'crm_found_count' => count(array_filter($allUsers, fn ($user) => $user['crm_found'] === true)),
            'crm_missing_count' => count(array_filter($allUsers, fn ($user) => $user['crm_found'] === false)),
            'due_count' => count(array_filter($allUsers, fn ($user) => $user['payment_status'] === 'due')),
            'pending_count' => count(array_filter($allUsers, fn ($user) => $user['payment_status'] === 'pending')),
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