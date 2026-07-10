<?php
    $flashType = $flash['type'] ?? '';
    $flashMessage = $flash['message'] ?? '';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            أمان المدير
        </h1>

        <p class="admin-page-description">
            من هنا يمكنك تغيير كلمة مرور حساب المدير الحالي. هذه الخطوة مهمة قبل أي ربط أو أوامر كتابة على MikroTik.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin">لوحة المدير</a>
        <a class="admin-mini-btn" href="/admin/logs">Logs</a>
    </div>
</div>

<?php if ($flashMessage !== ''): ?>
    <div class="notice" style="<?= $flashType === 'success' ? 'background:#f0fdf4;color:#166534;' : 'background:#fee2e2;color:#991b1b;' ?>">
        <?= htmlspecialchars($flashMessage) ?>
    </div>
<?php endif; ?>

<div class="admin-two-columns">

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            تغيير كلمة مرور المدير
        </h2>

        <p class="admin-section-subtitle">
            الحساب الحالي:
            <strong><?= htmlspecialchars($admin_username ?? 'admin') ?></strong>
        </p>

        <form method="post" action="/admin/security/password">

            <div class="form-group">
                <label>كلمة المرور الحالية</label>
                <input
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-group">
                <label>كلمة المرور الجديدة</label>
                <input
                    type="password"
                    name="new_password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
                <small>يفضل أن تكون 8 أحرف على الأقل، وتحتوي أرقاماً وحروفاً.</small>
            </div>

            <div class="form-group">
                <label>تأكيد كلمة المرور الجديدة</label>
                <input
                    type="password"
                    name="confirm_password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
            </div>

            <button class="btn btn-primary" type="submit">
                حفظ كلمة المرور الجديدة
            </button>

        </form>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            ملاحظات أمان مهمة
        </h2>

        <div class="admin-checklist" style="grid-template-columns:1fr;">

            <div class="admin-check-item">
                <div class="admin-check-icon">✓</div>
                <div>
                    <strong>لا تعتمد على ملف .env بعد أول تثبيت</strong>
                    <br>
                    كلمة مرور المدير محفوظة داخل قاعدة البيانات، لذلك تغيير ADMIN_PASSWORD في .env لا يغير الحساب الحالي.
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon warning">!</div>
                <div>
                    <strong>استخدم كلمة مرور قوية</strong>
                    <br>
                    لا تستخدم كلمة مرور مثل admin أو 12345678 قبل تشغيل النظام الحقيقي.
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon future">🔒</div>
                <div>
                    <strong>المرحلة القادمة</strong>
                    <br>
                    سنضيف لاحقاً CSRF Protection، وإغلاق صفحات التطوير عند وضع production.
                </div>
            </div>

        </div>
    </section>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">
        قائمة أمان قبل MikroTik Write
    </h2>

    <p class="admin-section-subtitle">
        هذه العناصر سيتم استكمالها قبل أول أمر تعديل فعلي على الراوتر.
    </p>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">✓</div>
            <div>
                <strong>تغيير كلمة مرور المدير</strong>
                <br>
                أصبحت متاحة الآن من هذه الصفحة.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon warning">!</div>
            <div>
                <strong>CSRF Protection</strong>
                <br>
                سنضيف حماية للنماذج قبل أي أوامر خطيرة.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon warning">!</div>
            <div>
                <strong>إغلاق صفحات التطوير</strong>
                <br>
                سيتم منع /install و /dev/database عند production.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">↻</div>
            <div>
                <strong>Backup حديث قبل الأوامر</strong>
                <br>
                لاحقاً سنضيف تحذير إذا لم يوجد Backup حديث قبل MikroTik Write.
            </div>
        </div>

    </div>
</section>