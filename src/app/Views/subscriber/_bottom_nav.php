<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$previewQuery = '';
if (($_SESSION['admin_logged_in'] ?? false) === true && trim((string) ($_GET['username'] ?? '')) !== '') {
    $previewQuery = '?username=' . rawurlencode(trim((string) $_GET['username']));
}
$isActive = static fn (array $paths): string => in_array($currentPath, $paths, true) ? 'active' : '';
?>

<nav class="subscriber-bottom-nav" aria-label="التنقل الرئيسي">
    <a class="<?= $isActive(['/dashboard']) ?>" href="/dashboard<?= gn_subscriber_h($previewQuery) ?>"><span aria-hidden="true">⌂</span><small>الرئيسية</small></a>
    <a class="<?= $isActive(['/my/package', '/my/usage']) ?>" href="/my/package<?= gn_subscriber_h($previewQuery) ?>"><span aria-hidden="true">◫</span><small>الباقة</small></a>
    <a class="<?= $isActive(['/my/renew']) ?>" href="/my/renew<?= gn_subscriber_h($previewQuery) ?>"><span aria-hidden="true">↻</span><small>التجديد</small></a>
    <a class="<?= $isActive(['/my/notifications']) ?>" href="/my/notifications<?= gn_subscriber_h($previewQuery) ?>"><span aria-hidden="true">●</span><small>الإشعارات</small></a>
    <a class="<?= $isActive(['/support']) ?>" href="/support<?= gn_subscriber_h($previewQuery) ?>"><span aria-hidden="true">☎</span><small>الدعم</small></a>
    <a class="<?= $isActive(['/my/account']) ?>" href="/my/account<?= gn_subscriber_h($previewQuery) ?>"><span aria-hidden="true">○</span><small>حسابي</small></a>
</nav>
