<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Models\CustomerLocal;
use GreenNet\Services\RouterOS\MikroTikService;
use Throwable;

class AdminApiRecordController
{
    public function show(): string
    {
        Database::migrate();
        $this->requireLogin();

        $datasets = $this->datasets();

        $datasetKey = trim((string) ($_GET['dataset'] ?? ''));
        $id = trim((string) ($_GET['id'] ?? ''));
        $username = trim((string) ($_GET['username'] ?? ''));

        if (!isset($datasets[$datasetKey])) {
            return View::render('admin/api_record', [
                'title' => 'API Record Details',
                'error' => 'Dataset غير صالح.',
                'dataset_key' => $datasetKey,
                'dataset' => [],
                'record' => [],
                'username' => $username,
                'crm_customer' => [],
                'usage' => [],
                'reset_preview' => [],
            ]);
        }

        $dataset = $datasets[$datasetKey];

        try {
            $service = new MikroTikService();
            $method = (string) ($dataset['method'] ?? '');

            if ($method === '' || !method_exists($service, $method)) {
                throw new \RuntimeException('Method not found: ' . $method);
            }

            $raw = $service->{$method}();
            $rows = $this->normalizeRows($raw);
            $rows = $this->sanitizeRows($rows);

            $record = $this->findRecord($rows, $id, $username);

            if ($record === null) {
                throw new \RuntimeException('لم يتم العثور على السجل المطلوب.');
            }

            $recordUsername = $this->extractUsername($record);
            $crmCustomer = [];

            if ($recordUsername !== '') {
                $foundCustomer = CustomerLocal::findByUsername($recordUsername);

                if (is_array($foundCustomer)) {
                    $crmCustomer = $foundCustomer;
                }
            }

            $usage = $this->usageCounters($record);
            $resetPreview = $this->resetPreview($datasetKey, $dataset, $record);

            AppLog::info('تم فتح تفاصيل API Record', [
                'dataset' => $datasetKey,
                'id' => $id,
                'username' => $recordUsername,
            ]);

            return View::render('admin/api_record', [
                'title' => 'API Record Details',
                'error' => '',
                'dataset_key' => $datasetKey,
                'dataset' => $dataset,
                'record' => $record,
                'username' => $recordUsername,
                'crm_customer' => $crmCustomer,
                'usage' => $usage,
                'reset_preview' => $resetPreview,
            ]);
        } catch (Throwable $e) {
            AppLog::error('فشل فتح تفاصيل API Record', [
                'dataset' => $datasetKey,
                'id' => $id,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return View::render('admin/api_record', [
                'title' => 'API Record Details',
                'error' => $e->getMessage(),
                'dataset_key' => $datasetKey,
                'dataset' => $dataset,
                'record' => [],
                'username' => $username,
                'crm_customer' => [],
                'usage' => [],
                'reset_preview' => [],
            ]);
        }
    }

    private function datasets(): array
    {
        return [
            'hotspot_active' => [
                'title' => 'Hotspot Active',
                'method' => 'hotspotActiveUsers',
                'source' => 'hotspot_active',
                'reset_supported' => false,
                'reset_note' => 'Hotspot Active يمثل الجلسة الحالية. تصفير العدادات يحتاج منطق منفصل لاحقاً.',
            ],

            'hotspot_users' => [
                'title' => 'Hotspot Users',
                'method' => 'readHotspotUsers',
                'source' => 'hotspot_user',
                'reset_supported' => true,
                'reset_command' => '/ip/hotspot/user/reset-counters',
                'reset_note' => 'هذا هو النوع الأنسب لتصفير counters الخاصة بمستخدم Hotspot.',
            ],

            'hotspot_profiles' => [
                'title' => 'Hotspot Profiles',
                'method' => 'readHotspotProfiles',
                'source' => 'hotspot_profile',
                'reset_supported' => false,
                'reset_note' => 'Profiles لا تحتوي عدادات مستخدمين.',
            ],

            'ppp_active' => [
                'title' => 'PPP Active',
                'method' => 'pppActiveUsers',
                'source' => 'ppp_active',
                'reset_supported' => false,
                'reset_note' => 'PPP Active يعرض عدادات الجلسة الحالية. التصفير الحقيقي غالباً يحتاج فصل الجلسة أو منطق مختلف.',
            ],

            'ppp_secrets' => [
                'title' => 'PPP Secrets',
                'method' => 'readPppSecrets',
                'source' => 'ppp_secret',
                'reset_supported' => false,
                'reset_note' => 'PPP Secrets عادة لا تحتوي reset counters بنفس طريقة Hotspot Users.',
            ],

            'ppp_profiles' => [
                'title' => 'PPP Profiles',
                'method' => 'readPppProfiles',
                'source' => 'ppp_profile',
                'reset_supported' => false,
                'reset_note' => 'Profiles لا تحتوي عدادات مستخدمين.',
            ],

            'user_manager_users' => [
                'title' => 'User Manager Users',
                'method' => 'readUserManagerUsers',
                'source' => 'user_manager_user',
                'reset_supported' => false,
                'reset_note' => 'User Manager يحتاج أمر منفصل بعد دراسة sessions/counters الخاصة به.',
            ],

            'user_manager_profiles' => [
                'title' => 'User Manager Profiles',
                'method' => 'readUserManagerProfiles',
                'source' => 'user_manager_profile',
                'reset_supported' => false,
                'reset_note' => 'Profiles لا تحتوي عدادات مستخدمين.',
            ],
        ];
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

    private function findRecord(array $rows, string $id, string $username): ?array
    {
        foreach ($rows as $row) {
            $rowId = (string) ($row['.id'] ?? $row['id'] ?? '');
            $rowUsername = $this->extractUsername($row);

            if ($id !== '' && $rowId === $id) {
                return $row;
            }

            if ($username !== '' && $rowUsername === $username) {
                return $row;
            }
        }

        return null;
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

    private function usageCounters(array $record): array
    {
        $bytesIn = $this->intValue($record['bytes-in'] ?? $record['bytes_in'] ?? 0);
        $bytesOut = $this->intValue($record['bytes-out'] ?? $record['bytes_out'] ?? 0);

        $packetsIn = $this->intValue($record['packets-in'] ?? $record['packets_in'] ?? 0);
        $packetsOut = $this->intValue($record['packets-out'] ?? $record['packets_out'] ?? 0);

        $totalBytes = $bytesIn + $bytesOut;
        $totalPackets = $packetsIn + $packetsOut;

        return [
            'bytes_in' => $bytesIn,
            'bytes_out' => $bytesOut,
            'total_bytes' => $totalBytes,

            'bytes_in_human' => $this->formatBytes($bytesIn),
            'bytes_out_human' => $this->formatBytes($bytesOut),
            'total_bytes_human' => $this->formatBytes($totalBytes),

            'packets_in' => $packetsIn,
            'packets_out' => $packetsOut,
            'total_packets' => $totalPackets,

            'uptime' => (string) ($record['uptime'] ?? '-'),
            'limit_uptime' => (string) ($record['limit-uptime'] ?? $record['limit_uptime'] ?? '-'),

            'has_usage' => $bytesIn > 0 || $bytesOut > 0 || $packetsIn > 0 || $packetsOut > 0,
        ];
    }

    private function resetPreview(string $datasetKey, array $dataset, array $record): array
    {
        $supported = !empty($dataset['reset_supported']);
        $id = (string) ($record['.id'] ?? $record['id'] ?? '');
        $username = $this->extractUsername($record);

        $command = (string) ($dataset['reset_command'] ?? '');

        $parameters = [];

        if ($supported) {
            if ($id !== '') {
                $parameters['=.id'] = $id;
            } elseif ($username !== '') {
                $parameters['=numbers'] = $username;
            }
        }

        return [
            'supported' => $supported,
            'dataset' => $datasetKey,
            'source' => (string) ($dataset['source'] ?? $datasetKey),
            'command' => $command,
            'parameters' => $parameters,
            'username' => $username,
            'id' => $id,
            'dry_run' => true,
            'message' => (string) ($dataset['reset_note'] ?? ''),
        ];
    }

    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $value = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($value === '') {
            return 0;
        }

        return (int) $value;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
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

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}