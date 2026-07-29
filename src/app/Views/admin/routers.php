<?php
$routers = is_array($routers ?? null) ? $routers : [];
$editing = is_array($editing ?? null) ? $editing : [];
$packages = is_array($packages ?? null) ? $packages : [];
$mappings = is_array($mappings ?? null) ? $mappings : [];
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabel = static fn (array $router): string => empty($router['enabled'])
    ? 'معطّل'
    : match ((string) ($router['last_status'] ?? 'unknown')) {
        'available' => 'متاح',
        'unreachable' => 'غير متاح',
        default => 'لم يُفحص',
    };
?>
<div class="admin-page-header">
    <div><h1 class="admin-page-title">إدارة الراوترات</h1><p class="admin-page-description">سجل مركزي للراوترات التي تستهدفها عمليات المشتركين.</p></div>
    <a class="admin-mini-btn" href="/admin/router-onboarding">إعداد موجّه جديد</a>
</div>
<?php if (($message ?? '') !== ''): ?>
    <div class="notice" style="<?= ($message_type ?? '') === 'warning' ? 'background:#fff7ed;color:#9a3412' : 'background:#ecfdf5;color:#166534' ?>"><?= $h($message) ?></div>
<?php endif; ?>
<div class="admin-two-columns">
    <section class="admin-section-card">
        <h2 class="admin-section-title">الراوترات المسجلة</h2>
        <?php if ($routers === []): ?><div class="notice">لا توجد سجلات بعد. العملاء الحاليون يستمرون باستخدام إعداد الراوتر القديم.</div><?php endif; ?>
        <div class="admin-payment-list">
            <?php foreach ($routers as $router): ?>
                <div class="admin-payment-item">
                    <div>
                        <div class="admin-payment-user"><?= $h($router['name'] ?? '') ?> <?php if (!empty($router['is_default'])): ?><span class="admin-badge admin-badge-success">افتراضي</span><?php endif; ?></div>
                        <div style="direction:ltr;text-align:right;color:#6b7280;font-size:12px"><?= $h($router['host'] ?? '') ?>:<?= (int) ($router['api_port'] ?? 8728) ?></div>
                        <div style="color:#6b7280;font-size:12px"><?= $h($statusLabel($router)) ?> · <?= (int) ($router['customer_count'] ?? 0) ?> مشترك <?php if (($router['routeros_version'] ?? '') !== ''): ?>· RouterOS <?= $h($router['routeros_version']) ?><?php endif; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:7px">
                            <?php foreach (($router['selected_roles_list'] ?? []) as $role): ?>
                                <span class="admin-badge"><?= $h(match ($role) { 'user-manager' => 'User Manager', 'native-hotspot' => 'Hotspot', 'native-pppoe' => 'PPPoE', 'container-host' => 'Container', default => 'إدارة' }) ?></span>
                            <?php endforeach; ?>
                            <?php $ready = (string) ($router['readiness']['status'] ?? 'setup_pending') === 'ready'; ?>
                            <span class="admin-badge <?= $ready ? 'admin-badge-success' : 'admin-badge-warning' ?>"><?= $ready ? 'جاهز' : 'يحتاج تجهيز' ?></span>
                        </div>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <a class="admin-mini-btn" href="/admin/routers?id=<?= (int) ($router['id'] ?? 0) ?>">إدارة</a>
                        <a class="admin-mini-btn" href="/admin/router-onboarding?id=<?= (int) ($router['id'] ?? 0) ?>">تجهيز</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="admin-section-card">
        <h2 class="admin-section-title"><?= $editing ? 'تعديل الراوتر' : 'إضافة راوتر' ?></h2>
        <form method="post" action="/admin/routers/save">
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <div class="form-group"><label>الاسم</label><input name="name" value="<?= $h($editing['name'] ?? '') ?>" required></div>
            <div class="form-group"><label>العنوان / Host</label><input name="host" value="<?= $h($editing['host'] ?? '') ?>" dir="ltr" required></div>
            <div class="form-group"><label>منفذ API</label><input type="number" name="api_port" value="<?= (int) ($editing['api_port'] ?? 8728) ?>" min="1" max="65535"></div>
            <div class="form-group"><label>اسم مستخدم API</label><input name="username" value="<?= $h($editing['username'] ?? '') ?>" dir="ltr"></div>
            <div class="form-group"><label>كلمة مرور API</label><input type="password" name="password" autocomplete="new-password" placeholder="<?= $editing ? 'اتركها فارغة للاحتفاظ بالحالية' : '' ?>"></div>
            <div class="form-group"><label>الموقع الوصفي</label><input name="location" value="<?= $h($editing['location'] ?? '') ?>"></div>
            <div class="form-group"><label>ملاحظات</label><textarea name="notes"><?= $h($editing['notes'] ?? '') ?></textarea></div>
            <label><input type="checkbox" name="enabled" value="1" <?= !$editing || !empty($editing['enabled']) ? 'checked' : '' ?>> مفعّل</label>
            <label style="margin-inline-start:16px"><input type="checkbox" name="is_default" value="1" <?= !empty($editing['is_default']) ? 'checked' : '' ?>> افتراضي</label>
            <input type="hidden" name="access_mode" value="<?= $h($editing['access_mode'] ?? 'hybrid') ?>"><input type="hidden" name="auth_backend" value="<?= $h($editing['auth_backend'] ?? 'user-manager') ?>">
            <div style="margin-top:16px"><button class="gn-btn gn-btn-primary" type="submit">حفظ</button></div>
        </form>
        <?php if ($editing): ?><form method="post" action="/admin/routers/test" style="margin-top:12px"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><button class="gn-btn gn-btn-secondary" type="submit">فحص الاتصال الآن</button></form><?php endif; ?>
    </section>
</div>
<?php if ($editing): ?>
<section class="admin-section-card">
    <h2 class="admin-section-title">ملفات الباقات على هذا الراوتر</h2>
    <p class="admin-section-subtitle">عند غياب ربط خاص، يُستخدم اسم الملف العام الموجود في الباقة.</p>
    <form method="post" action="/admin/routers/package-mapping" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="router_id" value="<?= (int) $editing['id'] ?>">
        <div class="form-group"><label>الباقة</label><select name="package_id"><?php foreach ($packages as $package): ?><option value="<?= (int) ($package['id'] ?? 0) ?>"><?= $h($package['name'] ?? '') ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Backend</label><select name="backend"><option value="user-manager">User Manager</option><option value="native-hotspot">Native Hotspot</option><option value="native-pppoe">Native PPPoE</option></select></div>
        <div class="form-group"><label>اسم Profile</label><input name="profile_name" dir="ltr" required></div>
        <div class="form-group"><label>معرّف Profile (اختياري)</label><input name="profile_id" dir="ltr"></div>
        <button class="gn-btn gn-btn-primary" type="submit">حفظ الربط</button>
    </form>
    <?php foreach ($mappings as $mapping): ?><div class="admin-payment-item"><strong><?= $h($mapping['package_name'] ?? '') ?> · <?= $h($mapping['backend'] ?? '') ?></strong><span dir="ltr"><?= $h($mapping['profile_name'] ?? '') ?></span></div><?php endforeach; ?>
</section>
<?php endif; ?>
