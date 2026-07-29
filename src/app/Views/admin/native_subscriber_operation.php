<?php
$customer = is_array($customer ?? null) ? $customer : [];
$router = is_array($router ?? null) ? $router : [];
$package = is_array($package ?? null) ? $package : [];
$result = is_array($result ?? null) ? $result : [];
$username = (string) ($customer['username'] ?? '');
$backend = (string) ($customer['service_backend'] ?? '');
$labels = [
    'create' => 'إنشاء الحساب',
    'package' => 'تطبيق الباقة',
    'password' => 'تغيير كلمة المرور',
    'disable' => 'تعليق الخدمة',
    'enable' => 'إعادة التفعيل',
    'delete' => 'حذف حساب RouterOS',
];
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="admin-page-header">
    <div><h1 class="admin-page-title"><?= $h($labels[$action] ?? 'عملية الحساب') ?></h1><p class="admin-page-description">تنفيذ العملية على الحساب عبر الراوتر المعيّن للمشترك.</p></div>
    <a class="admin-mini-btn" href="/admin/customers/profile?username=<?= rawurlencode($username) ?>">العودة للمشترك</a>
</div>
<?php if (($message ?? '') !== ''): ?><div class="notice" style="<?= ($message_type ?? '') === 'warning' ? 'background:#fee2e2;color:#991b1b' : 'background:#ecfdf5;color:#166534' ?>"><?= $h($message) ?></div><?php endif; ?>
<?php if (!$customer): ?>
    <div class="notice">لم يتم العثور على المشترك.</div>
<?php else: ?>
<div class="admin-stats-grid">
    <div class="admin-stat-card"><div class="admin-stat-label">المشترك</div><div class="admin-stat-value" style="font-size:17px" dir="ltr"><?= $h($username) ?></div></div>
    <div class="admin-stat-card"><div class="admin-stat-label">Backend</div><div class="admin-stat-value" style="font-size:17px"><?= $h($backend) ?></div></div>
    <div class="admin-stat-card"><div class="admin-stat-label">الراوتر</div><div class="admin-stat-value" style="font-size:17px"><?= $h($router['name'] ?? '') ?></div></div>
    <div class="admin-stat-card"><div class="admin-stat-label">الباقة</div><div class="admin-stat-value" style="font-size:17px"><?= $h($package['name'] ?? '-') ?></div></div>
</div>
<section class="admin-section-card">
    <h2 class="admin-section-title">1. فحص الجاهزية</h2>
    <form method="post" action="/admin/native-subscriber/preview">
        <input type="hidden" name="username" value="<?= $h($username) ?>"><input type="hidden" name="action" value="<?= $h($action) ?>">
        <button class="gn-btn gn-btn-primary" type="submit">متابعة <?= $h($labels[$action] ?? '') ?></button>
    </form>
</section>
<?php if ($result): ?>
<section class="admin-section-card">
    <h2 class="admin-section-title">تفاصيل العملية</h2>
    <?php if (!empty($result['error'])): ?><div class="notice" style="background:#fee2e2;color:#991b1b"><?= $h($result['error']) ?></div><?php else: ?>
        <div class="admin-checklist">
            <div class="admin-check-item"><div><strong>الراوتر:</strong> <?= $h($result['router_name'] ?? '') ?></div></div>
            <div class="admin-check-item"><div><strong>Backend:</strong> <?= $h($result['backend'] ?? '') ?></div></div>
            <div class="admin-check-item"><div><strong>Command:</strong> <span dir="ltr"><?= $h($result['command'] ?? '') ?></span></div></div>
            <div class="admin-check-item"><div><strong>Exact ID:</strong> <span dir="ltr"><?= $h($result['record_id'] ?? 'new record') ?></span></div></div>
            <?php if (($result['profile_name'] ?? '') !== ''): ?><div class="admin-check-item"><div><strong>Profile:</strong> <span dir="ltr"><?= $h($result['profile_name']) ?></span></div></div><?php endif; ?>
        </div>
        <?php if (empty($result['executed'])): ?>
        <form method="post" action="/admin/native-subscriber/execute" onsubmit="return confirm('سيتم تنفيذ تعديل حقيقي على الراوتر. متابعة؟');">
            <input type="hidden" name="username" value="<?= $h($username) ?>"><input type="hidden" name="action" value="<?= $h($action) ?>">
            <?php if (in_array($action, ['create', 'password'], true)): ?><div class="form-group"><label>كلمة المرور (تُستخدم في الطلب الحالي فقط)</label><input type="password" name="password" autocomplete="new-password" required></div><?php endif; ?>
            <div class="form-group"><label>اكتب <?= strtoupper($h($action)) ?> للتأكيد</label><input name="confirm" dir="ltr" required></div>
            <button class="gn-btn gn-btn-danger" type="submit">تنفيذ</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($result['execute_error'])): ?><div class="notice" style="background:#fee2e2;color:#991b1b"><?= $h($result['execute_error']) ?></div><?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
