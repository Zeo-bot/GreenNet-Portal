<?php

declare(strict_types=1);

if (!function_exists('gn_s10_h')) {
    function gn_s10_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_s10_bool')) {
    function gn_s10_bool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (int) $value === 1;

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gn_s10_pretty')) {
    function gn_s10_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

$preflight = is_array($preflight ?? null) ? $preflight : [];
$result = is_array($result ?? null) ? $result : null;
$message = (string) ($message ?? '');
$messageType = (string) ($message_type ?? 'info');

$safeMode = gn_s10_bool($preflight['greennet_safe_mode'] ?? $preflight['safe_mode'] ?? true);
$writeEnabled = gn_s10_bool($preflight['mikrotik_write_enabled'] ?? $preflight['write_enabled'] ?? false);
$dryRunRequired = gn_s10_bool($preflight['dry_run_required'] ?? true);
$freshBackup = gn_s10_bool($preflight['fresh_backup'] ?? $preflight['has_fresh_backup'] ?? false);

$action = (string) ($result['action'] ?? 'hotspot_reset_counters');
$monitor = is_array($result['monitor_summary'] ?? null) ? $result['monitor_summary'] : [];
$baseline = is_array($result['latest_baseline'] ?? null) ? $result['latest_baseline'] : [];
$baselineUsage = is_array($result['baseline_usage'] ?? null) ? $result['baseline_usage'] : [];
$baselineResult = is_array($result['baseline_result'] ?? null) ? $result['baseline_result'] : [];
$realResult = is_array($result['real_result'] ?? null) ? $result['real_result'] : [];
$userManagerUser = is_array($result['user_manager_user'] ?? null) ? $result['user_manager_user'] : [];

$recommendedBackend = (string) ($result['recommended_backend'] ?? '');
$canExecuteLater = !empty($result['can_execute_later']);

$canCreateBaseline = $result !== null
    && $action === 'hotspot_reset_counters'
    && $recommendedBackend === 'user-manager'
    && !empty($monitor['ok'])
    && empty($result['execute_error']);

$canExecuteUmWrite = $result !== null
    && in_array($action, ['um_disable_user', 'um_enable_user'], true)
    && $recommendedBackend === 'user-manager'
    && $canExecuteLater
    && empty($result['execute_error'])
    && empty($result['executed']);

$confirmWord = (string) ($result['confirm_word'] ?? ($action === 'um_enable_user' ? 'ENABLE' : 'DISABLE'));
?>

<style>
    .gn-s10-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-s10-card {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-s10-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-s10-card.is-success::before { background: var(--gn-success); }
    .gn-s10-card.is-warning::before { background: var(--gn-warning); }
    .gn-s10-card.is-danger::before { background: var(--gn-danger); }

    .gn-s10-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-s10-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        line-height: 1.25;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-s10-box {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-s10-box h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-s10-box p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-s10-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .gn-s10-badge {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 950;
    }

    .gn-s10-badge.is-success { background: var(--gn-success-soft); color: var(--gn-success); }
    .gn-s10-badge.is-warning { background: var(--gn-warning-soft); color: var(--gn-warning); }
    .gn-s10-badge.is-danger { background: var(--gn-danger-soft); color: var(--gn-danger); }
    .gn-s10-badge.is-info { background: var(--gn-info-soft); color: var(--gn-info); }

    .gn-s10-row {
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

    .gn-s10-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-s10-alert {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-s10-danger-box {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.25);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-s10-success {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-s10-info {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-s10-command {
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

    .gn-s10-confirm-grid {
        display: grid;
        gap: 10px;
        margin-top: 14px;
    }

    .gn-s10-confirm-grid label {
        color: var(--gn-text);
        font-weight: 950;
        font-size: 13px;
    }

    .gn-s10-confirm-grid input {
        min-height: 46px;
        width: 100%;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        box-sizing: border-box;
    }

    @media (max-width: 1100px) {
        .gn-s10-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">MikroTik Dry Run</h1>
        <p class="admin-page-description">
            S10.3 — User Manager Enable / Disable مع Dry Run و WriteSafetyGuard.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/write-safety">Write Safety</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/api/diagnostics">API Diagnostics</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/audit">Audit Center</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="gn-dry-alert <?= $messageType === 'success' ? 'is-success' : '' ?>">
        <?= gn_s10_h($message) ?>
    </div>
<?php endif; ?>

<section class="gn-dry-preflight">
    <div class="gn-dry-preflight-card <?= $safeMode ? 'is-warning' : 'is-success' ?>">
        <div class="gn-dry-preflight-label">Safe Mode</div>
        <div class="gn-dry-preflight-value"><?= $safeMode ? 'ON' : 'OFF' ?></div>
        <div class="gn-dry-preflight-note">للـ Write الحقيقي يجب أن يكون OFF.</div>
    </div>

    <div class="gn-dry-preflight-card <?= $writeEnabled ? 'is-success' : 'is-warning' ?>">
        <div class="gn-dry-preflight-label">Write Enabled</div>
        <div class="gn-dry-preflight-value"><?= $writeEnabled ? 'ON' : 'OFF' ?></div>
        <div class="gn-dry-preflight-note">للـ Enable/Disable يجب أن يكون ON.</div>
    </div>

    <div class="gn-dry-preflight-card <?= $dryRunRequired ? 'is-success' : 'is-warning' ?>">
        <div class="gn-dry-preflight-label">Dry Run Required</div>
        <div class="gn-dry-preflight-value"><?= $dryRunRequired ? 'ON' : 'OFF' ?></div>
        <div class="gn-dry-preflight-note">مطلوب قبل التنفيذ.</div>
    </div>

    <div class="gn-dry-preflight-card <?= $freshBackup ? 'is-success' : 'is-warning' ?>">
        <div class="gn-dry-preflight-label">Fresh Backup</div>
        <div class="gn-dry-preflight-value"><?= $freshBackup ? 'ON' : 'OFF' ?></div>
        <div class="gn-dry-preflight-note">مطلوب حسب إعدادات الحماية.</div>
    </div>
</section>

<section class="gn-dry-hero">
    <div class="gn-dry-panel">
        <div class="gn-dry-panel-header">
            <div class="gn-dry-eyebrow">S10.3</div>
            <h2 class="gn-dry-title">User Manager Operations</h2>
            <p class="gn-dry-description">
                اكتب اسم المستخدم، اختر العملية، ثم أنشئ Dry Run قبل أي تنفيذ.
            </p>
        </div>

        <form class="gn-dry-form" method="post" action="/admin/mikrotik-dry-run/preview">
            <div class="gn-dry-operation-grid">
                <label class="gn-dry-operation">
                    <input type="radio" name="action" value="hotspot_reset_counters" <?= $action === 'hotspot_reset_counters' ? 'checked' : '' ?>>
                    <span class="gn-dry-operation-card">
                        <span class="gn-dry-operation-title">GreenNet Baseline</span>
                        <span class="gn-dry-operation-desc">قراءة Monitor وحساب الاستهلاك من Baseline.</span>
                        <span class="gn-cell-sub" dir="ltr">No MikroTik write</span>
                    </span>
                </label>

                <label class="gn-dry-operation">
                    <input type="radio" name="action" value="um_disable_user" <?= $action === 'um_disable_user' ? 'checked' : '' ?>>
                    <span class="gn-dry-operation-card">
                        <span class="gn-dry-operation-title">Disable User</span>
                        <span class="gn-dry-operation-desc">تعطيل مستخدم User Manager.</span>
                        <span class="gn-cell-sub" dir="ltr">/user-manager/user/set disabled=yes</span>
                    </span>
                </label>

                <label class="gn-dry-operation">
                    <input type="radio" name="action" value="um_enable_user" <?= $action === 'um_enable_user' ? 'checked' : '' ?>>
                    <span class="gn-dry-operation-card">
                        <span class="gn-dry-operation-title">Enable User</span>
                        <span class="gn-dry-operation-desc">تفعيل مستخدم User Manager.</span>
                        <span class="gn-cell-sub" dir="ltr">/user-manager/user/set disabled=no</span>
                    </span>
                </label>
            </div>

            <div class="gn-dry-fields">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?= gn_s10_h((string) ($result['username'] ?? 'user1')) ?>" placeholder="user1" dir="ltr" required>
                </div>
            </div>

            <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                إنشاء Dry Run
            </button>
        </form>
    </div>

    <aside class="gn-dry-side">
        <h3>قاعدة الأمان</h3>
        <p class="admin-page-description">
            Baseline لا يكتب على MikroTik. أما Enable / Disable فهو Write حقيقي ويحتاج WriteSafetyGuard.
        </p>

        <div class="gn-s10-info">
            للـ Write الحقيقي: Safe Mode = OFF، Write Enabled = ON، Backup Guard حسب الإعدادات.
        </div>
    </aside>
</section>

<?php if ($result !== null): ?>
    <section class="gn-dry-result">
        <div class="gn-dry-result-header">
            <div class="gn-dry-eyebrow">Dry Run Result</div>
            <h2 class="gn-dry-title"><?= gn_s10_h((string) ($result['title'] ?? 'Result')) ?></h2>
            <p class="gn-dry-description">راجع النتيجة قبل أي تنفيذ.</p>
        </div>

        <div class="gn-dry-result-body">
            <?php if (!empty($result['error'])): ?>
                <div class="gn-s10-alert"><?= gn_s10_h((string) $result['error']) ?></div>
            <?php endif; ?>

            <?php if (!empty($result['execute_error'])): ?>
                <div class="gn-s10-danger-box"><?= gn_s10_h((string) $result['execute_error']) ?></div>
            <?php endif; ?>

            <?php if (!empty($baselineResult)): ?>
                <div class="gn-s10-success">
                    تم إنشاء Baseline داخل GreenNet فقط.
                    <br>
                    Baseline ID:
                    <strong dir="ltr"><?= gn_s10_h((string) ($baselineResult['baseline_id'] ?? '-')) ?></strong>
                    <br>
                    Baseline Total:
                    <strong dir="ltr"><?= gn_s10_h((string) ($baselineResult['baseline_total_human'] ?? '0 B')) ?></strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($realResult)): ?>
                <div class="<?= !empty($realResult['success']) ? 'gn-s10-success' : 'gn-s10-danger-box' ?>">
                    التنفيذ الحقيقي:
                    <strong><?= !empty($realResult['success']) ? 'نجح' : 'فشل' ?></strong>
                    <br>
                    Command:
                    <strong dir="ltr"><?= gn_s10_h((string) ($realResult['command'] ?? '-')) ?></strong>
                    <br>
                    Verified Disabled:
                    <strong dir="ltr"><?= !empty($realResult['verified_disabled']) ? 'YES' : 'NO' ?></strong>
                </div>
            <?php endif; ?>

            <div class="gn-s10-grid">
                <article class="gn-s10-card is-success">
                    <div class="gn-s10-label">Username</div>
                    <div class="gn-s10-value"><?= gn_s10_h((string) ($result['username'] ?? '-')) ?></div>
                </article>

                <article class="gn-s10-card <?= $recommendedBackend === 'user-manager' ? 'is-success' : 'is-warning' ?>">
                    <div class="gn-s10-label">Backend</div>
                    <div class="gn-s10-value"><?= gn_s10_h($recommendedBackend !== '' ? $recommendedBackend : '-') ?></div>
                </article>

                <article class="gn-s10-card <?= $canExecuteLater ? 'is-success' : 'is-warning' ?>">
                    <div class="gn-s10-label">Can Execute</div>
                    <div class="gn-s10-value"><?= $canExecuteLater ? 'YES' : 'NO' ?></div>
                </article>
            </div>

            <?php if (in_array($action, ['um_disable_user', 'um_enable_user'], true)): ?>
                <div class="gn-s10-box">
                    <h3>User Manager Write Plan</h3>

                    <div class="gn-s10-row">
                        <span>User Manager .id</span>
                        <strong><?= gn_s10_h((string) ($result['user_manager_id'] ?? '-')) ?></strong>
                    </div>

                    <div class="gn-s10-row">
                        <span>Current Disabled</span>
                        <strong><?= !empty($result['current_disabled_bool']) ? 'YES' : 'NO' ?></strong>
                    </div>

                    <div class="gn-s10-row">
                        <span>Desired Disabled</span>
                        <strong><?= !empty($result['desired_disabled_bool']) ? 'YES' : 'NO' ?></strong>
                    </div>

                    <div class="gn-s10-row">
                        <span>Expected Command</span>
                        <strong><?= gn_s10_h((string) ($result['expected_command'] ?? '-')) ?></strong>
                    </div>

                    <?php if (!empty($result['block_reason'])): ?>
                        <div class="gn-s10-alert"><?= gn_s10_h((string) $result['block_reason']) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($userManagerUser)): ?>
                        <details style="margin-top:14px;">
                            <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">User Manager Row</summary>
                            <pre class="gn-s10-command"><?= gn_s10_h(gn_s10_pretty($userManagerUser)) ?></pre>
                        </details>
                    <?php endif; ?>
                </div>

                <?php if ($canExecuteUmWrite): ?>
                    <div class="gn-s10-box">
                        <h3>Execute Real MikroTik Write</h3>
                        <p>
                            هذا سيعدل حالة المستخدم داخل MikroTik User Manager.
                        </p>

                        <div class="gn-s10-danger-box">
                            للتنفيذ اكتب:
                            <strong dir="ltr"><?= gn_s10_h($confirmWord) ?></strong>
                        </div>

                        <form
                            method="post"
                            action="/admin/mikrotik-dry-run/execute"
                            onsubmit="return confirm('سيتم تنفيذ Write حقيقي على MikroTik. متابعة؟');"
                        >
                            <input type="hidden" name="method" value="um_set_disabled">
                            <input type="hidden" name="action" value="<?= gn_s10_h($action) ?>">
                            <input type="hidden" name="username" value="<?= gn_s10_h((string) ($result['username'] ?? '')) ?>">

                            <div class="gn-s10-confirm-grid">
                                <label>Confirmation</label>
                                <input type="text" name="confirm_execute" placeholder="<?= gn_s10_h($confirmWord) ?>" dir="ltr" autocomplete="off" required>
                            </div>

                            <button class="gn-btn gn-btn-danger gn-btn-lg" type="submit" style="margin-top:14px;">
                                Execute Real Write
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($action === 'hotspot_reset_counters'): ?>
                <?php if (!empty($monitor)): ?>
                    <div class="gn-s10-box">
                        <h3>User Manager Monitor</h3>

                        <div class="gn-s10-grid">
                            <article class="gn-s10-card <?= !empty($monitor['ok']) ? 'is-success' : 'is-danger' ?>">
                                <div class="gn-s10-label">Monitor Status</div>
                                <div class="gn-s10-value"><?= !empty($monitor['ok']) ? 'OK' : 'FAILED' ?></div>
                            </article>

                            <article class="gn-s10-card is-success">
                                <div class="gn-s10-label">Monitor Total</div>
                                <div class="gn-s10-value"><?= gn_s10_h((string) ($monitor['total_human'] ?? '0 B')) ?></div>
                            </article>

                            <article class="gn-s10-card">
                                <div class="gn-s10-label">Monitor Uptime</div>
                                <div class="gn-s10-value"><?= gn_s10_h((string) ($monitor['total_uptime_human'] ?? '0s')) ?></div>
                            </article>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($baselineUsage)): ?>
                    <div class="gn-s10-box">
                        <h3>GreenNet Usage After Baseline</h3>

                        <div class="gn-s10-grid">
                            <article class="gn-s10-card is-success">
                                <div class="gn-s10-label">Used Since Baseline</div>
                                <div class="gn-s10-value"><?= gn_s10_h((string) ($baselineUsage['used_since_baseline_human'] ?? '0 B')) ?></div>
                            </article>

                            <article class="gn-s10-card <?= !empty($baselineUsage['has_baseline']) ? 'is-success' : 'is-warning' ?>">
                                <div class="gn-s10-label">Baseline Status</div>
                                <div class="gn-s10-value"><?= !empty($baselineUsage['has_baseline']) ? 'EXISTS' : 'MISSING' ?></div>
                            </article>

                            <article class="gn-s10-card">
                                <div class="gn-s10-label">Baseline Total</div>
                                <div class="gn-s10-value"><?= gn_s10_h((string) ($baselineUsage['baseline_total_human'] ?? '0 B')) ?></div>
                            </article>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($baseline)): ?>
                    <div class="gn-s10-box">
                        <h3>Latest Baseline</h3>

                        <div class="gn-s10-row">
                            <span>Baseline ID</span>
                            <strong><?= gn_s10_h((string) ($baseline['id'] ?? '-')) ?></strong>
                        </div>

                        <div class="gn-s10-row">
                            <span>Baseline At</span>
                            <strong><?= gn_s10_h((string) ($baseline['baseline_at'] ?? '-')) ?></strong>
                        </div>

                        <div class="gn-s10-row">
                            <span>Baseline Total</span>
                            <strong><?= gn_s10_h((string) ($baseline['baseline_total_human'] ?? '0 B')) ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($canCreateBaseline): ?>
                    <div class="gn-s10-box">
                        <h3>Create GreenNet Baseline</h3>
                        <p>هذا يحفظ نقطة بداية داخل SQLite فقط. لا يرسل أي Write إلى MikroTik.</p>

                        <form
                            method="post"
                            action="/admin/mikrotik-dry-run/execute"
                            onsubmit="return confirm('سيتم إنشاء Baseline داخل GreenNet فقط. متابعة؟');"
                        >
                            <input type="hidden" name="method" value="create_greennet_baseline">
                            <input type="hidden" name="username" value="<?= gn_s10_h((string) ($result['username'] ?? '')) ?>">

                            <div class="gn-s10-confirm-grid">
                                <label>اكتب BASELINE للتأكيد</label>
                                <input type="text" name="confirm_baseline" placeholder="BASELINE" dir="ltr" autocomplete="off" required>
                            </div>

                            <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit" style="margin-top:14px;">
                                Create GreenNet Baseline
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($result['notes']) && is_array($result['notes'])): ?>
                <div class="gn-s10-box">
                    <h3>Notes</h3>
                    <?php foreach ($result['notes'] as $note): ?>
                        <div class="gn-s10-info"><?= gn_s10_h((string) $note) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <details style="margin-top:18px;">
                <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw Result</summary>
                <pre class="gn-s10-command"><?= gn_s10_h(gn_s10_pretty($result)) ?></pre>
            </details>
        </div>
    </section>
<?php endif; ?>