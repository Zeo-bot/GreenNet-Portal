<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Models\CustomerLocal;
use GreenNet\Services\RouterOS\MikroTikService;
use Throwable;

class AdminApiBrowserController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $datasets = $this->datasets();

        $selectedKey = trim((string) ($_GET['dataset'] ?? 'hotspot_users'));

        if (!isset($datasets[$selectedKey])) {
            $selectedKey = 'hotspot_users';
        }

        $query = trim((string) ($_GET['q'] ?? ''));

        $service = new MikroTikService();

        $results = [];

        foreach ($datasets as $key => $dataset) {
            $results[$key] = $this->loadDataset($key, $dataset, $service, $query, $key === $selectedKey);
        }

        $selected = $results[$selectedKey];

        AppLog::info('تم فتح API Data Browser', [
            'dataset' => $selectedKey,
            'query' => $query,
            'rows' => $selected['filtered_count'] ?? 0,
        ]);

        return View::render('admin/api_browser', [
            'title' => 'API Data Browser',
            'datasets' => $datasets,
            'results' => $results,
            'selected_key' => $selectedKey,
            'selected' => $selected,
            'query' => $query,
        ]);
    }

    private function datasets(): array
    {
        return [
            'hotspot_active' => [
                'title' => 'Hotspot Active',
                'description' => 'المستخدمون المتصلون حالياً عبر Hotspot',
                'method' => 'hotspotActiveUsers',
                'type' => 'user_rows',
                'source' => 'hotspot_active',
            ],

            'hotspot_users' => [
                'title' => 'Hotspot Users',
                'description' => 'الحسابات المخزنة داخل IP Hotspot Users',
                'method' => 'readHotspotUsers',
                'type' => 'user_rows',
                'source' => 'hotspot_user',
            ],

            'hotspot_profiles' => [
                'title' => 'Hotspot Profiles',
                'description' => 'Hotspot User Profiles',
                'method' => 'readHotspotProfiles',
                'type' => 'profile_rows',
                'source' => 'hotspot_profile',
            ],

            'ppp_active' => [
                'title' => 'PPP Active',
                'description' => 'مستخدمو PPP/PPPoE المتصلون حالياً',
                'method' => 'pppActiveUsers',
                'type' => 'user_rows',
                'source' => 'ppp_active',
            ],

            'ppp_secrets' => [
                'title' => 'PPP Secrets',
                'description' => 'الحسابات المخزنة داخل PPP Secrets',
                'method' => 'readPppSecrets',
                'type' => 'user_rows',
                'source' => 'ppp_secret',
            ],

            'ppp_profiles' => [
                'title' => 'PPP Profiles',
                'description' => 'PPP Profiles',
                'method' => 'readPppProfiles',
                'type' => 'profile_rows',
                'source' => 'ppp_profile',
            ],

            'user_manager_users' => [
                'title' => 'User Manager Users',
                'description' => 'مستخدمو User Manager',
                'method' => 'readUserManagerUsers',
                'type' => 'user_rows',
                'source' => 'user_manager_user',
            ],

            'user_manager_profiles' => [
                'title' => 'User Manager Profiles',
                'description' => 'Profiles داخل User Manager',
                'method' => 'readUserManagerProfiles',
                'type' => 'profile_rows',
                'source' => 'user_manager_profile',
            ],

            'discovery' => [
                'title' => 'Discovery',
                'description' => 'ملخص أوامر discovery المقروءة من RouterOS',
                'method' => 'dataDiscovery',
                'type' => 'discovery_rows',
                'source' => 'discovery',
            ],
        ];
    }

    private function loadDataset(
        string $key,
        array $dataset,
        MikroTikService $service,
        string $query,
        bool $includeRows
    ): array {
        $startedAt = microtime(true);

        try {
            $method = (string) ($dataset['method'] ?? '');

            if ($method === '' || !method_exists($service, $method)) {
                throw new \RuntimeException('Method not found: ' . $method);
            }

            $raw = $service->{$method}();

            if (($dataset['type'] ?? '') === 'discovery_rows') {
                $rows = $this->normalizeDiscoveryRows($raw);
            } else {
                $rows = $this->normalizeRows($raw);
            }

            $rows = $this->sanitizeRows($rows);

            if (($dataset['type'] ?? '') === 'user_rows') {
                $rows = $this->enrichUserRows($rows, (string) ($dataset['source'] ?? $key));
            }

            $totalCount = count($rows);
            $filteredRows = $this->filterRows($rows, $query);

            return [
                'key' => $key,
                'title' => (string) ($dataset['title'] ?? $key),
                'description' => (string) ($dataset['description'] ?? ''),
                'status' => 'ok',
                'status_label' => 'ناجح',
                'message' => 'تمت القراءة بنجاح',
                'duration_ms' => $this->elapsedMs($startedAt),
                'total_count' => $totalCount,
                'filtered_count' => count($filteredRows),
                'rows' => $includeRows ? $filteredRows : [],
                'source' => (string) ($dataset['source'] ?? $key),
                'type' => (string) ($dataset['type'] ?? 'rows'),
            ];
        } catch (Throwable $e) {
            return [
                'key' => $key,
                'title' => (string) ($dataset['title'] ?? $key),
                'description' => (string) ($dataset['description'] ?? ''),
                'status' => 'failed',
                'status_label' => 'فشل',
                'message' => $e->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
                'total_count' => 0,
                'filtered_count' => 0,
                'rows' => [],
                'source' => (string) ($dataset['source'] ?? $key),
                'type' => (string) ($dataset['type'] ?? 'rows'),
            ];
        }
    }

    private function normalizeRows(array $raw): array
    {
        if (isset($raw['rows']) && is_array($raw['rows'])) {
            return $this->onlyArrayRows($raw['rows']);
        }

        if (array_is_list($raw)) {
            return $this->onlyArrayRows($raw);
        }

        if (count($raw) === 0) {
            return [];
        }

        if (isset($raw['ok']) || isset($raw['error'])) {
            return [];
        }

        return [$raw];
    }

    private function normalizeDiscoveryRows(array $raw): array
    {
        $items = [];

        if (isset($raw['rows']) && is_array($raw['rows'])) {
            $raw = $raw['rows'];
        }

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rows = [];

            if (isset($item['rows']) && is_array($item['rows'])) {
                $rows = $item['rows'];
            }

            $items[] = [
                'command' => (string) ($item['command'] ?? '-'),
                'ok' => !empty($item['ok']) ? 'yes' : 'no',
                'rows_count' => count($rows),
                'error' => (string) ($item['error'] ?? ''),
                'first_row_keys' => count($rows) > 0 && is_array($rows[0])
                    ? implode(', ', array_keys($rows[0]))
                    : '',
            ];
        }

        return $items;
    }

    private function onlyArrayRows(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $clean[] = $row;
            }
        }

        return $clean;
    }

    private function sanitizeRows(array $rows): array
    {
        $sanitized = [];

        foreach ($rows as $row) {
            $cleanRow = [];

            foreach ($row as $key => $value) {
                $key = (string) $key;

                if ($this->isSensitiveKey($key)) {
                    $cleanRow[$key] = '****';
                    continue;
                }

                if (is_array($value)) {
                    $cleanRow[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    continue;
                }

                if (is_bool($value)) {
                    $cleanRow[$key] = $value ? 'true' : 'false';
                    continue;
                }

                if ($value === null) {
                    $cleanRow[$key] = '';
                    continue;
                }

                $cleanRow[$key] = (string) $value;
            }

            $sanitized[] = $cleanRow;
        }

        return $sanitized;
    }

    private function enrichUserRows(array $rows, string $source): array
    {
        $enriched = [];

        foreach ($rows as $row) {
            $username = $this->extractUsername($row);

            $row['_greennet_source'] = $source;
            $row['_greennet_username'] = $username;
            $row['_crm_found'] = 'no';
            $row['_crm_name'] = '';

            if ($username !== '') {
                try {
                    $customer = CustomerLocal::findByUsername($username);

                    if (is_array($customer) && count($customer) > 0) {
                        $row['_crm_found'] = 'yes';

                        $row['_crm_name'] = (string) (
                            $customer['display_name']
                            ?? $customer['full_name']
                            ?? $customer['name']
                            ?? $username
                        );
                    }
                } catch (Throwable) {
                    $row['_crm_found'] = 'unknown';
                }
            }

            $enriched[] = $row;
        }

        return $enriched;
    }

    private function extractUsername(array $row): string
    {
        foreach (['name', 'user', 'username', 'login', 'customer', 'caller-id'] as $key) {
            if (!empty($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function filterRows(array $rows, string $query): array
    {
        if ($query === '') {
            return $rows;
        }

        $query = mb_strtolower($query);
        $filtered = [];

        foreach ($rows as $row) {
            $haystack = mb_strtolower(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

            if (str_contains($haystack, $query)) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = mb_strtolower($key);

        $sensitiveParts = [
            'password',
            'secret',
            'token',
            'key',
            'otp',
        ];

        foreach ($sensitiveParts as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}