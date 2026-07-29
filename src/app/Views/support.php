<?php
require BASE_PATH . '/app/Views/subscriber/_helpers.php';
$previewQuery = !empty($is_admin_preview) && trim((string) ($username ?? '')) !== ''
    ? '?username=' . rawurlencode((string) $username)
    : '';
$phone = trim((string) ($support_phone ?? ''));
$whatsapp = preg_replace('/\D+/', '', (string) ($support_whatsapp ?? '')) ?? '';
$phoneHref = $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '';
$whatsappHref = $whatsapp !== ''
    ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode((string) ($whatsapp_message ?? ''))
    : '';
?>

<div class="subscriber-app">
    <section class="subscriber-hero subscriber-hero-compact">
        <div class="subscriber-hello">الدعم والمساعدة</div>
        <h1 class="subscriber-username">كيف يمكننا مساعدتك؟</h1>
        <p class="subscriber-hero-copy"><?= nl2br(gn_subscriber_h($support_text ?? 'تواصل مع فريق GreenNet للمساعدة في حسابك أو اشتراكك.')) ?></p>
    </section>

    <?php if (!empty($is_admin_preview)): ?>
        <div class="subscriber-notice warning">أنت تشاهد صفحة المشترك في وضع المعاينة.</div>
    <?php endif; ?>

    <section class="subscriber-support-grid">
        <?php if ($whatsappHref !== ''): ?>
            <a class="subscriber-support-card whatsapp" href="<?= gn_subscriber_h($whatsappHref) ?>" target="_blank" rel="noopener">
                <span class="subscriber-support-icon">WA</span><strong>واتساب</strong><small>فتح محادثة مع الدعم</small>
            </a>
        <?php endif; ?>
        <?php if ($phoneHref !== ''): ?>
            <a class="subscriber-support-card" href="<?= gn_subscriber_h($phoneHref) ?>">
                <span class="subscriber-support-icon">☎</span><strong>اتصال هاتفي</strong><small dir="ltr"><?= gn_subscriber_h($phone) ?></small>
            </a>
        <?php endif; ?>
    </section>

    <?php if ($whatsappHref === '' && $phoneHref === ''): ?>
        <section class="subscriber-card"><div class="subscriber-empty">معلومات التواصل غير متاحة حالياً. حاول مرة أخرى لاحقاً.</div></section>
    <?php endif; ?>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">معلومات تساعد فريق الدعم</h2>
        <div class="subscriber-list">
            <div class="subscriber-row"><span class="subscriber-row-label">اسم المستخدم</span><strong class="subscriber-row-value" dir="ltr"><?= gn_subscriber_h($username ?? '') ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">الباقة</span><strong class="subscriber-row-value"><?= gn_subscriber_h(trim((string) ($package_name ?? '')) ?: 'غير متاح') ?></strong></div>
            <div class="subscriber-row"><span class="subscriber-row-label">حالة الاشتراك</span><strong class="subscriber-row-value"><?= gn_subscriber_h(gn_subscriber_status($subscription_label ?? '')[0]) ?></strong></div>
        </div>
    </section>
</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>
