<?php
    $filters = is_array($filters ?? null) ? $filters : [];
    $payments = is_array($payments ?? null) ? $payments : [];
    $packages = is_array($packages ?? null) ? $packages : [];
    $paymentStats = is_array($payment_stats ?? null) ? $payment_stats : [];
    $packageSales = is_array($package_sales ?? null) ? $package_sales : [];
    $customerStats = is_array($customer_stats ?? null) ? $customer_stats : [];
    $subscriptionStats = is_array($subscription_stats ?? null) ? $subscription_stats : [];

    $totalPayments = (float) ($paymentStats['total'] ?? 0);
    $paymentCount = (int) ($paymentStats['count'] ?? 0);
    $averagePayment = (float) ($paymentStats['average'] ?? 0);
    $uniqueUsers = (int) ($paymentStats['unique_users'] ?? 0);

    $currencies = is_array($paymentStats['currencies'] ?? null) ? $paymentStats['currencies'] : [];

    $primaryCurrency = $default_currency ?? 'SYP';

    if (count($currencies) > 0) {
        $currencyKeys = array_keys($currencies);
        $primaryCurrency = (string) ($currencyKeys[0] ?? $primaryCurrency);
    }
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">التقارير</h1>

        <p class="admin-page-description">
            ملخص مالي وإداري عن الدفعات، الزبائن، الاشتراكات، وأداء الباقات داخل GreenNet.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/export/payments.csv">تصدير الدفعات CSV</a>
        <a class="admin-mini-btn" href="/admin/export/subscriptions.csv">تصدير الاشتراكات CSV</a>
        <a class="admin-mini-btn" href="/admin/backup">Backup</a>
    </div>
</div>

