<?php
    $usedPercent = (int) ($used_percent ?? 0);

    if ($usedPercent < 0) {
        $usedPercent = 0;
    }

    if ($usedPercent > 100) {
        $usedPercent = 100;
    }

    $isOnline = (bool) ($routeros_found ?? false);
    $crmFound = (bool) ($crm_found ?? false);
    $packageFound = (bool) ($package_found ?? false);
    $subscriptionFound = (bool) ($subscription_found ?? false);

    $paymentStatus = (string) ($payment_status ?? 'not_registered');
    $subscriptionStatus = (string) ($subscription_status ?? 'none');

    $usernameForLinks = urlencode((string) ($username ?? ''));
    $adminPreviewSuffix = !empty($is_admin_preview) ? '?username=' . $usernameForLinks : '';

    $statusClass = 'info';

    if ($subscriptionStatus === 'active') {
        $statusClass = 'success';
    }

    if ($subscriptionStatus === 'expired' || $paymentStatus === 'due') {
        $statusClass = 'danger';
    }
?>

<div class="subscriber-shell">
    <div class="subscriber-container">
        <div class="subscriber-card">

            <div class="subscriber-header">
                <div class="subscriber-logo <?= !empty($site_logo_path) ? 'has-logo' : '' ?>">
                    <?php if (!empty($site_logo_path)): ?>
                        <img src="<?= htmlspecialchars($site_logo_path) ?>" alt="Logo">
                    <?php else: ?>
                        G
                    <?php endif; ?>
                </div>

                <div>
                    <h1 class="subscriber-title">
                        <?= htmlspecialchars($app_name ?? 'GreenNet') ?>
                    </h1>

                    <p class="subscriber-subtitle">
                        لوحة المشترك
                    </p>

                    <div class="subscriber-pill">
                        <span class="dot"></span>
                        <?= htmlspecialchars($connection_status ?? 'غير معروف') ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($is_admin_preview)): ?>
                <div class="subscriber-alert info">
                    أنت تشاهد هذه الصفحة كمدير للمعاينة.
                </div>
            <?php endif; ?>

            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="subscriber-alert">
                        <strong>📢 <?= htmlspecialchars($announcement['title']) ?></strong>
                        <br>
                        <?= nl2br(htmlspecialchars($announcement['body'])) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($subscriber_welcome_text)): ?>
                <div class="subscriber-alert info">
                    <?= nl2br(htmlspecialchars($subscriber_welcome_text)) ?>
                </div>
            <?php endif; ?>

            <?php if (!$crmFound): ?>
                <div class="subscriber-alert danger">
                    هذا المستخدم غير مضاف بعد إلى نظام <?= htmlspecialchars($app_name ?? 'GreenNet') ?>.
                </div>
            <?php endif; ?>

            <?php if ($crmFound && !$packageFound): ?>
                <div class="subscriber-alert warning">
                    حسابك موجود، لكن لم يتم ربطه بباقة بعد.
                </div>
            <?php endif; ?>

            <?php if ($packageFound && !$subscriptionFound): ?>
                <div class="subscriber-alert warning">
                    توجد باقة مرتبطة بالحساب، لكن لا يوجد تجديد مسجل بعد.
                </div>
            <?php endif; ?>

            <?php if ($subscriptionStatus === 'expired'): ?>
                <div class="subscriber-alert danger">
                    الاشتراك منتهي. يرجى التواصل مع الإدارة لتجديد الاشتراك.
                </div>
            <?php endif; ?>

            <?php if ($paymentStatus === 'due'): ?>
                <div class="subscriber-alert warning">
                    يوجد مبلغ مستحق على الحساب.
                </div>
            <?php endif; ?>

            <?php if (!$isOnline): ?>
                <div class="subscriber-alert info">
                    هذا المستخدم غير ظاهر حالياً ضمن المتصلين النشطين في MikroTik.
                </div>
            <?php endif; ?>

            <div class="subscriber-hero">
                <div class="subscriber-hero-label">حالة الاشتراك</div>

                <div class="subscriber-hero-value">
                    <?= htmlspecialchars($subscription_label ?? '-') ?>
                </div>

                <div class="subscriber-hero-note">
                    اسم المستخدم:
                    <strong><?= htmlspecialchars($username ?? '-') ?></strong>
                    <br>
                    المتبقي:
                    <strong><?= htmlspecialchars($subscription_days_left_label ?? '-') ?></strong>
                </div>
            </div>

            <div class="subscriber-grid">

                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">👤 الاسم</div>
                    <div class="subscriber-info-value">
                        <?= htmlspecialchars(($customer_display_name ?? '') !== '' ? $customer_display_name : ($username ?? '-')) ?>
                    </div>
                </div>

                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">💳 الدفع</div>
                    <div class="subscriber-info-value">
                        <?= htmlspecialchars($payment_label ?? '-') ?>
                    </div>
                </div>

                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">📅 الانتهاء</div>
                    <div class="subscriber-info-value" style="font-size:13px;">
                        <?= htmlspecialchars(($subscription_expires_at ?? '') !== '' ? $subscription_expires_at : '-') ?>
                    </div>
                </div>

                <div class="subscriber-info-card">
                    <div class="subscriber-info-label">🚀 السرعة</div>
                    <div class="subscriber-info-value" style="font-size:13px;direction:ltr;">
                        <?= htmlspecialchars($speed ?? '-') ?>
                    </div>
                </div>

            </div>

            <div class="subscriber-section">
                <h2 class="subscriber-section-title">📦 الباقة الحالية</h2>

                <?php if (!$packageFound): ?>
                    <p class="subscriber-section-note">
                        لا توجد باقة مرتبطة حالياً.
                    </p>
                <?php else: ?>
                    <div class="subscriber-grid">
                        <div class="subscriber-info-card">
                            <div class="subscriber-info-label">اسم الباقة</div>
                            <div class="subscriber-info-value">
                                <?= htmlspecialchars($package_name ?? '-') ?>
                            </div>
                        </div>

                        <div class="subscriber-info-card">
                            <div class="subscriber-info-label">الصلاحية</div>
                            <div class="subscriber-info-value">
                                <?= htmlspecialchars((string) ($package_duration_days ?? 0)) ?> يوم
                            </div>
                        </div>

                        <?php if (!empty($show_price_to_subscriber)): ?>
                            <div class="subscriber-info-card">
                                <div class="subscriber-info-label">السعر</div>
                                <div class="subscriber-info-value">
                                    <?= htmlspecialchars((string) ($package_price ?? 0)) ?>
                                    <?= htmlspecialchars($package_currency ?? ($default_currency ?? 'SYP')) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($show_quota_to_subscriber)): ?>
                            <div class="subscriber-info-card">
                                <div class="subscriber-info-label">الحجم</div>
                                <div class="subscriber-info-value">
                                    <?= htmlspecialchars((string) ($package_quota_gb ?? 0)) ?> GB
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($show_mikrotik_profile_to_subscriber)): ?>
                            <div class="subscriber-info-card">
                                <div class="subscriber-info-label">MikroTik Profile</div>
                                <div class="subscriber-info-value" style="font-size:13px;">
                                    <?= htmlspecialchars($package_source_profile ?? '-') ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="subscriber-section-note" style="margin-top:12px;direction:ltr;">
                        Rate Limit:
                        <?= htmlspecialchars($package_rate_limit ?? '-') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="subscriber-section">
                <h2 class="subscriber-section-title">📊 الاستهلاك</h2>

                <div class="subscriber-progress-wrap">
                    <div class="subscriber-progress-info">
                        <span>المستخدم</span>
                        <span><?= htmlspecialchars($used ?? '-') ?></span>
                    </div>

                    <div class="subscriber-progress">
                        <div class="subscriber-progress-bar" style="width: <?= htmlspecialchars((string) $usedPercent) ?>%;"></div>
                    </div>

                    <div class="subscriber-progress-info" style="margin-top:8px;">
                        <span>المتبقي</span>
                        <span><?= htmlspecialchars($remaining ?? '-') ?></span>
                    </div>
                </div>
            </div>

            <div class="subscriber-section">
                <h2 class="subscriber-section-title">🌐 معلومات الاتصال</h2>

                <div class="subscriber-grid">
                    <div class="subscriber-info-card">
                        <div class="subscriber-info-label">IP</div>
                        <div class="subscriber-info-value">
                            <?= htmlspecialchars($ip_address ?? '-') ?>
                        </div>
                    </div>

                    <div class="subscriber-info-card">
                        <div class="subscriber-info-label">MAC / Caller ID</div>
                        <div class="subscriber-info-value" style="font-size:12px;">
                            <?= htmlspecialchars($mac_address ?? '-') ?>
                        </div>
                    </div>

                    <div class="subscriber-info-card">
                        <div class="subscriber-info-label">مدة الاتصال</div>
                        <div class="subscriber-info-value">
                            <?= htmlspecialchars($uptime ?? '-') ?>
                        </div>
                    </div>

                    <div class="subscriber-info-card">
                        <div class="subscriber-info-label">مصدر البيانات</div>
                        <div class="subscriber-info-value" style="font-size:12px;">
                            <?= htmlspecialchars($source_detail ?? ($data_source ?? '-')) ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (($customer_notes ?? '') !== ''): ?>
                <div class="subscriber-section">
                    <h2 class="subscriber-section-title">ملاحظات الحساب</h2>
                    <p class="subscriber-section-note">
                        <?= nl2br(htmlspecialchars($customer_notes)) ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="subscriber-actions">
                <a class="subscriber-btn primary" href="/support<?= htmlspecialchars($adminPreviewSuffix) ?>">
                    💬 الدعم وطلب التجديد
                </a>

                <a class="subscriber-btn" href="/my/payments<?= htmlspecialchars($adminPreviewSuffix) ?>">
                    💳 سجل الدفعات
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
                <br>
                الدعم: <?= htmlspecialchars($support_phone ?? '-') ?>
            </div>

        </div>
    </div>

    <nav class="subscriber-bottom-nav">
        <a class="active" href="/dashboard<?= htmlspecialchars($adminPreviewSuffix) ?>">
            <span class="icon">🏠</span>
            <span>الرئيسية</span>
        </a>

        <a href="/my/payments<?= htmlspecialchars($adminPreviewSuffix) ?>">
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