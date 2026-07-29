<?php
require BASE_PATH . '/app/Views/subscriber/_helpers.php';
$subscriber = is_array($subscriber ?? null) ? $subscriber : [];
$packageData = is_array($subscriber['package'] ?? null) ? $subscriber['package'] : [];
$requests = is_array($pending_requests ?? null) ? $pending_requests : [];
$flash = is_array($flash ?? null) ? $flash : [];
$pending = array_values(array_filter($requests, static fn (array $request): bool => strtolower((string) ($request['status'] ?? 'pending')) === 'pending'));
$phone = trim((string) ($subscriber['phone'] ?? ''));
$packageName = trim((string) ($packageData['name'] ?? '')) ?: 'الباقة الحالية';
$whatsappNumber = preg_replace('/\D+/', '', (string) ($support_whatsapp ?? '')) ?? '';
$whatsappUrl = $whatsappNumber !== ''
    ? 'https://wa.me/' . $whatsappNumber . '?text=' . rawurlencode('مرحباً، أحتاج مساعدة في تجديد اشتراك ' . (string) ($username ?? '') . '.')
    : '';
?>

<div class="subscriber-app">
    <?php if (($flash['message'] ?? '') !== ''): ?>
        <div class="subscriber-notice <?= gn_subscriber_h($flash['type'] ?? 'success') ?>"><?= gn_subscriber_h($flash['message']) ?></div>
    <?php endif; ?>

    <section class="subscriber-hero subscriber-hero-compact">
        <div class="subscriber-hero-top">
            <div><div class="subscriber-hello">تجديد الاشتراك</div><h1 class="subscriber-username"><?= gn_subscriber_h($packageName) ?></h1></div>
            <?php if ($pending !== []): ?><span class="subscriber-status-pill warning">طلب قيد المراجعة</span><?php endif; ?>
        </div>
        <div class="subscriber-hero-grid">
            <div class="subscriber-hero-mini"><span>تاريخ الانتهاء</span><strong><?= gn_subscriber_date($subscriber['expiration_date'] ?? null) ?></strong></div>
            <div class="subscriber-hero-mini"><span>طلبات التجديد</span><strong><?= count($requests) ?></strong></div>
        </div>
    </section>

    <?php if ($pending !== []): ?>
        <section class="subscriber-notice warning">
            استلمنا طلب التجديد الخاص بك وهو قيد المراجعة. لا تحتاج لإرسال طلب آخر الآن.
        </section>
    <?php endif; ?>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">إرسال طلب تجديد</h2>
        <p class="subscriber-muted">سنراجع الطلب ونتواصل معك عند الحاجة. إرسال الطلب لا يغيّر الباقة مباشرة.</p>
        <form class="subscriber-form" method="post" action="/my/renew">
            <input type="hidden" name="_csrf" value="<?= gn_subscriber_h($csrf_token ?? '') ?>">
            <div>
                <label for="renew-phone">رقم الهاتف للتواصل</label>
                <input id="renew-phone" type="tel" name="phone" value="<?= gn_subscriber_h($phone) ?>" placeholder="رقم الهاتف" dir="ltr" autocomplete="tel">
            </div>
            <div>
                <label for="renew-message">ملاحظة اختيارية</label>
                <textarea id="renew-message" name="message" placeholder="اكتب أي ملاحظة تساعدنا في معالجة الطلب">أرغب في تجديد اشتراكي على نفس الباقة.</textarea>
            </div>
            <button class="subscriber-btn primary subscriber-btn-block" type="submit" <?= $pending !== [] ? 'disabled' : '' ?>>
                <?= $pending !== [] ? 'الطلب قيد المراجعة' : 'إرسال طلب التجديد' ?>
            </button>
        </form>
        <?php if ($whatsappUrl !== ''): ?>
            <a class="subscriber-btn whatsapp subscriber-btn-block subscriber-btn-spaced" href="<?= gn_subscriber_h($whatsappUrl) ?>" target="_blank" rel="noopener">التواصل عبر واتساب</a>
        <?php endif; ?>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">حالة طلبات التجديد</h2>
        <?php if ($requests === []): ?>
            <div class="subscriber-empty">لا توجد طلبات تجديد حتى الآن.</div>
        <?php else: ?>
            <div class="subscriber-timeline">
                <?php foreach ($requests as $request): $status = gn_subscriber_status($request['status'] ?? 'pending'); ?>
                    <article class="subscriber-timeline-item">
                        <span class="subscriber-timeline-dot <?= gn_subscriber_h($status[1]) ?>"></span>
                        <div>
                            <div class="subscriber-card-heading">
                                <strong><?= gn_subscriber_h($request['package_name'] ?? 'طلب تجديد') ?></strong>
                                <span class="subscriber-badge <?= gn_subscriber_h($status[1]) ?>"><?= gn_subscriber_h($status[0]) ?></span>
                            </div>
                            <time class="subscriber-muted"><?= gn_subscriber_date($request['created_at'] ?? null) ?></time>
                            <?php if (trim((string) ($request['admin_note'] ?? '')) !== ''): ?>
                                <p class="subscriber-request-note"><?= gn_subscriber_h($request['admin_note']) ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>
