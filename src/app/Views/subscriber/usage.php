<?php
require BASE_PATH . '/app/Views/subscriber/_helpers.php';
$subscriber = is_array($subscriber ?? null) ? $subscriber : [];
$packageData = is_array($subscriber['package'] ?? null) ? $subscriber['package'] : [];
$usage = is_array($subscriber['usage'] ?? null) ? $subscriber['usage'] : [];
$quota = $packageData['quota_bytes'] ?? null;
$used = $usage['total_bytes'] ?? null;
$percent = $quota !== null && (int) $quota > 0 && $used !== null
    ? min(100, max(0, (int) round(((int) $used / (int) $quota) * 100)))
    : null;
?>

<div class="subscriber-app">
    <section class="subscriber-hero subscriber-hero-compact">
        <div class="subscriber-hero-top">
            <div><div class="subscriber-hello">تفاصيل الاستخدام</div><h1 class="subscriber-username"><?= gn_subscriber_h($packageData['name'] ?? 'الباقة الحالية') ?></h1></div>
            <span class="subscriber-status-pill"><?= !empty($subscriber['online']) ? 'متصل الآن' : 'غير متصل' ?></span>
        </div>
    </section>

    <section class="subscriber-card">
        <div class="subscriber-card-heading">
            <div><p class="subscriber-eyebrow">المستخدم من الباقة</p><h2 class="subscriber-card-title"><?= $percent !== null ? $percent . '%' : 'غير متاح' ?></h2></div>
            <strong class="subscriber-quota-total"><?= gn_subscriber_bytes($quota) ?></strong>
        </div>
        <?php if ($percent !== null): ?>
            <div class="subscriber-progress subscriber-progress-large"><span style="width: <?= $percent ?>%"></span></div>
            <p class="subscriber-muted">متبقي <?= gn_subscriber_bytes($usage['remaining_quota_bytes'] ?? null) ?> من إجمالي الباقة.</p>
        <?php else: ?>
            <div class="subscriber-inline-state">بيانات الاستهلاك غير متاحة حالياً، حاول مرة أخرى لاحقاً.</div>
        <?php endif; ?>
        <div class="subscriber-grid subscriber-grid-usage">
            <div class="subscriber-stat"><span>التنزيل</span><strong><?= gn_subscriber_bytes($usage['download_bytes'] ?? null) ?></strong></div>
            <div class="subscriber-stat"><span>الرفع</span><strong><?= gn_subscriber_bytes($usage['upload_bytes'] ?? null) ?></strong></div>
            <div class="subscriber-stat subscriber-stat-wide"><span>إجمالي الاستخدام</span><strong><?= gn_subscriber_bytes($used) ?></strong></div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">معلومات الباقة</h2>
        <div class="subscriber-list">
            <div class="subscriber-row"><span class="subscriber-row-label">السرعة</span><strong class="subscriber-row-value"><?= gn_subscriber_h($packageData['speed'] ?? 'غير متاح') ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">تاريخ البداية</span><strong class="subscriber-row-value"><?= gn_subscriber_date($subscriber['start_date'] ?? null) ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">تاريخ الانتهاء</span><strong class="subscriber-row-value"><?= gn_subscriber_date($subscriber['expiration_date'] ?? null) ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">الأيام المتبقية</span><strong class="subscriber-row-value"><?= ($subscriber['remaining_days'] ?? null) !== null ? gn_subscriber_h($subscriber['remaining_days']) . ' يوم' : 'غير متاح' ?></strong></div>
        </div>
    </section>

    <div class="subscriber-actions"><a class="subscriber-btn primary" href="/my/renew">طلب تجديد</a><a class="subscriber-btn secondary" href="/my/package">تفاصيل الباقة</a></div>
</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>
