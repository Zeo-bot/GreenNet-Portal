<?php

declare(strict_types=1);

if (!function_exists('gn_push_h')) {
    function gn_push_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_push_pretty')) {
    function gn_push_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (!function_exists('gn_push_bool')) {
    function gn_push_bool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (int) $value === 1;

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}

$packages = is_array($packages ?? null) ? $packages : [];
$preflight = is_array($preflight ?? null) ? $preflight : [];
$result = is_array($result ?? null) ? $result : null;
$message = (string) ($message ?? '');
$messageType = (string) ($message_type ?? 'info');

$safeMode = gn_push_bool($preflight['greennet_safe_mode'] ?? $preflight['safe_mode'] ?? true);
$writeEnabled = gn_push_bool($preflight['mikrotik_write_enabled'] ?? $preflight['write_enabled'] ?? false);
$freshBackup = gn_push_bool($preflight['fresh_backup'] ?? $preflight['has_fresh_backup'] ?? false);

$selectedPackageId = (int) ($result['package_id'] ?? 0);
$operations = is_array($result['operations'] ?? null) ? $result['operations'] : [];
$existing = is_array($result['existing'] ?? null) ? $result['existing'] : [];
$realResult = is_array($result['real_result'] ?? null) ? $result['real_result'] : [];
$canExecute = $result !== null && !empty($result['can_execute_later']) && empty($result['executed']) && empty($result['execute_error']);
?>

<style>
    .gn-push-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-push-card {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-push-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-push-card.is-success::before { background: var(--gn-success); }
    .gn-push-card.is-warning::before { background: var(--gn-warning); }
    .gn-push-card.is-danger::before { background: var(--gn-danger); }

    .gn-push-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-push-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        line-height: 1.25;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-push-box {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-push-box h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-push-box p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-push-form-grid {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: end;
        margin-top: 14px;
    }

    .gn-push-field label {
        display: block;
        color: var(--gn-text);
        font-weight: 950;
        margin-bottom: 8px;
    }

    .gn-push-field select,
    .gn-push-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        box-sizing: border-box;
    }

    .gn-push-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
        color: var(--gn-text);
        font-weight: 850;
        margin-top: 8px;
    }

    .gn-push-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-push-alert {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-push-success {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-push-danger {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.25);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-push-info {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-push-code {
        margin-top: 12px;
        padding: 14px;
        border-radius: var(--gn-radius-md);
        background: #0f172a;
        color: #dbeafe;
        overflow: auto;
        direction: ltr;
        text-align: left;
        unicode-bidi: plaintext;
        font-size: 12px;
        line-height: 1.6;
    }

    @media (max-width: 900px) {
        .gn-push-grid,
        .gn-push-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Push Package to MikroTik</h1>
        <p class="admin-page-description">
            S10.6 — إرسال باقة من GreenNet إلى MikroTik User Manager.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/packages">GreenNet Packages</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/user-manager-packages">Import from MikroTik</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/write-safety">Write Safety</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="<?= $messageType === 'success' ? 'gn-push-success' : 'gn-push-alert' ?>">
        <?= gn_push_h($message) ?>
    </div>
<?php endif; ?>

<section class="gn-push-grid">
    <article class="gn-push-card <?= $safeMode ? 'is-warning' : 'is-success' ?>">
        <div class="gn-push-label">Safe Mode</div>
        <div class="gn-push-value"><?= $safeMode ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-push-card <?= $writeEnabled ? 'is-success' : 'is-warning' ?>">
        <div class="gn-push-label">Write Enabled</div>
        <div class="gn-push-value"><?= $writeEnabled ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-push-card <?= $freshBackup ? 'is-success' : 'is-warning' ?>">
        <div class="gn-push-label">Fresh Backup</div>
        <div class="gn-push-value"><?= $freshBackup ? 'ON' : 'OFF' ?></div>
    </article>
</section>

<div class="gn-push-info">
    هذه العملية تحتاج Dry Run أولاً. التنفيذ الحقيقي سيكتب على MikroTik:
    <span dir="ltr">limitation/profile/profile-limitation</span>
</div>

<section class="gn-push-box">
    <h3>Create Dry Run</h3>
    <p>اختر باقة من GreenNet وشاهد ما الذي سيتم إنشاؤه أو تحديثه على MikroTik.</p>

    <?php if (empty($packages)): ?>
        <div class="gn-push-alert">
            لا توجد باقات داخل GreenNet. افتح صفحة Packages أو استورد من MikroTik أولاً.
        </div>
    <?php else: ?>
        <form method="post" action="/admin/package-push/preview">
            <div class="gn-push-form-grid">
                <div class="gn-push-field">
                    <label>GreenNet Package</label>
                    <select name="package_id" required>
                        <?php foreach ($packages as $package): ?>
                            <?php
                                $id = (int) ($package['id'] ?? 0);
                                $name = (string) ($package['name'] ?? '');
                                $sourceProfile = (string) ($package['source_profile'] ?? '');
                                $rate = (string) ($package['rate_limit'] ?? '');
                                $quota = (string) ($package['quota_gb'] ?? '0');
                                $days = (string) ($package['duration_days'] ?? '0');
                            ?>
                            <option value="<?= gn_push_h((string) $id) ?>" <?= $id === $selectedPackageId ? 'selected' : '' ?>>
                                #<?= gn_push_h((string) $id) ?>
                                -
                                <?= gn_push_h($name) ?>
                                |
                                profile: <?= gn_push_h($sourceProfile !== '' ? $sourceProfile : $name) ?>
                                |
                                <?= gn_push_h($quota) ?>GB
                                |
                                <?= gn_push_h($days) ?>d
                                |
                                <?= gn_push_h($rate) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                    Create Dry Run
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($result !== null): ?>
    <section class="gn-push-box">
        <h3>Dry Run Result</h3>

        <?php if (!empty($result['error'])): ?>
            <div class="gn-push-alert"><?= gn_push_h((string) $result['error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($result['execute_error'])): ?>
            <div class="gn-push-danger"><?= gn_push_h((string) $result['execute_error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($realResult)): ?>
            <div class="<?= !empty($realResult['ok']) ? 'gn-push-success' : 'gn-push-danger' ?>">
                التنفيذ الحقيقي:
                <strong><?= !empty($realResult['ok']) ? 'نجح' : 'فشل' ?></strong>
                <br>
                <?= gn_push_h((string) ($realResult['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <div class="gn-push-grid">
            <article class="gn-push-card is-success">
                <div class="gn-push-label">Package ID</div>
                <div class="gn-push-value"><?= gn_push_h((string) ($result['package_id'] ?? '-')) ?></div>
            </article>

            <article class="gn-push-card is-success">
                <div class="gn-push-label">Profile Name</div>
                <div class="gn-push-value"><?= gn_push_h((string) ($result['router_names']['profile_name'] ?? '-')) ?></div>
            </article>

            <article class="gn-push-card <?= !empty($result['can_execute_later']) ? 'is-success' : 'is-warning' ?>">
                <div class="gn-push-label">Operations</div>
                <div class="gn-push-value"><?= gn_push_h((string) ($result['operations_count'] ?? 0)) ?></div>
            </article>
        </div>

        <?php if (!empty($result['block_reason'])): ?>
            <div class="gn-push-alert"><?= gn_push_h((string) $result['block_reason']) ?></div>
        <?php endif; ?>

        <div class="gn-push-box">
            <h3>Existing on MikroTik</h3>

            <div class="gn-push-row">
                <span>Profile Exists</span>
                <strong><?= !empty($existing['profile']['found']) ? 'YES' : 'NO' ?></strong>
            </div>

            <div class="gn-push-row">
                <span>Limitation Exists</span>
                <strong><?= !empty($existing['limitation']['found']) ? 'YES' : 'NO' ?></strong>
            </div>

            <div class="gn-push-row">
                <span>Profile-Limitation Link Exists</span>
                <strong><?= !empty($existing['profile_limitation']['found']) ? 'YES' : 'NO' ?></strong>
            </div>
        </div>

        <div class="gn-push-box">
            <h3>Planned Operations</h3>

            <?php if (empty($operations)): ?>
                <div class="gn-push-info">لا توجد عمليات مطلوبة.</div>
            <?php else: ?>
                <?php foreach ($operations as $operation): ?>
                    <div class="gn-push-row">
                        <span><?= gn_push_h((string) ($operation['type'] ?? '-')) ?></span>
                        <strong><?= gn_push_h((string) ($operation['command'] ?? '-')) ?></strong>
                    </div>

                    <pre class="gn-push-code"><?= gn_push_h(gn_push_pretty($operation['params'] ?? [])) ?></pre>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canExecute): ?>
            <div class="gn-push-box">
                <h3>Execute Real Push</h3>
                <p>هذا سيكتب فعلياً على MikroTik. نفّذه أولاً على باقة تجريبية.</p>

                <div class="gn-push-danger">
                    للتنفيذ اكتب:
                    <strong dir="ltr">PUSH</strong>
                </div>

                <form method="post" action="/admin/package-push/execute" onsubmit="return confirm('سيتم تنفيذ Write حقيقي على MikroTik. متابعة؟');">
                    <input type="hidden" name="package_id" value="<?= gn_push_h((string) ($result['package_id'] ?? 0)) ?>">

                    <div class="gn-push-field" style="margin-top:14px;">
                        <label>Confirmation</label>
                        <input type="text" name="confirm_push" placeholder="PUSH" dir="ltr" autocomplete="off" required>
                    </div>

                    <button class="gn-btn gn-btn-danger gn-btn-lg" type="submit" style="margin-top:14px;">
                        Execute Real Push
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($result['notes']) && is_array($result['notes'])): ?>
            <div class="gn-push-box">
                <h3>Notes</h3>
                <?php foreach ($result['notes'] as $note): ?>
                    <div class="gn-push-info"><?= gn_push_h((string) $note) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <details style="margin-top:18px;">
            <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw Result</summary>
            <pre class="gn-push-code"><?= gn_push_h(gn_push_pretty($result)) ?></pre>
        </details>
    </section>
<?php endif; ?>