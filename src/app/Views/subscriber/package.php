<?php
require BASE_PATH . '/app/Views/subscriber/_helpers.php';
$subscriber = is_array($subscriber ?? null) ? $subscriber : [];
$packageData = is_array($subscriber['package'] ?? null) ? $subscriber['package'] : [];
$latestPayment = is_array($subscriber['latest_payment'] ?? null) ? $subscriber['latest_payment'] : [];
?>

<div class="subscriber-app">
    <section class="subscriber-hero subscriber-hero-compact">
        <div class="subscriber-hello">باقتك الحالية</div>
        <h1 class="subscriber-username"><?= gn_subscriber_h($packageData['name'] ?? 'لا توجد باقة حالية') ?></h1>
        <div class="subscriber-hero-grid">
            <div class="subscriber-hero-mini"><span>السرعة</span><strong><?= gn_subscriber_h($packageData['speed'] ?? 'غير متاح') ?></strong></div>
            <div class="subscriber-hero-mini"><span>السعة</span><strong><?= gn_subscriber_bytes($packageData['quota_bytes'] ?? null) ?></strong></div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">تفاصيل الباقة</h2>
        <?php if ($packageData === [] || empty($packageData['id'])): ?>
            <div class="subscriber-empty">لا توجد باقة مرتبطة بحسابك حالياً. تواصل مع الدعم لتحديث بيانات الاشتراك.</div>
        <?php else: ?>
            <div class="subscriber-list">
                <div class="subscriber-row"><span class="subscriber-row-label">مدة الباقة</span><strong class="subscriber-row-value"><?= isset($packageData['duration_days']) && (int) $packageData['duration_days'] > 0 ? (int) $packageData['duration_days'] . ' يوم' : 'غير متاح' ?></strong></div>
                <div class="subscriber-row"><span class="subscriber-row-label">بداية الاشتراك</span><strong class="subscriber-row-value"><?= gn_subscriber_date($subscriber['start_date'] ?? null) ?></strong></div>
                <div class="subscriber-row"><span class="subscriber-row-label">نهاية الاشتراك</span><strong class="subscriber-row-value"><?= gn_subscriber_date($subscriber['expiration_date'] ?? null) ?></strong></div>
                <div class="subscriber-row"><span class="subscriber-row-label">الأيام المتبقية</span><strong class="subscriber-row-value"><?= ($subscriber['remaining_days'] ?? null) !== null ? gn_subscriber_h($subscriber['remaining_days']) . ' يوم' : 'غير متاح' ?></strong></div>
                <?php if (isset($packageData['price']) && (float) $packageData['price'] > 0): ?>
                    <div class="subscriber-row"><span class="subscriber-row-label">قيمة التجديد</span><strong class="subscriber-row-value"><?= gn_subscriber_h($packageData['price']) ?> <?= gn_subscriber_h($packageData['currency'] ?? '') ?></strong></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">آخر تجديد</h2>
        <?php if ($latestPayment === [] || empty($latestPayment['id'])): ?>
            <div class="subscriber-empty">لا يوجد تجديد مسجل حتى الآن.</div>
        <?php else: ?>
            <div class="subscriber-list">
                <div class="subscriber-row"><span class="subscriber-row-label">تاريخ الدفع</span><strong class="subscriber-row-value"><?= gn_subscriber_date($latestPayment['paid_at'] ?? null) ?></strong></div>
                <div class="subscriber-row"><span class="subscriber-row-label">بداية الباقة</span><strong class="subscriber-row-value"><?= gn_subscriber_date($latestPayment['starts_at'] ?? null) ?></strong></div>
                <div class="subscriber-row"><span class="subscriber-row-label">نهاية الباقة</span><strong class="subscriber-row-value"><?= gn_subscriber_date($latestPayment['expires_at'] ?? null) ?></strong></div>
            </div>
        <?php endif; ?>
    </section>

    <div class="subscriber-actions"><a class="subscriber-btn primary" href="/my/usage">عرض الاستهلاك</a><a class="subscriber-btn secondary" href="/my/renew">طلب تجديد</a></div>
</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>
