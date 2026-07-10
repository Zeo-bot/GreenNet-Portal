<?php

declare(strict_types=1);

namespace GreenNet\Controllers;

use GreenNet\Core\Database;
use GreenNet\Core\View;
use GreenNet\Models\AppLog;
use GreenNet\Services\RouterOS\MikroTikService;
use PDO;
use Throwable;

class AdminAutoMatchController
{
    public function index(): string
    {
        Database::migrate();
        $this->requireLogin();

        $data = $this->buildData();

        return View::render('admin/auto_match', [
            'title' => 'Auto Match / Auto Link',
            'data' => $data,
            'message' => $_SESSION['flash_message'] ?? '',
            'message_type' => $_SESSION['flash_type'] ?? 'success',
        ]);
    }

    public function importUsers(): void
    {
        Database::migrate();
        $this->requireLogin();

        $selected = $_POST['users'] ?? [];

        if (!is_array($selected) || count($selected) === 0) {
            $this->flash('لم يتم اختيار أي مستخدم للاستيراد.', 'warning');
            $this->redirect();
        }

        $data = $this->buildData();
        $routerUsersByKey = [];

        foreach ($data['router_users'] as $user) {
            $key = $this->userKey($user);
            $routerUsersByKey[$key] = $user;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($selected as $key) {
            $key = (string) $key;

            if (!isset($routerUsersByKey[$key])) {
                $skipped++;
                continue;
            }

            $user = $routerUsersByKey[$key];

            if ($this->insertCustomerFromRouterUser($user)) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        AppLog::info('Auto Match: تم استيراد مستخدمين من MikroTik إلى CRM', [
            'imported' => $imported,
            'skipped' => $skipped,
        ]);

        $this->flash('تم استيراد ' . $imported . ' مستخدم إلى CRM. تم تجاهل ' . $skipped . '.', 'success');
        $this->redirect();
    }

    public function importProfiles(): void
    {
        Database::migrate();
        $this->requireLogin();

        $selected = $_POST['profiles'] ?? [];

        if (!is_array($selected) || count($selected) === 0) {
            $this->flash('لم يتم اختيار أي Profile للاستيراد.', 'warning');
            $this->redirect();
        }

        $data = $this->buildData();
        $profilesByKey = [];

        foreach ($data['router_profiles'] as $profile) {
            $key = $this->profileKey($profile);
            $profilesByKey[$key] = $profile;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($selected as $key) {
            $key = (string) $key;

            if (!isset($profilesByKey[$key])) {
                $skipped++;
                continue;
            }

            $profile = $profilesByKey[$key];

            if ($this->insertPackageFromRouterProfile($profile)) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        AppLog::info('Auto Match: تم استيراد Profiles كباقات GreenNet', [
            'imported' => $imported,
            'skipped' => $skipped,
        ]);

        $this->flash('تم استيراد ' . $imported . ' Profile كباقات. تم تجاهل ' . $skipped . '.', 'success');
        $this->redirect();
    }

    public function linkPackages(): void
    {
        Database::migrate();
        $this->requireLogin();

        $data = $this->buildData();

        $linked = 0;
        $skipped = 0;

        foreach ($data['package_link_suggestions'] as $suggestion) {
            $username = (string) ($suggestion['username'] ?? '');
            $packageId = (int) ($suggestion['package_id'] ?? 0);

            if ($username === '' || $packageId <= 0) {
                $skipped++;
                continue;
            }

            if ($this->updateCustomerPackage($username, $packageId)) {
                $linked++;
            } else {
                $skipped++;
            }
        }

        AppLog::info('Auto Match: تم ربط زبائن CRM بالباقات حسب Router Profile', [
            'linked' => $linked,
            'skipped' => $skipped,
        ]);

        $this->flash('تم ربط ' . $linked . ' زبون بالباقات المناسبة. تم تجاهل ' . $skipped . '.', 'success');
        $this->redirect();
    }

    private function buildData(): array
    {
        $customers = $this->customers();
        $packages = $this->packages();
        $router = $this->routerData();

        $customersByUsername = $this->customersByUsername($customers);
        $packagesByProfile = $this->packagesByProfile($packages);

        $routerUsersMissingInCrm = [];
        $routerProfilesMissingAsPackages = [];
        $packageLinkSuggestions = [];
        $duplicates = $this->duplicates($router['users']);

        foreach ($router['users'] as $user) {
            $usernameKey = mb_strtolower((string) ($user['username'] ?? ''));

            if ($usernameKey === '') {
                continue;
            }

            if (!isset($customersByUsername[$usernameKey])) {
                $routerUsersMissingInCrm[] = $user;
                continue;
            }

            $customer = $customersByUsername[$usernameKey];
            $customerPackageId = (int) ($customer['package_id'] ?? 0);
            $profileKey = mb_strtolower(trim((string) ($user['profile'] ?? '')));

            if ($profileKey !== '' && isset($packagesByProfile[$profileKey])) {
                $package = $packagesByProfile[$profileKey];
                $packageId = (int) ($package['id'] ?? 0);

                if ($packageId > 0 && $customerPackageId !== $packageId) {
                    $packageLinkSuggestions[] = [
                        'username' => (string) ($user['username'] ?? ''),
                        'current_package_id' => $customerPackageId,
                        'current_package_name' => (string) ($customer['package_name'] ?? ''),
                        'router_profile' => (string) ($user['profile'] ?? ''),
                        'router_source' => (string) ($user['source_label'] ?? ''),
                        'package_id' => $packageId,
                        'package_name' => (string) ($package['name'] ?? ''),
                    ];
                }
            }
        }

        foreach ($router['profiles'] as $profile) {
            $nameKey = mb_strtolower(trim((string) ($profile['name'] ?? '')));

            if ($nameKey === '') {
                continue;
            }

            if (!isset($packagesByProfile[$nameKey])) {
                $routerProfilesMissingAsPackages[] = $profile;
            }
        }

        return [
            'customers' => $customers,
            'packages' => $packages,
            'router_users' => $router['users'],
            'router_profiles' => $router['profiles'],
            'router_errors' => $router['errors'],

            'router_users_missing_in_crm' => $routerUsersMissingInCrm,
            'router_profiles_missing_as_packages' => $routerProfilesMissingAsPackages,
            'package_link_suggestions' => $packageLinkSuggestions,
            'duplicates' => $duplicates,

            'summary' => [
                'customers_count' => count($customers),
                'packages_count' => count($packages),
                'router_users_count' => count($router['users']),
                'router_profiles_count' => count($router['profiles']),
                'missing_users_count' => count($routerUsersMissingInCrm),
                'missing_profiles_count' => count($routerProfilesMissingAsPackages),
                'link_suggestions_count' => count($packageLinkSuggestions),
                'duplicates_count' => count($duplicates),
                'router_errors_count' => count($router['errors']),
            ],
        ];
    }

    private function customers(): array
    {
        $stmt = Database::connection()->query("
            SELECT
                c.*,
                sp.name AS package_name,
                sp.source_profile AS package_source_profile,
                sp.source_type AS package_source_type
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

    private function routerData(): array
    {
        $users = [];
        $profiles = [];
        $errors = [];

        try {
            $service = new MikroTikService();

            $this->appendRouterUsers($users, 'hotspot_user', 'Hotspot Users', $this->safeRead(fn () => $service->readHotspotUsers(), 'Hotspot Users', $errors));
            $this->appendRouterUsers($users, 'ppp_secret', 'PPP Secrets', $this->safeRead(fn () => $service->readPppSecrets(), 'PPP Secrets', $errors));
            $this->appendRouterUsers($users, 'user_manager_user', 'User Manager Users', $this->safeRead(fn () => $service->readUserManagerUsers(), 'User Manager Users', $errors));

            $this->appendRouterProfiles($profiles, 'hotspot_profile', 'Hotspot Profiles', $this->safeRead(fn () => $service->readHotspotProfiles(), 'Hotspot Profiles', $errors));
            $this->appendRouterProfiles($profiles, 'ppp_profile', 'PPP Profiles', $this->safeRead(fn () => $service->readPppProfiles(), 'PPP Profiles', $errors));
            $this->appendRouterProfiles($profiles, 'user_manager_profile', 'User Manager Profiles', $this->safeRead(fn () => $service->readUserManagerProfiles(), 'User Manager Profiles', $errors));
        } catch (Throwable $e) {
            $errors[] = [
                'source' => 'RouterOS',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'users' => $users,
            'profiles' => $profiles,
            'errors' => $errors,
        ];
    }

    private function safeRead(callable $callback, string $label, array &$errors): array
    {
        try {
            $rows = $callback();

            if (isset($rows['rows']) && is_array($rows['rows'])) {
                return $rows['rows'];
            }

            if (is_array($rows)) {
                return array_values(array_filter($rows, 'is_array'));
            }

            return [];
        } catch (Throwable $e) {
            $errors[] = [
                'source' => $label,
                'message' => $e->getMessage(),
            ];

            return [];
        }
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
                'shared_users' => (string) ($row['shared-users'] ?? ''),
                'comment' => (string) ($row['comment'] ?? ''),
                'raw' => $row,
            ];
        }
    }

    private function insertCustomerFromRouterUser(array $user): bool
    {
        $username = trim((string) ($user['username'] ?? ''));

        if ($username === '') {
            return false;
        }

        if ($this->customerExists($username)) {
            return false;
        }

        $columns = $this->tableColumns('customers_local');

        $data = [
            'username' => $username,
            'full_name' => $username,
            'phone' => '',
            'address' => '',
            'payment_status' => 'unpaid',
            'notes' => 'Imported from ' . (string) ($user['source_label'] ?? 'MikroTik') . ' — profile: ' . (string) ($user['profile'] ?? ''),
            'package_id' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $insert = [];

        foreach ($data as $column => $value) {
            if (in_array($column, $columns, true)) {
                $insert[$column] = $value;
            }
        }

        if (!isset($insert['username'])) {
            return false;
        }

        $columnSql = implode(', ', array_keys($insert));
        $placeholderSql = ':' . implode(', :', array_keys($insert));

        $stmt = Database::connection()->prepare("
            INSERT OR IGNORE INTO customers_local ({$columnSql})
            VALUES ({$placeholderSql})
        ");

        $stmt->execute($insert);

        return $stmt->rowCount() > 0;
    }

    private function insertPackageFromRouterProfile(array $profile): bool
    {
        $name = trim((string) ($profile['name'] ?? ''));

        if ($name === '') {
            return false;
        }

        $sourceType = (string) ($profile['source_type'] ?? 'router_profile');

        if ($this->packageProfileExists($name)) {
            return false;
        }

        $accessType = match ($sourceType) {
            'hotspot_profile' => 'hotspot',
            'ppp_profile' => 'ppp',
            'user_manager_profile' => 'user-manager',
            default => 'hybrid',
        };

        $columns = $this->tableColumns('service_packages');

        $data = [
            'name' => $name,
            'source_type' => $sourceType,
            'source_profile' => $name,
            'access_type' => $accessType,
            'rate_limit' => (string) ($profile['rate_limit'] ?? ''),
            'duration_days' => 30,
            'quota_gb' => 0,
            'price' => 0,
            'currency' => 'SYP',
            'is_active' => 1,
            'notes' => 'Imported from ' . (string) ($profile['source_label'] ?? 'MikroTik Profiles'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $insert = [];

        foreach ($data as $column => $value) {
            if (in_array($column, $columns, true)) {
                $insert[$column] = $value;
            }
        }

        if (!isset($insert['name'])) {
            return false;
        }

        $columnSql = implode(', ', array_keys($insert));
        $placeholderSql = ':' . implode(', :', array_keys($insert));

        $stmt = Database::connection()->prepare("
            INSERT OR IGNORE INTO service_packages ({$columnSql})
            VALUES ({$placeholderSql})
        ");

        $stmt->execute($insert);

        return $stmt->rowCount() > 0;
    }

    private function updateCustomerPackage(string $username, int $packageId): bool
    {
        $columns = $this->tableColumns('customers_local');

        if (!in_array('package_id', $columns, true)) {
            return false;
        }

        $sql = "
            UPDATE customers_local
            SET package_id = :package_id
        ";

        if (in_array('updated_at', $columns, true)) {
            $sql .= ", updated_at = :updated_at";
        }

        $sql .= " WHERE username = :username";

        $params = [
            'package_id' => $packageId,
            'username' => $username,
        ];

        if (in_array('updated_at', $columns, true)) {
            $params['updated_at'] = date('Y-m-d H:i:s');
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    private function customerExists(string $username): bool
    {
        $stmt = Database::connection()->prepare("
            SELECT COUNT(*)
            FROM customers_local
            WHERE lower(username) = lower(:username)
        ");

        $stmt->execute([
            'username' => $username,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function packageProfileExists(string $profile): bool
    {
        $stmt = Database::connection()->prepare("
            SELECT COUNT(*)
            FROM service_packages
            WHERE lower(source_profile) = lower(:profile)
        ");

        $stmt->execute([
            'profile' => $profile,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableColumns(string $table): array
    {
        $allowed = [
            'customers_local',
            'service_packages',
        ];

        if (!in_array($table, $allowed, true)) {
            return [];
        }

        $stmt = Database::connection()->query("PRAGMA table_info({$table})");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columns = [];

        foreach ($rows as $row) {
            $columns[] = (string) ($row['name'] ?? '');
        }

        return $columns;
    }

    private function customersByUsername(array $customers): array
    {
        $indexed = [];

        foreach ($customers as $customer) {
            $username = mb_strtolower(trim((string) ($customer['username'] ?? '')));

            if ($username !== '') {
                $indexed[$username] = $customer;
            }
        }

        return $indexed;
    }

    private function packagesByProfile(array $packages): array
    {
        $indexed = [];

        foreach ($packages as $package) {
            $profile = mb_strtolower(trim((string) ($package['source_profile'] ?? '')));

            if ($profile !== '') {
                $indexed[$profile] = $package;
            }
        }

        return $indexed;
    }

    private function duplicates(array $routerUsers): array
    {
        $groups = [];

        foreach ($routerUsers as $user) {
            $usernameKey = mb_strtolower(trim((string) ($user['username'] ?? '')));

            if ($usernameKey === '') {
                continue;
            }

            $groups[$usernameKey][] = $user;
        }

        $duplicates = [];

        foreach ($groups as $items) {
            if (count($items) > 1) {
                $duplicates[] = [
                    'username' => (string) ($items[0]['username'] ?? ''),
                    'items' => $items,
                    'count' => count($items),
                ];
            }
        }

        return $duplicates;
    }

    private function userKey(array $user): string
    {
        return base64_encode(json_encode([
            'username' => (string) ($user['username'] ?? ''),
            'source_type' => (string) ($user['source_type'] ?? ''),
            'id' => (string) ($user['id'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function profileKey(array $profile): string
    {
        return base64_encode(json_encode([
            'name' => (string) ($profile['name'] ?? ''),
            'source_type' => (string) ($profile['source_type'] ?? ''),
            'id' => (string) ($profile['id'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }

    private function redirect(): void
    {
        header('Location: /admin/auto-match');
        exit;
    }

    private function requireLogin(): void
    {
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        if (($_SESSION['admin_logged_in'] ?? false) !== true) {
            header('Location: /admin/login');
            exit;
        }
    }
}