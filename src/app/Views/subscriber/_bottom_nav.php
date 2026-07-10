<?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    $previewQuery = '';

    if (($_SESSION['admin_logged_in'] ?? false) === true && isset($_GET['username']) && trim((string) $_GET['username']) !== '') {
        $previewQuery = '?username=' . urlencode(trim((string) $_GET['username']));
    }

    $active = function (string $path) use ($currentPath): string {
        return $currentPath === $path ? 'active' : '';
    };
?>

<nav class="subscriber-bottom-nav">
    <a class="<?= $active('/dashboard') ?>" href="/dashboard<?= htmlspecialchars($previewQuery) ?>">
        <span>🏠</span>
        الرئيسية
    </a>

    <a class="<?= $active('/my/usage') ?>" href="/my/usage<?= htmlspecialchars($previewQuery) ?>">
        <span>📊</span>
        الاستهلاك
    </a>

    <a class="<?= $active('/my/package') ?>" href="/my/package<?= htmlspecialchars($previewQuery) ?>">
        <span>📦</span>
        الباقة
    </a>

    <a class="<?= $active('/my/renew') ?>" href="/my/renew<?= htmlspecialchars($previewQuery) ?>">
        <span>🔄</span>
        التجديد
    </a>

    <a class="<?= $active('/support') ?>" href="/support<?= htmlspecialchars($previewQuery) ?>">
        <span>☎️</span>
        الدعم
    </a>
</nav>