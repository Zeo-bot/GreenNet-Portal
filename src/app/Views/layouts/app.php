<?php

declare(strict_types=1);

if (!function_exists('gn_sub_h')) {
    function gn_sub_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_sub_current_path')) {
    function gn_sub_current_path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? rtrim($path, '/') ?: '/' : '/';
    }
}

$appName = (string) ($_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'GreenNet');
$pageTitle = (string) ($title ?? $appName);
$currentPath = gn_sub_current_path();

$isAdminLogin = $currentPath === '/admin/login';

$username = (string) ($_GET['username'] ?? ($_SESSION['subscriber_username'] ?? ''));
$usernameQuery = $username !== '' ? '?username=' . rawurlencode($username) : '';

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= gn_sub_h($pageTitle) ?> - <?= gn_sub_h($appName) ?></title>

    <meta name="theme-color" content="#11945a">
    <link rel="manifest" href="/manifest.webmanifest">

    <?php if ($isAdminLogin): ?>
        <style>
            :root {
                --login-bg: #06120b;
                --login-bg-2: #0c2618;
                --login-card: rgba(255, 255, 255, 0.94);
                --login-card-dark: rgba(11, 30, 20, 0.92);
                --login-text: #102018;
                --login-muted: #64748b;
                --login-border: rgba(148, 163, 184, 0.28);
                --login-primary: #16a464;
                --login-primary-2: #0f7f4d;
                --login-primary-3: #34d98b;
                --login-danger: #dc2626;
                --login-danger-soft: #fee2e2;
                --login-radius: 30px;
                --login-shadow: 0 28px 90px rgba(0, 0, 0, 0.28);
            }

            * {
                box-sizing: border-box;
            }

            html,
            body {
                min-height: 100%;
            }

            body {
                margin: 0;
                font-family: Tahoma, Arial, sans-serif;
                color: var(--login-text);
                background:
                    radial-gradient(circle at 12% 12%, rgba(52, 217, 139, 0.24), transparent 28%),
                    radial-gradient(circle at 88% 18%, rgba(22, 164, 100, 0.18), transparent 30%),
                    radial-gradient(circle at 50% 100%, rgba(22, 164, 100, 0.12), transparent 32%),
                    linear-gradient(135deg, #041009, #07180f 45%, #0c2618);
                overflow-x: hidden;
            }

            .gn-admin-login-page {
                min-height: 100vh;
                display: grid;
                grid-template-rows: auto 1fr;
            }

            .gn-admin-login-topbar {
                position: relative;
                z-index: 2;
                min-height: 82px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 18px 34px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                background: rgba(4, 16, 9, 0.72);
                backdrop-filter: blur(18px);
            }

            .gn-admin-login-brand {
                display: flex;
                align-items: center;
                gap: 12px;
                color: #ffffff;
                text-decoration: none;
            }

            .gn-admin-login-mark {
                width: 48px;
                height: 48px;
                border-radius: 18px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background:
                    radial-gradient(circle at 30% 20%, rgba(255,255,255,0.34), transparent 24%),
                    linear-gradient(135deg, var(--login-primary-3), var(--login-primary), var(--login-primary-2));
                color: #ffffff;
                font-size: 24px;
                font-weight: 950;
                box-shadow: 0 18px 40px rgba(22, 164, 100, 0.34);
            }

            .gn-admin-login-brand-title {
                display: block;
                font-size: 19px;
                font-weight: 950;
                line-height: 1.15;
            }

            .gn-admin-login-brand-subtitle {
                display: block;
                margin-top: 4px;
                color: rgba(255,255,255,0.68);
                font-size: 12px;
                font-weight: 800;
            }

            .gn-admin-login-theme {
                min-height: 42px;
                padding: 8px 16px;
                border-radius: 999px;
                border: 1px solid rgba(255,255,255,0.14);
                background: rgba(255,255,255,0.08);
                color: #ffffff;
                font-weight: 950;
                cursor: pointer;
            }

            .gn-admin-login-main {
                position: relative;
                z-index: 1;
                display: grid;
                place-items: center;
                padding: 44px 22px;
            }

            .gn-admin-login-main::before {
                content: "";
                position: absolute;
                inset: 0;
                background:
                    linear-gradient(rgba(255,255,255,0.026) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,255,255,0.026) 1px, transparent 1px);
                background-size: 38px 38px;
                mask-image: radial-gradient(circle at center, black, transparent 72%);
                pointer-events: none;
            }

            .gn-admin-login-shell {
                position: relative;
                width: min(100%, 1040px);
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(360px, 430px);
                gap: 0;
                border-radius: 34px;
                overflow: hidden;
                background: rgba(255,255,255,0.06);
                border: 1px solid rgba(255,255,255,0.12);
                box-shadow: var(--login-shadow);
                backdrop-filter: blur(20px);
            }

            .gn-admin-login-info {
                position: relative;
                min-height: 560px;
                padding: 44px;
                color: #ffffff;
                background:
                    radial-gradient(circle at 18% 18%, rgba(52, 217, 139, 0.28), transparent 28%),
                    linear-gradient(135deg, rgba(15, 127, 77, 0.82), rgba(6, 18, 11, 0.94));
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                overflow: hidden;
            }

            .gn-admin-login-info::after {
                content: "";
                position: absolute;
                width: 280px;
                height: 280px;
                border-radius: 999px;
                inset-inline-start: -90px;
                bottom: -120px;
                background: rgba(255,255,255,0.08);
            }

            .gn-admin-login-kicker {
                display: inline-flex;
                width: fit-content;
                min-height: 30px;
                padding: 5px 12px;
                border-radius: 999px;
                background: rgba(255,255,255,0.12);
                border: 1px solid rgba(255,255,255,0.16);
                color: rgba(255,255,255,0.86);
                font-size: 12px;
                font-weight: 950;
            }

            .gn-admin-login-info h1 {
                position: relative;
                z-index: 1;
                margin: 18px 0 0;
                font-size: 44px;
                line-height: 1.12;
                letter-spacing: -0.06em;
                font-weight: 950;
            }

            .gn-admin-login-info p {
                position: relative;
                z-index: 1;
                max-width: 560px;
                margin: 16px 0 0;
                color: rgba(255,255,255,0.78);
                line-height: 1.9;
                font-size: 15px;
            }

            .gn-admin-login-points {
                position: relative;
                z-index: 1;
                display: grid;
                gap: 10px;
                margin: 24px 0 0;
                padding: 0;
                list-style: none;
            }

            .gn-admin-login-points li {
                display: flex;
                align-items: center;
                gap: 10px;
                color: rgba(255,255,255,0.84);
                font-weight: 800;
                font-size: 13px;
            }

            .gn-admin-login-points span {
                width: 26px;
                height: 26px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: rgba(255,255,255,0.14);
                color: #ffffff;
                font-size: 12px;
                font-weight: 950;
            }

            .gn-admin-login-copy {
                position: relative;
                z-index: 1;
                color: rgba(255,255,255,0.56);
                font-size: 12px;
                font-weight: 800;
            }

            .gn-admin-login-card {
                padding: 44px;
                background: var(--login-card);
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            html[data-theme="dark"] .gn-admin-login-card {
                background: var(--login-card-dark);
                color: #ecfdf5;
            }

            html[data-theme="dark"] {
                --login-text: #ecfdf5;
                --login-muted: #9bbba8;
                --login-border: rgba(148, 163, 184, 0.22);
            }

            .gn-admin-login-card-header {
                margin-bottom: 24px;
            }

            .gn-admin-login-card-logo {
                width: 58px;
                height: 58px;
                border-radius: 22px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background:
                    radial-gradient(circle at 30% 20%, rgba(255,255,255,0.34), transparent 24%),
                    linear-gradient(135deg, var(--login-primary-3), var(--login-primary), var(--login-primary-2));
                color: #ffffff;
                font-size: 28px;
                font-weight: 950;
                box-shadow: 0 18px 40px rgba(22, 164, 100, 0.24);
            }

            .gn-admin-login-card h2 {
                margin: 18px 0 0;
                color: var(--login-text);
                font-size: 30px;
                font-weight: 950;
                letter-spacing: -0.05em;
            }

            .gn-admin-login-card p {
                margin: 8px 0 0;
                color: var(--login-muted);
                line-height: 1.7;
            }

            .gn-admin-login-error {
                padding: 13px 14px;
                border-radius: 18px;
                background: var(--login-danger-soft);
                color: var(--login-danger);
                font-weight: 900;
                line-height: 1.6;
                margin-bottom: 16px;
            }

            .gn-admin-login-form {
                display: grid;
                gap: 15px;
            }

            .gn-admin-login-field {
                display: grid;
                gap: 7px;
            }

            .gn-admin-login-field label {
                color: var(--login-text);
                font-size: 13px;
                font-weight: 950;
            }

            .gn-admin-login-field input {
                width: 100%;
                min-height: 52px;
                padding: 12px 16px;
                border-radius: 18px;
                border: 1px solid var(--login-border);
                background: #ffffff;
                color: #0f172a;
                outline: none;
                font-size: 15px;
                direction: ltr;
                text-align: left;
                transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
            }

            html[data-theme="dark"] .gn-admin-login-field input {
                background: rgba(255,255,255,0.08);
                color: #ecfdf5;
            }

            .gn-admin-login-field input:focus {
                border-color: var(--login-primary);
                box-shadow: 0 0 0 4px rgba(22, 164, 100, 0.16);
            }

            .gn-admin-login-actions {
                display: grid;
                gap: 10px;
                margin-top: 6px;
            }

            .gn-admin-login-button,
            .gn-admin-login-secondary {
                min-height: 52px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 18px;
                font-weight: 950;
                text-decoration: none;
                cursor: pointer;
            }

            .gn-admin-login-button {
                border: 0;
                background: linear-gradient(135deg, var(--login-primary-3), var(--login-primary), var(--login-primary-2));
                color: #ffffff;
                box-shadow: 0 16px 38px rgba(22, 164, 100, 0.28);
            }

            .gn-admin-login-secondary {
                border: 1px solid var(--login-border);
                background: transparent;
                color: var(--login-text);
            }

            .gn-admin-login-footer {
                margin-top: 20px;
                color: var(--login-muted);
                font-size: 12px;
                font-weight: 800;
                text-align: center;
            }

            @media (max-width: 900px) {
                .gn-admin-login-shell {
                    grid-template-columns: 1fr;
                }

                .gn-admin-login-info {
                    min-height: auto;
                    padding: 32px;
                }

                .gn-admin-login-info h1 {
                    font-size: 34px;
                }

                .gn-admin-login-card {
                    padding: 32px;
                }
            }

            @media (max-width: 560px) {
                .gn-admin-login-topbar {
                    padding: 14px 16px;
                }

                .gn-admin-login-main {
                    padding: 22px 12px;
                }

                .gn-admin-login-card,
                .gn-admin-login-info {
                    padding: 24px;
                }

                .gn-admin-login-info h1 {
                    font-size: 30px;
                }
            }
        </style>
    <?php else: ?>
        <link rel="stylesheet" href="/css/subscriber.css?v=base">
        <link rel="stylesheet" href="/css/subscriber-app.css?v=base">
        <link rel="stylesheet" href="/css/subscriber-ui.css?v=ui9pack">
    <?php endif; ?>
</head>

<?php if ($isAdminLogin): ?>
    <body>
        <div class="gn-admin-login-page">
            <header class="gn-admin-login-topbar">
                <a class="gn-admin-login-brand" href="/admin/login">
                    <span class="gn-admin-login-mark">G</span>
                    <span>
                        <span class="gn-admin-login-brand-title"><?= gn_sub_h($appName) ?></span>
                        <span class="gn-admin-login-brand-subtitle">لوحة المدير</span>
                    </span>
                </a>

                <button class="gn-admin-login-theme" type="button" onclick="
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    document.documentElement.setAttribute('data-theme', isDark ? 'light' : 'dark');
                    this.textContent = isDark ? 'Dark' : 'Light';
                ">Dark</button>
            </header>

            <main class="gn-admin-login-main">
                <?= $content ?? '' ?>
            </main>
        </div>
    </body>
<?php else: ?>
    <body class="gn-subscriber-body">
        <header class="gn-sub-header">
            <div class="gn-sub-header-inner">
                <div class="gn-sub-brand">
                    <div class="gn-sub-brand-mark">G</div>
                    <div>
                        <div class="gn-sub-brand-title"><?= gn_sub_h($appName) ?></div>
                        <div class="gn-sub-brand-subtitle">بوابة المشترك</div>
                    </div>
                </div>

                <?php if ($username !== ''): ?>
                    <a class="gn-sub-btn" href="/login?switch=1">خروج</a>
                <?php endif; ?>
            </div>
        </header>

        <main class="gn-sub-shell">
            <?= $content ?? '' ?>
        </main>

        <?php if ($username !== ''): ?>
            <nav class="gn-sub-bottom-nav" aria-label="Subscriber navigation">
                <a href="/dashboard<?= gn_sub_h($usernameQuery) ?>">
                    <span>⌂</span>
                    <span>الرئيسية</span>
                </a>

                <a href="/my/usage<?= gn_sub_h($usernameQuery) ?>">
                    <span>▤</span>
                    <span>الاستهلاك</span>
                </a>

                <a href="/my/package<?= gn_sub_h($usernameQuery) ?>">
                    <span>▣</span>
                    <span>الباقة</span>
                </a>

                <a href="/my/renew<?= gn_sub_h($usernameQuery) ?>">
                    <span>↻</span>
                    <span>تجديد</span>
                </a>

                <a href="/support<?= gn_sub_h($usernameQuery) ?>">
                    <span>☎</span>
                    <span>دعم</span>
                </a>
            </nav>
        <?php endif; ?>

        <script src="/js/subscriber-ui.js?v=ui9pack"></script>
    </body>
<?php endif; ?>
</html>