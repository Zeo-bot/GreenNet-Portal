<?php
    $usernameForLinks = urlencode((string) ($username ?? ''));
    $adminPreviewSuffix = !empty($is_admin_preview) ? '?username=' . $usernameForLinks : '';

    $whatsappUrl = 'https://wa.me/' . ($support_whatsapp ?? '963966393915') . '?text=' . rawurlencode($whatsapp_message ?? '');
?>

<div class="subscriber-shell">
    <div class="subscriber-container">
        <div class="subscriber-card">

            <div class="subscriber-header">
                <div class="subscriber-logo <?= !empty($site_logo_path) ? 'has-logo' : '' ?>">
                    <?php if (!empty($site_logo_path)): ?>
                        <img src="<?= htmlspecialchars($site_logo_path) ?>" alt="Logo">
                    <?php else: ?>
                        💬
                    <?php endif; ?>
                </div>

                <div>
                    <h1 class="subscriber-title">
                        الدعم والتجديد
                    </h1>

                    <p class="subscriber-subtitle">
                        <?= htmlspecialchars($app_name ?? 'GreenNet') ?>
                    </p>

                    <div class="subscriber-pill">
                        <span class="dot"></span>
                        <?= htmlspecialchars($username ?? '-') ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($is_admin_preview)): ?>
                <div class="subscriber-alert info">
                    أنت تشاهد هذه الصفحة كمدير للمعاينة.
                </div>
            <?php endif; ?>

            <div class="subscriber-hero">
                <div class="subscriber-hero-label">تحتاج مساعدة؟</div>

                <div class="subscriber-hero-value">
                    تواصل معنا عبر واتساب
                </div>

                <div class="subscriber-hero-note">
                    <?= nl2br(htmlspecialchars($support_text ?? 'للدعم أو التجديد، تواصل معنا عبر واتساب.')) ?>
                </div>
            </div>

            <div class="subscriber-grid">
                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">👤 المستخدم</div>
                    <div class="subscriber-info-value">
                        <?= htmlspecialchars($username ?? '-') ?>
                    </div>
                </div>

                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">📦 الباقة</div>
                    <div class="subscriber-info-value">
                        <?= htmlspecialchars(($package_name ?? '') !== '' ? $package_name : '-') ?>
                    </div>
                </div>

                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">🧾 الاشتراك</div>
                    <div class="subscriber-info-value">
                        <?= htmlspecialchars($subscription_label ?? '-') ?>
                    </div>
                </div>

                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">📞 الدعم</div>
                    <div class="subscriber-info-value">
                        <?= htmlspecialchars($support_phone ?? '-') ?>
                    </div>
                </div>
            </div>

            <div class="subscriber-section">
                <h2 class="subscriber-section-title">الرسالة الجاهزة</h2>

                <p class="subscriber-section-note">
                    سيتم فتح واتساب مع هذه الرسالة لتسريع طلب التجديد أو الدعم.
                </p>

                <div class="subscriber-info-card">
                    <div class="subscriber-info-value" style="font-size:13px; white-space:pre-wrap;">
                        <?= htmlspecialchars($whatsapp_message ?? '') ?>
                    </div>
                </div>
            </div>

            <div class="subscriber-actions">
                <a class="subscriber-btn primary" href="<?= htmlspecialchars($whatsappUrl) ?>">
                    فتح واتساب الآن
                </a>

                <a class="subscriber-btn" href="/dashboard<?= htmlspecialchars($adminPreviewSuffix) ?>">
                    العودة للوحة المشترك
                </a>

                <?php if (!empty($is_admin_preview)): ?>
                    <a class="subscriber-btn" href="/admin/customers/profile?username=<?= urlencode($username ?? '') ?>">
                        ملف المشترك الإداري
                    </a>
                <?php else: ?>
                    <a class="subscriber-btn danger" href="/logout">
                        تسجيل الخروج
                    </a>
                <?php endif; ?>
            </div>

            <div class="subscriber-footer">
                <?= htmlspecialchars($footer_text ?? '') ?>
            </div>

        </div>
    </div>

    <nav class="subscriber-bottom-nav">
        <a href="/dashboard<?= htmlspecialchars($adminPreviewSuffix) ?>">
            <span class="icon">🏠</span>
            <span>الرئيسية</span>
        </a>

        <a href="/my/payments<?= htmlspecialchars($adminPreviewSuffix) ?>">
            <span class="icon">💳</span>
            <span>دفعاتي</span>
        </a>

        <a class="active" href="/support<?= htmlspecialchars($adminPreviewSuffix) ?>">
            <span class="icon">💬</span>
            <span>الدعم</span>
        </a>

        <?php if (!empty($is_admin_preview)): ?>
            <a href="/admin/customers/profile?username=<?= urlencode($username ?? '') ?>">
                <span class="icon">⚙️</span>
                <span>إدارة</span>
            </a>
        <?php else: ?>
            <a href="/logout">
                <span class="icon">🚪</span>
                <span>خروج</span>
            </a>
        <?php endif; ?>
    </nav>
</div>