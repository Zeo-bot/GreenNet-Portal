<?php
require BASE_PATH . '/app/Views/subscriber/_helpers.php';
$subscriber = is_array($subscriber ?? null) ? $subscriber : [];
$packageData = is_array($subscriber['package'] ?? null) ? $subscriber['package'] : [];
$usage = is_array($subscriber['usage'] ?? null) ? $subscriber['usage'] : [];
$flash = is_array($flash ?? null) ? $flash : [];
$accountStatus = gn_subscriber_status($subscriber['status'] ?? '');
$displayName = trim((string) ($subscriber['display_name'] ?? '')) ?: (string) ($username ?? '');
$quota = $packageData['quota_bytes'] ?? null;
$used = $usage['total_bytes'] ?? null;
$remaining = $usage['remaining_quota_bytes'] ?? null;
$percent = $quota !== null && (int) $quota > 0 && $used !== null
    ? min(100, max(0, (int) round(((int) $used / (int) $quota) * 100)))
    : null;
?>

<div class="subscriber-app">
    <?php if (($flash['message'] ?? '') !== ''): ?>
        <div class="subscriber-notice <?= gn_subscriber_h($flash['type'] ?? 'success') ?>">
            <?= gn_subscriber_h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <section class="subscriber-hero">
        <div class="subscriber-hero-top">
            <div>
                <div class="subscriber-hello">أهلاً بك في GreenNet</div>
                <h1 class="subscriber-username"><?= gn_subscriber_h($displayName) ?></h1>
                <div class="subscriber-hero-account" dir="ltr"><?= gn_subscriber_h($username ?? '') ?></div>
            </div>
            <span class="subscriber-status-pill <?= gn_subscriber_h($accountStatus[1]) ?>"><?= gn_subscriber_h($accountStatus[0]) ?></span>
        </div>
        <div class="subscriber-hero-grid">
            <div class="subscriber-hero-mini">
                <span>الباقة الحالية</span>
                <strong><?= gn_subscriber_h($packageData['name'] ?? 'غير متاح') ?></strong>
            </div>
            <div class="subscriber-hero-mini">
                <span>الأيام المتبقية</span>
                <strong><?= ($subscriber['remaining_days'] ?? null) !== null ? gn_subscriber_h($subscriber['remaining_days']) . ' يوم' : 'غير متاح' ?></strong>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <div class="subscriber-card-heading">
            <div>
                <p class="subscriber-eyebrow">استهلاك الباقة</p>
                <h2 class="subscriber-card-title"><?= $percent !== null ? $percent . '% مستخدم' : 'الاستهلاك الحالي' ?></h2>
            </div>
            <a class="subscriber-text-link" href="/my/usage">التفاصيل</a>
        </div>
        <?php if ($percent !== null): ?>
            <div class="subscriber-progress" role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width: <?= $percent ?>%"></span>
            </div>
        <?php else: ?>
            <div class="subscriber-inline-state">بيانات الاستخدام غير متاحة حالياً.</div>
        <?php endif; ?>
        <div class="subscriber-grid subscriber-grid-usage">
            <div class="subscriber-stat"><span>التنزيل</span><strong><?= gn_subscriber_bytes($usage['download_bytes'] ?? null) ?></strong></div>
            <div class="subscriber-stat"><span>الرفع</span><strong><?= gn_subscriber_bytes($usage['upload_bytes'] ?? null) ?></strong></div>
            <div class="subscriber-stat"><span>الإجمالي</span><strong><?= gn_subscriber_bytes($used) ?></strong></div>
            <div class="subscriber-stat"><span>المتبقي</span><strong><?= gn_subscriber_bytes($remaining) ?></strong></div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">تفاصيل الاشتراك</h2>
        <div class="subscriber-list">
            <div class="subscriber-row"><span class="subscriber-row-label">السرعة</span><strong class="subscriber-row-value"><?= gn_subscriber_h($packageData['speed'] ?? 'غير متاح') ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">تاريخ الانتهاء</span><strong class="subscriber-row-value"><?= gn_subscriber_date($subscriber['expiration_date'] ?? null) ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">الجلسة الحالية</span><strong class="subscriber-row-value"><?= !empty($subscriber['online']) ? 'متصل الآن' : 'غير متصل' ?></strong></div>
        </div>
    </section>

    <section class="subscriber-quick-grid">
        <a href="/my/package"><span>الباقة والاستهلاك</span><small>التفاصيل الكاملة</small></a>
        <a href="/my/renew"><span>طلب تجديد</span><small>تابع حالة طلبك</small></a>
        <a href="/my/notifications"><span>الإشعارات</span><small>آخر التنبيهات</small></a>
        <a href="/support"><span>الدعم</span><small>تواصل معنا</small></a>
    </section>
</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>
