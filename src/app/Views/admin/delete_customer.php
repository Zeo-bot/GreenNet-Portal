<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">!</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>تأكيد حذف الزبون</p>
        </div>

        <div class="notice" style="background:#fee2e2;color:#991b1b;">
            أنت على وشك حذف هذا الزبون من قاعدة GreenNet المحلية فقط.
            هذا لا يحذف حساب MikroTik أو User Manager.
        </div>

        <div class="stat">
            <div class="label">اسم المستخدم</div>
            <div class="value"><?= htmlspecialchars($customer['username'] ?? '-') ?></div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">اسم الزبون</div>
            <div class="value"><?= htmlspecialchars($customer['display_name'] ?: '-') ?></div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">الهاتف</div>
            <div class="value"><?= htmlspecialchars($customer['phone'] ?: '-') ?></div>
        </div>

        <form method="post" action="/admin/customers/delete" style="margin-top:18px;">
            <input type="hidden" name="username" value="<?= htmlspecialchars($customer['username'] ?? '') ?>">

            <div class="form-group">
                <label>للتأكيد اكتب DELETE</label>
                <input type="text" name="confirm" placeholder="DELETE" required>
            </div>

            <button class="btn btn-danger" type="submit">
                تأكيد الحذف
            </button>
        </form>

        <a class="btn btn-outline" href="/admin/customers">
            إلغاء والعودة
        </a>

        <div class="footer">
            GreenNet Safe Delete
        </div>

    </div>
</div>