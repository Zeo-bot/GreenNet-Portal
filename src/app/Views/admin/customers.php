<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">G</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>إدارة الزبائن</p>

            <div class="status-pill">
                <span class="dot"></span>
                CRM محلي
            </div>
        </div>

        <div class="notice">
            هذه الصفحة لإدارة بيانات الزبائن محلياً. يمكنك أيضاً ربط كل زبون بباقة GreenNet.
        </div>

        <a class="btn btn-primary" href="/admin/customers/sync">
            🔄 مزامنة MikroTik مع CRM
        </a>

        <a class="btn btn-primary" href="/admin/packages">
            📦 إدارة الباقات
        </a>

        <form method="post" action="/admin/customers/store">

            <div class="form-group">
                <label>اسم المستخدم</label>
                <input type="text" name="username" placeholder="مثال: ahmad01" required>
            </div>

            <div class="form-group">
                <label>اسم الزبون</label>
                <input type="text" name="display_name" placeholder="مثال: أحمد محمد">
            </div>

            <div class="form-group">
                <label>رقم الهاتف</label>
                <input type="text" name="phone" placeholder="09xxxxxxxx">
            </div>

            <div class="form-group">
                <label>نوع الوصول</label>
                <select name="access_type" class="input-select">
                    <option value="hotspot">Hotspot</option>
                    <option value="ppp">PPP / PPPoE</option>
                    <option value="hybrid" selected>Hybrid</option>
                </select>
            </div>

            <div class="form-group">
                <label>حالة الدفع</label>
                <select name="payment_status" class="input-select">
                    <option value="paid">مدفوع</option>
                    <option value="due">عليه دفع</option>
                    <option value="pending">مؤجل</option>
                    <option value="unknown" selected>غير معروف</option>
                </select>
            </div>

            <div class="form-group">
                <label>ملاحظات</label>
                <input type="text" name="notes" placeholder="مثال: يدفع يوم الجمعة">
            </div>

            <button class="btn btn-primary" type="submit">
                إضافة الزبون
            </button>

        </form>

        <div class="stat" style="margin-top:18px;">
            <div class="label">قائمة الزبائن</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                <?php if (count($customers) === 0): ?>
                    لا يوجد زبائن حالياً.
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <?php
                            $status = $customer['payment_status'];

                            $label = match ($status) {
                                'paid' => 'مدفوع',
                                'due' => 'عليه دفع',
                                'pending' => 'مؤجل',
                                default => 'غير معروف',
                            };
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

                            النوع:
                            <?= htmlspecialchars($customer['access_type']) ?>
                            <br>

                            رقم الباقة:
                            <?= htmlspecialchars((string) ($customer['package_id'] ?? 0)) ?>
                            <br>

                            ملاحظات:
                            <?= htmlspecialchars($customer['notes'] ?: '-') ?>

                            <a class="btn btn-primary" href="/admin/customers/profile?username=<?= urlencode($customer['username']) ?>">
                                ملف المشترك الكامل
                            </a>

                            <a class="btn btn-primary" href="/admin/customers/package?username=<?= urlencode($customer['username']) ?>">
                                تعيين الباقة
                            </a>

                            <a class="btn btn-primary" href="/admin/customers/edit?username=<?= urlencode($customer['username']) ?>">
                                تعديل بيانات الزبون
                            </a>

                            <a class="btn btn-danger" href="/admin/customers/delete?username=<?= urlencode($customer['username']) ?>">
                                حذف الزبون المحلي
                            </a>

                            <form method="post" action="/admin/customers/status" style="margin-top:10px;">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($customer['username']) ?>">
                                <input type="hidden" name="redirect_to" value="/admin/customers">

                                <div class="form-group">
                                    <label>تعديل حالة الدفع السريع</label>
                                    <select name="payment_status" class="input-select">
                                        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>مدفوع</option>
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

        <a class="btn btn-primary" href="/admin/customers/due">
            ⚠️ الزبائن غير المدفوعين
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Customers CRM
        </div>

    </div>
</div>