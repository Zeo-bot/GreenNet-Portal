<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">📦</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>تعيين باقة المشترك</p>

            <div class="status-pill">
                <span class="dot"></span>
                CRM Package
            </div>
        </div>

        <div class="notice">
            هذا الربط يتم داخل GreenNet فقط. لا يتم تعديل بروفايل المستخدم على MikroTik في هذه الخطوة.
        </div>

        <div class="stat">
            <div class="label">المشترك</div>
            <div class="value" style="font-size:15px; line-height:1.8;">
                Username:
                <?= htmlspecialchars($customer['username'] ?? '-') ?>
                <br>

                الاسم:
                <?= htmlspecialchars(($customer['display_name'] ?? '') !== '' ? $customer['display_name'] : '-') ?>
                <br>

                الهاتف:
                <?= htmlspecialchars(($customer['phone'] ?? '') !== '' ? $customer['phone'] : '-') ?>
            </div>
        </div>

        <form method="post" action="/admin/customers/package/update">

            <input type="hidden" name="username" value="<?= htmlspecialchars($customer['username'] ?? '') ?>">

            <div class="form-group">
                <label>اختر الباقة</label>

                <select name="package_id" class="input-select">
                    <option value="0">
                        بدون باقة
                    </option>

                    <?php foreach ($packages as $package): ?>
                        <option
                            value="<?= htmlspecialchars((string) $package['id']) ?>"
                            <?= ((int) ($customer['package_id'] ?? 0)) === ((int) $package['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($package['name']) ?>
                            —
                            <?= htmlspecialchars($package['access_type']) ?>
                            —
                            <?= htmlspecialchars((string) $package['price']) ?>
                            <?= htmlspecialchars($package['currency']) ?>
                            —
                            <?= htmlspecialchars((string) $package['duration_days']) ?> يوم
                            —
                            <?= htmlspecialchars((string) $package['quota_gb']) ?> GB
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (count($packages) === 0): ?>
                <div class="notice" style="background:#fff7ed;color:#92400e;">
                    لا توجد باقات فعالة حالياً. اذهب إلى إدارة الباقات واستورد بروفايلات MikroTik أولاً.
                </div>
            <?php endif; ?>

            <button class="btn btn-primary" type="submit">
                حفظ الباقة
            </button>

        </form>

        <a class="btn btn-outline" href="/admin/packages">
            إدارة الباقات
        </a>

        <a class="btn btn-outline" href="/admin/customers/profile?username=<?= urlencode($customer['username'] ?? '') ?>">
            العودة لملف المشترك
        </a>

        <a class="btn btn-outline" href="/admin/customers">
            إدارة الزبائن
        </a>

        <div class="footer">
            GreenNet Customer Package
        </div>

    </div>
</div>