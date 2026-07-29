<?php
require BASE_PATH . '/app/Views/subscriber/_helpers.php';
$subscriber = is_array($subscriber ?? null) ? $subscriber : [];
$packageData = is_array($subscriber['package'] ?? null) ? $subscriber['package'] : [];
$status = gn_subscriber_status($subscriber['status'] ?? '');
?>

<div class="subscriber-app">
    <section class="subscriber-hero subscriber-hero-compact">
        <div class="subscriber-hero-top">
            <div><div class="subscriber-hello">حساب المشترك</div><h1 class="subscriber-username"><?= gn_subscriber_h($subscriber['display_name'] ?? $username ?? '') ?></h1></div>
            <span class="subscriber-status-pill <?= gn_subscriber_h($status[1]) ?>"><?= gn_subscriber_h($status[0]) ?></span>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">بيانات الحساب</h2>
        <div class="subscriber-list">
            <div class="subscriber-row"><span class="subscriber-row-label">اسم المستخدم</span><strong class="subscriber-row-value" dir="ltr"><?= gn_subscriber_h($username ?? '') ?></strong></div>
            <?php if (!empty($subscriber['phone'])): ?><div class="subscriber-row"><span class="subscriber-row-label">رقم الهاتف</span><strong class="subscriber-row-value" dir="ltr"><?= gn_subscriber_h($subscriber['phone']) ?></strong></div><?php endif; ?>
            <div class="subscriber-row"><span class="subscriber-row-label">الباقة الحالية</span><strong class="subscriber-row-value"><?= gn_subscriber_h($packageData['name'] ?? 'غير متاح') ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">تاريخ الانتهاء</span><strong class="subscriber-row-value"><?= gn_subscriber_date($subscriber['expiration_date'] ?? null) ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">حالة الجلسة</span><strong class="subscriber-row-value"><?= !empty($subscriber['online']) ? 'متصل الآن' : 'غير متصل' ?></strong></div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">المساعدة والحساب</h2>
        <p class="subscriber-muted">لتغيير كلمة المرور أو تحديث بيانات الحساب، تواصل مع الدعم.</p>
        <div class="subscriber-actions">
            <a class="subscriber-btn secondary" href="/support">الدعم والمساعدة</a>
            <form method="post" action="/logout">
                <input type="hidden" name="_csrf" value="<?= gn_subscriber_h($csrf_token ?? '') ?>">
                <button class="subscriber-btn danger" type="submit">تسجيل الخروج</button>
            </form>
        </div>
    </section>
</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>
