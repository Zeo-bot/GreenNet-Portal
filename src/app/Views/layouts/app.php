<?php
    $safeThemeColor = $theme_color ?? '#16a34a';

    if (!is_string($safeThemeColor) || preg_match('/^#[0-9a-fA-F]{6}$/', $safeThemeColor) !== 1) {
        $safeThemeColor = '#16a34a';
    }

    $safeIcon = '/img/greennet-icon.svg';

    if (!empty($app_icon_path) && is_string($app_icon_path) && str_starts_with($app_icon_path, '/media/')) {
        $safeIcon = $app_icon_path;
    } elseif (!empty($site_logo_path) && is_string($site_logo_path) && str_starts_with($site_logo_path, '/media/')) {
        $safeIcon = $site_logo_path;
    }

    $safeBackgroundImage = '';

    if (
        !empty($subscriber_bg_image_path)
        && is_string($subscriber_bg_image_path)
        && str_starts_with($subscriber_bg_image_path, '/media/')
    ) {
        $safeBackgroundImage = $subscriber_bg_image_path;
    }

    $pageTitle = $title ?? ($app_title ?? 'GreenNet Portal');
    $appName = $app_name ?? 'GreenNet';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title><?= htmlspecialchars((string) $pageTitle) ?></title>

    <meta name="application-name" content="<?= htmlspecialchars((string) $appName) ?>">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars((string) $appName) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="<?= htmlspecialchars($safeThemeColor) ?>">
    <meta name="msapplication-TileColor" content="<?= htmlspecialchars($safeThemeColor) ?>">

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="<?= htmlspecialchars($safeIcon) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($safeIcon) ?>">

    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/subscriber.css">
    <link rel="stylesheet" href="/css/subscriber-app.css">

    <style>
        :root {
            --greennet-theme: <?= htmlspecialchars($safeThemeColor) ?>;
            --subscriber-bg-image: <?= $safeBackgroundImage !== '' ? "url('" . htmlspecialchars($safeBackgroundImage) . "')" : 'none' ?>;
        }

        .brand-mark,
        .btn-primary,
        .progress-bar,
        .subscriber-bottom-nav a.active,
        .subscriber-btn.primary,
        .subscriber-status-pill {
            background: var(--greennet-theme) !important;
        }

        .btn-primary,
        .subscriber-btn.primary {
            border-color: var(--greennet-theme) !important;
        }

        a {
            color: var(--greennet-theme);
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline-color: var(--greennet-theme);
        }

        .brand-logo {
            width: 54px;
            height: 54px;
            object-fit: contain;
            border-radius: 14px;
            display: block;
        }

        .brand-mark.has-logo {
            background: #ffffff !important;
            border: 1px solid #e5e7eb;
            padding: 4px;
        }

        body.subscriber-layout {
            min-height: 100vh;
            background:
                linear-gradient(rgba(248,250,252,0.92), rgba(248,250,252,0.92)),
                var(--subscriber-bg-image),
                #f8fafc;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        @media (display-mode: standalone) {
            body.subscriber-layout {
                padding-top: env(safe-area-inset-top);
                padding-bottom: env(safe-area-inset-bottom);
            }
        }
    </style>
</head>

<body class="subscriber-layout">

    <?= $content ?? '' ?>

    <script src="/js/pwa.js" defer></script>

</body>
</html>