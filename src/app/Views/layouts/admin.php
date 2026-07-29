<?php

declare(strict_types=1);

if (!function_exists('gn_admin_h')) {
    function gn_admin_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_admin_cookie_lang')) {
    function gn_admin_cookie_lang(): string
    {
        $lang = (string) ($_COOKIE['greennet_admin_lang'] ?? 'ar');

        return $lang === 'en' ? 'en' : 'ar';
    }
}

if (!function_exists('gn_admin_dir')) {
    function gn_admin_dir(string $lang): string
    {
        return $lang === 'en' ? 'ltr' : 'rtl';
    }
}

if (!function_exists('gn_admin_path')) {
    function gn_admin_path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? rtrim($path, '/') ?: '/' : '/admin';
    }
}

if (!function_exists('gn_admin_is_active')) {
    function gn_admin_is_active(string $href): bool
    {
        $current = gn_admin_path();
        $href = rtrim($href, '/') ?: '/';

        if ($href === '/admin') {
            return $current === '/admin';
        }

        return $current === $href || str_starts_with($current, $href . '/');
    }
}

if (!function_exists('gn_admin_nav_item')) {
    function gn_admin_nav_item(string $href, string $icon, string $label, ?string $match = null): string
    {
        $target = $match ?: $href;
        $active = gn_admin_is_active($target) ? ' is-active' : '';

        return '
            <a class="gn-nav-item' . $active . '" href="' . gn_admin_h($href) . '">
                <span class="gn-nav-icon">' . gn_admin_h($icon) . '</span>
                <span class="gn-nav-label">' . gn_admin_h($label) . '</span>
            </a>
        ';
    }
}

$lang = gn_admin_cookie_lang();
$dir = gn_admin_dir($lang);
$bodyDirClass = $lang === 'en' ? 'gn-dir-ltr' : 'gn-dir-rtl';

$pageTitle = (string) ($title ?? ($lang === 'en' ? 'Admin Panel' : 'لوحة المدير'));
$appName = (string) ($_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'GreenNet');
$adminUsername = (string) ($_SESSION['admin_username'] ?? 'admin');

$labels = [
    'ar' => [
        'admin_panel' => 'لوحة الإدارة',
        'dashboard' => 'لوحة التحكم',
        'overview' => 'Overview',
        'health' => 'System Health',
        'notifications' => 'Notifications',
        'widgets' => 'Widgets',
        'automation' => 'Automation',
        'customers' => 'Customers',
        'customers_table' => 'Customer Table',
        'add_customer' => 'Add Customer',
        'passwords' => 'Subscriber Passwords',
        'timeline' => 'Customer Timeline',
        'global_search' => 'Global Search',
        'billing' => 'Billing',
        'packages' => 'Packages',
        'payments' => 'Payments',
        'renewal_requests' => 'Renewal Requests',
        'subscriptions' => 'Subscriptions',
        'reports' => 'Reports',
        'mikrotik' => 'MikroTik',
        'dry_run' => 'MikroTik Dry Run',
        'write_safety' => 'Write Safety',
        'router_setup' => 'Router Setup',
        'api_diagnostics' => 'API Diagnostics',
        'api_browser' => 'API Browser',
        'routeros_users' => 'RouterOS Users',
        'auto_match' => 'Auto Match',
        'system' => 'System',
        'audit' => 'Audit Center',
        'logs' => 'Logs',
        'settings' => 'Settings',
        'security' => 'Security',
        'media' => 'Media',
        'backup' => 'Backup',
        'logout' => 'Logout',
        'preview' => 'Preview',
        'console_note' => 'Safe UI layer before MikroTik real write.',
    ],
    'en' => [
        'admin_panel' => 'Admin Panel',
        'dashboard' => 'Dashboard',
        'overview' => 'Overview',
        'health' => 'System Health',
        'notifications' => 'Notifications',
        'widgets' => 'Widgets',
        'automation' => 'Automation',
        'customers' => 'Customers',
        'customers_table' => 'Customer Table',
        'add_customer' => 'Add Customer',
        'passwords' => 'Subscriber Passwords',
        'timeline' => 'Customer Timeline',
        'global_search' => 'Global Search',
        'billing' => 'Billing',
        'packages' => 'Packages',
        'payments' => 'Payments',
        'renewal_requests' => 'Renewal Requests',
        'subscriptions' => 'Subscriptions',
        'reports' => 'Reports',
        'mikrotik' => 'MikroTik',
        'dry_run' => 'MikroTik Dry Run',
        'write_safety' => 'Write Safety',
        'router_setup' => 'Router Setup',
        'api_diagnostics' => 'API Diagnostics',
        'api_browser' => 'API Browser',
        'routeros_users' => 'RouterOS Users',
        'auto_match' => 'Auto Match',
        'system' => 'System',
        'audit' => 'Audit Center',
        'logs' => 'Logs',
        'settings' => 'Settings',
        'security' => 'Security',
        'media' => 'Media',
        'backup' => 'Backup',
        'logout' => 'Logout',
        'preview' => 'Preview',
        'console_note' => 'Safe UI layer before MikroTik real write.',
    ],
];

