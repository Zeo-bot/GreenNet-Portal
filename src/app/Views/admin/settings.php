<?php
    $flashType = $flash['type'] ?? '';
    $flashMessage = $flash['message'] ?? '';

    $logoPath = $settings['site_logo_path'] ?? '';
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark <?= $logoPath !== '' ? 'has-logo' : '' ?>">
                <?php if ($logoPath !== ''): ?>
                    <img class="brand-logo" src="<?= htmlspecialchars($logoPath) ?>" alt="Logo">
                <?php else: ?>
                    G
                <?php endif; ?>
            </div>

            <h1><?= htmlspecialchars($settings['app_name'] ?? 'GreenNet') ?></h1>
            <p>إعدادات النظام والهوية</p>

            <div class="status-pill">
                <span class="dot"></span>
                Settings
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تتحكم بالاسم، الشعار، الألوان، أرقام التواصل، وما يظهر للمشتركين.
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div class="notice" style="<?= $flashType === 'success' ? 'background:#f0fdf4;color:#166534;' : 'background:#fee2e2;color:#991b1b;' ?>">
                <?= htmlspecialchars($flashMessage) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/admin/settings" enctype="multipart/form-data">

            <div class="stat">
                <div class="label">هوية الشبكة</div>

                <div class="form-group">
                    <label>اسم الشبكة</label>
                    <input
                        type="text"
                        name="network_name"
                        value="<?= htmlspecialchars($settings['network_name'] ?? 'GreenNet') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>اسم التطبيق</label>
                    <input
                        type="text"
                        name="app_name"
                        value="<?= htmlspecialchars($settings['app_name'] ?? 'GreenNet') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>عنوان الصفحات</label>
                    <input
                        type="text"
                        name="app_title"
                        value="<?= htmlspecialchars($settings['app_title'] ?? 'GreenNet Portal') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>لون الثيم</label>
                    <input
                        type="color"
                        name="theme_color"
                        value="<?= htmlspecialchars($settings['theme_color'] ?? '#16a34a') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>رفع شعار الشبكة</label>
                    <input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp,image/gif">
                </div>

                <?php if ($logoPath !== ''): ?>
                    <div class="notice" style="background:#f9fafb;color:#374151;">
                        الشعار الحالي:
                        <br>
                        <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" style="width:90px;height:90px;object-fit:contain;margin-top:10px;background:white;border:1px solid #e5e7eb;border-radius:16px;padding:6px;">
                        <br>
                        <label style="display:block;margin-top:12px;">
                            <input type="checkbox" name="remove_logo" value="1">
                            حذف الشعار الحالي
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="stat" style="margin-top:18px;">
                <div class="label">التواصل والدعم</div>

                <div class="form-group">
                    <label>رقم الدعم الظاهر للمشترك</label>
                    <input
                        type="text"
                        name="support_phone"
                        value="<?= htmlspecialchars($settings['support_phone'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>رمز الدولة</label>
                    <input
                        type="text"
                        name="support_country_code"
                        value="<?= htmlspecialchars($settings['support_country_code'] ?? '963') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>رقم واتساب الدولي</label>
                    <input
                        type="text"
                        name="support_whatsapp"
                        value="<?= htmlspecialchars($settings['support_whatsapp'] ?? '') ?>"
                        placeholder="مثال: 963966393915"
                    >
                </div>

                <div class="form-group">
                    <label>نص الدعم</label>
                    <textarea name="support_text" rows="3"><?= htmlspecialchars($settings['support_text'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="stat" style="margin-top:18px;">
                <div class="label">نصوص الواجهة</div>

                <div class="form-group">
                    <label>نص الترحيب في صفحة الدخول</label>
                    <textarea name="login_welcome_text" rows="3"><?= htmlspecialchars($settings['login_welcome_text'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>نص الترحيب في لوحة المشترك</label>
                    <textarea name="subscriber_welcome_text" rows="3"><?= htmlspecialchars($settings['subscriber_welcome_text'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>نص أسفل الصفحات</label>
                    <input
                        type="text"
                        name="footer_text"
                        value="<?= htmlspecialchars($settings['footer_text'] ?? 'GreenNet Portal') ?>"
                    >
                </div>
            </div>

            <div class="stat" style="margin-top:18px;">
                <div class="label">الدفع والاشتراك</div>

                <div class="form-group">
                    <label>العملة الافتراضية</label>
                    <input
                        type="text"
                        name="default_currency"
                        value="<?= htmlspecialchars($settings['default_currency'] ?? 'SYP') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>رسالة واتساب الافتراضية للتجديد</label>
                    <textarea name="whatsapp_renew_message" rows="6"><?= htmlspecialchars($settings['whatsapp_renew_message'] ?? '') ?></textarea>
                    <small>
                        يمكنك استخدام:
                        {username}
                        {package}
                        {expires_at}
                    </small>
                </div>

                <label style="display:block;margin:10px 0;">
                    <input
                        type="checkbox"
                        name="show_price_to_subscriber"
                        value="1"
                        <?= (($settings['show_price_to_subscriber'] ?? '1') === '1') ? 'checked' : '' ?>
                    >
                    إظهار السعر للمشترك
                </label>

                <label style="display:block;margin:10px 0;">
                    <input
                        type="checkbox"
                        name="show_quota_to_subscriber"
                        value="1"
                        <?= (($settings['show_quota_to_subscriber'] ?? '1') === '1') ? 'checked' : '' ?>
                    >
                    إظهار حجم الباقة للمشترك
                </label>

                <label style="display:block;margin:10px 0;">
                    <input
                        type="checkbox"
                        name="show_mikrotik_profile_to_subscriber"
                        value="1"
                        <?= (($settings['show_mikrotik_profile_to_subscriber'] ?? '0') === '1') ? 'checked' : '' ?>
                    >
                    إظهار MikroTik Profile للمشترك
                </label>
            </div>

            <button class="btn btn-primary" type="submit">
                حفظ الإعدادات
            </button>

        </form>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <a class="btn btn-outline" href="/login">
            معاينة صفحة الدخول
        </a>

        <a class="btn btn-outline" href="/dashboard">
            معاينة لوحة المشترك
        </a>

        <div class="footer">
            <?= htmlspecialchars($settings['footer_text'] ?? 'GreenNet Settings') ?>
        </div>

    </div>
</div>