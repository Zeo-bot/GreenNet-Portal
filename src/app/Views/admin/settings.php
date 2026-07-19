<?php

declare(strict_types=1);

if (!function_exists('gn_set_h')) {
    function gn_set_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_set_bool')) {
    function gn_set_bool(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}

$settings = is_array($settings ?? null) ? $settings : [];
$success = (string) ($success ?? '');
$error = (string) ($error ?? '');
$whatsappUrl = (string) ($whatsappUrl ?? '');

$successMap = [
    'saved' => 'تم حفظ الإعدادات بنجاح.',
    'visual_reset' => 'تمت إعادة إعدادات المظهر الأساسية.',
];

$get = static function (string $key, string $fallback = '') use ($settings): string {
    return (string) ($settings[$key] ?? $fallback);
};

$primaryColor = $get('primary_color', '#11945a');
$secondaryColor = $get('secondary_color', '#0f7f4d');

?>

<style>
    .gn-settings-page {
        display: grid;
        gap: 18px;
    }

    .gn-settings-hero,
    .gn-settings-kpi,
    .gn-settings-section,
    .gn-settings-card,
    .gn-settings-brand-preview {
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-settings-hero {
        position: relative;
        overflow: hidden;
        padding: 26px;
        border-radius: var(--gn-radius-xl);
        background:
            radial-gradient(circle at 0% 0%, rgba(32, 201, 120, 0.16), transparent 30%),
            linear-gradient(135deg, var(--gn-surface), var(--gn-surface-2));
    }

    .gn-settings-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-settings-hero p {
        margin: 10px 0 0;
        max-width: 940px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-settings-hero-actions,
    .gn-settings-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-settings-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-settings-kpi {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
    }

    .gn-settings-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-settings-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-settings-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-settings-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-settings-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-settings-kpi-value {
        margin-top: 7px;
        color: var(--gn-text);
        font-size: 22px;
        font-weight: 950;
        line-height: 1.35;
        word-break: break-word;
    }

    .gn-settings-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(320px, 0.55fr);
        gap: 18px;
        align-items: start;
    }

    .gn-settings-card,
    .gn-settings-section {
        border-radius: var(--gn-radius-xl);
        padding: 20px;
    }

    .gn-settings-card h2,
    .gn-settings-section-title {
        margin: 0 0 14px;
        color: var(--gn-text);
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-settings-form {
        display: grid;
        gap: 18px;
    }

    .gn-settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-settings-field {
        display: grid;
        gap: 7px;
        min-width: 0;
    }

    .gn-settings-field.is-wide {
        grid-column: 1 / -1;
    }

    .gn-settings-field label {
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 950;
    }

    .gn-settings-field input,
    .gn-settings-field select,
    .gn-settings-field textarea {
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

    .gn-settings-field textarea {
        min-height: 96px;
        resize: vertical;
        line-height: 1.7;
    }

    .gn-settings-field input[type="color"] {
        padding: 4px;
        cursor: pointer;
    }

    .gn-settings-switches {
        display: grid;
        gap: 10px;
    }

    .gn-settings-switch {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
        color: var(--gn-text);
        font-weight: 900;
    }

    .gn-settings-switch input {
        width: 18px;
        height: 18px;
    }

    .gn-settings-alert {
        padding: 14px 16px;
        border-radius: var(--gn-radius-lg);
        font-weight: 900;
        line-height: 1.7;
    }

    .gn-settings-alert.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.22);
    }

    .gn-settings-alert.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.22);
    }

    .gn-settings-brand-preview {
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: linear-gradient(135deg, var(--gn-surface), var(--gn-surface-2));
    }

    .gn-settings-brand-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .gn-settings-brand-mark {
        width: 54px;
        height: 54px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, <?= gn_set_h($primaryColor) ?>, <?= gn_set_h($secondaryColor) ?>);
        color: #ffffff;
        font-size: 26px;
        font-weight: 950;
        box-shadow: 0 18px 42px rgba(17, 148, 90, 0.22);
    }

    .gn-settings-brand-name {
        color: var(--gn-text);
        font-size: 21px;
        font-weight: 950;
    }

    .gn-settings-brand-sub {
        margin-top: 4px;
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 850;
    }

    .gn-settings-preview-list {
        display: grid;
        gap: 10px;
        margin-top: 14px;
    }

    .gn-settings-preview-item {
        padding: 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-settings-preview-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .gn-settings-preview-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        line-height: 1.55;
        word-break: break-word;
    }

    .gn-settings-note {
        margin-top: 14px;
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        line-height: 1.75;
        font-weight: 850;
    }

    html[data-theme="greennet-dark"] .gn-settings-hero,
    html[data-theme="greennet-dark"] .gn-settings-kpi,
    html[data-theme="greennet-dark"] .gn-settings-section,
    html[data-theme="greennet-dark"] .gn-settings-card,
    html[data-theme="greennet-dark"] .gn-settings-brand-preview {
        background:
            radial-gradient(circle at 0% 0%, rgba(46, 230, 139, 0.07), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2)) !important;
    }

    @media (max-width: 1200px) {
        .gn-settings-layout {
            grid-template-columns: 1fr;
        }

        .gn-settings-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-settings-grid,
        .gn-settings-kpis {
            grid-template-columns: 1fr;
        }

        .gn-settings-hero h1 {
            font-size: 26px;
        }
    }