<form class="admin-filter-bar" method="get" action="/admin/reports">
    <div class="form-group">
        <label>اسم المستخدم</label>
        <input
            type="text"
            name="username"
            value="<?= htmlspecialchars((string) ($filters['username'] ?? '')) ?>"
            placeholder="مثال: user01"
        >
    </div>

    <div class="form-group">
        <label>من تاريخ</label>
        <input
            type="date"
            name="date_from"
            value="<?= htmlspecialchars((string) ($filters['date_from'] ?? '')) ?>"
        >
    </div>

    <div class="form-group">
        <label>إلى تاريخ</label>
        <input
            type="date"
            name="date_to"
            value="<?= htmlspecialchars((string) ($filters['date_to'] ?? '')) ?>"
        >
    </div>

    <div class="form-group">
        <label>الباقة</label>
        <select name="package_id">
            <option value="0">كل الباقات</option>
            <?php foreach ($packages as $package): ?>
                <option value="<?= htmlspecialchars((string) $package['id']) ?>" <?= ((int) ($filters['package_id'] ?? 0) === (int) $package['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($package['name'] ?? '-')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn-primary" type="submit">
        تطبيق
    </button>
</form>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">💰 إجمالي الدفعات</div>
        <div class="admin-stat-value" style="font-size:21px;">
            <?= htmlspecialchars((string) $totalPayments) ?>
        </div>
        <div class="admin-stat-note">
            <?= htmlspecialchars($primaryCurrency) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">🧾 عدد الدفعات</div>
        <div class="admin-stat-value">
            <?= htmlspecialchars((string) $paymentCount) ?>
        </div>
        <div class="admin-stat-note">
            ضمن الفلاتر الحالية
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">📊 متوسط الدفعة</div>
        <div class="admin-stat-value" style="font-size:21px;">
            <?= htmlspecialchars((string) $averagePayment) ?>
        </div>
        <div class="admin-stat-note">
            <?= htmlspecialchars($primaryCurrency) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">👥 مشتركين دفعوا</div>
        <div class="admin-stat-value">
            <?= htmlspecialchars((string) $uniqueUsers) ?>
        </div>
        <div class="admin-stat-note">
            أسماء مستخدمين فريدة
        </div>
    </div>

</div>

<div class="admin-two-columns">

    <section class="admin-section-card">
        <h2 class="admin-section-title">إحصائيات الزبائن</h2>

        <p class="admin-section-subtitle">
            ملخص حالة الزبائن داخل CRM المحلي.
        </p>

        <div class="admin-kpi-line">
            <span class="admin-badge admin-badge-primary">
                الكل: <?= htmlspecialchars((string) ($customerStats['total'] ?? 0)) ?>
            </span>

            <span class="admin-badge admin-badge-success">
                مدفوع: <?= htmlspecialchars((string) ($customerStats['paid'] ?? 0)) ?>
            </span>

            <span class="admin-badge admin-badge-danger">
                عليه دفع: <?= htmlspecialchars((string) ($customerStats['due'] ?? 0)) ?>
            </span>

            <span class="admin-badge admin-badge-warning">
                مؤجل: <?= htmlspecialchars((string) ($customerStats['pending'] ?? 0)) ?>
            </span>

            <span class="admin-badge">
                بلا باقة: <?= htmlspecialchars((string) ($customerStats['no_package'] ?? 0)) ?>
            </span>
        </div>

        <div class="admin-section-card" style="margin-top:16px;background:#f9fafb;">
            <h3 class="admin-section-title" style="font-size:15px;">حسب نوع الوصول</h3>

            <div class="admin-kpi-line">
                <span class="admin-badge">Hotspot: <?= htmlspecialchars((string) ($customerStats['hotspot'] ?? 0)) ?></span>
                <span class="admin-badge">PPP: <?= htmlspecialchars((string) ($customerStats['ppp'] ?? 0)) ?></span>
                <span class="admin-badge">PPPoE: <?= htmlspecialchars((string) ($customerStats['pppoe'] ?? 0)) ?></span>
                <span class="admin-badge">Hybrid: <?= htmlspecialchars((string) ($customerStats['hybrid'] ?? 0)) ?></span>
                <span class="admin-badge">Other: <?= htmlspecialchars((string) ($customerStats['other_access'] ?? 0)) ?></span>
            </div>
        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">إحصائيات الاشتراكات</h2>

        <p class="admin-section-subtitle">
            قراءة محلية بناءً على آخر دفعة وتجديد مسجلين.
        </p>

        <div class="admin-kpi-line">
            <span class="admin-badge admin-badge-success">
                فعال: <?= htmlspecialchars((string) ($subscriptionStats['active'] ?? 0)) ?>
            </span>

            <span class="admin-badge admin-badge-warning">
                قريب الانتهاء: <?= htmlspecialchars((string) ($subscriptionStats['soon'] ?? 0)) ?>
            </span>

            <span class="admin-badge admin-badge-danger">
                منتهي: <?= htmlspecialchars((string) ($subscriptionStats['expired'] ?? 0)) ?>
            </span>

            <span class="admin-badge">
                بلا تجديد: <?= htmlspecialchars((string) ($subscriptionStats['no_renewal'] ?? 0)) ?>
            </span>

            <span class="admin-badge">
                بلا باقة: <?= htmlspecialchars((string) ($subscriptionStats['no_package'] ?? 0)) ?>
            </span>
        </div>

        <div class="notice" style="margin-top:16px;background:#eff6ff;color:#1d4ed8;">
            لاحقاً سيتم ربط هذه الصفحة مع Readiness Check وAPI Diagnostics قبل أوامر MikroTik Write.
        </div>
    </section>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">أفضل الباقات حسب الدفعات</h2>

    <p class="admin-section-subtitle">
        ترتيب الباقات حسب إجمالي المبالغ ضمن الفلاتر الحالية.
    </p>

    <?php if (count($packageSales) === 0): ?>
        <div class="admin-empty-state">
            لا توجد دفعات ضمن الفلاتر الحالية.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:700px;">
                <thead>
                    <tr>
                        <th>الباقة</th>
                        <th>عدد الدفعات</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($packageSales as $sale): ?>
                        <tr>
                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($sale['package_name'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <span class="admin-badge">
                                    <?= htmlspecialchars((string) ($sale['count'] ?? 0)) ?>
                                </span>
                            </td>

                            <td>
                                <strong style="direction:ltr;display:inline-block;">
                                    <?= htmlspecialchars((string) ($sale['total'] ?? 0)) ?>
                                    <?= htmlspecialchars((string) ($sale['currency'] ?? $primaryCurrency)) ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">آخر الدفعات</h2>

    <p class="admin-section-subtitle">
        آخر 30 دفعة مطابقة للفلاتر الحالية.
    </p>

    <?php if (count($payments) === 0): ?>
        <div class="admin-empty-state">
            لا توجد دفعات مطابقة للفلاتر الحالية.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>المستخدم</th>
                        <th>الباقة</th>
                        <th>المبلغ</th>
                        <th>الاشتراك</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                            $username = (string) ($payment['username'] ?? '');
                            $encodedUsername = urlencode($username);
                        ?>

                        <tr>
                            <td>
                                <div class="admin-table-title">
                                    <span class="admin-code"><?= htmlspecialchars($username !== '' ? $username : '-') ?></span>
                                </div>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($payment['package_name'] ?? 'غير محدد')) ?>
                                </div>

                                <div class="admin-table-subtitle">
                                    مدة:
                                    <?= htmlspecialchars((string) ($payment['duration_days'] ?? '-')) ?>
                                    يوم
                                    —
                                    حجم:
                                    <?= htmlspecialchars((string) ($payment['quota_gb'] ?? '-')) ?>
                                    GB
                                </div>
                            </td>

                            <td>
                                <strong style="direction:ltr;display:inline-block;">
                                    <?= htmlspecialchars((string) ($payment['amount'] ?? 0)) ?>
                                    <?= htmlspecialchars((string) ($payment['currency'] ?? $primaryCurrency)) ?>
                                </strong>
                            </td>

                            <td>
                                <div class="admin-table-subtitle">
                                    من:
                                    <?= htmlspecialchars((string) ($payment['starts_at'] ?? '-')) ?>
                                    <br>
                                    إلى:
                                    <?= htmlspecialchars((string) ($payment['expires_at'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <?= htmlspecialchars((string) ($payment['paid_at'] ?? $payment['created_at'] ?? '-')) ?>
                            </td>

                            <td>
                                <?php if ($username !== ''): ?>
                                    <div class="admin-row-actions">
                                        <a class="admin-row-action primary" href="/admin/customers/profile?username=<?= htmlspecialchars($encodedUsername) ?>">
                                            ملف
                                        </a>

                                        <a class="admin-row-action" href="/my/payments?username=<?= htmlspecialchars($encodedUsername) ?>">
                                            دفعاته
                                        </a>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</section>