<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\RouterOS\MikroTikService;
use Throwable;

class AdminApiDiagnosticsController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $diagnostics = $this->runDiagnostics();

        return View::render('admin/api_diagnostics', [
            'title' => 'API Diagnostics',
            'diagnostics' => $diagnostics,
        ]);
    }

    private function runDiagnostics(): array
    {
        $startedAt = microtime(true);

        $config = [
            'host' => Config::mikrotikHost(),
            'port' => Config::mikrotikApiPort(),
            'username' => Config::mikrotikUsername(),
            'password_set' => Config::mikrotikPassword() !== '',
            'access_mode' => Config::accessMode(),
            'auth_backend' => Config::authBackend(),
        ];

        $checks = [];

        $service = null;

        try {
            $service = new MikroTikService();

            $checks[] = $this->check(
                'RouterOS API Connection',
                'الاتصال الأساسي مع MikroTik API',
                function () use ($service): array {
                    return $service->status();
                }
            );
        } catch (Throwable $e) {
            $checks[] = [
                'name' => 'RouterOS API Connection',
                'description' => 'الاتصال الأساسي مع MikroTik API',
                'status' => 'failed',
                'status_label' => 'فشل',
                'duration_ms' => 0,
                'message' => $e->getMessage(),
                'rows_count' => 0,
                'sample' => [],
            ];

            AppLog::error('فشل إنشاء MikroTikService في API Diagnostics', [
                'error' => $e->getMessage(),
            ]);

            return [
                'config' => $config,
                'checks' => $checks,
                'summary' => $this->summary($checks),
                'total_duration_ms' => $this->elapsedMs($startedAt),
            ];
        }

        $checks[] = $this->check(
            'Hotspot Active',
            'قراءة المستخدمين المتصلين حالياً عبر Hotspot',
            function () use ($service): array {
                return $service->hotspotActiveUsers();
            }
        );

        $checks[] = $this->check(
            'PPP Active',
            'قراءة مستخدمي PPP/PPPoE المتصلين حالياً',
            function () use ($service): array {
                return $service->pppActiveUsers();
            }
        );

        $checks[] = $this->check(
            'Hotspot Users',
            'قراءة المستخدمين المخزنين في Hotspot Users',
            function () use ($service): array {
                return $service->readHotspotUsers();
            }
        );

        $checks[] = $this->check(
            'PPP Secrets',
            'قراءة PPP Secrets',
            function () use ($service): array {
                return $service->readPppSecrets();
            }
        );

        $checks[] = $this->check(
            'User Manager Users',
            'قراءة مستخدمي User Manager',
            function () use ($service): array {
                return $service->readUserManagerUsers();
            }
        );

        $checks[] = $this->check(
            'Hotspot Profiles',
            'قراءة Hotspot User Profiles',
            function () use ($service): array {
                return $service->readHotspotProfiles();
            }
        );

        $checks[] = $this->check(
            'PPP Profiles',
            'قراءة PPP Profiles',
            function () use ($service): array {
                return $service->readPppProfiles();
            }
        );

        $checks[] = $this->check(
            'User Manager Profiles',
            'قراءة User Manager Profiles',
            function () use ($service): array {
                return $service->readUserManagerProfiles();
            }
        );

        $checks[] = $this->check(
            'Data Discovery',
            'اختبار أوامر discovery المعروفة في RouterOS 7',
            function () use ($service): array {
                return $service->dataDiscovery();
            },
            true
        );

        $summary = $this->summary($checks);

        AppLog::info('تم تشغيل API Diagnostics', [
            'summary' => $summary,
            'host' => $config['host'],
            'port' => $config['port'],
        ]);

        return [
            'config' => $config,
            'checks' => $checks,
            'summary' => $summary,
            'total_duration_ms' => $this->elapsedMs($startedAt),
        ];
    }

    private function check(string $name, string $description, callable $callback, bool $isDiscovery = false): array
    {
        $startedAt = microtime(true);

        try {
            $result = $callback();

            $rowsCount = $this->rowsCount($result, $isDiscovery);
            $sample = $this->sample($result, $isDiscovery);

            return [
                'name' => $name,
                'description' => $description,
                'status' => 'ok',
                'status_label' => 'ناجح',
                'duration_ms' => $this->elapsedMs($startedAt),
                'message' => 'تمت القراءة بنجاح',
                'rows_count' => $rowsCount,
                'sample' => $sample,
            ];
        } catch (Throwable $e) {
            return [
                'name' => $name,
                'description' => $description,
                'status' => 'failed',
                'status_label' => 'فشل',
                'duration_ms' => $this->elapsedMs($startedAt),
                'message' => $e->getMessage(),
                'rows_count' => 0,
                'sample' => [],
            ];
        }
    }

    private function rowsCount(array $result, bool $isDiscovery): int
    {
        if (!$isDiscovery) {
            return count($result);
        }

        $count = 0;

        foreach ($result as $item) {
            if (is_array($item) && isset($item['rows']) && is_array($item['rows'])) {
                $count += count($item['rows']);
            }
        }

        return $count;
    }

    private function sample(array $result, bool $isDiscovery): array
    {
        if (!$isDiscovery) {
            return array_slice($result, 0, 3);
        }

        $sample = [];

        foreach ($result as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sample[] = [
                'command' => $item['command'] ?? '-',
                'ok' => $item['ok'] ?? false,
                'rows_count' => isset($item['rows']) && is_array($item['rows']) ? count($item['rows']) : 0,
                'error' => $item['error'] ?? '',
            ];
        }

        return array_slice($sample, 0, 12);
    }

    private function summary(array $checks): array
    {
        $summary = [
            'total' => count($checks),
            'ok' => 0,
            'failed' => 0,
            'warning' => 0,
            'ready_for_read' => false,
        ];

        foreach ($checks as $check) {
            if (($check['status'] ?? '') === 'ok') {
                $summary['ok']++;
            } else {
                $summary['failed']++;
            }
        }

        $summary['warning'] = $summary['failed'];

        $summary['ready_for_read'] = $summary['ok'] >= 1 && $summary['failed'] < $summary['total'];

        return $summary;
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