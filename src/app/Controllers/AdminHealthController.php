<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Models\Setting;
use GreenNet\Services\RouterSettingsService;
use Throwable;

class AdminHealthController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $refresh = (string) ($_GET['refresh'] ?? '') === '1';

        $testResult = null;
        $detectResult = null;

        if ($refresh) {
            $testResult = RouterSettingsService::testConnection();

            if (!empty($testResult['ok'])) {
                $detectResult = RouterSettingsService::detectServices();
            }

            AppLog::info('تم تحديث Health Dashboard', [
                'test_ok' => $testResult['ok'] ?? false,
                'detect_ok' => $detectResult['ok'] ?? null,
            ]);
        }

        $settings = RouterSettingsService::current();

        $counts = [
            'customers' => $this->tableCount('customers_local'),
            'packages' => $this->tableCount('service_packages'),
            'payments' => $this->tableCount('payments'),
            'announcements' => $this->tableCount('announcements'),
            'logs' => $this->tableCount('app_logs'),
        ];

        $backup = $this->latestBackupInfo();
        $readiness = $this->latestReadinessInfo();

        $safety = [
            'write_enabled' => Setting::get('mikrotik_write_enabled', 'false'),
            'safe_mode' => Setting::get('greennet_safe_mode', 'true'),
            'csrf_enabled' => Setting::get('csrf_enabled', 'false'),
        ];

        $checklist = $this->checklist($settings, $counts, $backup, $readiness, $safety);

        return View::render('admin/health', [
            'title' => 'Health Dashboard',
            'settings' => $settings,
            'counts' => $counts,
            'backup' => $backup,
            'readiness' => $readiness,
            'safety' => $safety,
            'checklist' => $checklist,
            'test_result' => $testResult,
            'detect_result' => $detectResult,
            'refresh' => $refresh,
        ]);
    }

    private function tableCount(string $table): int
    {
        $allowed = [
            'customers_local',
            'service_packages',
            'payments',
            'announcements',
            'app_logs',
        ];

        if (!in_array($table, $allowed, true)) {
            return 0;
        }

        try {
            $stmt = Database::connection()->query("SELECT COUNT(*) FROM {$table}");
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function latestBackupInfo(): array
    {
        $dir = BASE_PATH . '/storage/backups';

        if (!is_dir($dir)) {
            return [
                'exists' => false,
                'status' => 'missing',
                'status_label' => 'لا يوجد Backup',
                'filename' => '',
                'path' => '',
                'size' => 0,
                'size_human' => '0 B',
                'modified_at' => '',
                'age_human' => '-',
                'message' => 'لم يتم العثور على مجلد النسخ الاحتياطي.',
            ];
        }

        $files = glob($dir . '/*') ?: [];
        $files = array_values(array_filter($files, 'is_file'));

        if (count($files) === 0) {
            return [
                'exists' => false,
                'status' => 'missing',
                'status_label' => 'لا يوجد Backup',
                'filename' => '',
                'path' => '',
                'size' => 0,
                'size_human' => '0 B',
                'modified_at' => '',
                'age_human' => '-',
                'message' => 'لم يتم إنشاء أي Backup بعد.',
            ];
        }

        usort($files, function (string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        });

        $latest = $files[0];
        $modified = filemtime($latest) ?: time();
        $ageSeconds = time() - $modified;

        if ($ageSeconds <= 24 * 60 * 60) {
            $status = 'ok';
            $label = 'Backup حديث';
            $message = 'يوجد Backup حديث خلال آخر 24 ساعة.';
        } elseif ($ageSeconds <= 7 * 24 * 60 * 60) {
            $status = 'warning';
            $label = 'Backup قديم نسبياً';
            $message = 'يوجد Backup لكن يفضل إنشاء Backup جديد قبل أي أوامر Write.';
        } else {
            $status = 'danger';
            $label = 'Backup قديم';
            $message = 'آخر Backup قديم. لا ننصح بأي أوامر Write قبل إنشاء Backup جديد.';
        }

        return [
            'exists' => true,
            'status' => $status,
            'status_label' => $label,
            'filename' => basename($latest),
            'path' => $latest,
            'size' => filesize($latest) ?: 0,
            'size_human' => $this->formatBytes((int) (filesize($latest) ?: 0)),
            'modified_at' => date('Y-m-d H:i:s', $modified),
            'age_human' => $this->formatAge($ageSeconds),
            'message' => $message,
        ];
    }

    private function latestReadinessInfo(): array
    {
        try {
            $stmt = Database::connection()->query("
                SELECT *
                FROM app_logs
                WHERE message LIKE '%Readiness%'
                ORDER BY id DESC
                LIMIT 1
            ");

            $row = $stmt->fetch();

            if (!$row) {
                return [
                    'exists' => false,
                    'created_at' => '',
                    'message' => 'لم يتم تشغيل Readiness Check بعد.',
                ];
            }

            return [
                'exists' => true,
                'created_at' => (string) ($row['created_at'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
            ];
        } catch (Throwable) {
            return [
                'exists' => false,
                'created_at' => '',
                'message' => 'تعذر قراءة آخر Readiness Check.',
            ];
        }
    }

    private function checklist(
        array $settings,
        array $counts,
        array $backup,
        array $readiness,
        array $safety
    ): array {
        $items = [];

        $host = trim((string) ($settings['host'] ?? ''));
        $username = trim((string) ($settings['username'] ?? ''));
        $lastSeen = trim((string) ($settings['last_seen'] ?? ''));

        $items[] = [
            'status' => $host !== '' && $username !== '' ? 'done' : 'missing',
            'title' => 'إعداد الراوتر',
            'description' => $host !== '' && $username !== ''
                ? 'تم ضبط Host و Username للراوتر.'
                : 'يجب ضبط بيانات الراوتر من Router Setup.',
            'action_url' => '/admin/router-setup',
            'action_label' => 'Router Setup',
        ];

        $items[] = [
            'status' => $lastSeen !== '' ? 'done' : 'warning',
            'title' => 'اختبار API',
            'description' => $lastSeen !== ''
                ? 'تم الاتصال بالراوتر آخر مرة: ' . $lastSeen
                : 'لم يتم تسجيل اتصال ناجح بعد. شغّل Test Connection.',
            'action_url' => '/admin/router-setup',
            'action_label' => 'اختبار الاتصال',
        ];

        $detectedTotal =
            (int) ($settings['detected_hotspot_users'] ?? 0)
            + (int) ($settings['detected_ppp_secrets'] ?? 0)
            + (int) ($settings['detected_user_manager_users'] ?? 0)
            + (int) ($settings['detected_profiles'] ?? 0);

        $items[] = [
            'status' => $detectedTotal > 0 ? 'done' : 'warning',
            'title' => 'Detect Services',
            'description' => $detectedTotal > 0
                ? 'تم كشف مستخدمين أو Profiles من MikroTik.'
                : 'لم يتم كشف خدمات بعد. شغّل Detect Services.',
            'action_url' => '/admin/router-setup',
            'action_label' => 'Detect',
        ];

        $items[] = [
            'status' => (int) ($counts['packages'] ?? 0) > 0 ? 'done' : 'warning',
            'title' => 'الباقات',
            'description' => (int) ($counts['packages'] ?? 0) > 0
                ? 'يوجد باقات داخل GreenNet.'
                : 'لا توجد باقات بعد. استورد Profiles كباقات لاحقاً.',
            'action_url' => '/admin/packages',
            'action_label' => 'الباقات',
        ];

        $items[] = [
            'status' => (int) ($counts['customers'] ?? 0) > 0 ? 'done' : 'warning',
            'title' => 'الزبائن',
            'description' => (int) ($counts['customers'] ?? 0) > 0
                ? 'يوجد زبائن داخل CRM.'
                : 'لا يوجد زبائن داخل CRM بعد. Auto Match سيساعدنا بالاستيراد.',
            'action_url' => '/admin/customers/table',
            'action_label' => 'الزبائن',
        ];

        $items[] = [
            'status' => !empty($readiness['exists']) ? 'done' : 'warning',
            'title' => 'Readiness Check',
            'description' => !empty($readiness['exists'])
                ? 'تم تشغيل Readiness Check سابقاً.'
                : 'لم يتم تشغيل Readiness Check بعد.',
            'action_url' => '/admin/readiness',
            'action_label' => 'Readiness',
        ];

        $items[] = [
            'status' => !empty($backup['exists']) ? ($backup['status'] === 'ok' ? 'done' : 'warning') : 'missing',
            'title' => 'Backup',
            'description' => (string) ($backup['message'] ?? 'لا توجد معلومات Backup.'),
            'action_url' => '/admin/backup',
            'action_label' => 'Backup',
        ];

        $safeModeOn = strtolower((string) ($safety['safe_mode'] ?? 'true')) === 'true';
        $writeEnabled = strtolower((string) ($safety['write_enabled'] ?? 'false')) === 'true';

        $items[] = [
            'status' => !$writeEnabled && $safeModeOn ? 'done' : 'warning',
            'title' => 'Safe Mode / Write Safety',
            'description' => !$writeEnabled && $safeModeOn
                ? 'النظام ما زال في الوضع الآمن. لا توجد أوامر Write فعلية.'
                : 'راجع إعدادات Write Safety قبل أي تنفيذ فعلي.',
            'action_url' => '/admin/readiness',
            'action_label' => 'مراجعة',
        ];

        $items[] = [
            'status' => 'ready',
            'title' => 'Subscriber App / PWA',
            'description' => 'سيتم تطوير واجهة المشترك كتطبيق Web/PWA يعمل خارجياً أو داخل MikroTik.',
            'action_url' => '/dashboard',
            'action_label' => 'معاينة',
        ];

        $items[] = [
            'status' => 'future',
            'title' => 'RouterOS YAML Installer',
            'description' => 'يتوفر RouterOS Apps YAML من إعداد الموجّه عندما يثبت فحص القدرات توافق RouterOS والمعمارية وContainer mode.',
            'action_url' => '/admin/router-onboarding',
            'action_label' => 'Router Onboarding',
        ];

        return $items;
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

    private function formatAge(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' ثانية';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60) . ' دقيقة';
        }

        if ($seconds < 86400) {
            return floor($seconds / 3600) . ' ساعة';
        }

        return floor($seconds / 86400) . ' يوم';
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}
