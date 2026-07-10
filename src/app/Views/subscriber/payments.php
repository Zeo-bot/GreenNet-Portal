<?php
    $usernameForLinks = urlencode((string) ($username ?? ''));
    $adminPreviewSuffix = !empty($is_admin_preview) ? '?username=' . $usernameForLinks : '';
    $paymentsList = $payments ?? [];
?>

<div class="subscriber-shell">
    <div class="subscriber-container">
        <div class="subscriber-card">

            <div class="subscriber-header">
                <div class="subscriber-logo <?= !empty($site_logo_path) ? 'has-logo' : '' ?>">
                    <?php if (!empty($site_logo_path)): ?>
                        <img src="<?= htmlspecialchars($site_logo_path) ?>" alt="Logo">
                    <?php else: ?>
                        💳
                    <?php endif; ?>
                </div>

                <div>
                    <h1 class="subscriber-title">
                        دفعاتي
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
                    أنت تشاهد سجل الدفعات كمدير للمعاينة.
                </div>
            <?php endif; ?>

            <div class="subscriber-hero">
                <div class="subscriber-hero-label">إجمالي الدفعات</div>

                <div class="subscriber-hero-value">
                    <?= htmlspecialchars((string) ($total_paid ?? 0)) ?>
                    <?= htmlspecialchars($default_currency ?? 'SYP') ?>
                </div>

                <div class="subscriber-hero-note">
                    عدد الدفعات المسجلة:
                    <strong><?= htmlspecialchars((string) ($payments_count ?? 0)) ?></strong>
                </div>
            </div>

            <div class="subscriber-grid">
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
                    <div class="subscriber-info-label">📅 الانتهاء</div>
                    <div class="subscriber-info-value" style="font-size:13px;">
                        <?= htmlspecialchars(($subscription_expires_at ?? '') !== '' ? $subscription_expires_at : '-') ?>
                    </div>
                </div>

                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">💳 حالة الدفع</div>
                    <div class="subscriber-info-value">
                        <?= htmlspecialchars($payment_label ?? '-') ?>
                    </div>
                </div>
            </div>

            <div class="subscriber-section">
                <h2 class="subscriber-section-title">سجل الدفعات</h2>

                <?php if (count($paymentsList) === 0): ?>
                    <div class="subscriber-alert info">
                        لا توجد دفعات مسجلة لهذا الحساب حالياً.
                    </div>
                <?php else: ?>
                    <?php foreach ($paymentsList as $payment): ?>
                        <div class="subscriber-payment-item">
                            <div>
                                <div class="subscriber-payment-user">
                                    <?= htmlspecialchars($payment['package_name'] ?? 'دفعة اشتراك') ?>
                                </div>

                                <div class="subscriber-payment-meta">
                                    التاريخ:
                                    <?= htmlspecialchars($payment['paid_at'] ?? ($payment['created_at'] ?? '-')) ?>
                                    <br>

                                    <?php if (($payment['notes'] ?? '') !== ''): ?>
                                        ملاحظة:
                                        <?= htmlspecialchars($payment['notes']) ?>
                                    <?php else: ?>
                                        المستخدم:
                                        <?= htmlspecialchars($payment['username'] ?? ($username ?? '-')) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="subscriber-payment-amount">
                                <?= htmlspecialchars((string) ($payment['amount'] ?? 0)) ?>
                                <?= htmlspecialchars($payment['currency'] ?? ($default_currency ?? 'SYP')) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="subscriber-actions">
                <a class="subscriber-btn primary" href="/support<?= htmlspecialchars($adminPreviewSuffix) ?>">
                    طلب تجديد عبر واتساب
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

        <a class="active" href="/my/payments<?= htmlspecialchars($adminPreviewSuffix) ?>">
            <span class="icon">💳</span>
            <span>دفعاتي</span>
        </a>

        <a href="/support<?= htmlspecialchars($adminPreviewSuffix) ?>">
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