<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">✏️</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>تعديل الباقة</p>

            <div class="status-pill">
                <span class="dot"></span>
                Package Edit
            </div>
        </div>

        <div class="notice">
            هذا التعديل يتم داخل GreenNet فقط، ولا يغير بروفايل MikroTik.
        </div>

        <div class="stat">
            <div class="label">مصدر الباقة</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                Source:
                <?= htmlspecialchars($package['source_type'] ?? '-') ?>
                <br>

                MikroTik Profile:
                <?= htmlspecialchars($package['source_profile'] ?? '-') ?>
                <br>

                Rate Limit:
                <span style="direction:ltr; display:inline-block;">
                    <?= htmlspecialchars($package['rate_limit'] ?? '-') ?>
                </span>
            </div>
        </div>

        <form method="post" action="/admin/packages/update">

            <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($package['id'] ?? 0)) ?>">

            <div class="form-group">
                <label>اسم الباقة</label>
                <input
                    type="text"
                    name="name"
                    value="<?= htmlspecialchars($package['name'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>السعر</label>
                <input
                    type="number"
                    name="price"
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
                <label>الصلاحية بالأيام</label>
                <input
                    type="number"
                    name="duration_days"
                    value="<?= htmlspecialchars((string) ($package['duration_days'] ?? 0)) ?>"
                    min="0"
                >
            </div>

            <div class="form-group">
                <label>الحجم بالغيغا GB</label>
                <input
                    type="number"
                    step="0.01"
                    name="quota_gb"
                    value="<?= htmlspecialchars((string) ($package['quota_gb'] ?? 0)) ?>"
                    min="0"
                >
            </div>

            <div class="form-group">
                <label>حالة الباقة</label>
                <select name="is_active" class="input-select">
                    <option value="1" <?= ((int) ($package['is_active'] ?? 0)) === 1 ? 'selected' : '' ?>>
                        فعالة
                    </option>
                    <option value="0" <?= ((int) ($package['is_active'] ?? 0)) === 0 ? 'selected' : '' ?>>
                        متوقفة
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>ملاحظات</label>
                <textarea name="notes" rows="4"><?= htmlspecialchars($package['notes'] ?? '') ?></textarea>
            </div>

            <button class="btn btn-primary" type="submit">
                حفظ التعديلات
            </button>

        </form>

        <a class="btn btn-outline" href="/admin/packages">
            العودة للباقات
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Package Edit
        </div>

    </div>
</div>