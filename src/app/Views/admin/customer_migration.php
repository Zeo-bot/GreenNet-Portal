<?php
$customer = is_array($customer ?? null) ? $customer : [];
$targets = is_array($targets ?? null) ? $targets : [];
$readiness = is_array($readiness ?? null) ? $readiness : null;
$migration = is_array($migration ?? null) ? $migration : [];
$username = (string) ($customer['username'] ?? '');
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$backendLabel = static fn (string $backend): string => match ($backend) {
    'native-hotspot' => 'Hotspot',
    'native-pppoe' => 'PPPoE',
    default => 'User Manager',
};
?>
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">نقل إلى راوتر آخر</h1>
        <p class="admin-page-description">نقل الحساب إلى الهدف أولاً، ثم قطع تعيين GreenNet، ثم معالجة الحساب القديم باختيارك.</p>
    </div>
    <a class="admin-mini-btn" href="/admin/customers/profile?username=<?= rawurlencode($username) ?>">إدارة المشترك</a>
</div>
<?php if (($message ?? '') !== ''): ?><div class="notice"><?= $h($message) ?></div><?php endif; ?>

<?php if ($customer === []): ?>
    <div class="notice">المشترك غير موجود.</div>
<?php elseif ($targets === []): ?>
    <div class="notice">لا يوجد راوتر هدف آخر جاهز بنظام خدمة قابل للاختيار.</div>
<?php else: ?>
<section class="admin-section-card">
    <h2 class="admin-section-title">اختيار الهدف</h2>
    <form method="get" action="/admin/customers/migrate">
        <input type="hidden" name="username" value="<?= $h($username) ?>">
        <div class="admin-two-columns">
            <div class="form-group"><label>الراوتر الهدف</label>
                <select name="target_router_id" required>
                    <option value="">اختر الراوتر</option>
                    <?php foreach ($targets as $router): ?>
                        <option value="<?= (int) $router['id'] ?>" <?= (int) $target_router_id === (int) $router['id'] ? 'selected' : '' ?>><?= $h($router['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>نظام الخدمة الهدف</label>
                <select name="target_backend" required>
                    <option value="">اختر النظام</option>
                    <?php foreach (['user-manager', 'native-hotspot', 'native-pppoe'] as $backend): ?>
                        <option value="<?= $h($backend) ?>" <?= $target_backend === $backend ? 'selected' : '' ?>><?= $h($backendLabel($backend)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="gn-btn gn-btn-primary" type="submit">فحص جاهزية النقل</button>
    </form>
</section>

<?php if ($readiness !== null): ?>
    <?php $state = (string) ($readiness['state'] ?? 'blocked'); ?>
    <section class="admin-section-card">
        <h2 class="admin-section-title">الجاهزية:
            <span class="admin-badge <?= $state === 'ready' ? 'admin-badge-success' : ($state === 'warning' ? 'admin-badge-warning' : 'admin-badge-danger') ?>">
                <?= $h(strtoupper($state)) ?>
            </span>
        </h2>
        <div class="admin-two-columns">
            <div>
                <h3>المصدر</h3>
                <p><?= $h($readiness['source_router']['name'] ?? 'غير معيّن') ?> · <?= $h($backendLabel((string) ($readiness['source_backend'] ?? ''))) ?></p>
                <p>الحساب: <?= !empty($readiness['source_record']['found']) ? 'موجود' : 'غير ظاهر' ?> · الجلسات: <?= count($readiness['source_sessions'] ?? []) ?></p>
            </div>
            <div>
                <h3>الهدف</h3>
                <p><?= $h($readiness['target_router']['name'] ?? '-') ?> · <?= $h($backendLabel((string) ($readiness['target_backend'] ?? ''))) ?></p>
                <p>Profile: <span dir="ltr"><?= $h($readiness['target_profile_name'] ?? 'مفقود') ?></span> · الحساب: <?= !empty($readiness['target_record']['found']) ? 'موجود' : 'غير موجود' ?></p>
            </div>
        </div>
        <?php foreach (($readiness['blocks'] ?? []) as $block): ?><div class="notice" style="background:#fee2e2;color:#991b1b"><?= $h($block) ?></div><?php endforeach; ?>
        <?php foreach (($readiness['warnings'] ?? []) as $warning): ?><div class="notice" style="background:#fff7ed;color:#9a3412"><?= $h($warning) ?></div><?php endforeach; ?>
        <div class="admin-stats-grid">
            <div class="admin-stat-card"><div class="admin-stat-label">الاستهلاك المحفوظ</div><div class="admin-stat-value" style="font-size:17px"><?= number_format(((int) ($readiness['usage']['used_bytes'] ?? 0)) / 1073741824, 2) ?> GB</div></div>
            <div class="admin-stat-card"><div class="admin-stat-label">المتبقي المحفوظ</div><div class="admin-stat-value" style="font-size:17px"><?= number_format(((int) ($readiness['usage']['remaining_bytes'] ?? 0)) / 1073741824, 2) ?> GB</div></div>
        </div>
        <?php if ($state !== 'blocked'): ?>
        <form method="post" action="/admin/customers/migrate" style="margin-top:18px">
            <input type="hidden" name="username" value="<?= $h($username) ?>">
            <input type="hidden" name="target_router_id" value="<?= (int) ($readiness['target_router']['id'] ?? 0) ?>">
            <input type="hidden" name="target_backend" value="<?= $h($readiness['target_backend'] ?? '') ?>">
            <div class="form-group"><label>كلمة مرور الحساب الهدف</label><input type="password" name="password" autocomplete="new-password" <?= empty($readiness['resume_migration_id']) ? 'required' : '' ?>><small>لا تُحفظ في GreenNet. تُطلب لأن كلمة مرور RouterOS الحالية غير قابلة للاسترجاع الآمن.</small></div>
            <div class="form-group"><label>استمرارية الاستهلاك</label>
                <select name="usage_decision" required>
                    <option value="preserve_recorded_usage">حفظ الاستهلاك المسجل ومتابعة دورة GreenNet الحالية</option>
                    <option value="accept_backend_limitations">أوافق على اختلاف عدادات النظام الهدف مع بقاء التاريخ محفوظاً</option>
                </select>
            </div>
            <div class="form-group"><label>الحساب القديم بعد نجاح النقل</label>
                <select name="cleanup_action">
                    <option value="leave">تركه مؤقتاً</option>
                    <option value="disable">تعطيله — موصى به</option>
                    <option value="disconnect">فصل الاتصال القديم</option>
                    <option value="delete">حذفه من الراوتر المصدر</option>
                </select>
            </div>
            <button class="gn-btn gn-btn-primary" type="submit">نقل المشترك</button>
        </form>
        <?php endif; ?>
        <?php if (in_array($state, ['blocked', 'warning'], true) && in_array('ربط الباقة بالـ Profile على الراوتر الهدف مفقود.', $readiness['blocks'] ?? [], true)): ?>
            <a class="admin-mini-btn" href="/admin/routers?id=<?= (int) ($readiness['target_router']['id'] ?? 0) ?>">تجهيز ربط الباقة</a>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php endif; ?>

<?php if ($migration !== []): ?>
<section class="admin-section-card">
    <h2 class="admin-section-title">آخر عملية نقل</h2>
    <p><?= $h($migration['source_router_name'] ?? '-') ?> ← <?= $h($migration['target_router_name'] ?? '-') ?> · الحالة: <?= $h($migration['status'] ?? '-') ?></p>
    <?php if (($migration['failure_reason'] ?? '') !== ''): ?><div class="notice"><?= $h($migration['failure_reason']) ?></div><?php endif; ?>
</section>
<?php endif; ?>
