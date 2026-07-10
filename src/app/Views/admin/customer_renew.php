<?php
    $customer = $preview['customer'] ?? null;
    $package = $preview['package'] ?? null;
    $latestRenewal = $preview['latest_renewal'] ?? null;
    $subscription = $preview['subscription'] ?? null;

    $canRenew = (bool) ($preview['ok'] ?? false);
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">💳</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>تجديد الاشتراك</p>

            <div class="status-pill">
                <span class="dot"></span>
                Renewal
            </div>
        </div>

        <div class="notice">
            التجديد هنا يسجل دفعة ومدة اشتراك داخل GreenNet فقط.
            لا يتم تعديل أي شيء على MikroTik في هذه الخطوة.
        </div>

        <?php if (!$canRenew): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                <?= htmlspecialchars($preview['message'] ?? 'لا يمكن التجديد.') ?>
            </div>
        <?php endif; ?>

        <?php if ($customer): ?>
            <div class="stat">
                <div class="label">المشترك</div>
                <div class="value" style="font-size:13px; line-height:1.9;">
                    Username:
                    <?= htmlspecialchars($customer['username'] ?? '-') ?>
                    <br>

                    الاسم:
                    <?= htmlspecialchars(($customer['display_name'] ?? '') !== '' ? $customer['display_name'] : '-') ?>
                    <br>

                    الهاتف:
                    <?= htmlspecialchars(($customer['phone'] ?? '') !== '' ? $customer['phone'] : '-') ?>
                    <br>

                    حالة الدفع الحالية:
                    <?= htmlspecialchars($customer['payment_status'] ?? '-') ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="stat" style="margin-top:12px;">
            <div class="label">الباقة الحالية</div>
            <div class="value" style="font-size:13px; line-height:1.9;">
                <?php if (!$package): ?>
                    لا توجد باقة مرتبطة بهذا المشترك.
                <?php else: ?>
                    اسم الباقة:
                    <strong><?= htmlspecialchars($package['name'] ?? '-') ?></strong>
                    <br>

                    السعر:
                    <?= htmlspecialchars((string) ($package['price'] ?? 0)) ?>
                    <?= htmlspecialchars($package['currency'] ?? 'SYP') ?>
                    <br>

                    الصلاحية:
                    <?= htmlspecialchars((string) ($package['duration_days'] ?? 0)) ?>
                    يوم
                    <br>

                    الحجم:
                    <?= htmlspecialchars((string) ($package['quota_gb'] ?? 0)) ?>
                    GB
                    <br>

                    Rate Limit:
                    <span style="direction:ltr; display:inline-block;">
                        <?= htmlspecialchars($package['rate_limit'] ?? '-') ?>
                    </span>
                    <br>

                    MikroTik Profile:
                    <?= htmlspecialchars($package['source_profile'] ?? '-') ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">آخر تجديد</div>
            <div class="value" style="font-size:13px; line-height:1.9;">
                <?php if (!$latestRenewal): ?>
                    لا يوجد تجديد سابق.
                <?php else: ?>
                    الباقة:
                    <?= htmlspecialchars($latestRenewal['package_name'] ?? '-') ?>
                    <br>

                    المبلغ:
                    <?= htmlspecialchars((string) ($latestRenewal['amount'] ?? 0)) ?>
                    <?= htmlspecialchars($latestRenewal['currency'] ?? 'SYP') ?>
                    <br>

                    البداية:
                    <?= htmlspecialchars($latestRenewal['starts_at'] ?? '-') ?>
                    <br>

                    الانتهاء:
                    <?= htmlspecialchars($latestRenewal['expires_at'] ?? '-') ?>
                    <br>

                    الحالة:
                    <?= htmlspecialchars($subscription['label'] ?? '-') ?>
                    <br>

                    المتبقي:
                    <?= htmlspecialchars($subscription['days_left_label'] ?? '-') ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canRenew): ?>
            <form method="post" action="/admin/customers/renew">

                <input type="hidden" name="username" value="<?= htmlspecialchars($customer['username'] ?? '') ?>">

                <div class="form-group">
                    <label>المبلغ المدفوع</label>
                    <input
                        type="number"
                        name="amount"
                        value="<?= htmlspecialchars((string) ($package['price'] ?? 0)) ?>"
                        min="0"
                    >
                </div>

                <div class="form-group">
                    <label>العملة</label>
                    <input
                        type="text"
                        name="currency"
                        value="<?= htmlspecialchars($package['currency'] ?? 'SYP') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>ملاحظة الدفعة</label>
                    <textarea name="note" rows="3">تجديد اشتراك باقة <?= htmlspecialchars($package['name'] ?? '-') ?></textarea>
                </div>

                <button class="btn btn-primary" type="submit">
                    تسجيل الدفعة وتجديد الاشتراك
                </button>

            </form>
        <?php endif; ?>

        <?php if ($customer): ?>
            <a class="btn btn-outline" href="/admin/customers/profile?username=<?= urlencode($customer['username'] ?? '') ?>">
                العودة لملف المشترك
            </a>

            <a class="btn btn-outline" href="/admin/customers/package?username=<?= urlencode($customer['username'] ?? '') ?>">
                تغيير الباقة
            </a>
        <?php endif; ?>

        <a class="btn btn-outline" href="/admin/customers">
            إدارة الزبائن
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Customer Renewal
        </div>

    </div>
</div>