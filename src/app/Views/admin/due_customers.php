<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">!</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>الزبائن غير المدفوعين</p>

            <div class="status-pill">
                <span class="dot"></span>
                متابعة التحصيل
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تعرض الزبائن الذين يحتاجون متابعة دفع:
            عليه دفع، مؤجل، أو غير معروف.
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">⚠️ الإجمالي</div>
                <div class="value"><?= htmlspecialchars((string) ($unpaid_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">عليه دفع</div>
                <div class="value"><?= htmlspecialchars((string) ($due_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">مؤجل</div>
                <div class="value"><?= htmlspecialchars((string) ($pending_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">غير معروف</div>
                <div class="value"><?= htmlspecialchars((string) ($unknown_count ?? 0)) ?></div>
            </div>

        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">قائمة المتابعة</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                <?php if (count($customers) === 0): ?>
                    لا يوجد زبائن غير مدفوعين حالياً.
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <?php
                            $status = $customer['payment_status'];

                            $label = match ($status) {
                                'due' => 'عليه دفع',
                                'pending' => 'مؤجل',
                                'unknown' => 'غير معروف',
                                default => $status,
                            };

                            $phone = trim((string) ($customer['phone'] ?? ''));
                            $whatsappPhone = '';

                            if ($phone !== '') {
                                $whatsappPhone = '963' . ltrim($phone, '0');
                            }
                        ?>

                        <div style="border-bottom:1px solid #e5e7eb; padding:12px 0;">
                            <strong><?= htmlspecialchars($customer['username']) ?></strong>
                            —
                            <?= htmlspecialchars($label) ?>
                            <br>

                            الاسم:
                            <?= htmlspecialchars($customer['display_name'] ?: '-') ?>
                            <br>

                            الهاتف:
                            <?= htmlspecialchars($customer['phone'] ?: '-') ?>
                            <br>

                            نوع الوصول:
                            <?= htmlspecialchars($customer['access_type']) ?>
                            <br>

                            ملاحظات:
                            <?= htmlspecialchars($customer['notes'] ?: '-') ?>

                            <?php if ($whatsappPhone !== ''): ?>
                                <a class="btn btn-outline" style="margin-top:10px;" href="https://wa.me/<?= htmlspecialchars($whatsappPhone) ?>">
                                    مراسلة واتساب
                                </a>
                            <?php endif; ?>

                            <form method="post" action="/admin/customers/status" style="margin-top:10px;">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($customer['username']) ?>">
                                <input type="hidden" name="redirect_to" value="/admin/customers/due">

                                <div class="form-group">
                                    <label>تعديل حالة الدفع</label>
                                    <select name="payment_status" class="input-select">
                                        <option value="paid">مدفوع</option>
                                        <option value="due" <?= $status === 'due' ? 'selected' : '' ?>>عليه دفع</option>
                                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>مؤجل</option>
                                        <option value="unknown" <?= $status === 'unknown' ? 'selected' : '' ?>>غير معروف</option>
                                    </select>
                                </div>

                                <button class="btn btn-outline" type="submit">
                                    حفظ الحالة
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <a class="btn btn-primary" href="/admin/payments">
            💳 تسجيل دفعة
        </a>

        <a class="btn btn-outline" href="/admin/customers">
            👥 إدارة الزبائن
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Collection Follow-up
        </div>

    </div>
</div>