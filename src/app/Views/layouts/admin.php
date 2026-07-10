<?php
    $safeThemeColor = $theme_color ?? '#16a34a';

    if (!is_string($safeThemeColor) || preg_match('/^#[0-9a-fA-F]{6}$/', $safeThemeColor) !== 1) {
        $safeThemeColor = '#16a34a';
    }

    $currentPath = $current_path ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin');

    $isActive = function (string $href) use ($currentPath): string {
        if ($href === '/admin') {
            return $currentPath === '/admin' ? 'active' : '';
        }

        if ($href === '/admin/customers') {
            return $currentPath === '/admin/customers' ? 'active' : '';
        }

        return str_starts_with($currentPath, $href) ? 'active' : '';
    };

    $adminName = $admin_username ?? ($_SESSION['admin_username'] ?? 'admin');

    $safeIcon = '';

    if (!empty($app_icon_path) && is_string($app_icon_path) && str_starts_with($app_icon_path, '/media/')) {
        $safeIcon = $app_icon_path;
    } elseif (!empty($site_logo_path) && is_string($site_logo_path) && str_starts_with($site_logo_path, '/media/')) {
        $safeIcon = $site_logo_path;
    }
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? ($app_title ?? 'GreenNet Admin')) ?></title>

    <?php if ($safeIcon !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($safeIcon) ?>">
        <link rel="apple-touch-icon" href="<?= htmlspecialchars($safeIcon) ?>">
    <?php endif; ?>

    <meta name="theme-color" content="<?= htmlspecialchars($safeThemeColor) ?>">

    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/admin.css">

    <style>
        :root {
            --greennet-theme: <?= htmlspecialchars($safeThemeColor) ?>;
        }

        .brand-mark,
        .btn-primary,
        .progress-bar,
        .admin-nav-link.active,
        .admin-mobile-link.active,
        .admin-badge-primary {
            background: var(--greennet-theme) !important;
        }

        .btn-primary {
            border-color: var(--greennet-theme) !important;
        }

        .status-pill .dot,
        .admin-status-dot {
            background: var(--greennet-theme) !important;
        }

        .brand-logo {
            width: 54px;
            height: 54px;
            object-fit: contain;
            border-radius: 14px;
            display: block;
        }

        .brand-mark.has-logo,
        .admin-logo-box.has-logo {
            background: #ffffff !important;
            border: 1px solid #e5e7eb;
            padding: 4px;
        }

        a {
            color: var(--greennet-theme);
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline-color: var(--greennet-theme);
        }
    </style>
</head>
<body>

<div class="admin-shell">

    <aside class="admin-sidebar">

        <div class="admin-brand">
            <div class="admin-logo-box <?= !empty($site_logo_path) ? 'has-logo' : '' ?>">
                <?php if (!empty($site_logo_path)): ?>
                    <img class="brand-logo" src="<?= htmlspecialchars($site_logo_path) ?>" alt="Logo">
                <?php else: ?>
                    G
                <?php endif; ?>
            </div>

            <div>
                <div class="admin-brand-title">
                    <?= htmlspecialchars($app_name ?? 'GreenNet') ?>
                </div>
                <div class="admin-brand-subtitle">
                    Admin Console
                </div>
            </div>
        </div>

        <nav class="admin-nav">

            <div class="admin-nav-section">الرئيسية</div>

            <a class="admin-nav-link <?= $isActive('/admin') ?>" href="/admin">
                <span>🏠</span>
                <span>لوحة المدير</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/dashboard-widgets') ?>" href="/admin/dashboard-widgets">
                <span>📊</span>
                <span>Dashboard Widgets</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/notifications') ?>" href="/admin/notifications">
                <span>🔔</span>
                <span>مركز الإشعارات</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/global-search') ?>" href="/admin/global-search">
                <span>🌐</span>
                <span>Global Search</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/audit') ?>" href="/admin/audit">
                <span>🧿</span>
                <span>Audit Center</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/health') ?>" href="/admin/health">
                <span>❤️</span>
                <span>Health Dashboard</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/search') ?>" href="/admin/search">
                <span>🔎</span>
                <span>البحث السريع</span>
            </a>

            <div class="admin-nav-section">المشتركين</div>

            <a class="admin-nav-link <?= $isActive('/admin/customers/table') ?>" href="/admin/customers/table">
                <span>👥</span>
                <span>جدول الزبائن</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/customers/timeline') ?>" href="/admin/customers/timeline">
                <span>🕓</span>
                <span>Timeline المشترك</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/customers/password') ?>" href="/admin/customers/password">
                <span>🔐</span>
                <span>كلمات مرور المشتركين</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/customers') ?>" href="/admin/customers">
                <span>🧾</span>
                <span>إدارة الزبائن</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/subscriptions') ?>" href="/admin/subscriptions">
                <span>📅</span>
                <span>الاشتراكات</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/renewal-requests') ?>" href="/admin/renewal-requests">
                <span>📨</span>
                <span>طلبات التجديد</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/packages') ?>" href="/admin/packages">
                <span>📦</span>
                <span>الباقات</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/payments') ?>" href="/admin/payments">
                <span>💳</span>
                <span>الدفعات</span>
            </a>

            <div class="admin-nav-section">MikroTik</div>

            <a class="admin-nav-link <?= $isActive('/admin/setup-wizard') ?>" href="/admin/setup-wizard">
                <span>🧙</span>
                <span>Setup Wizard</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/write-safety') ?>" href="/admin/write-safety">
                <span>🛡️</span>
                <span>Write Safety</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/router-setup') ?>" href="/admin/router-setup">
                <span>🧭</span>
                <span>Router Setup</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/routeros') ?>" href="/admin/routeros">
                <span>🧩</span>
                <span>RouterOS</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/api/diagnostics') ?>" href="/admin/api/diagnostics">
                <span>🧪</span>
                <span>API Diagnostics</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/api/browser') ?>" href="/admin/api/browser">
                <span>🗂️</span>
                <span>API Data Browser</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/readiness') ?>" href="/admin/readiness">
                <span>✅</span>
                <span>Readiness Check</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/auto-match') ?>" href="/admin/auto-match">
                <span>🔗</span>
                <span>Auto Match</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/routeros/active-users') ?>" href="/admin/routeros/active-users">
                <span>🟢</span>
                <span>المتصلون الآن</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/routeros/users') ?>" href="/admin/routeros/users">
                <span>👤</span>
                <span>مستخدمو MikroTik</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/routeros/profiles') ?>" href="/admin/routeros/profiles">
                <span>📡</span>
                <span>Profiles</span>
            </a>

            <div class="admin-nav-section">النظام</div>

            <a class="admin-nav-link <?= $isActive('/admin/reports') ?>" href="/admin/reports">
                <span>📊</span>
                <span>التقارير</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/announcements') ?>" href="/admin/announcements">
                <span>📢</span>
                <span>الإعلانات</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/qos') ?>" href="/admin/qos">
                <span>⚙️</span>
                <span>Smart QoS</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/backup') ?>" href="/admin/backup">
                <span>💾</span>
                <span>Backup & Restore</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/settings') ?>" href="/admin/settings">
                <span>🎨</span>
                <span>الإعدادات والهوية</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/media') ?>" href="/admin/media">
                <span>🖼️</span>
                <span>الصور والهوية</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/security') ?>" href="/admin/security">
                <span>🔒</span>
                <span>الأمان</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/logs') ?>" href="/admin/logs">
                <span>🧾</span>
                <span>Logs</span>
            </a>

            <a class="admin-nav-link <?= $isActive('/admin/system') ?>" href="/admin/system">
                <span>🛠️</span>
                <span>حالة النظام</span>
            </a>

        </nav>

        <div class="admin-sidebar-footer">
            <a href="/admin/logout">تسجيل الخروج</a>
        </div>

    </aside>

    <div class="admin-main-wrap">

        <header class="admin-topbar">

            <div>
                <div class="admin-topbar-title">
                    <?= htmlspecialchars($title ?? 'لوحة المدير') ?>
                </div>

                <div class="admin-topbar-subtitle">
                    <span class="admin-status-dot"></span>
                    المدير: <?= htmlspecialchars((string) $adminName) ?>
                </div>
            </div>

            <div class="admin-topbar-actions">
                <a class="admin-mini-btn" href="/admin/dashboard-widgets">Widgets</a>
                <a class="admin-mini-btn" href="/admin/notifications">الإشعارات</a>
                <a class="admin-mini-btn" href="/admin/global-search">Search</a>
                <a class="admin-mini-btn" href="/admin/write-safety">Safety</a>
                <a class="admin-mini-btn" href="/admin/health">Health</a>
                <a class="admin-mini-btn" href="/dashboard">معاينة المشترك</a>
                <a class="admin-mini-btn" href="/admin/backup">Backup</a>
                <a class="admin-mini-btn danger" href="/admin/logout">خروج</a>
            </div>

        </header>

        <div class="admin-mobile-nav">
            <a class="admin-mobile-link <?= $isActive('/admin') ?>" href="/admin">الرئيسية</a>
            <a class="admin-mobile-link <?= $isActive('/admin/notifications') ?>" href="/admin/notifications">إشعارات</a>
            <a class="admin-mobile-link <?= $isActive('/admin/global-search') ?>" href="/admin/global-search">بحث</a>
            <a class="admin-mobile-link <?= $isActive('/admin/audit') ?>" href="/admin/audit">Audit</a>
            <a class="admin-mobile-link <?= $isActive('/admin/customers/table') ?>" href="/admin/customers/table">الزبائن</a>
            <a class="admin-mobile-link <?= $isActive('/admin/customers/timeline') ?>" href="/admin/customers/timeline">Timeline</a>
            <a class="admin-mobile-link <?= $isActive('/admin/renewal-requests') ?>" href="/admin/renewal-requests">طلبات</a>
            <a class="admin-mobile-link <?= $isActive('/admin/write-safety') ?>" href="/admin/write-safety">Safety</a>
            <a class="admin-mobile-link <?= $isActive('/admin/router-setup') ?>" href="/admin/router-setup">Router</a>
        </div>

        <main class="admin-main">
            <div class="admin-content-card">
                <?= $content ?? '' ?>
            </div>
        </main>

    </div>

</div>

</body>
</html>