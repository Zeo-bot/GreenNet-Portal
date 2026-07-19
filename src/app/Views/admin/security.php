<?php

declare(strict_types=1);

if (!function_exists('gn_sec_h')) {
    function gn_sec_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_sec_date')) {
    function gn_sec_date(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

$security = is_array($security ?? null) ? $security : [];
$success = (string) ($success ?? '');
$error = (string) ($error ?? '');

$adminUsername = (string) ($security['admin_username'] ?? 'admin');
$sessionUser = (string) ($security['session_user'] ?? $adminUsername);
$hasPasswordHash = (bool) ($security['has_password_hash'] ?? false);
$passwordChangedAt = (string) ($security['password_changed_at'] ?? '');
$failedAttempts = (string) ($security['failed_login_attempts'] ?? '0');
$lockedUntil = (string) ($security['locked_until'] ?? '');
$envPasswordExists = (bool) ($security['env_password_exists'] ?? false);
$ipAddress = (string) ($security['ip_address'] ?? '');

$successMap = [
    'profile_saved' => 'تم حفظ بيانات المدير.',
    'password_changed' => 'تم تغيير كلمة مرور المدير بنجاح.',
    'failed_reset' => 'تم تصفير محاولات الدخول الفاشلة.',
];

?>

<style>
    .gn-sec-page {
        display: grid;
        gap: 18px;
    }

    .gn-sec-hero {
        position: relative;
        overflow: hidden;
        padding: 26px;
        border-radius: var(--gn-radius-xl);
        background:
            radial-gradient(circle at 0% 0%, rgba(32, 201, 120, 0.16), transparent 30%),
            linear-gradient(135deg, var(--gn-surface), var(--gn-surface-2));
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-sec-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-sec-hero p {
        margin: 10px 0 0;
        max-width: 930px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-sec-hero-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-sec-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-sec-kpi {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-sec-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-sec-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-sec-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-sec-kpi.is-danger::before {
        background: var(--gn-danger);
    }

    .gn-sec-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-sec-kpi-value {
        margin-top: 7px;
        color: var(--gn-text);
        font-size: 22px;
        font-weight: 950;
        line-height: 1.35;
        word-break: break-word;
    }

    .gn-sec-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(320px, 0.58fr);
        gap: 18px;
        align-items: start;
    }

    .gn-sec-card,
    .gn-sec-section {
        padding: 20px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-sec-card h2,
    .gn-sec-section h2 {
        margin: 0 0 14px;
        color: var(--gn-text);
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-sec-form {
        display: grid;
        gap: 18px;
    }

    .gn-sec-section {
        display: grid;
        gap: 14px;
    }

    .gn-sec-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-sec-field {
        display: grid;
        gap: 7px;
        min-width: 0;
    }

    .gn-sec-field.is-wide {
        grid-column: 1 / -1;
    }

    .gn-sec-field label {
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 950;
    }

    .gn-sec-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        outline: none;
        box-sizing: border-box;
    }

    .gn-sec-field input:focus {
        border-color: var(--gn-primary);
        box-shadow: 0 0 0 4px rgba(17, 148, 90, 0.14);
    }

    .gn-sec-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .gn-sec-alert {
        padding: 14px 16px;
        border-radius: var(--gn-radius-lg);
        font-weight: 900;
        line-height: 1.7;
    }

    .gn-sec-alert.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.22);
    }

    .gn-sec-alert.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.22);
    }

    .gn-sec-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        width: fit-content;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 950;
        white-space: nowrap;
    }

    .gn-sec-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-sec-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-sec-badge.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-sec-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-sec-list {
        display: grid;
        gap: 10px;
    }

    .gn-sec-item {
        padding: 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-sec-item-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 5px;
    }

    .gn-sec-item-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        line-height: 1.55;
        word-break: break-word;
    }

    .gn-sec-note {
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        line-height: 1.75;
        font-weight: 850;
    }

    .gn-sec-danger-zone {
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        line-height: 1.75;
        font-weight: 850;
    }

    html[data-theme="greennet-dark"] .gn-sec-hero,
    html[data-theme="greennet-dark"] .gn-sec-kpi,
    html[data-theme="greennet-dark"] .gn-sec-card,
    html[data-theme="greennet-dark"] .gn-sec-section {
        background:
            radial-gradient(circle at 0% 0%, rgba(46, 230, 139, 0.07), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2)) !important;
    }

    @media (max-width: 1200px) {
        .gn-sec-layout {
            grid-template-columns: 1fr;
        }

        .gn-sec-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-sec-grid,
        .gn-sec-kpis {
            grid-template-columns: 1fr;
        }

        .gn-sec-hero h1 {
            font-size: 26px;
        }
    }
