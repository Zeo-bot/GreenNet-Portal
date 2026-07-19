<?php

declare(strict_types=1);

if (!function_exists('gn_ws_h')) {
    function gn_ws_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_ws_bool')) {
    function gn_ws_bool(array $settings, string $key, bool $default = false): bool
    {
        $value = $settings[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}

$settings = is_array($settings ?? null) ? $settings : [];
$queue = is_array($queue ?? null) ? $queue : [];
$apiAudits = is_array($api_audits ?? null) ? $api_audits : [];
$message = (string) ($message ?? '');
$messageType = (string) ($message_type ?? 'success');

$items = [
    'greennet_safe_mode' => [
        'title' => 'Safe Mode',
        'icon' => '🛡',
        'description' => 'عند تفعيله يمنع أي تنفيذ حقيقي حتى لو كانت الأوامر جاهزة.',
        'recommended' => 'ON',
        'note_on' => 'أكثر أماناً',
        'note_off' => 'وضع اختبار',
        'default' => true,
    ],
    'mikrotik_write_enabled' => [
        'title' => 'MikroTik Write Enabled',
        'icon' => '⚡',
        'description' => 'يسمح بإرسال أوامر حقيقية للراوتر. فعّله فقط أثناء الاختبار الحقيقي.',
        'recommended' => 'OFF',
        'note_on' => 'الكتابة مفعلة',
        'note_off' => 'آمن حالياً',
        'default' => false,
    ],
    'backup_guard_enabled' => [
        'title' => 'Backup Guard',
        'icon' => '⬇',
        'description' => 'يفرض وجود نسخة احتياطية حديثة قبل تنفيذ أي أمر حساس.',
        'recommended' => 'ON',
        'note_on' => 'محمي',
        'note_off' => 'غير مفعل',
        'default' => true,
    ],
    'dry_run_required' => [
        'title' => 'Dry Run Required',
        'icon' => '⌁',
        'description' => 'كل عملية يجب أن تمر بمحاكاة قبل التنفيذ الحقيقي.',
        'recommended' => 'ON',
        'note_on' => 'محاكاة مطلوبة',
        'note_off' => 'غير مطلوب',
        'default' => true,
    ],
    'confirm_required' => [
        'title' => 'Confirmation Required',
        'icon' => '✓',
        'description' => 'يفرض تأكيد إداري واضح قبل تنفيذ العملية.',
        'recommended' => 'ON',
        'note_on' => 'تأكيد مطلوب',
        'note_off' => 'غير مطلوب',
        'default' => true,
    ],
    'transaction_queue_enabled' => [
        'title' => 'Transaction Queue',
        'icon' => '☷',
        'description' => 'يحفظ العمليات في طابور آمن قبل تنفيذها ومراجعتها.',
        'recommended' => 'ON',
        'note_on' => 'الطابور مفعل',
        'note_off' => 'تنفيذ مباشر',
        'default' => true,
    ],
];

$onCount = 0;

foreach ($items as $key => $item) {
    if (gn_ws_bool($settings, $key, (bool) $item['default'])) {
        $onCount++;
    }
}

?>

<style>
    .gn-ws-page {
        display: grid;
        gap: 18px;
    }

    .gn-ws-hero {
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

    .gn-ws-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-ws-hero p {
        margin: 10px 0 0;
        max-width: 920px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-ws-hero-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-ws-alert {
        padding: 14px 16px;
        border-radius: var(--gn-radius-lg);
        font-weight: 900;
        line-height: 1.7;
    }

    .gn-ws-alert.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.22);
    }

    .gn-ws-alert.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.22);
    }

    .gn-ws-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-ws-kpi {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-ws-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-ws-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-ws-kpi.is-danger::before {
        background: var(--gn-danger);
    }

    .gn-ws-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-ws-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-ws-kpi-value {
        margin-top: 7px;
        color: var(--gn-text);
        font-size: 26px;
        font-weight: 950;
        line-height: 1.35;
    }

    .gn-ws-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .gn-ws-card {
        position: relative;
        overflow: hidden;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-ws-card.is-on {
        border-color: rgba(22, 163, 74, 0.32);
    }

    .gn-ws-card.is-off {
        border-color: rgba(245, 158, 11, 0.35);
    }

    .gn-ws-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-warning);
    }

    .gn-ws-card.is-on::before {
        background: var(--gn-success);
    }

    .gn-ws-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .gn-ws-icon {
        width: 46px;
        height: 46px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--gn-primary-soft);
        color: var(--gn-primary);
        font-size: 22px;
        font-weight: 950;
    }

    .gn-ws-title {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-ws-desc {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        font-size: 13px;
        line-height: 1.75;
        font-weight: 850;
    }

    .gn-ws-switch {
        position: relative;
        display: inline-flex;
        width: 64px;
        height: 34px;
        flex: 0 0 auto;
    }

    .gn-ws-switch input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .gn-ws-slider {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: var(--gn-surface-3);
        border: 1px solid var(--gn-border);
        cursor: pointer;
        transition: 0.18s ease;
    }

    .gn-ws-slider::after {
        content: "";
        position: absolute;
        top: 4px;
        inset-inline-start: 4px;
        width: 24px;
        height: 24px;
        border-radius: 999px;
        background: #ffffff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18);
        transition: 0.18s ease;
    }

    .gn-ws-switch input:checked + .gn-ws-slider {
        background: var(--gn-success);
        border-color: var(--gn-success);
    }

    .gn-ws-switch input:checked + .gn-ws-slider::after {
        inset-inline-start: calc(100% - 28px);
    }

    .gn-ws-meta {
        display: flex;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .gn-ws-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 26px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 950;
        white-space: nowrap;
    }

    .gn-ws-badge.is-on {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-ws-badge.is-off {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-ws-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-ws-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-ws-note {
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        line-height: 1.75;
        font-weight: 850;
    }

    html[data-theme="greennet-dark"] .gn-ws-hero,
    html[data-theme="greennet-dark"] .gn-ws-kpi,
    html[data-theme="greennet-dark"] .gn-ws-card {
        background:
            radial-gradient(circle at 0% 0%, rgba(46, 230, 139, 0.07), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2)) !important;
    }

    @media (max-width: 1280px) {
        .gn-ws-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gn-ws-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-ws-grid,
        .gn-ws-kpis {
            grid-template-columns: 1fr;
        }

        .gn-ws-hero h1 {
            font-size: 26px;
        }
    }
</style>

<div class="gn-ws-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">Write Safety</h1>
            <p class="admin-page-description">
                إعدادات الأمان الخاصة بأي كتابة حقيقية على MikroTik.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/mikrotik-dry-run">Dry Run</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/audit">Audit</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/write-safety">Refresh</a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="gn-ws-alert <?= $messageType === 'danger' || $messageType === 'error' ? 'is-danger' : 'is-success' ?>">
            <?= gn_ws_h($message) ?>
        </div>
    <?php endif; ?>

    <section class="gn-ws-hero">
        <h1>طبقة الأمان قبل الكتابة الحقيقية</h1>
        <p>
            هذه الصفحة تتحكم فقط بالسماح أو المنع. لا يتم تنفيذ أي أمر على MikroTik من هنا.
            للتجربة على الراوتر الحالي يمكن تفعيل الكتابة وإيقاف Safe Mode مؤقتاً.
        </p>

        <div class="gn-ws-hero-actions">
            <a class="gn-btn gn-btn-primary" href="#write-safety-form">تعديل الإعدادات</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/mikrotik-dry-run">فتح Dry Run</a>
        </div>
    </section>

    <section class="gn-ws-kpis">
        <div class="gn-ws-kpi">
            <div class="gn-ws-kpi-label">Enabled Guards</div>
            <div class="gn-ws-kpi-value"><?= gn_ws_h($onCount) ?> / <?= gn_ws_h(count($items)) ?></div>
        </div>

        <div class="gn-ws-kpi <?= gn_ws_bool($settings, 'greennet_safe_mode', true) ? 'is-warning' : 'is-info' ?>">
            <div class="gn-ws-kpi-label">Safe Mode</div>
            <div class="gn-ws-kpi-value"><?= gn_ws_bool($settings, 'greennet_safe_mode', true) ? 'ON' : 'OFF' ?></div>
        </div>

        <div class="gn-ws-kpi <?= gn_ws_bool($settings, 'mikrotik_write_enabled', false) ? 'is-danger' : 'is-info' ?>">
            <div class="gn-ws-kpi-label">MikroTik Write</div>
            <div class="gn-ws-kpi-value"><?= gn_ws_bool($settings, 'mikrotik_write_enabled', false) ? 'ON' : 'OFF' ?></div>
        </div>

        <div class="gn-ws-kpi is-info">
            <div class="gn-ws-kpi-label">Queue Items</div>
            <div class="gn-ws-kpi-value"><?= gn_ws_h(count($queue)) ?></div>
        </div>
    </section>

    <form
        id="write-safety-form"
        method="post"
        action="/admin/write-safety"
        data-gn-form-wrapped="1"
        data-gn-form-enhanced="1"
        data-gn-fields-grouped="1"
    >
        <section class="gn-ws-grid">
            <?php foreach ($items as $key => $item): ?>
                <?php $enabled = gn_ws_bool($settings, $key, (bool) $item['default']); ?>

                <article class="gn-ws-card <?= $enabled ? 'is-on' : 'is-off' ?>">
                    <div class="gn-ws-card-head">
                        <div class="gn-ws-icon"><?= gn_ws_h($item['icon']) ?></div>

                        <label class="gn-ws-switch" title="<?= gn_ws_h($item['title']) ?>">
                            <input
                                type="checkbox"
                                name="<?= gn_ws_h($key) ?>"
                                value="1"
                                <?= $enabled ? 'checked' : '' ?>
                            >
                            <span class="gn-ws-slider"></span>
                        </label>
                    </div>

                    <h2 class="gn-ws-title"><?= gn_ws_h($item['title']) ?></h2>

                    <p class="gn-ws-desc">
                        <?= gn_ws_h($item['description']) ?>
                    </p>

                    <div class="gn-ws-meta">
                        <span class="gn-ws-badge <?= $enabled ? 'is-on' : 'is-off' ?>">
                            <?= $enabled ? 'ON' : 'OFF' ?>
                        </span>

                        <span class="gn-ws-badge is-info">
                            Recommended: <?= gn_ws_h($item['recommended']) ?>
                        </span>

                        <span class="gn-ws-badge <?= $enabled ? 'is-on' : 'is-off' ?>">
                            <?= gn_ws_h($enabled ? $item['note_on'] : $item['note_off']) ?>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="gn-ws-actions">
            <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                حفظ إعدادات الأمان
            </button>

            <a class="gn-btn gn-btn-secondary gn-btn-lg" href="/admin/mikrotik-dry-run">
                Dry Run اختبار
            </a>
        </div>
    </form>

    <div class="gn-ws-note">
        للتجربة الحالية على راوتر اختبار بدون مشتركين: اجعل
        <strong>Safe Mode = OFF</strong>
        و
        <strong>MikroTik Write Enabled = ON</strong>
        واترك باقي الحمايات ON.
    </div>

</div>