<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">G</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>تعديل بيانات الزبون</p>

            <div class="status-pill">
                <span class="dot"></span>
                <?= htmlspecialchars($customer['username'] ?? '-') ?>
            </div>
        </div>

        <div class="notice">
            اسم المستخدم ثابت حالياً لأنه سيكون مفتاح الربط مع MikroTik و User Manager والدفعات.
        </div>

        <form method="post" action="/admin/customers/update">

            <input type="hidden" name="username" value="<?= htmlspecialchars($customer['username'] ?? '') ?>">

            <div class="form-group">
                <label>اسم المستخدم</label>
                <input type="text" value="<?= htmlspecialchars($customer['username'] ?? '') ?>" disabled>
            </div>

            <div class="form-group">
                <label>اسم الزبون</label>
                <input
                    type="text"
                    name="display_name"
                    value="<?= htmlspecialchars($customer['display_name'] ?? '') ?>"
                    placeholder="مثال: أحمد محمد"
                >
            </div>

            <div class="form-group">
                <label>رقم الهاتف</label>
                <input
                    type="text"
                    name="phone"
                    value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                    placeholder="09xxxxxxxx"
                >
            </div>

            <div class="form-group">
                <label>نوع الوصول</label>
                <select name="access_type" class="input-select">
                    <option value="hotspot" <?= ($customer['access_type'] ?? '') === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                    <option value="ppp" <?= ($customer['access_type'] ?? '') === 'ppp' ? 'selected' : '' ?>>PPP / PPPoE</option>
                    <option value="hybrid" <?= ($customer['access_type'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                </select>
            </div>

            <div class="form-group">
                <label>الراوتر المستهدف</label>
                <select name="router_id" class="input-select">
                    <option value="">الراوتر الافتراضي / إعداد التوافق القديم</option>
                    <?php foreach (($routers ?? []) as $router): ?>
                        <option value="<?= (int) ($router['id'] ?? 0) ?>" <?= (int) ($customer['router_id'] ?? 0) === (int) ($router['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($router['name'] ?? '')) ?> — <?= htmlspecialchars((string) ($router['host'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>تغيير التعيين لا ينقل أو يحذف حساب RouterOS تلقائياً.</small>
            </div>

            <div class="form-group">
                <label>نظام الحساب</label>
                <select name="service_backend" class="input-select">
                    <option value="user-manager" <?= ($customer['service_backend'] ?? 'user-manager') === 'user-manager' ? 'selected' : '' ?>>User Manager</option>
                    <option value="native-hotspot" <?= ($customer['service_backend'] ?? '') === 'native-hotspot' ? 'selected' : '' ?>>Native Hotspot</option>
                    <option value="native-pppoe" <?= ($customer['service_backend'] ?? '') === 'native-pppoe' ? 'selected' : '' ?>>Native PPPoE</option>
                </select>
            </div>

            <div class="form-group">
                <label>حالة الدفع</label>
                <select name="payment_status" class="input-select">
                    <option value="paid" <?= ($customer['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>مدفوع</option>
                    <option value="due" <?= ($customer['payment_status'] ?? '') === 'due' ? 'selected' : '' ?>>عليه دفع</option>
                    <option value="pending" <?= ($customer['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>مؤجل</option>
                    <option value="unknown" <?= ($customer['payment_status'] ?? '') === 'unknown' ? 'selected' : '' ?>>غير معروف</option>
                </select>
            </div>

            <div class="form-group">
                <label>ملاحظات</label>
                <input
                    type="text"
                    name="notes"
                    value="<?= htmlspecialchars($customer['notes'] ?? '') ?>"
                    placeholder="مثال: يدفع يوم الجمعة"
                >
            </div>

            <button class="btn btn-primary" type="submit">
                حفظ التعديلات
            </button>

        </form>

        <a class="btn btn-outline" href="/admin/customers">
            العودة لإدارة الزبائن
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Customer Edit
        </div>

    </div>
</div>