</style>

<div class="gn-sec-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">الأمان</h1>
            <p class="admin-page-description">
                إدارة بيانات دخول المدير وحالة كلمة المرور بدون أي اتصال مباشر مع MikroTik.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/settings">الإعدادات</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/audit">Audit Center</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/security">Refresh</a>
        </div>
    </div>

    <?php if ($success !== '' && isset($successMap[$success])): ?>
        <div class="gn-sec-alert is-success">
            <?= gn_sec_h($successMap[$success]) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="gn-sec-alert is-danger">
            <?= gn_sec_h($error) ?>
        </div>
    <?php endif; ?>

    <section class="gn-sec-hero">
        <h1>مركز أمان GreenNet</h1>
        <p>
            هنا نضبط حساب المدير وكلمة المرور. هذه الصفحة لا تتصل بالراوتر ولا تنفذ أي أوامر MikroTik.
        </p>

        <div class="gn-sec-hero-actions">
            <a class="gn-btn gn-btn-primary" href="#password-form">تغيير كلمة المرور</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/logout">تسجيل خروج</a>
        </div>
    </section>

    <section class="gn-sec-kpis">
        <div class="gn-sec-kpi">
            <div class="gn-sec-kpi-label">Admin Username</div>
            <div class="gn-sec-kpi-value" dir="ltr"><?= gn_sec_h($adminUsername) ?></div>
        </div>

        <div class="gn-sec-kpi <?= $hasPasswordHash ? 'is-success' : 'is-warning' ?>">
            <div class="gn-sec-kpi-label">Password Source</div>
            <div class="gn-sec-kpi-value">
                <?= $hasPasswordHash ? 'Database Hash' : ($envPasswordExists ? '.env Password' : 'Not Set') ?>
            </div>
        </div>

        <div class="gn-sec-kpi is-info">
            <div class="gn-sec-kpi-label">Last Changed</div>
            <div class="gn-sec-kpi-value" dir="ltr"><?= gn_sec_h(gn_sec_date($passwordChangedAt)) ?></div>
        </div>

        <div class="gn-sec-kpi <?= (int) $failedAttempts > 0 ? 'is-warning' : 'is-success' ?>">
            <div class="gn-sec-kpi-label">Failed Attempts</div>
            <div class="gn-sec-kpi-value"><?= gn_sec_h($failedAttempts) ?></div>
        </div>
    </section>

    <section class="gn-sec-layout">

        <div class="gn-sec-form">

            <section class="gn-sec-section">
                <h2>بيانات المدير</h2>

                <form
                    method="post"
                    action="/admin/security"
                    data-gn-form-wrapped="1"
                    data-gn-form-enhanced="1"
                    data-gn-fields-grouped="1"
                >
                    <input type="hidden" name="gn_security_action" value="profile">

                    <div class="gn-sec-grid">
                        <div class="gn-sec-field">
                            <label>اسم المدير</label>
                            <input type="text" name="admin_username" value="<?= gn_sec_h($adminUsername) ?>" dir="ltr" required>
                        </div>

                        <div class="gn-sec-field">
                            <label>المستخدم الحالي في الجلسة</label>
                            <input type="text" value="<?= gn_sec_h($sessionUser) ?>" dir="ltr" readonly>
                        </div>
                    </div>

                    <div class="gn-sec-actions">
                        <button class="gn-btn gn-btn-primary" type="submit">
                            حفظ اسم المدير
                        </button>
                    </div>
                </form>
            </section>

            <section class="gn-sec-section" id="password-form">
                <h2>تغيير كلمة المرور</h2>

                <form
                    method="post"
                    action="/admin/security"
                    autocomplete="off"
                    data-gn-form-wrapped="1"
                    data-gn-form-enhanced="1"
                    data-gn-fields-grouped="1"
                >
                    <input type="hidden" name="gn_security_action" value="password">

                    <div class="gn-sec-grid">
                        <div class="gn-sec-field is-wide">
                            <label>كلمة المرور الحالية</label>
                            <input type="password" name="current_password" autocomplete="current-password" required>
                        </div>

                        <div class="gn-sec-field">
                            <label>كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
                        </div>

                        <div class="gn-sec-field">
                            <label>تأكيد كلمة المرور الجديدة</label>
                            <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                        </div>
                    </div>

                    <div class="gn-sec-actions">
                        <button class="gn-btn gn-btn-primary" type="submit">
                            تغيير كلمة المرور
                        </button>
                    </div>
                </form>

                <div class="gn-sec-note">
                    بعد تغيير كلمة المرور، يفضل تسجيل الخروج والدخول من جديد للتأكد من أن النظام يستخدم البيانات الجديدة.
                </div>
            </section>

            <section class="gn-sec-section">
                <h2>إجراءات أمان إضافية</h2>

                <form
                    method="post"
                    action="/admin/security"
                    data-gn-form-wrapped="1"
                    data-gn-form-enhanced="1"
                    data-gn-fields-grouped="1"
                >
                    <input type="hidden" name="gn_security_action" value="reset_failed_logins">

                    <div class="gn-sec-actions">
                        <button class="gn-btn gn-btn-secondary" type="submit">
                            تصفير محاولات الدخول الفاشلة
                        </button>
                    </div>
                </form>

                <div class="gn-sec-danger-zone">
                    لا تشارك ملف reset-admin-once.php أو تتركه على شبكة عامة قبل النشر الحقيقي. هذا الملف يجب حذفه لاحقاً قبل التشغيل النهائي.
                </div>
            </section>

        </div>

        <aside class="gn-sec-card">
            <h2>ملخص الأمان</h2>

            <div class="gn-sec-list">
                <div class="gn-sec-item">
                    <span class="gn-sec-item-label">Admin</span>
                    <span class="gn-sec-item-value" dir="ltr"><?= gn_sec_h($adminUsername) ?></span>
                </div>

                <div class="gn-sec-item">
                    <span class="gn-sec-item-label">Password Hash</span>
                    <span class="gn-sec-item-value">
                        <?php if ($hasPasswordHash): ?>
                            <span class="gn-sec-badge is-success">موجود</span>
                        <?php else: ?>
                            <span class="gn-sec-badge is-warning">غير موجود بقاعدة البيانات</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="gn-sec-item">
                    <span class="gn-sec-item-label">ENV Password</span>
                    <span class="gn-sec-item-value">
                        <?php if ($envPasswordExists): ?>
                            <span class="gn-sec-badge is-info">موجود</span>
                        <?php else: ?>
                            <span class="gn-sec-badge is-warning">غير موجود</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="gn-sec-item">
                    <span class="gn-sec-item-label">Locked Until</span>
                    <span class="gn-sec-item-value" dir="ltr"><?= gn_sec_h($lockedUntil !== '' ? $lockedUntil : '-') ?></span>
                </div>

                <div class="gn-sec-item">
                    <span class="gn-sec-item-label">Your IP</span>
                    <span class="gn-sec-item-value" dir="ltr"><?= gn_sec_h($ipAddress !== '' ? $ipAddress : '-') ?></span>
                </div>
            </div>

            <div class="gn-sec-note" style="margin-top: 14px;">
                صفحة الأمان الحالية مخصصة لحساب المدير فقط. لاحقاً يمكن إضافة سجل جلسات، صلاحيات متعددة، و2FA.
            </div>
        </aside>

    </section>

</div>