</style>

<div class="gn-settings-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">الإعدادات والهوية</h1>
            <p class="admin-page-description">
                إدارة اسم النظام، بيانات الدعم، ألوان الهوية، وخيارات ظهور بوابة المشترك.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/media">Media</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/security">الأمان</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/settings">Refresh</a>
        </div>
    </div>

    <?php if ($success !== '' && isset($successMap[$success])): ?>
        <div class="gn-settings-alert is-success">
            <?= gn_set_h($successMap[$success]) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="gn-settings-alert is-danger">
            <?= gn_set_h($error) ?>
        </div>
    <?php endif; ?>

    <section class="gn-settings-hero">
        <h1><?= gn_set_h($get('app_name', 'GreenNet')) ?></h1>
        <p>
            <?= gn_set_h($get('brand_slogan', 'إدارة ذكية لمشتركي الإنترنت')) ?>
            — هذه الإعدادات تحفظ محلياً داخل قاعدة بيانات GreenNet.
        </p>

        <div class="gn-settings-hero-actions">
            <a class="gn-btn gn-btn-primary" href="#settings-form">تعديل الإعدادات</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/media">إدارة الصور والشعار</a>
            <a class="gn-btn gn-btn-secondary" href="/dashboard?username=test" target="_blank">معاينة بوابة المشترك</a>
        </div>
    </section>

    <section class="gn-settings-kpis">
        <div class="gn-settings-kpi">
            <div class="gn-settings-kpi-label">APP NAME</div>
            <div class="gn-settings-kpi-value"><?= gn_set_h($get('app_name', 'GreenNet')) ?></div>
        </div>

        <div class="gn-settings-kpi is-info">
            <div class="gn-settings-kpi-label">ACCESS MODE</div>
            <div class="gn-settings-kpi-value"><?= gn_set_h($get('access_mode', 'hybrid')) ?></div>
        </div>

        <div class="gn-settings-kpi is-success">
            <div class="gn-settings-kpi-label">SUPPORT</div>
            <div class="gn-settings-kpi-value" dir="ltr"><?= gn_set_h($get('support_phone', '')) ?></div>
        </div>

        <div class="gn-settings-kpi is-warning">
            <div class="gn-settings-kpi-label">CURRENCY</div>
            <div class="gn-settings-kpi-value"><?= gn_set_h($get('default_currency', 'SYP')) ?></div>
        </div>
    </section>

    <section class="gn-settings-layout">

        <form
            id="settings-form"
            class="gn-settings-form"
            method="post"
            action="/admin/settings"
            data-gn-form-wrapped="1"
            data-gn-form-enhanced="1"
            data-gn-fields-grouped="1"
        >
            <input type="hidden" name="gn_settings_action" value="save">

            <section class="gn-settings-section">
                <h2 class="gn-settings-section-title">هوية النظام</h2>

                <div class="gn-settings-grid">
                    <div class="gn-settings-field">
                        <label>اسم التطبيق</label>
                        <input type="text" name="app_name" value="<?= gn_set_h($get('app_name', 'GreenNet')) ?>" dir="ltr">
                    </div>

                    <div class="gn-settings-field">
                        <label>عنوان التطبيق</label>
                        <input type="text" name="app_title" value="<?= gn_set_h($get('app_title', 'GreenNet Portal')) ?>" dir="ltr">
                    </div>

                    <div class="gn-settings-field">
                        <label>اسم الجهة / الشركة</label>
                        <input type="text" name="organization_name" value="<?= gn_set_h($get('organization_name', '')) ?>">
                    </div>

                    <div class="gn-settings-field">
                        <label>عنوان لوحة المدير</label>
                        <input type="text" name="admin_panel_title" value="<?= gn_set_h($get('admin_panel_title', 'لوحة المدير')) ?>">
                    </div>

                    <div class="gn-settings-field is-wide">
                        <label>الشعار النصي</label>
                        <input type="text" name="brand_slogan" value="<?= gn_set_h($get('brand_slogan', '')) ?>">
                    </div>
                </div>
            </section>

            <section class="gn-settings-section">
                <h2 class="gn-settings-section-title">بوابة المشترك</h2>

                <div class="gn-settings-grid">
                    <div class="gn-settings-field">
                        <label>عنوان بوابة المشترك</label>
                        <input type="text" name="subscriber_portal_title" value="<?= gn_set_h($get('subscriber_portal_title', 'بوابة المشترك')) ?>">
                    </div>

                    <div class="gn-settings-field">
                        <label>عنوان صفحة الدخول</label>
                        <input type="text" name="subscriber_login_title" value="<?= gn_set_h($get('subscriber_login_title', 'دخول المشترك')) ?>">
                    </div>

                    <div class="gn-settings-field is-wide">
                        <label>وصف صفحة الدخول</label>
                        <textarea name="subscriber_login_subtitle"><?= gn_set_h($get('subscriber_login_subtitle', '')) ?></textarea>
                    </div>

                    <div class="gn-settings-field is-wide">
                        <label>تنبيه عام للمشتركين</label>
                        <textarea name="system_notice"><?= gn_set_h($get('system_notice', '')) ?></textarea>
                    </div>
                </div>
            </section>

            <section class="gn-settings-section">
                <h2 class="gn-settings-section-title">الدعم والاتصال</h2>

                <div class="gn-settings-grid">
                    <div class="gn-settings-field">
                        <label>رقم الدعم</label>
                        <input type="text" name="support_phone" value="<?= gn_set_h($get('support_phone', '')) ?>" dir="ltr">
                    </div>

                    <div class="gn-settings-field">
                        <label>رمز الدولة</label>
                        <input type="text" name="support_country_code" value="<?= gn_set_h($get('support_country_code', '963')) ?>" dir="ltr">
                    </div>

                    <div class="gn-settings-field">
                        <label>العملة الافتراضية</label>
                        <input type="text" name="default_currency" value="<?= gn_set_h($get('default_currency', 'SYP')) ?>" dir="ltr">
                    </div>

                    <div class="gn-settings-field">
                        <label>المنطقة الزمنية</label>
                        <input type="text" name="timezone" value="<?= gn_set_h($get('timezone', 'Asia/Damascus')) ?>" dir="ltr">
                    </div>
                </div>
            </section>

            <section class="gn-settings-section">
                <h2 class="gn-settings-section-title">التشغيل والتكامل</h2>

                <div class="gn-settings-grid">
                    <div class="gn-settings-field">
                        <label>Access Mode</label>
                        <select name="access_mode">
                            <option value="hybrid" <?= $get('access_mode', 'hybrid') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                            <option value="hotspot" <?= $get('access_mode', 'hybrid') === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                            <option value="ppp" <?= in_array($get('access_mode', 'hybrid'), ['ppp', 'pppoe'], true) ? 'selected' : '' ?>>PPPoE</option>
                        </select>
                    </div>

                    <div class="gn-settings-field">
                        <label>Auth Backend</label>
                        <select name="auth_backend">
                            <option value="user-manager" <?= $get('auth_backend', 'user-manager') === 'user-manager' ? 'selected' : '' ?>>User Manager</option>
                            <option value="local" <?= $get('auth_backend', 'user-manager') === 'local' ? 'selected' : '' ?>>Local DB</option>
                            <option value="routeros" <?= $get('auth_backend', 'user-manager') === 'routeros' ? 'selected' : '' ?>>RouterOS API</option>
                            <option value="hybrid" <?= $get('auth_backend', 'user-manager') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="gn-settings-section">
                <h2 class="gn-settings-section-title">الألوان والمظهر</h2>

                <div class="gn-settings-grid">
                    <div class="gn-settings-field">
                        <label>اللون الأساسي</label>
                        <input type="color" name="primary_color" value="<?= gn_set_h($primaryColor) ?>">
                    </div>

                    <div class="gn-settings-field">
                        <label>اللون الثانوي</label>
                        <input type="color" name="secondary_color" value="<?= gn_set_h($secondaryColor) ?>">
                    </div>
                </div>
            </section>

            <section class="gn-settings-section">
                <h2 class="gn-settings-section-title">خيارات الظهور</h2>

                <div class="gn-settings-switches">
                    <label class="gn-settings-switch">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= gn_set_bool($get('maintenance_mode', '0')) ? 'checked' : '' ?>>
                        تفعيل وضع الصيانة
                    </label>

                    <label class="gn-settings-switch">
                        <input type="checkbox" name="show_subscriber_support" value="1" <?= gn_set_bool($get('show_subscriber_support', '1')) ? 'checked' : '' ?>>
                        إظهار زر الدعم للمشترك
                    </label>

                    <label class="gn-settings-switch">
                        <input type="checkbox" name="show_renewal_requests" value="1" <?= gn_set_bool($get('show_renewal_requests', '1')) ? 'checked' : '' ?>>
                        إظهار طلبات التجديد
                    </label>

                    <label class="gn-settings-switch">
                        <input type="checkbox" name="show_usage_cards" value="1" <?= gn_set_bool($get('show_usage_cards', '1')) ? 'checked' : '' ?>>
                        إظهار كروت الاستهلاك
                    </label>
                </div>
            </section>

            <div class="gn-settings-actions">
                <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                    حفظ الإعدادات
                </button>

                <button
                    class="gn-btn gn-btn-secondary gn-btn-lg"
                    type="submit"
                    name="gn_settings_action"
                    value="reset_visual"
                    onclick="return confirm('هل تريد إعادة ألوان الهوية للقيم الافتراضية؟');"
                >
                    إعادة ألوان الهوية
                </button>
            </div>
        </form>

        <aside class="gn-settings-card">
            <h2>معاينة الهوية</h2>

            <div class="gn-settings-brand-preview">
                <div class="gn-settings-brand-row">
                    <div class="gn-settings-brand-mark">G</div>

                    <div>
                        <div class="gn-settings-brand-name"><?= gn_set_h($get('app_name', 'GreenNet')) ?></div>
                        <div class="gn-settings-brand-sub"><?= gn_set_h($get('brand_slogan', '')) ?></div>
                    </div>
                </div>
            </div>

            <div class="gn-settings-preview-list">
                <div class="gn-settings-preview-item">
                    <span class="gn-settings-preview-label">لوحة المدير</span>
                    <span class="gn-settings-preview-value"><?= gn_set_h($get('admin_panel_title', 'لوحة المدير')) ?></span>
                </div>

                <div class="gn-settings-preview-item">
                    <span class="gn-settings-preview-label">بوابة المشترك</span>
                    <span class="gn-settings-preview-value"><?= gn_set_h($get('subscriber_portal_title', 'بوابة المشترك')) ?></span>
                </div>

                <div class="gn-settings-preview-item">
                    <span class="gn-settings-preview-label">WhatsApp</span>
                    <span class="gn-settings-preview-value" dir="ltr"><?= gn_set_h($whatsappUrl !== '' ? $whatsappUrl : '-') ?></span>
                </div>

                <div class="gn-settings-preview-item">
                    <span class="gn-settings-preview-label">Timezone</span>
                    <span class="gn-settings-preview-value" dir="ltr"><?= gn_set_h($get('timezone', 'Asia/Damascus')) ?></span>
                </div>
            </div>

            <div class="gn-settings-note">
                تم فصل منطق الحفظ عن الواجهة لتجنب Timeout. هذه الصفحة لا تتصل بـ MikroTik.
            </div>
        </aside>

    </section>

</div>