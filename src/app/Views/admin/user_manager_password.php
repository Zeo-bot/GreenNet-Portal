<?php

declare(strict_types=1);

if (!function_exists('gn_um_pass_h')) {
    function gn_um_pass_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_um_pass_pretty')) {
    function gn_um_pass_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (!function_exists('gn_um_pass_bool')) {
    function gn_um_pass_bool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (int) $value === 1;

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}

$customers = is_array($customers ?? null) ? $customers : [];
$preflight = is_array($preflight ?? null) ? $preflight : [];
$result = is_array($result ?? null) ? $result : null;
$message = (string) ($message ?? '');
$messageType = (string) ($message_type ?? 'info');

$safeMode = gn_um_pass_bool($preflight['greennet_safe_mode'] ?? $preflight['safe_mode'] ?? true);
$writeEnabled = gn_um_pass_bool($preflight['mikrotik_write_enabled'] ?? $preflight['write_enabled'] ?? false);
$freshBackup = gn_um_pass_bool($preflight['fresh_backup'] ?? $preflight['has_fresh_backup'] ?? false);

$selectedUsername = (string) ($result['username'] ?? '');
$operations = is_array($result['operations'] ?? null) ? $result['operations'] : [];
$realResult = is_array($result['real_result'] ?? null) ? $result['real_result'] : [];
$canExecute = $result !== null && !empty($result['can_execute_later']) && empty($result['executed']) && empty($result['execute_error']);
?>

<style>
    .gn-pass-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-pass-card,
    .gn-pass-box {
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        border-radius: var(--gn-radius-xl);
    }

    .gn-pass-card {
        position: relative;
        padding: 16px;
        overflow: hidden;
    }

    .gn-pass-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-pass-card.is-success::before { background: var(--gn-success); }
    .gn-pass-card.is-warning::before { background: var(--gn-warning); }
    .gn-pass-card.is-danger::before { background: var(--gn-danger); }

    .gn-pass-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-pass-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-pass-box {
        margin-top: 18px;
        padding: 16px;
    }

    .gn-pass-box h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-pass-box p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-pass-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 12px;
        align-items: end;
        margin-top: 14px;
    }

    .gn-pass-field label {
        display: block;
        color: var(--gn-text);
        font-weight: 950;
        margin-bottom: 8px;
    }

    .gn-pass-field select,
    .gn-pass-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        box-sizing: border-box;
    }

    .gn-pass-row {
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

    .gn-pass-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
        text-align: left;
    }

    .gn-pass-alert,
    .gn-pass-success,
    .gn-pass-danger,
    .gn-pass-info {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-pass-alert {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    .gn-pass-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
    }

    .gn-pass-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.25);
    }

    .gn-pass-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
    }

    .gn-pass-code {
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

    @media (max-width: 1000px) {
        .gn-pass-grid,
        .gn-pass-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Change User Manager Password</h1>
        <p class="admin-page-description">
            S10.10 — تغيير كلمة مرور مستخدم MikroTik User Manager من GreenNet.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers">Customers</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/user-manager-user-create">Create UM User</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/package-assign">Assign / Replace</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="<?= $messageType === 'success' ? 'gn-pass-success' : 'gn-pass-alert' ?>">
        <?= gn_um_pass_h($message) ?>
    </div>
<?php endif; ?>

<section class="gn-pass-grid">
    <article class="gn-pass-card <?= $safeMode ? 'is-warning' : 'is-success' ?>">
        <div class="gn-pass-label">Safe Mode</div>
        <div class="gn-pass-value"><?= $safeMode ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-pass-card <?= $writeEnabled ? 'is-success' : 'is-warning' ?>">
        <div class="gn-pass-label">Write Enabled</div>
        <div class="gn-pass-value"><?= $writeEnabled ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-pass-card <?= $freshBackup ? 'is-success' : 'is-warning' ?>">
        <div class="gn-pass-label">Fresh Backup</div>
        <div class="gn-pass-value"><?= $freshBackup ? 'ON' : 'OFF' ?></div>
    </article>
</section>

<div class="gn-pass-info">
    هذه الصفحة تغيّر كلمة مرور المستخدم داخل MikroTik User Manager فقط، ولا تغيّر كلمة مرور لوحة المشترك المحلية في GreenNet.
</div>

<section class="gn-pass-box">
    <h3>Create Dry Run</h3>
    <p>اختر مشتركاً موجوداً داخل MikroTik User Manager، ثم أدخل كلمة المرور الجديدة.</p>

    <?php if (empty($customers)): ?>
        <div class="gn-pass-alert">لا يوجد مشتركين داخل GreenNet.</div>
    <?php else: ?>
        <form method="post" action="/admin/user-manager-password/preview">
            <div class="gn-pass-form-grid">
                <div class="gn-pass-field">
                    <label>Customer</label>
                    <select name="username" required>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                                $username = (string) ($customer['username'] ?? '');
                                $name = (string) ($customer['full_name'] ?? $customer['display_name'] ?? '');
                            ?>
                            <option value="<?= gn_um_pass_h($username) ?>" <?= $username === $selectedUsername ? 'selected' : '' ?>>
                                <?= gn_um_pass_h($username) ?>
                                <?= $name !== '' ? ' - ' . gn_um_pass_h($name) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gn-pass-field">
                    <label>New User Manager Password</label>
                    <input type="password" name="password" required autocomplete="new-password" placeholder="New password">
                </div>

                <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                    Create Dry Run
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($result !== null): ?>
    <section class="gn-pass-box">
        <h3>Dry Run Result</h3>

        <?php if (!empty($result['error'])): ?>
            <div class="gn-pass-alert"><?= gn_um_pass_h((string) $result['error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($result['execute_error'])): ?>
            <div class="gn-pass-danger"><?= gn_um_pass_h((string) $result['execute_error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($realResult)): ?>
            <div class="<?= !empty($realResult['ok']) ? 'gn-pass-success' : 'gn-pass-danger' ?>">
                التنفيذ الحقيقي:
                <strong><?= !empty($realResult['ok']) ? 'نجح' : 'فشل' ?></strong>
                <br>
                <?= gn_um_pass_h((string) ($realResult['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <div class="gn-pass-grid">
            <article class="gn-pass-card <?= !empty($result['user_found']) ? 'is-success' : 'is-danger' ?>">
                <div class="gn-pass-label">User Found</div>
                <div class="gn-pass-value"><?= !empty($result['user_found']) ? 'YES' : 'NO' ?></div>
            </article>

            <article class="gn-pass-card is-success">
                <div class="gn-pass-label">Router User ID</div>
                <div class="gn-pass-value"><?= gn_um_pass_h((string) ($result['router_user_id'] ?? '-')) ?></div>
            </article>

            <article class="gn-pass-card is-success">
                <div class="gn-pass-label">Operations</div>
                <div class="gn-pass-value"><?= gn_um_pass_h((string) ($result['operations_count'] ?? 0)) ?></div>
            </article>
        </div>

        <div class="gn-pass-box">
            <h3>Target</h3>

            <div class="gn-pass-row">
                <span>Username</span>
                <strong><?= gn_um_pass_h((string) ($result['username'] ?? '-')) ?></strong>
            </div>

            <div class="gn-pass-row">
                <span>Command</span>
                <strong>/user-manager/user/set</strong>
            </div>
        </div>

        <?php if (!empty($result['block_reason'])): ?>
            <div class="gn-pass-alert"><?= gn_um_pass_h((string) $result['block_reason']) ?></div>
        <?php endif; ?>

        <div class="gn-pass-box">
            <h3>Planned Operations</h3>

            <?php if (empty($operations)): ?>
                <div class="gn-pass-info">لا توجد عمليات مطلوبة.</div>
            <?php else: ?>
                <?php foreach ($operations as $operation): ?>
                    <div class="gn-pass-row">
                        <span><?= gn_um_pass_h((string) ($operation['type'] ?? '-')) ?></span>
                        <strong><?= gn_um_pass_h((string) ($operation['command'] ?? '-')) ?></strong>
                    </div>

                    <pre class="gn-pass-code"><?= gn_um_pass_h(gn_um_pass_pretty($operation['params'] ?? [])) ?></pre>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canExecute): ?>
            <div class="gn-pass-box">
                <h3>Execute Real Password Change</h3>

                <div class="gn-pass-alert">
                    أعد إدخال كلمة المرور الجديدة، ثم اكتب:
                    <strong dir="ltr">PASSWORD</strong>
                </div>

                <form method="post" action="/admin/user-manager-password/execute" onsubmit="return confirm('سيتم تغيير كلمة مرور مستخدم حقيقي على MikroTik. متابعة؟');">
                    <input type="hidden" name="username" value="<?= gn_um_pass_h((string) ($result['username'] ?? '')) ?>">

                    <div class="gn-pass-form-grid">
                        <div class="gn-pass-field">
                            <label>New User Manager Password</label>
                            <input type="password" name="password" required autocomplete="new-password" placeholder="New password">
                        </div>

                        <div class="gn-pass-field">
                            <label>Confirmation</label>
                            <input type="text" name="confirm_password" placeholder="PASSWORD" dir="ltr" autocomplete="off" required>
                        </div>

                        <button class="gn-btn gn-btn-danger gn-btn-lg" type="submit">
                            Execute Real Change
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($result['notes']) && is_array($result['notes'])): ?>
            <div class="gn-pass-box">
                <h3>Notes</h3>
                <?php foreach ($result['notes'] as $note): ?>
                    <div class="gn-pass-info"><?= gn_um_pass_h((string) $note) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <details style="margin-top:18px;">
            <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw Result</summary>
            <pre class="gn-pass-code"><?= gn_um_pass_h(gn_um_pass_pretty($result)) ?></pre>
        </details>
    </section>
<?php endif; ?>