$t = $labels[$lang];

$navGroups = [
    [
        'title' => $t['overview'],
        'items' => [
            ['/admin', '⌂', $t['dashboard'], '/admin'],
            ['/admin/health', '◆', $t['health'], '/admin/health'],
            ['/admin/notifications', '●', $t['notifications'], '/admin/notifications'],
            ['/admin/dashboard-widgets', '▦', $t['widgets'], '/admin/dashboard-widgets'],
            ['/admin/automation', 'A', $t['automation'], '/admin/automation'],
            ['/admin/lifecycle', 'L', 'Subscription Lifecycle', '/admin/lifecycle'],
        ],
    ],
    [
        'title' => $t['customers'],
        'items' => [
            ['/admin/customers/table', '☷', $t['customers_table'], '/admin/customers/table'],
            ['/admin/customers', '+', $t['add_customer'], '/admin/customers'],
            ['/admin/customers/password', '◉', $t['passwords'], '/admin/customers/password'],
            ['/admin/customers/timeline', '◷', $t['timeline'], '/admin/customers/timeline'],
            ['/admin/global-search', '⌕', $t['global_search'], '/admin/global-search'],
        ],
    ],
    [
        'title' => $t['billing'],
        'items' => [
            ['/admin/packages', '▣', $t['packages'], '/admin/packages'],
            ['/admin/user-manager-packages', '⇣', 'Import UM Packages', '/admin/user-manager-packages'],
            ['/admin/package-push', '⇡', 'Push Packages', '/admin/package-push'],
            ['/admin/package-assign', '⇄', 'Assign Package', '/admin/package-assign'],
            ['/admin/user-manager-user-create', '+', 'Create UM User', '/admin/user-manager-user-create'],
            ['/admin/user-manager-password', '🔑', 'UM Password', '/admin/user-manager-password'],
            ['/admin/user-manager-user-delete', '✖', 'Delete UM User', '/admin/user-manager-user-delete'],
            ['/admin/user-disconnect', '⏏', 'Disconnect User', '/admin/user-disconnect'],
            ['/admin/user-manager-control', '◎', 'UM Control', '/admin/user-manager-control'],
            ['/admin/payments', '$', $t['payments'], '/admin/payments'],
            ['/admin/renewal-requests', '↻', $t['renewal_requests'], '/admin/renewal-requests'],
            ['/admin/subscriptions', '◫', $t['subscriptions'], '/admin/subscriptions'],
            ['/admin/reports', '▤', $t['reports'], '/admin/reports'],
        ],
    ],
    [
        'title' => $t['mikrotik'],
        'items' => [
            ['/admin/mikrotik-dry-run', '⚗', $t['dry_run'], '/admin/mikrotik-dry-run'],
            ['/admin/write-safety', '🛡', $t['write_safety'], '/admin/write-safety'],
            ['/admin/router-setup', '◎', $t['router_setup'], '/admin/router-setup'],
            ['/admin/routers', 'R', 'Routers', '/admin/routers'],
            ['/admin/router-onboarding', '+', 'Router Onboarding', '/admin/router-onboarding'],
            ['/admin/api/diagnostics', '◈', $t['api_diagnostics'], '/admin/api/diagnostics'],
            ['/admin/api/browser', '⌘', $t['api_browser'], '/admin/api/browser'],
            ['/admin/routeros/users', '◌', $t['routeros_users'], '/admin/routeros/users'],
            ['/admin/auto-match', '⇄', $t['auto_match'], '/admin/auto-match'],
        ],
    ],
    [
        'title' => $t['system'],
        'items' => [
            ['/admin/audit', '☰', $t['audit'], '/admin/audit'],
            ['/admin/logs', '≡', $t['logs'], '/admin/logs'],
            ['/admin/settings', '⚙', $t['settings'], '/admin/settings'],
            ['/admin/security', '🔒', $t['security'], '/admin/security'],
            ['/admin/media', '▧', $t['media'], '/admin/media'],
            ['/admin/backup', '⬇', $t['backup'], '/admin/backup'],
        ],
    ],
];

