<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\RouterOS\MikroTikService;
use PDO;
use Throwable;

class AdminReadinessController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $report = $this->buildReport();

        AppLog::info('تم تشغيل Readiness Check', [
            'summary' => $report['summary'] ?? [],
        ]);

        return View::render('admin/readiness', [
            'title' => 'Readiness Check',
            'report' => $report,
        ]);
    }

    private function buildReport(): array
    {
        $customers = $this->customers();
        $packages = $this->packages();
        $latestPayments = $this->latestPaymentsByUsername();

        $routerData = $this->routerData();

        $routerUsers = $routerData['users'];
        $routerProfiles = $routerData['profiles'];

        $issues = [];

        $issues = array_merge($issues, $this->checkCustomersWithoutPackage($customers, $packages));
        $issues = array_merge($issues, $this->checkCustomersWithoutRenewal($customers, $latestPayments));
        $issues = array_merge($issues, $this->checkCustomersMissingOnRouter($customers, $routerUsers));
        $issues = array_merge($issues, $this->checkRouterUsersMissingInCrm($routerUsers, $customers));
        $issues = array_merge($issues, $this->checkDuplicateRouterUsers($routerUsers));
        $issues = array_merge($issues, $this->checkDisabledRouterUsers($routerUsers));
        $issues = array_merge($issues, $this->checkProfileMismatch($customers, $packages, $routerUsers));
        $issues = array_merge($issues, $this->checkPackagesWithoutSourceProfile($packages));
        $issues = array_merge($issues, $this->checkPackagesWithMissingRouterProfile($packages, $routerProfiles));
        $issues = array_merge($issues, $this->checkRouterProfilesNotImported($packages, $routerProfiles));

        $summary = $this->summary($customers, $packages, $routerUsers, $routerProfiles, $issues, $routerData);

        return [
            'summary' => $summary,
            'issues' => $issues,
            'router_errors' => $routerData['errors'],
            'customers' => $customers,
            'packages' => $packages,
            'router_users' => $routerUsers,
            'router_profiles' => $routerProfiles,
        ];
    }

    private function customers(): array
    {
        $stmt = Database::connection()->query("
            SELECT
                c.*,
                sp.name AS package_name,
                sp.source_type AS package_source_type,
                sp.source_profile AS package_source_profile,
                sp.rate_limit AS package_rate_limit
            FROM customers_local c
            LEFT JOIN service_packages sp ON sp.id = c.package_id
            ORDER BY c.username ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function packages(): array
    {
        $stmt = Database::connection()->query("
            SELECT *
            FROM service_packages
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function latestPaymentsByUsername(): array
    {
        $stmt = Database::connection()->query("
            SELECT p.*
            FROM payments p
            INNER JOIN (
                SELECT username, MAX(id) AS latest_id
                FROM payments
                GROUP BY username
            ) latest ON latest.latest_id = p.id
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $indexed = [];

        foreach ($rows as $row) {
            $username = trim((string) ($row['username'] ?? ''));

            if ($username !== '') {
                $indexed[$username] = $row;
            }
        }

        return $indexed;
    }

    private function routerData(): array
    {
        $users = [];
        $profiles = [];
        $errors = [];

        try {
            $service = new MikroTikService();

            $this->appendRouterUsers(
                $users,
                'hotspot_user',
                'Hotspot Users',
                $this->safeRows($service, 'readHotspotUsers', 'Hotspot Users', $errors)
            );

            $this->appendRouterUsers(
                $users,
                'ppp_secret',
                'PPP Secrets',
                $this->safeRows($service, 'readPppSecrets', 'PPP Secrets', $errors)
            );

            $this->appendRouterUsers(
                $users,
                'user_manager_user',
                'User Manager Users',
                $this->safeRows($service, 'readUserManagerUsers', 'User Manager Users', $errors)
            );

            $this->appendRouterProfiles(
                $profiles,
                'hotspot_profile',
                'Hotspot Profiles',
                $this->safeRows($service, 'readHotspotProfiles', 'Hotspot Profiles', $errors)
            );

            $this->appendRouterProfiles(
                $profiles,
                'ppp_profile',
                'PPP Profiles',
                $this->safeRows($service, 'readPppProfiles', 'PPP Profiles', $errors)
            );

            $this->appendRouterProfiles(
                $profiles,
                'user_manager_profile',
                'User Manager Profiles',
                $this->safeRows($service, 'readUserManagerProfiles', 'User Manager Profiles', $errors)
            );
        } catch (Throwable $e) {
            $errors[] = [
                'source' => 'routeros',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'users' => $users,
            'profiles' => $profiles,
            'errors' => $errors,
        ];
    }

    private function safeRows(MikroTikService $service, string $method, string $label, array &$errors): array
    {
        try {
            if (!method_exists($service, $method)) {
                throw new \RuntimeException('Method not found: ' . $method);
            }

            $raw = $service->{$method}();

            if (
                is_array($raw)
                && isset($raw['ok'])
                && empty($raw['ok'])
            ) {
                $errors[] = [
                    'source' => $label,
                    'message' => (string) ($raw['error'] ?? 'Unknown RouterOS API error'),
                ];

                return [];
            }

            return $this->normalizeRows($raw);
        } catch (Throwable $e) {
            $errors[] = [
                'source' => $label,
                'message' => $e->getMessage(),
            ];

            return [];
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

    private function appendRouterUsers(array &$users, string $sourceType, string $sourceLabel, array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $username = $this->extractUsername($row);

            if ($username === '') {
                continue;
            }

            $users[] = [
                'username' => $username,
                'source_type' => $sourceType,
                'source_label' => $sourceLabel,
                'id' => (string) ($row['.id'] ?? $row['id'] ?? ''),
                'profile' => $this->extractProfile($row),
                'disabled' => $this->isDisabled($row),
                'comment' => (string) ($row['comment'] ?? ''),
                'raw' => $row,
            ];
        }
    }

    private function appendRouterProfiles(array &$profiles, string $sourceType, string $sourceLabel, array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? $row['profile'] ?? ''));

            if ($name === '') {
                continue;
            }

            $profiles[] = [
                'name' => $name,
                'source_type' => $sourceType,
                'source_label' => $sourceLabel,
                'id' => (string) ($row['.id'] ?? $row['id'] ?? ''),
                'rate_limit' => (string) ($row['rate-limit'] ?? $row['rate_limit'] ?? ''),
                'comment' => (string) ($row['comment'] ?? ''),
                'raw' => $row,
            ];
        }
    }

    private function checkCustomersWithoutPackage(array $customers, array $packages): array
    {
        $issues = [];
        $packageIds = [];

        foreach ($packages as $package) {
            $packageIds[(int) ($package['id'] ?? 0)] = true;
        }

        foreach ($customers as $customer) {
            $username = (string) ($customer['username'] ?? '');
            $packageId = (int) ($customer['package_id'] ?? 0);

            if ($packageId <= 0 || !isset($packageIds[$packageId])) {
                $issues[] = $this->issue(
                    'error',
                    'customer_without_package',
                    'مشترك بلا باقة',
                    'المشترك موجود في CRM لكن لا توجد باقة GreenNet مرتبطة به.',
                    $username,
                    'CRM',
                    '/admin/customers/package?username=' . urlencode($username)
                );
            }
        }

        return $issues;
    }

    private function checkCustomersWithoutRenewal(array $customers, array $latestPayments): array
    {
        $issues = [];

        foreach ($customers as $customer) {
            $username = (string) ($customer['username'] ?? '');

            if ($username === '') {
                continue;
            }

            if (!isset($latestPayments[$username])) {
                $issues[] = $this->issue(
                    'warning',
                    'customer_without_renewal',
                    'مشترك بلا تجديد',
                    'المشترك لديه حساب في CRM لكن لا توجد دفعة أو تجديد مسجل.',
                    $username,
                    'CRM',
                    '/admin/customers/renew?username=' . urlencode($username)
                );

                continue;
            }

            $expiresAt = (string) ($latestPayments[$username]['expires_at'] ?? '');

            if ($expiresAt === '') {
                $issues[] = $this->issue(
                    'warning',
                    'customer_without_expiry',
                    'تجديد بلا تاريخ انتهاء',
                    'توجد دفعة للمشترك لكن لا يوجد تاريخ انتهاء واضح.',
                    $username,
                    'CRM',
                    '/admin/customers/renew?username=' . urlencode($username)
                );
            }
        }

        return $issues;
    }

    private function checkCustomersMissingOnRouter(array $customers, array $routerUsers): array
    {
        $issues = [];
        $routerIndex = $this->routerUsersByUsername($routerUsers);

        foreach ($customers as $customer) {
            $username = trim((string) ($customer['username'] ?? ''));

            if ($username === '') {
                continue;
            }

            if (!isset($routerIndex[mb_strtolower($username)])) {
                $issues[] = $this->issue(
                    'error',
                    'customer_missing_on_router',
                    'مشترك CRM غير موجود على MikroTik',
                    'هذا المستخدم موجود في GreenNet CRM لكن لم يتم العثور عليه ضمن Hotspot أو PPP أو User Manager.',
                    $username,
                    'CRM',
                    '/admin/search?q=' . urlencode($username)
                );
            }
        }

        return $issues;
    }

    private function checkRouterUsersMissingInCrm(array $routerUsers, array $customers): array
    {
        $issues = [];
        $customerIndex = $this->customersByUsername($customers);

        foreach ($routerUsers as $routerUser) {
            $username = (string) ($routerUser['username'] ?? '');

            if ($username === '') {
                continue;
            }

            if (!isset($customerIndex[mb_strtolower($username)])) {
                $issues[] = $this->issue(
                    'warning',
                    'router_user_missing_in_crm',
                    'مستخدم MikroTik غير موجود في CRM',
                    'هذا المستخدم موجود على MikroTik لكنه غير مستورد إلى GreenNet CRM.',
                    $username,
                    (string) ($routerUser['source_label'] ?? 'MikroTik'),
                    '/admin/search?q=' . urlencode($username)
                );
            }
        }

        return $issues;
    }

    private function checkDuplicateRouterUsers(array $routerUsers): array
    {
        $issues = [];
        $index = [];

        foreach ($routerUsers as $routerUser) {
            $username = (string) ($routerUser['username'] ?? '');

            if ($username === '') {
                continue;
            }

            $index[mb_strtolower($username)][] = $routerUser;
        }

        foreach ($index as $items) {
            if (count($items) <= 1) {
                continue;
            }

            $username = (string) ($items[0]['username'] ?? '-');
            $sources = [];

            foreach ($items as $item) {
                $sources[] = (string) ($item['source_label'] ?? '-');
            }

            $issues[] = $this->issue(
                'error',
                'duplicate_router_username',
                'اسم مستخدم مكرر على MikroTik',
                'المستخدم موجود في أكثر من مصدر: ' . implode(' / ', array_unique($sources)),
                $username,
                'MikroTik',
                '/admin/search?q=' . urlencode($username)
            );
        }

        return $issues;
    }

    private function checkDisabledRouterUsers(array $routerUsers): array
    {
        $issues = [];

        foreach ($routerUsers as $routerUser) {
            if (empty($routerUser['disabled'])) {
                continue;
            }

            $username = (string) ($routerUser['username'] ?? '');

            $issues[] = $this->issue(
                'warning',
                'router_user_disabled',
                'مستخدم Disabled على MikroTik',
                'المستخدم موجود لكنه معطّل حالياً على MikroTik.',
                $username,
                (string) ($routerUser['source_label'] ?? 'MikroTik'),
                '/admin/api/browser?dataset=' . urlencode($this->datasetFromSource((string) ($routerUser['source_type'] ?? ''))) . '&q=' . urlencode($username)
            );
        }

        return $issues;
    }

    private function checkProfileMismatch(array $customers, array $packages, array $routerUsers): array
    {
        $issues = [];
        $packagesById = $this->packagesById($packages);
        $routerIndex = $this->routerUsersByUsername($routerUsers);

        foreach ($customers as $customer) {
            $username = (string) ($customer['username'] ?? '');
            $packageId = (int) ($customer['package_id'] ?? 0);

            if ($username === '' || $packageId <= 0 || !isset($packagesById[$packageId])) {
                continue;
            }

            $usernameKey = mb_strtolower($username);

            if (!isset($routerIndex[$usernameKey])) {
                continue;
            }

            $package = $packagesById[$packageId];
            $expectedProfile = trim((string) ($package['source_profile'] ?? ''));

            if ($expectedProfile === '') {
                continue;
            }

            foreach ($routerIndex[$usernameKey] as $routerUser) {
                $actualProfile = trim((string) ($routerUser['profile'] ?? ''));

                if ($actualProfile === '') {
                    continue;
                }

                if ($actualProfile !== $expectedProfile) {
                    $issues[] = $this->issue(
                        'warning',
                        'profile_mismatch',
                        'اختلاف Profile بين GreenNet وMikroTik',
                        'GreenNet يتوقع profile: ' . $expectedProfile . ' لكن MikroTik يعرض: ' . $actualProfile,
                        $username,
                        (string) ($routerUser['source_label'] ?? 'MikroTik'),
                        '/admin/customers/profile?username=' . urlencode($username)
                    );
                }
            }
        }

        return $issues;
    }

    private function checkPackagesWithoutSourceProfile(array $packages): array
    {
        $issues = [];

        foreach ($packages as $package) {
            $name = (string) ($package['name'] ?? '-');
            $sourceProfile = trim((string) ($package['source_profile'] ?? ''));

            if ($sourceProfile === '') {
                $issues[] = $this->issue(
                    'error',
                    'package_without_source_profile',
                    'باقة بلا MikroTik Profile',
                    'هذه الباقة لا تحتوي source_profile، لذلك لا يمكن تطبيقها لاحقاً على MikroTik.',
                    $name,
                    'GreenNet Package',
                    '/admin/packages/edit?id=' . urlencode((string) ($package['id'] ?? ''))
                );
            }
        }

        return $issues;
    }

    private function checkPackagesWithMissingRouterProfile(array $packages, array $routerProfiles): array
    {
        $issues = [];
        $profileNames = $this->routerProfileNames($routerProfiles);

        foreach ($packages as $package) {
            $name = (string) ($package['name'] ?? '-');
            $sourceProfile = trim((string) ($package['source_profile'] ?? ''));

            if ($sourceProfile === '') {
                continue;
            }

            if (!isset($profileNames[mb_strtolower($sourceProfile)])) {
                $issues[] = $this->issue(
                    'error',
                    'package_profile_missing_on_router',
                    'باقة تشير إلى Profile غير موجود على MikroTik',
                    'الباقة مرتبطة بـ source_profile غير موجود ضمن profiles المقروءة من MikroTik: ' . $sourceProfile,
                    $name,
                    'GreenNet Package',
                    '/admin/packages/edit?id=' . urlencode((string) ($package['id'] ?? ''))
                );
            }
        }

        return $issues;
    }

    private function checkRouterProfilesNotImported(array $packages, array $routerProfiles): array
    {
        $issues = [];
        $packageProfiles = [];

        foreach ($packages as $package) {
            $sourceProfile = trim((string) ($package['source_profile'] ?? ''));

            if ($sourceProfile !== '') {
                $packageProfiles[mb_strtolower($sourceProfile)] = true;
            }
        }

        foreach ($routerProfiles as $profile) {
            $name = (string) ($profile['name'] ?? '');

            if ($name === '') {
                continue;
            }

            if (!isset($packageProfiles[mb_strtolower($name)])) {
                $issues[] = $this->issue(
                    'info',
                    'router_profile_not_imported',
                    'Profile على MikroTik غير مستورد كباقة',
                    'هذا Profile موجود على MikroTik لكن لا توجد باقة GreenNet مرتبطة به.',
                    $name,
                    (string) ($profile['source_label'] ?? 'MikroTik Profile'),
                    '/admin/packages'
                );
            }
        }

        return $issues;
    }

    private function summary(
        array $customers,
        array $packages,
        array $routerUsers,
        array $routerProfiles,
        array $issues,
        array $routerData
    ): array {
        $errors = 0;
        $warnings = 0;
        $info = 0;

        foreach ($issues as $issue) {
            $severity = (string) ($issue['severity'] ?? 'info');

            if ($severity === 'error') {
                $errors++;
            } elseif ($severity === 'warning') {
                $warnings++;
            } else {
                $info++;
            }
        }

        $routerErrors = count($routerData['errors'] ?? []);

        $readyForWrite = $errors === 0 && $routerErrors === 0;

        if ($routerErrors > 0 || $errors > 0) {
            $statusLabel = 'غير جاهز';
            $statusDescription = 'توجد أخطاء يجب إصلاحها قبل تنفيذ أي أوامر كتابة على MikroTik.';
        } elseif ($warnings > 0) {
            $statusLabel = 'جاهز مبدئياً مع تحذيرات';
            $statusDescription = 'لا توجد أخطاء حرجة، لكن توجد تحذيرات يفضل مراجعتها قبل Write Safety.';
        } else {
            $statusLabel = 'جاهز مبدئياً';
            $statusDescription = 'لا توجد أخطاء حرجة تمنع الانتقال إلى طبقة Write Safety.';
        }

        return [
            'customers_count' => count($customers),
            'packages_count' => count($packages),
            'router_users_count' => count($routerUsers),
            'router_profiles_count' => count($routerProfiles),

            'issues_count' => count($issues),
            'errors_count' => $errors,
            'warnings_count' => $warnings,
            'info_count' => $info,
            'router_errors_count' => $routerErrors,

            'ready_for_write' => $readyForWrite,
            'status_label' => $statusLabel,
            'status_description' => $statusDescription,
        ];
    }

    private function issue(
        string $severity,
        string $type,
        string $title,
        string $description,
        string $subject,
        string $source,
        string $actionUrl
    ): array {
        return [
            'severity' => $severity,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'subject' => $subject,
            'source' => $source,
            'action_url' => $actionUrl,
        ];
    }

    private function customersByUsername(array $customers): array
    {
        $indexed = [];

        foreach ($customers as $customer) {
            $username = trim((string) ($customer['username'] ?? ''));

            if ($username !== '') {
                $indexed[mb_strtolower($username)] = $customer;
            }
        }

        return $indexed;
    }

    private function packagesById(array $packages): array
    {
        $indexed = [];

        foreach ($packages as $package) {
            $indexed[(int) ($package['id'] ?? 0)] = $package;
        }

        return $indexed;
    }

    private function routerUsersByUsername(array $routerUsers): array
    {
        $indexed = [];

        foreach ($routerUsers as $routerUser) {
            $username = trim((string) ($routerUser['username'] ?? ''));

            if ($username !== '') {
                $indexed[mb_strtolower($username)][] = $routerUser;
            }
        }

        return $indexed;
    }

    private function routerProfileNames(array $routerProfiles): array
    {
        $indexed = [];

        foreach ($routerProfiles as $routerProfile) {
            $name = trim((string) ($routerProfile['name'] ?? ''));

            if ($name !== '') {
                $indexed[mb_strtolower($name)] = true;
            }
        }

        return $indexed;
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

    private function extractProfile(array $row): string
    {
        foreach (['profile', 'actual-profile', 'group'] as $key) {
            if (!empty($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function isDisabled(array $row): bool
    {
        $value = strtolower(trim((string) ($row['disabled'] ?? 'false')));

        return in_array($value, ['true', 'yes', '1'], true);
    }

    private function datasetFromSource(string $sourceType): string
    {
        return match ($sourceType) {
            'hotspot_user' => 'hotspot_users',
            'ppp_secret' => 'ppp_secrets',
            'user_manager_user' => 'user_manager_users',
            default => 'hotspot_users',
        };
    }

    private function requireLogin(): void
    {
        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}