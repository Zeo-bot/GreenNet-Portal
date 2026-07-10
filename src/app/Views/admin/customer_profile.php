<?php
    $customer = is_array($customer ?? null) ? $customer : [];
    $package = is_array($package ?? null) ? $package : [];
    $dashboard = is_array($dashboard ?? null) ? $dashboard : [];
    $payments = is_array($payments ?? null) ? $payments : [];

    $username = (string) ($username ?? ($customer['username'] ?? ''));
    $encodedUsername = urlencode($username);

    $crmFound = !empty($customer);
    $packageFound = !empty($package) || !empty($dashboard['package_found']);
    $routerFound = (bool) ($dashboard['routeros_found'] ?? false);

    $subscriptionStatus = (string) ($dashboard['subscription_status'] ?? 'none');
    $paymentStatus = (string) ($dashboard['payment_status'] ?? ($customer['payment_status'] ?? 'unknown'));

    $displayName = (string) (
        $customer['display_name']
        ?? $customer['full_name']
        ?? $customer['name']
        ?? $dashboard['customer_display_name']
        ?? ''
    );

    if ($displayName === '') {
        $displayName = $username !== '' ? $username : '-';
    }

    $phone = (string) (
        $customer['phone']
        ?? $customer['mobile']
        ?? $customer['phone_number']
        ?? '-'
    );

    $accessType = (string) (
        $dashboard['access_type']
        ?? $customer['access_type']
        ?? '-'
    );

    $packageName = (string) (
        $dashboard['package_name']
        ?? $package['name']
        ?? '-'
    );

    $packageProfile = (string) (
        $dashboard['package_source_profile']
        ?? $package['source_profile']
        ?? '-'
    );

    $subscriptionLabel = (string) ($dashboard['subscription_label'] ?? '-');
    $expiresAt = (string) ($dashboard['subscription_expires_at'] ?? '-');
    $daysLeft = (string) ($dashboard['subscription_days_left_label'] ?? '-');

    $connectionStatus = (string) ($dashboard['connection_status'] ?? 'غير معروف');

    $statusBadgeClass = 'admin-badge';

    if ($subscriptionStatus === 'active') {
        $statusBadgeClass = 'admin-badge admin-badge-success';
    } elseif ($subscriptionStatus === 'expired') {
        $statusBadgeClass = 'admin-badge admin-badge-danger';
    } elseif ($subscriptionStatus === 'soon_3' || $subscriptionStatus === 'soon_7') {
        $statusBadgeClass = 'admin-badge admin-badge-warning';
    }

    $paymentBadgeClass = 'admin-badge';

    if ($paymentStatus === 'paid') {
        $paymentBadgeClass = 'admin-badge admin-badge-success';
    } elseif ($paymentStatus === 'due' || $paymentStatus === 'unpaid') {
        $paymentBadgeClass = 'admin-badge admin-badge-danger';
    } elseif ($paymentStatus === 'pending') {
        $paymentBadgeClass = 'admin-badge admin-badge-warning';
    }
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            ملف المشترك
        </h1>

        <p class="admin-page-description">
            عرض شامل لبيانات المشترك داخل CRM، حالة الاشتراك، الباقة، الدفعات، وحالة الاتصال المقروءة من MikroTik.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/customers/table">جدول الزبائن</a>
        <a class="admin-mini-btn" href="/admin/search?q=<?= htmlspecialchars($encodedUsername) ?>">بحث</a>
        <a class="admin-mini-btn" href="/dashboard?username=<?= htmlspecialchars($encodedUsername) ?>">معاينة المشترك</a>
    </div>
</div>

