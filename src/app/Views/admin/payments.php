<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">G</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>إدارة الدفعات</p>

            <div class="status-pill">
                <span class="dot"></span>
                Payments
            </div>
        </div>

        <div class="notice">
            من هذه الصفحة تستطيع تسجيل دفعة لأي زبون. بعد تسجيل الدفعة سيتم تحويل حالة الزبون إلى "مدفوع" تلقائياً.
        </div>

        <div class="stat">
            <div class="label">إجمالي الدفعات المسجلة</div>
            <div class="value"><?= htmlspecialchars((string) $total_paid) ?> SYP</div>
        </div>

        <form method="post" action="/admin/payments/store" style="margin-top:18px;">

            <div class="form-group">
                <label>الزبون</label>
                <select name="username" class="input-select" required>
                    <option value="">اختر الزبون</option>

                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= htmlspecialchars($customer['username']) ?>">
                            <?= htmlspecialchars($customer['username']) ?>
                            <?php if (!empty($customer['display_name'])): ?>
                                - <?= htmlspecialchars($customer['display_name']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>المبلغ</label>
                <input type="number" name="amount" placeholder="مثال: 10000" required>
            </div>

            <div class="form-group">
                <label>العملة</label>
                <select name="currency" class="input-select">
                    <option value="SYP" selected>ليرة سورية</option>
                    <option value="USD">دولار</option>
                    <option value="EUR">يورو</option>
                </select>
            </div>

            <div class="form-group">
                <label>ملاحظة</label>
                <input type="text" name="note" placeholder="مثال: تجديد باقة 10GB">
            </div>

            <button class="btn btn-primary" type="submit">
                تسجيل الدفعة
            </button>

        </form>

        <div class="stat" style="margin-top:18px;">
            <div class="label">آخر الدفعات</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                <?php if (count($payments) === 0): ?>
                    لا توجد دفعات حالياً.
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <div style="border-bottom:1px solid #e5e7eb; padding:10px 0;">
                            <strong><?= htmlspecialchars($payment['username']) ?></strong>
                            <br>

                            المبلغ:
                            <?= htmlspecialchars((string) $payment['amount']) ?>
                            <?= htmlspecialchars($payment['currency']) ?>
                            <br>

                            الحالة:
                            <?= htmlspecialchars($payment['status']) ?>
                            <br>

                            الملاحظة:
                            <?= htmlspecialchars($payment['note'] ?: '-') ?>
                            <br>

                            التاريخ:
                            <?= htmlspecialchars($payment['paid_at']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <a class="btn btn-outline" href="/admin/customers">
            👥 إدارة الزبائن
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Payments
        </div>

    </div>
</div>