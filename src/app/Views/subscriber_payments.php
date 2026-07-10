<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">💳</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>سجل الدفعات</p>

            <div class="status-pill">
                <span class="dot"></span>
                <?= htmlspecialchars($username ?? '-') ?>
            </div>
        </div>

        <?php if (!empty($is_admin_preview)): ?>
            <div class="notice" style="background:#eff6ff;color:#1d4ed8;">
                أنت تشاهد هذه الصفحة كمدير للمعاينة.
            </div>
        <?php endif; ?>

        <div class="stat">
            <div class="label">آخر تجديد</div>
            <div class="value" style="font-size:13px; line-height:1.9;">
                <?php if (!$latest_renewal): ?>
                    لا يوجد تجديد مسجل بعد.
                <?php else: ?>
                    الباقة:
                    <?= htmlspecialchars($latest_renewal['package_name'] ?? '-') ?>
                    <br>

                    المبلغ:
                    <?= htmlspecialchars((string) ($latest_renewal['amount'] ?? 0)) ?>
                    <?= htmlspecialchars($latest_renewal['currency'] ?? 'SYP') ?>
                    <br>

                    البداية:
                    <?= htmlspecialchars($latest_renewal['starts_at'] ?? '-') ?>
                    <br>

                    الانتهاء:
                    <?= htmlspecialchars($latest_renewal['expires_at'] ?? '-') ?>
                    <br>

                    الحالة:
                    <?= htmlspecialchars($subscription['label'] ?? '-') ?>
                    <br>

                    المتبقي:
                    <?= htmlspecialchars($subscription['days_left_label'] ?? '-') ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">كل الدفعات</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                <?php if (count($payments) === 0): ?>
                    لا توجد دفعات حالياً.
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <div style="border-bottom:1px solid #e5e7eb; padding:10px 0;">
                            <strong>
                                <?= htmlspecialchars((string) ($payment['amount'] ?? 0)) ?>
                                <?= htmlspecialchars($payment['currency'] ?? 'SYP') ?>
                            </strong>
                            <br>

                            الحالة:
                            <?= htmlspecialchars($payment['status'] ?? '-') ?>
                            <br>

                            الباقة:
                            <?= htmlspecialchars(($payment['package_name'] ?? '') !== '' ? $payment['package_name'] : '-') ?>
                            <br>

                            البداية:
                            <?= htmlspecialchars(($payment['starts_at'] ?? '') !== '' ? $payment['starts_at'] : '-') ?>
                            <br>

                            الانتهاء:
                            <?= htmlspecialchars(($payment['expires_at'] ?? '') !== '' ? $payment['expires_at'] : '-') ?>
                            <br>

                            الملاحظة:
                            <?= htmlspecialchars(($payment['note'] ?? '') !== '' ? $payment['note'] : '-') ?>
                            <br>

                            التاريخ:
                            <?= htmlspecialchars($payment['paid_at'] ?? '-') ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($is_admin_preview)): ?>
            <a class="btn btn-outline" href="/dashboard?username=<?= urlencode($username ?? '') ?>">
                العودة للوحة المشترك
            </a>
        <?php else: ?>
            <a class="btn btn-outline" href="/dashboard">
                العودة للوحة المشترك
            </a>
        <?php endif; ?>

        <a class="btn btn-primary" href="/support<?= !empty($is_admin_preview) ? '?username=' . urlencode($username ?? '') : '' ?>">
            الدعم وطلب التجديد
        </a>

        <?php if (empty($is_admin_preview)): ?>
            <a class="btn btn-danger" href="/logout">
                تسجيل الخروج
            </a>
        <?php endif; ?>

        <div class="footer">
            الدعم: <?= htmlspecialchars($support_phone ?? '-') ?>
        </div>

    </div>
</div>