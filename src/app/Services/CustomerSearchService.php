<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Models\CustomerLocal;
use GreenNet\Services\RouterOS\ActiveUsersCrmService;
use GreenNet\Services\RouterOS\MikroTikService;

class CustomerSearchService
{
    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'query' => '',
                'results' => [],
                'total_count' => 0,
                'crm_count' => 0,
                'online_count' => 0,
                'missing_crm_count' => 0,
                'mikrotik_directory_count' => 0,
                'hotspot_users_count' => 0,
                'ppp_secrets_count' => 0,
                'user_manager_count' => 0,
            ];
        }

        $results = [];

        $this->mergeLocalCrmResults($results, $query);
        $this->mergeActiveMikroTikResults($results, $query);
        $directoryCounters = $this->mergeMikroTikDirectoryResults($results, $query);

        $finalResults = array_values($results);

        usort($finalResults, function (array $a, array $b): int {
            if ($a['online'] !== $b['online']) {
                return $a['online'] ? -1 : 1;
            }

            if ($a['crm_found'] !== $b['crm_found']) {
                return $a['crm_found'] ? -1 : 1;
            }

            return strcmp($a['username'], $b['username']);
        });

        return [
            'query' => $query,
            'results' => $finalResults,
            'total_count' => count($finalResults),
            'crm_count' => count(array_filter($finalResults, fn ($row) => $row['crm_found'] === true)),
            'online_count' => count(array_filter($finalResults, fn ($row) => $row['online'] === true)),
            'missing_crm_count' => count(array_filter($finalResults, fn ($row) => $row['crm_found'] === false)),
            'mikrotik_directory_count' => count(array_filter($finalResults, fn ($row) => $row['mikrotik_directory_found'] === true)),
            'hotspot_users_count' => $directoryCounters['hotspot_users_count'],
            'ppp_secrets_count' => $directoryCounters['ppp_secrets_count'],
            'user_manager_count' => $directoryCounters['user_manager_count'],
        ];
    }

    private function mergeLocalCrmResults(array &$results, string $query): void
    {
        $localCustomers = CustomerLocal::search($query);

        foreach ($localCustomers as $customer) {
            $username = (string) ($customer['username'] ?? '');

            if ($username === '') {
                continue;
            }

            $results[$username] = [
                'username' => $username,
                'crm_found' => true,
                'online' => false,
                'mikrotik_directory_found' => false,

                'source' => 'GreenNet CRM',
                'type' => $customer['access_type'] ?? '-',
                'mikrotik_source' => '-',

                'display_name' => $customer['display_name'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'payment_status' => $customer['payment_status'] ?? 'unknown',
                'payment_label' => $this->paymentLabel($customer['payment_status'] ?? 'unknown'),
                'notes' => $customer['notes'] ?? '',

                'ip_address' => '-',
                'mac_or_caller' => '-',
                'uptime' => '-',
                'service' => '-',

                'profile' => '-',
                'disabled' => '-',
                'comment' => '-',
            ];
        }
    }

    private function mergeActiveMikroTikResults(array &$results, string $query): void
    {
        $activeSummary = (new ActiveUsersCrmService())->getMergedActiveUsers();
        $activeUsers = $activeSummary['all_users'] ?? [];

        foreach ($activeUsers as $user) {
            if (!$this->matchesFields($user, $query, [
                'username',
                'display_name',
                'phone',
                'payment_label',
                'notes',
                'ip_address',
                'mac_or_caller',
                'service',
                'type',
            ])) {
                continue;
            }

            $username = (string) ($user['username'] ?? '');

            if ($username === '') {
                continue;
            }

            if (isset($results[$username])) {
                $results[$username]['online'] = true;
                $results[$username]['source'] = $this->appendSource($results[$username]['source'], 'MikroTik Active');
                $results[$username]['type'] = $user['type'] ?? $results[$username]['type'];
                $results[$username]['ip_address'] = $user['ip_address'] ?? '-';
                $results[$username]['mac_or_caller'] = $user['mac_or_caller'] ?? '-';
                $results[$username]['uptime'] = $user['uptime'] ?? '-';
                $results[$username]['service'] = $user['service'] ?? '-';
                continue;
            }

            $results[$username] = [
                'username' => $username,
                'crm_found' => (bool) ($user['crm_found'] ?? false),
                'online' => true,
                'mikrotik_directory_found' => false,

                'source' => 'MikroTik Active',
                'type' => $user['type'] ?? '-',
                'mikrotik_source' => 'Active',

                'display_name' => $user['display_name'] ?? '',
                'phone' => $user['phone'] ?? '',
                'payment_status' => $user['payment_status'] ?? 'not_registered',
                'payment_label' => $user['payment_label'] ?? $this->paymentLabel('not_registered'),
                'notes' => $user['notes'] ?? '',

                'ip_address' => $user['ip_address'] ?? '-',
                'mac_or_caller' => $user['mac_or_caller'] ?? '-',
                'uptime' => $user['uptime'] ?? '-',
                'service' => $user['service'] ?? '-',

                'profile' => '-',
                'disabled' => '-',
                'comment' => '-',
            ];
        }
    }

    private function mergeMikroTikDirectoryResults(array &$results, string $query): array
    {
        $mikrotik = new MikroTikService();

        $hotspotCount = 0;
        $pppCount = 0;
        $userManagerCount = 0;

        $hotspotUsers = $mikrotik->readHotspotUsers();

        if (($hotspotUsers['ok'] ?? false) === true) {
            foreach ($hotspotUsers['rows'] as $row) {
                if (!$this->matchesRouterRow($row, $query)) {
                    continue;
                }

                $username = $this->firstValue($row, ['name', 'user', 'username']);

                if ($username === '') {
                    continue;
                }

                $this->mergeDirectoryRow(
                    $results,
                    $username,
                    'Hotspot',
                    'Hotspot User',
                    $row,
                    $row['profile'] ?? '-',
                    $row['disabled'] ?? '-',
                    $row['comment'] ?? '-'
                );

                $hotspotCount++;
            }
        }

        $pppSecrets = $mikrotik->readPppSecrets();

        if (($pppSecrets['ok'] ?? false) === true) {
            foreach ($pppSecrets['rows'] as $row) {
                if (!$this->matchesRouterRow($row, $query)) {
                    continue;
                }

                $username = $this->firstValue($row, ['name', 'user', 'username']);

                if ($username === '') {
                    continue;
                }

                $this->mergeDirectoryRow(
                    $results,
                    $username,
                    'PPP',
                    'PPP Secret',
                    $row,
                    $row['profile'] ?? '-',
                    $row['disabled'] ?? '-',
                    $row['comment'] ?? '-',
                    $row['service'] ?? '-'
                );

                $pppCount++;
            }
        }

        $userManagerUsers = $mikrotik->readUserManagerUsers();

        if (($userManagerUsers['ok'] ?? false) === true) {
            foreach ($userManagerUsers['rows'] as $row) {
                if (!$this->matchesRouterRow($row, $query)) {
                    continue;
                }

                $username = $this->firstValue($row, ['name', 'user', 'username', 'login']);

                if ($username === '') {
                    continue;
                }

                $this->mergeDirectoryRow(
                    $results,
                    $username,
                    'User Manager',
                    'User Manager User',
                    $row,
                    $row['profile'] ?? ($row['actual-profile'] ?? '-'),
                    $row['disabled'] ?? '-',
                    $row['comment'] ?? '-'
                );

                $userManagerCount++;
            }
        }

        return [
            'hotspot_users_count' => $hotspotCount,
            'ppp_secrets_count' => $pppCount,
            'user_manager_count' => $userManagerCount,
        ];
    }

    private function mergeDirectoryRow(
        array &$results,
        string $username,
        string $type,
        string $mikrotikSource,
        array $row,
        string $profile = '-',
        string $disabled = '-',
        string $comment = '-',
        string $service = '-'
    ): void {
        $customer = CustomerLocal::findByUsername($username);

        if (isset($results[$username])) {
            $results[$username]['mikrotik_directory_found'] = true;
            $results[$username]['source'] = $this->appendSource($results[$username]['source'], $mikrotikSource);
            $results[$username]['mikrotik_source'] = $this->appendSource($results[$username]['mikrotik_source'], $mikrotikSource);
            $results[$username]['type'] = $results[$username]['type'] !== '-' ? $results[$username]['type'] : $type;
            $results[$username]['profile'] = $profile;
            $results[$username]['disabled'] = $disabled;
            $results[$username]['comment'] = $comment;
            $results[$username]['service'] = $results[$username]['service'] !== '-' ? $results[$username]['service'] : $service;
            return;
        }

        $results[$username] = [
            'username' => $username,
            'crm_found' => $customer !== null,
            'online' => false,
            'mikrotik_directory_found' => true,

            'source' => $mikrotikSource,
            'type' => $type,
            'mikrotik_source' => $mikrotikSource,

            'display_name' => $customer['display_name'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'payment_status' => $customer['payment_status'] ?? 'not_registered',
            'payment_label' => $this->paymentLabel($customer['payment_status'] ?? 'not_registered'),
            'notes' => $customer['notes'] ?? '',

            'ip_address' => '-',
            'mac_or_caller' => $this->firstValue($row, ['mac-address', 'caller-id', 'last-caller-id']),
            'uptime' => '-',
            'service' => $service,

            'profile' => $profile,
            'disabled' => $disabled,
            'comment' => $comment,
        ];
    }

    private function matchesRouterRow(array $row, string $query): bool
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return false;
        }

        foreach ($row as $key => $value) {
            $text = mb_strtolower((string) $key . ' ' . (string) $value);

            if (str_contains($text, $query)) {
                return true;
            }
        }

        return false;
    }

    private function matchesFields(array $row, string $query, array $fields): bool
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return false;
        }

        foreach ($fields as $field) {
            if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $query)) {
                return true;
            }
        }

        return false;
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

    private function appendSource(string $current, string $new): string
    {
        if ($current === '' || $current === '-') {
            return $new;
        }

        if (str_contains($current, $new)) {
            return $current;
        }

        return $current . ' + ' . $new;
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