<?php if (($error ?? '') !== ''): ?>
    <div class="notice" style="background:#fee2e2;color:#991b1b;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($username !== ''): ?>

    <section class="admin-section-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">

            <div style="display:flex;align-items:center;gap:14px;">
                <div class="admin-logo-box" style="width:64px;height:64px;font-size:28px;">
                    👤
                </div>

                <div>
                    <h2 class="admin-section-title" style="margin-bottom:4px;">
                        <?= htmlspecialchars($displayName) ?>
                    </h2>

                    <div style="color:#6b7280;font-size:13px;line-height:1.8;">
                        اسم المستخدم:
                        <strong style="direction:ltr;display:inline-block;color:#111827;">
                            <?= htmlspecialchars($username) ?>
                        </strong>
                        <br>
                        الهاتف:
                        <?= htmlspecialchars($phone) ?>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <span class="<?= htmlspecialchars($statusBadgeClass) ?>">
                    <?= htmlspecialchars($subscriptionLabel) ?>
                </span>

                <span class="<?= htmlspecialchars($paymentBadgeClass) ?>">
                    <?= htmlspecialchars($dashboard['payment_label'] ?? $paymentStatus) ?>
                </span>

                <?php if ($routerFound): ?>
                    <span class="admin-badge admin-badge-success">Online / Found</span>
                <?php else: ?>
                    <span class="admin-badge admin-badge-warning">Not Active</span>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <div class="admin-stats-grid">

        <div class="admin-stat-card">
            <div class="admin-stat-label">📦 الباقة</div>
            <div class="admin-stat-value" style="font-size:18px;">
                <?= htmlspecialchars($packageName) ?>
            </div>
            <div class="admin-stat-note">
                Profile:
                <span style="direction:ltr;display:inline-block;">
                    <?= htmlspecialchars($packageProfile) ?>
                </span>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">📅 انتهاء الاشتراك</div>
            <div class="admin-stat-value" style="font-size:17px;">
                <?= htmlspecialchars($expiresAt !== '' ? $expiresAt : '-') ?>
            </div>
            <div class="admin-stat-note">
                <?= htmlspecialchars($daysLeft) ?>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">💳 إجمالي الدفعات</div>
            <div class="admin-stat-value" style="font-size:20px;">
                <?= htmlspecialchars((string) ($total_paid ?? 0)) ?>
            </div>
            <div class="admin-stat-note">
                عدد الدفعات:
                <?= htmlspecialchars((string) ($payments_count ?? 0)) ?>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">🧩 حالة MikroTik</div>
            <div class="admin-stat-value" style="font-size:16px;">
                <?= htmlspecialchars($connectionStatus) ?>
            </div>
            <div class="admin-stat-note">
                <?= htmlspecialchars((string) ($dashboard['source_detail'] ?? $dashboard['data_source'] ?? '-')) ?>
            </div>
        </div>

    </div>

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            إجراءات المشترك
        </h2>

        <p class="admin-section-subtitle">
            هذه الإجراءات حالياً محلية أو قراءة فقط. أوامر MikroTik Write ستُضاف لاحقاً بعد طبقة الأمان والـ Dry Run.
        </p>

        <div class="admin-action-grid">

            <a class="admin-action-card" href="/dashboard?username=<?= htmlspecialchars($encodedUsername) ?>">
                <div class="admin-action-icon">📱</div>
                <div class="admin-action-title">معاينة لوحة المشترك</div>
                <div class="admin-action-desc">عرض نفس الصفحة التي يراها المشترك.</div>
            </a>

            <a class="admin-action-card" href="/admin/customers/edit?username=<?= htmlspecialchars($encodedUsername) ?>">
                <div class="admin-action-icon">✏️</div>
                <div class="admin-action-title">تعديل بيانات CRM</div>
                <div class="admin-action-desc">تعديل الاسم، الهاتف، الملاحظات، وحالة الدفع.</div>
            </a>

            <a class="admin-action-card" href="/admin/customers/package?username=<?= htmlspecialchars($encodedUsername) ?>">
                <div class="admin-action-icon">📦</div>
                <div class="admin-action-title">ربط / تغيير الباقة</div>
                <div class="admin-action-desc">اختيار باقة GreenNet المرتبطة بهذا المشترك.</div>
            </a>

            <a class="admin-action-card" href="/admin/customers/renew?username=<?= htmlspecialchars($encodedUsername) ?>">
                <div class="admin-action-icon">🔁</div>
                <div class="admin-action-title">تجديد الاشتراك</div>
                <div class="admin-action-desc">تسجيل دفعة وتجديد محلي حسب الباقة.</div>
            </a>

            <a class="admin-action-card" href="/admin/search?q=<?= htmlspecialchars($encodedUsername) ?>">
                <div class="admin-action-icon">🔎</div>
                <div class="admin-action-title">البحث عن المستخدم</div>
                <div class="admin-action-desc">بحث في CRM وHotspot وPPP وUser Manager.</div>
            </a>

            <a class="admin-action-card" href="/support?username=<?= htmlspecialchars($encodedUsername) ?>">
                <div class="admin-action-icon">💬</div>
                <div class="admin-action-title">معاينة رسالة الدعم</div>
                <div class="admin-action-desc">مشاهدة رسالة واتساب الجاهزة للتجديد.</div>
            </a>

        </div>
    </section>

    <div class="admin-two-columns">

        <section class="admin-section-card">
            <h2 class="admin-section-title">
                بيانات CRM
            </h2>

            <?php if (!$crmFound): ?>
                <div class="notice" style="background:#fff7ed;color:#92400e;">
                    هذا المستخدم غير موجود داخل CRM المحلي. يمكن استيراده من صفحة البحث أو المزامنة.
                </div>
            <?php else: ?>
                <div class="admin-checklist" style="grid-template-columns:1fr;">

                    <div class="admin-check-item">
                        <div class="admin-check-icon">👤</div>
                        <div>
                            <strong>الاسم</strong>
                            <br>
                            <?= htmlspecialchars($displayName) ?>
                        </div>
                    </div>

                    <div class="admin-check-item">
                        <div class="admin-check-icon">☎️</div>
                        <div>
                            <strong>الهاتف</strong>
                            <br>
                            <?= htmlspecialchars($phone) ?>
                        </div>
                    </div>

                    <div class="admin-check-item">
                        <div class="admin-check-icon">🌐</div>
                        <div>
                            <strong>نوع الوصول</strong>
                            <br>
                            <?= htmlspecialchars($accessType) ?>
                        </div>
                    </div>

                    <div class="admin-check-item">
                        <div class="admin-check-icon">💳</div>
                        <div>
                            <strong>حالة الدفع المحلية</strong>
                            <br>
                            <?= htmlspecialchars($paymentStatus) ?>
                        </div>
                    </div>

                    <div class="admin-check-item">
                        <div class="admin-check-icon">📝</div>
                        <div>
                            <strong>الملاحظات</strong>
                            <br>
                            <?= nl2br(htmlspecialchars((string) ($customer['notes'] ?? '-'))) ?>
                        </div>
                    </div>

                </div>
            <?php endif; ?>
        </section>

        <section class="admin-section-card">
            <h2 class="admin-section-title">
                الباقة والاشتراك
            </h2>

            <div class="admin-checklist" style="grid-template-columns:1fr;">

                <div class="admin-check-item">
                    <div class="admin-check-icon">📦</div>
                    <div>
                        <strong>الباقة</strong>
                        <br>
                        <?= htmlspecialchars($packageName) ?>
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">📡</div>
                    <div>
                        <strong>MikroTik Profile</strong>
                        <br>
                        <span style="direction:ltr;display:inline-block;">
                            <?= htmlspecialchars($packageProfile) ?>
                        </span>
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">🚀</div>
                    <div>
                        <strong>Rate Limit</strong>
                        <br>
                        <span style="direction:ltr;display:inline-block;">
                            <?= htmlspecialchars((string) ($dashboard['package_rate_limit'] ?? $package['rate_limit'] ?? '-')) ?>
                        </span>
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">📅</div>
                    <div>
                        <strong>صلاحية الباقة</strong>
                        <br>
                        <?= htmlspecialchars((string) ($dashboard['package_duration_days'] ?? $package['duration_days'] ?? '-')) ?>
                        يوم
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">🧾</div>
                    <div>
                        <strong>حالة الاشتراك</strong>
                        <br>
                        <?= htmlspecialchars($subscriptionLabel) ?>
                        <br>
                        الانتهاء:
                        <?= htmlspecialchars($expiresAt) ?>
                    </div>
                </div>

            </div>
        </section>

    </div>

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            حالة MikroTik الحالية
        </h2>

        <p class="admin-section-subtitle">
            هذه بيانات قراءة فقط من MikroTik. لا يوجد أي أمر تعديل في هذه المرحلة.
        </p>

        <?php if (!empty($dashboard['dashboard_error'])): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                خطأ قراءة MikroTik:
                <?= htmlspecialchars((string) $dashboard['dashboard_error']) ?>
            </div>
        <?php endif; ?>

        <div class="admin-stats-grid">

            <div class="admin-stat-card">
                <div class="admin-stat-label">الحالة</div>
                <div class="admin-stat-value" style="font-size:16px;">
                    <?= htmlspecialchars($connectionStatus) ?>
                </div>
                <div class="admin-stat-note">
                    <?= $routerFound ? 'المستخدم ظاهر حالياً' : 'غير ظاهر ضمن Active الآن' ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">IP Address</div>
                <div class="admin-stat-value" style="font-size:17px;direction:ltr;">
                    <?= htmlspecialchars((string) ($dashboard['ip_address'] ?? '-')) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">MAC / Caller ID</div>
                <div class="admin-stat-value" style="font-size:14px;direction:ltr;">
                    <?= htmlspecialchars((string) ($dashboard['mac_address'] ?? '-')) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">Uptime</div>
                <div class="admin-stat-value" style="font-size:17px;">
                    <?= htmlspecialchars((string) ($dashboard['uptime'] ?? '-')) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">Bytes In</div>
                <div class="admin-stat-value" style="font-size:15px;direction:ltr;">
                    <?= htmlspecialchars((string) ($dashboard['bytes_in'] ?? '-')) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">Bytes Out</div>
                <div class="admin-stat-value" style="font-size:15px;direction:ltr;">
                    <?= htmlspecialchars((string) ($dashboard['bytes_out'] ?? '-')) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">Used</div>
                <div class="admin-stat-value" style="font-size:15px;">
                    <?= htmlspecialchars((string) ($dashboard['used'] ?? '-')) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">Remaining</div>
                <div class="admin-stat-value" style="font-size:15px;">
                    <?= htmlspecialchars((string) ($dashboard['remaining'] ?? '-')) ?>
                </div>
            </div>

        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            آخر الدفعات
        </h2>

        <p class="admin-section-subtitle">
            سجل الدفعات المرتبطة باسم المستخدم هذا.
        </p>

        <?php if (count($payments) === 0): ?>
            <div class="notice" style="background:#eff6ff;color:#1d4ed8;">
                لا توجد دفعات مسجلة لهذا المستخدم.
            </div>
        <?php else: ?>
            <div class="admin-payment-list">
                <?php foreach ($payments as $payment): ?>
                    <div class="admin-payment-item">
                        <div>
                            <div class="admin-payment-user">
                                <?= htmlspecialchars((string) ($payment['package_name'] ?? 'دفعة اشتراك')) ?>
                            </div>

                            <div style="color:#6b7280;font-size:12px;line-height:1.7;">
                                التاريخ:
                                <?= htmlspecialchars((string) ($payment['paid_at'] ?? $payment['created_at'] ?? '-')) ?>
                                <br>

                                <?php if (($payment['notes'] ?? '') !== ''): ?>
                                    ملاحظة:
                                    <?= htmlspecialchars((string) $payment['notes']) ?>
                                <?php else: ?>
                                    المستخدم:
                                    <?= htmlspecialchars((string) ($payment['username'] ?? $username)) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="admin-payment-amount">
                            <?= htmlspecialchars((string) ($payment['amount'] ?? 0)) ?>
                            <?= htmlspecialchars((string) ($payment['currency'] ?? $default_currency ?? 'SYP')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            أوامر MikroTik المستقبلية
        </h2>

        <p class="admin-section-subtitle">
            هذه الأزرار ستكون لاحقاً ضمن Sprint 10 بعد Dry Run وWrite Safety. حالياً معروضة كخطة فقط.
        </p>

        <div class="admin-checklist">

            <div class="admin-check-item">
                <div class="admin-check-icon future">⏸</div>
                <div>
                    <strong>تعطيل المستخدم</strong>
                    <br>
                    سيتم تفعيلها لاحقاً بعد طبقة الأمان.
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon future">▶</div>
                <div>
                    <strong>تفعيل المستخدم</strong>
                    <br>
                    ستنفذ على Hotspot User أو PPP Secret حسب نوع الحساب.
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon future">📦</div>
                <div>
                    <strong>تطبيق الباقة على MikroTik</strong>
                    <br>
                    ستطبّق profile المناسب من GreenNet على الراوتر.
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon future">🔁</div>
                <div>
                    <strong>تجديد + تطبيق تلقائي</strong>
                    <br>
                    التجديد لاحقاً سيحدث CRM وMikroTik معاً.
                </div>
            </div>

        </div>
    </section>

<?php endif; ?>