?><!doctype html>
<html lang="<?= gn_admin_h($lang) ?>" dir="<?= gn_admin_h($dir) ?>" data-theme="greennet-light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= gn_admin_h($pageTitle) ?> - <?= gn_admin_h($appName) ?></title>

    <link rel="stylesheet" href="/css/admin.css?v=base">
    <link rel="stylesheet" href="/css/admin-ui.css?v=ui9">
    <link rel="stylesheet" href="/css/admin-layout-fix.css?v=ui43hard">
    <link rel="stylesheet" href="/css/admin-tables.css?v=ui5pack">
    <link rel="stylesheet" href="/css/admin-audit.css?v=ui6pack">
    <link rel="stylesheet" href="/css/admin-dry-run.css?v=ui7pack">
    <link rel="stylesheet" href="/css/admin-pages.css?v=ui8pack">
    <link rel="stylesheet" href="/css/admin-theme-final.css?v=themefinal1">
</head>

<body class="gn-admin-body <?= gn_admin_h($bodyDirClass) ?>" data-admin-lang="<?= gn_admin_h($lang) ?>" data-admin-theme="greennet-light">
    <div class="gn-sidebar-overlay" data-gn-sidebar-close></div>

    <div class="gn-admin-shell">
        <aside class="gn-admin-sidebar" aria-label="Admin navigation">
            <div class="gn-sidebar-brand">
                <div class="gn-brand-mark">G</div>
                <div class="gn-brand-text">
                    <div class="gn-brand-title"><?= gn_admin_h($appName) ?></div>
                    <div class="gn-brand-subtitle"><?= gn_admin_h($t['admin_panel']) ?></div>
                </div>
            </div>

            <nav class="gn-sidebar-scroll">
                <?php foreach ($navGroups as $group): ?>
                    <section class="gn-nav-section">
                        <div class="gn-nav-section-title"><?= gn_admin_h((string) ($group['title'] ?? '')) ?></div>

                        <div class="gn-nav-list">
                            <?php foreach (($group['items'] ?? []) as $item): ?>
                                <?= gn_admin_nav_item(
                                    (string) ($item[0] ?? '#'),
                                    (string) ($item[1] ?? '•'),
                                    (string) ($item[2] ?? ''),
                                    (string) ($item[3] ?? ($item[0] ?? '#'))
                                ) ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </nav>

            <div class="gn-sidebar-footer">
                <div class="gn-sidebar-status">
                    <div class="gn-sidebar-status-title">GreenNet Console</div>
                    <div class="gn-sidebar-status-note"><?= gn_admin_h($t['console_note']) ?></div>
                </div>
            </div>
        </aside>

        <div class="gn-admin-main">
            <header class="gn-admin-topbar">
                <div class="gn-topbar-left">
                    <button class="gn-btn gn-btn-secondary gn-btn-icon gn-mobile-menu-btn" type="button" data-gn-sidebar-toggle aria-label="Menu">☰</button>

                    <div class="gn-topbar-title-wrap">
                        <div class="gn-topbar-kicker"><?= gn_admin_h($appName) ?></div>
                        <div class="gn-topbar-title"><?= gn_admin_h($pageTitle) ?></div>
                    </div>
                </div>

                <div class="gn-topbar-right">
                    <div class="gn-topbar-chip">
                        <span class="gn-topbar-chip-dot"></span>
                        <span><?= gn_admin_h($adminUsername) ?></span>
                    </div>

                    <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/dashboard?username=test" target="_blank"><?= gn_admin_h($t['preview']) ?></a>
                    <a class="gn-btn gn-btn-ghost gn-btn-sm" href="/admin/logout"><?= gn_admin_h($t['logout']) ?></a>
                </div>
            </header>

            <main class="gn-admin-content">
                <?= $content ?? '' ?>
            </main>

            <link rel="stylesheet" href="/css/admin-theme-final.css?v=themefinal1">
        </div>
    </div>

    <script src="/js/admin-theme.js?v=themefinal1"></script>
    <script src="/js/admin-buttons.js?v=ui3"></script>
    <script src="/js/admin-layout.js?v=ui4"></script>
    <script src="/js/admin-tables.js?v=ui5pack"></script>
    <script src="/js/admin-pages.js?v=ui8pack"></script>
</body>
</html>
