<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Models\CustomerLocal;
use GreenNet\Services\RouterOS\MikroTikService;

class MikroTikCrmSyncService
{
    public function getSummary(): array
    {
        $directoryUsers = $this->getDirectoryUsers();

        $missingUsers = array_values(array_filter(
            $directoryUsers,
            fn (array $user) => $user['crm_found'] === false
        ));

        $existingUsers = array_values(array_filter(
            $directoryUsers,
            fn (array $user) => $user['crm_found'] === true
        ));

        return [
            'all_users' => $directoryUsers,
            'missing_users' => $missingUsers,
            'existing_users' => $existingUsers,

            'total_count' => count($directoryUsers),
            'missing_count' => count($missingUsers),
            'existing_count' => count($existingUsers),

            'hotspot_count' => count(array_filter($directoryUsers, fn (array $user) => str_contains($user['sources'], 'Hotspot User'))),
            'ppp_count' => count(array_filter($directoryUsers, fn (array $user) => str_contains($user['sources'], 'PPP Secret'))),
            'user_manager_count' => count(array_filter($directoryUsers, fn (array $user) => str_contains($user['sources'], 'User Manager User'))),
        ];
    }

    public function importSelected(array $usernames): array
    {
        $usernames = array_values(array_unique(array_filter(array_map('trim', $usernames))));

        if (count($usernames) === 0) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        $directoryUsers = $this->getDirectoryUsers();
        $directoryByUsername = [];

        foreach ($directoryUsers as $user) {
            $directoryByUsername[$user['username']] = $user;
        }

        $importService = new CustomerImportService();

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($usernames as $username) {
            if (!isset($directoryByUsername[$username])) {
                $errors++;
                continue;
            }

            $user = $directoryByUsername[$username];

            $result = $importService->importFromMikroTik([
                'username' => $user['username'],
                'display_name' => '',
                'phone' => '',
                'access_type' => $user['access_type'],
                'payment_status' => 'unknown',
                'notes' => $user['default_notes'],
                'source' => $user['sources'],
                'profile' => $user['profile'],
                'comment' => $user['comment'],
            ]);

            if (($result['ok'] ?? false) !== true) {
                $errors++;
                continue;
            }

            if (($result['created'] ?? false) === true) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function getDirectoryUsers(): array
    {
        $mikrotik = new MikroTikService();
        $users = [];

        $hotspotUsers = $mikrotik->readHotspotUsers();

        if (($hotspotUsers['ok'] ?? false) === true) {
            foreach ($hotspotUsers['rows'] as $row) {
                $username = $this->firstValue($row, ['name', 'user', 'username']);

                if ($username === '') {
                    continue;
                }

                $this->mergeUser($users, $username, [
                    'type' => 'Hotspot',
                    'access_type' => 'hotspot',
                    'source' => 'Hotspot User',
                    'profile' => $row['profile'] ?? '-',
                    'disabled' => $row['disabled'] ?? '-',
                    'comment' => $row['comment'] ?? '-',
                    'service' => 'Hotspot',
                    'mac_or_caller' => $row['mac-address'] ?? '-',
                ]);
            }
        }

        $pppSecrets = $mikrotik->readPppSecrets();

        if (($pppSecrets['ok'] ?? false) === true) {
            foreach ($pppSecrets['rows'] as $row) {
                $username = $this->firstValue($row, ['name', 'user', 'username']);

                if ($username === '') {
                    continue;
                }

                $this->mergeUser($users, $username, [
                    'type' => 'PPP',
                    'access_type' => 'ppp',
                    'source' => 'PPP Secret',
                    'profile' => $row['profile'] ?? '-',
                    'disabled' => $row['disabled'] ?? '-',
                    'comment' => $row['comment'] ?? '-',
                    'service' => $row['service'] ?? '-',
                    'mac_or_caller' => $row['caller-id'] ?? ($row['last-caller-id'] ?? '-'),
                ]);
            }
        }

        $userManagerUsers = $mikrotik->readUserManagerUsers();

        if (($userManagerUsers['ok'] ?? false) === true) {
            foreach ($userManagerUsers['rows'] as $row) {
                $username = $this->firstValue($row, ['name', 'user', 'username', 'login']);

                if ($username === '') {
                    continue;
                }

                $this->mergeUser($users, $username, [
                    'type' => 'User Manager',
                    'access_type' => 'hybrid',
                    'source' => 'User Manager User',
                    'profile' => $row['profile'] ?? ($row['actual-profile'] ?? '-'),
                    'disabled' => $row['disabled'] ?? '-',
                    'comment' => $row['comment'] ?? '-',
                    'service' => 'User Manager',
                    'mac_or_caller' => '-',
                ]);
            }
        }

        $finalUsers = [];

        foreach ($users as $username => $user) {
            $customer = CustomerLocal::findByUsername($username);

            $sources = implode(' + ', array_unique($user['sources']));
            $types = implode(' + ', array_unique($user['types']));

            $accessType = $this->resolveAccessType($user['access_types']);

            $profile = $this->cleanJoinedValue($user['profiles']);
            $comment = $this->cleanJoinedValue($user['comments']);
            $disabled = $this->cleanJoinedValue($user['disabled_values']);
            $service = $this->cleanJoinedValue($user['services']);
            $macOrCaller = $this->cleanJoinedValue($user['mac_or_caller_values']);

            $defaultNotesParts = [
                'مستورد من ' . $sources,
            ];

            if ($profile !== '-') {
                $defaultNotesParts[] = 'Profile: ' . $profile;
            }

            if ($comment !== '-') {
                $defaultNotesParts[] = 'Comment: ' . $comment;
            }

            $finalUsers[] = [
                'username' => $username,
                'types' => $types,
                'sources' => $sources,
                'access_type' => $accessType,

                'profile' => $profile,
                'disabled' => $disabled,
                'comment' => $comment,
                'service' => $service,
                'mac_or_caller' => $macOrCaller,

                'crm_found' => $customer !== null,
                'display_name' => $customer['display_name'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'payment_status' => $customer['payment_status'] ?? 'not_registered',
                'payment_label' => $this->paymentLabel($customer['payment_status'] ?? 'not_registered'),

                'default_notes' => implode(' - ', $defaultNotesParts),
            ];
        }

        usort($finalUsers, function (array $a, array $b): int {
            if ($a['crm_found'] !== $b['crm_found']) {
                return $a['crm_found'] ? 1 : -1;
            }

            return strcmp($a['username'], $b['username']);
        });

        return $finalUsers;
    }

    private function mergeUser(array &$users, string $username, array $data): void
    {
        if (!isset($users[$username])) {
            $users[$username] = [
                'sources' => [],
                'types' => [],
                'access_types' => [],
                'profiles' => [],
                'disabled_values' => [],
                'comments' => [],
                'services' => [],
                'mac_or_caller_values' => [],
            ];
        }

        $users[$username]['sources'][] = $data['source'] ?? '-';
        $users[$username]['types'][] = $data['type'] ?? '-';
        $users[$username]['access_types'][] = $data['access_type'] ?? 'hybrid';
        $users[$username]['profiles'][] = $data['profile'] ?? '-';
        $users[$username]['disabled_values'][] = $data['disabled'] ?? '-';
        $users[$username]['comments'][] = $data['comment'] ?? '-';
        $users[$username]['services'][] = $data['service'] ?? '-';
        $users[$username]['mac_or_caller_values'][] = $data['mac_or_caller'] ?? '-';
    }

    private function firstValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveAccessType(array $accessTypes): string
    {
        $accessTypes = array_unique(array_filter($accessTypes));

        if (in_array('hotspot', $accessTypes, true) && count($accessTypes) === 1) {
            return 'hotspot';
        }

        if (in_array('ppp', $accessTypes, true) && count($accessTypes) === 1) {
            return 'ppp';
        }

        return 'hybrid';
    }

    private function cleanJoinedValue(array $values): string
    {
        $values = array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $values
        ))));

        $values = array_values(array_filter($values, fn ($value) => $value !== '' && $value !== '-'));

        if (count($values) === 0) {
            return '-';
        }

        return implode(' + ', $values);
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