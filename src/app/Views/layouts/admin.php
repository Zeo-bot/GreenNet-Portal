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
        'overview' => 'الرئيسية',
        'automation' => 'الأتمتة',
        'customers' => 'المشتركون',
        'customers_table' => 'المشتركون',
        'billing' => 'الباقات',
        'packages' => 'الباقات',
        'payments' => 'الدفعات',
        'renewal_requests' => 'طلبات التجديد',
        'mikrotik' => 'الشبكة',
        'api_diagnostics' => 'API Diagnostics',
        'api_browser' => 'API Browser',
        'system' => 'النظام',
        'settings' => 'الإعدادات',
        'backup' => 'النسخ الاحتياطي والاستعادة',
        'logout' => 'تسجيل الخروج',
        'advanced' => 'أدوات متقدمة',
        'console_note' => 'إدارة المشتركين والشبكة من مكان واحد.',
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
        'advanced' => 'Advanced Tools',
        'console_note' => 'Safe UI layer before MikroTik real write.',
    ],
];

$t = $labels[$lang];

$navGroups = [
    [
        'title' => $t['overview'],
        'items' => [
            ['/admin', '⌂', $t['dashboard'], '/admin'],
        ],
    ],
    [
        'title' => $t['customers'],
        'items' => [
            ['/admin/customers/table', '☷', $t['customers_table'], '/admin/customers/table'],
            ['/admin/renewal-requests', '↻', $t['renewal_requests'], '/admin/renewal-requests'],
            ['/admin/payments', '$', $t['payments'], '/admin/payments'],
        ],
    ],
    [
        'title' => $t['billing'],
        'items' => [
            ['/admin/packages', '▣', $t['packages'], '/admin/packages'],
            ['/admin/router-onboarding', '⇄', 'تجهيز الباقات / Profiles', '/admin/router-onboarding'],
        ],
    ],
    [
        'title' => $t['mikrotik'],
        'items' => [
            ['/admin/routers', 'R', 'الراوترات', '/admin/routers'],
            ['/admin/routeros/active-users', '●', 'الجلسات النشطة', '/admin/routeros/active-users'],
            ['/admin/automation', 'A', $t['automation'], '/admin/automation'],
        ],
    ],
    [
        'title' => $t['system'],
        'items' => [
            ['/admin/backup', '⬇', $t['backup'], '/admin/backup'],
            ['/admin/settings', '⚙', $t['settings'], '/admin/settings'],
        ],
    ],
    [
        'title' => $t['advanced'],
        'items' => [
            ['/admin/health', '◆', 'جاهزية النظام', '/admin/health'],
            ['/admin/api/diagnostics', '◈', $t['api_diagnostics'], '/admin/api/diagnostics'],
            ['/admin/api/browser', '⌘', $t['api_browser'], '/admin/api/browser'],
            ['/admin/api/record', '◌', 'تفاصيل السجل', '/admin/api/record'],
            ['/admin/readiness', '✓', 'فحص الجاهزية', '/admin/readiness'],
            ['/admin/user-manager-control', '◎', 'أدوات User Manager', '/admin/user-manager-control'],
            ['/admin/native-subscriber', 'N', 'عمليات RouterOS الأصلية', '/admin/native-subscriber'],
            ['/admin/write-safety', '🛡', 'إعدادات أمان الكتابة', '/admin/write-safety'],
            ['/admin/logs', '≡', 'السجلات', '/admin/logs'],
            ['/admin/audit', '☰', 'سجل العمليات', '/admin/audit'],
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
    <link rel="stylesheet" href="/css/product-experience.css?v=rc1">
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
                    <button class="gn-btn gn-btn-ghost gn-btn-sm" type="button" data-gn-language-toggle><?= $lang === 'ar' ? 'English' : 'العربية' ?></button>
                    <button class="gn-btn gn-btn-ghost gn-btn-sm" type="button" data-gn-theme-toggle><?= $lang === 'ar' ? 'المظهر' : 'Theme' ?></button>
                    <div class="gn-topbar-chip">
                        <span class="gn-topbar-chip-dot"></span>
                        <span><?= gn_admin_h($adminUsername) ?></span>
                    </div>

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
    <script src="/js/product-language.js?v=rc1"></script>
    <script src="/js/admin-buttons.js?v=ui3"></script>
    <script src="/js/admin-layout.js?v=ui4"></script>
    <script src="/js/admin-tables.js?v=ui5pack"></script>
    <script src="/js/admin-pages.js?v=ui8pack"></script>
</body>
</html>
