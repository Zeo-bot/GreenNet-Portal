<?php

declare(strict_types=1);

if (!function_exists('gn_um_create_h')) {
    function gn_um_create_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_um_create_pretty')) {
    function gn_um_create_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (!function_exists('gn_um_create_bool')) {
    function gn_um_create_bool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (int) $value === 1;

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}

$customers = is_array($customers ?? null) ? $customers : [];
$packages = is_array($packages ?? null) ? $packages : [];
$preflight = is_array($preflight ?? null) ? $preflight : [];
$result = is_array($result ?? null) ? $result : null;
$message = (string) ($message ?? '');
$messageType = (string) ($message_type ?? 'info');

$safeMode = gn_um_create_bool($preflight['greennet_safe_mode'] ?? $preflight['safe_mode'] ?? true);
$writeEnabled = gn_um_create_bool($preflight['mikrotik_write_enabled'] ?? $preflight['write_enabled'] ?? false);
$freshBackup = gn_um_create_bool($preflight['fresh_backup'] ?? $preflight['has_fresh_backup'] ?? false);

$selectedUsername = (string) ($result['username'] ?? '');
$selectedPackageId = (int) ($result['package_id'] ?? 0);
$router = is_array($result['router'] ?? null) ? $result['router'] : [];
$operations = is_array($result['operations'] ?? null) ? $result['operations'] : [];
$realResult = is_array($result['real_result'] ?? null) ? $result['real_result'] : [];
$canExecute = $result !== null && !empty($result['can_execute_later']) && empty($result['executed']) && empty($result['execute_error']);
?>

<style>
    .gn-um-create-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-um-create-card {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-um-create-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-um-create-card.is-success::before { background: var(--gn-success); }
    .gn-um-create-card.is-warning::before { background: var(--gn-warning); }
    .gn-um-create-card.is-danger::before { background: var(--gn-danger); }

    .gn-um-create-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-um-create-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        line-height: 1.25;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-um-create-box {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-um-create-box h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-um-create-box p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-um-create-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 12px;
        align-items: end;
        margin-top: 14px;
    }

    .gn-um-create-field label {
        display: block;
        color: var(--gn-text);
        font-weight: 950;
        margin-bottom: 8px;
    }

    .gn-um-create-field select,
    .gn-um-create-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        box-sizing: border-box;
    }

    .gn-um-create-row {
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

    .gn-um-create-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
        text-align: left;
    }

    .gn-um-create-alert,
    .gn-um-create-success,
    .gn-um-create-danger,
    .gn-um-create-info {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-um-create-alert {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    .gn-um-create-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
    }

    .gn-um-create-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.25);
    }

    .gn-um-create-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
    }

    .gn-um-create-code {
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
        .gn-um-create-grid,
        .gn-um-create-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Create User Manager User</h1>
        <p class="admin-page-description">
            S10.9 — إنشاء مستخدم داخل MikroTik User Manager من مشترك GreenNet وربطه بالباقـة.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers">Customers</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/package-assign">Assign / Replace</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/package-push">Push Packages</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="<?= $messageType === 'success' ? 'gn-um-create-success' : 'gn-um-create-alert' ?>">
        <?= gn_um_create_h($message) ?>
    </div>
<?php endif; ?>

<section class="gn-um-create-grid">
    <article class="gn-um-create-card <?= $safeMode ? 'is-warning' : 'is-success' ?>">
        <div class="gn-um-create-label">Safe Mode</div>
        <div class="gn-um-create-value"><?= $safeMode ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-um-create-card <?= $writeEnabled ? 'is-success' : 'is-warning' ?>">
        <div class="gn-um-create-label">Write Enabled</div>
        <div class="gn-um-create-value"><?= $writeEnabled ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-um-create-card <?= $freshBackup ? 'is-success' : 'is-warning' ?>">
        <div class="gn-um-create-label">Fresh Backup</div>
        <div class="gn-um-create-value"><?= $freshBackup ? 'ON' : 'OFF' ?></div>
    </article>
</section>

<div class="gn-um-create-info">
    هذه الصفحة تنشئ المستخدم في MikroTik User Manager وتضيف له Profile الباقة المختارة مباشرة.
</div>

<section class="gn-um-create-box">
    <h3>Create Dry Run</h3>
    <p>اختر مشتركاً موجوداً داخل GreenNet. إذا لم يكن موجوداً، أنشئه أولاً من صفحة Customers.</p>

    <?php if (empty($customers)): ?>
        <div class="gn-um-create-alert">لا يوجد مشتركين داخل GreenNet.</div>
    <?php elseif (empty($packages)): ?>
        <div class="gn-um-create-alert">لا توجد باقات فعالة داخل GreenNet.</div>
    <?php else: ?>
        <form method="post" action="/admin/user-manager-user-create/preview">
            <div class="gn-um-create-form-grid">
                <div class="gn-um-create-field">
                    <label>Customer</label>
                    <select name="username" required>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                                $username = (string) ($customer['username'] ?? '');
                                $name = (string) ($customer['full_name'] ?? $customer['display_name'] ?? '');
                                $currentPackageId = (string) ($customer['package_id'] ?? '0');
                            ?>
                            <option value="<?= gn_um_create_h($username) ?>" <?= $username === $selectedUsername ? 'selected' : '' ?>>
                                <?= gn_um_create_h($username) ?>
                                <?= $name !== '' ? ' - ' . gn_um_create_h($name) : '' ?>
                                | current package: #<?= gn_um_create_h($currentPackageId) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gn-um-create-field">
                    <label>Package</label>
                    <select name="package_id" required>
                        <?php foreach ($packages as $package): ?>
                            <?php
                                $id = (int) ($package['id'] ?? 0);
                                $name = (string) ($package['name'] ?? '');
                                $profile = (string) ($package['source_profile'] ?? '');
                                $quota = (string) ($package['quota_gb'] ?? '0');
                                $days = (string) ($package['duration_days'] ?? '0');
                            ?>
                            <option value="<?= gn_um_create_h((string) $id) ?>" <?= $id === $selectedPackageId ? 'selected' : '' ?>>
                                #<?= gn_um_create_h((string) $id) ?>
                                -
                                <?= gn_um_create_h($name) ?>
                                |
                                profile: <?= gn_um_create_h($profile !== '' ? $profile : $name) ?>
                                |
                                <?= gn_um_create_h($quota) ?>GB
                                |
                                <?= gn_um_create_h($days) ?>d
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gn-um-create-field">
                    <label>User Manager Password</label>
                    <input type="password" name="password" required autocomplete="new-password" placeholder="Password">
                </div>
            </div>

            <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit" style="margin-top:14px;">
                Create Dry Run
            </button>
        </form>
    <?php endif; ?>
</section>

<?php if ($result !== null): ?>
    <section class="gn-um-create-box">
        <h3>Dry Run Result</h3>

        <?php if (!empty($result['error'])): ?>
            <div class="gn-um-create-alert"><?= gn_um_create_h((string) $result['error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($result['execute_error'])): ?>
            <div class="gn-um-create-danger"><?= gn_um_create_h((string) $result['execute_error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($realResult)): ?>
            <div class="<?= !empty($realResult['ok']) ? 'gn-um-create-success' : 'gn-um-create-danger' ?>">
                التنفيذ الحقيقي:
                <strong><?= !empty($realResult['ok']) ? 'نجح' : 'فشل' ?></strong>
                <br>
                <?= gn_um_create_h((string) ($realResult['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <div class="gn-um-create-grid">
            <article class="gn-um-create-card <?= empty($result['user_found']) ? 'is-success' : 'is-danger' ?>">
                <div class="gn-um-create-label">User Exists on MikroTik</div>
                <div class="gn-um-create-value"><?= !empty($result['user_found']) ? 'YES' : 'NO' ?></div>
            </article>

            <article class="gn-um-create-card <?= !empty($result['profile_found']) ? 'is-success' : 'is-danger' ?>">
                <div class="gn-um-create-label">Profile Found</div>
                <div class="gn-um-create-value"><?= !empty($result['profile_found']) ? 'YES' : 'NO' ?></div>
            </article>

            <article class="gn-um-create-card is-success">
                <div class="gn-um-create-label">Operations</div>
                <div class="gn-um-create-value"><?= gn_um_create_h((string) ($result['operations_count'] ?? 0)) ?></div>
            </article>
        </div>

        <div class="gn-um-create-box">
            <h3>Target</h3>

            <div class="gn-um-create-row">
                <span>Username</span>
                <strong><?= gn_um_create_h((string) ($result['username'] ?? '-')) ?></strong>
            </div>

            <div class="gn-um-create-row">
                <span>Package ID</span>
                <strong><?= gn_um_create_h((string) ($result['package_id'] ?? '-')) ?></strong>
            </div>

            <div class="gn-um-create-row">
                <span>MikroTik Profile</span>
                <strong><?= gn_um_create_h((string) ($result['router_profile_name'] ?? '-')) ?></strong>
            </div>
        </div>

        <?php if (!empty($result['block_reason'])): ?>
            <div class="gn-um-create-alert"><?= gn_um_create_h((string) $result['block_reason']) ?></div>
        <?php endif; ?>

        <div class="gn-um-create-box">
            <h3>Planned Operations</h3>

            <?php if (empty($operations)): ?>
                <div class="gn-um-create-info">لا توجد عمليات مطلوبة.</div>
            <?php else: ?>
                <?php foreach ($operations as $operation): ?>
                    <div class="gn-um-create-row">
                        <span><?= gn_um_create_h((string) ($operation['type'] ?? '-')) ?></span>
                        <strong><?= gn_um_create_h((string) ($operation['command'] ?? '-')) ?></strong>
                    </div>

                    <pre class="gn-um-create-code"><?= gn_um_create_h(gn_um_create_pretty($operation['params'] ?? [])) ?></pre>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canExecute): ?>
            <div class="gn-um-create-box">
                <h3>Execute Real Create</h3>

                <div class="gn-um-create-alert">
                    أعد إدخال كلمة المرور، ثم اكتب:
                    <strong dir="ltr">CREATE</strong>
                </div>

                <form method="post" action="/admin/user-manager-user-create/execute" onsubmit="return confirm('سيتم إنشاء مستخدم حقيقي على MikroTik. متابعة؟');">
                    <input type="hidden" name="username" value="<?= gn_um_create_h((string) ($result['username'] ?? '')) ?>">
                    <input type="hidden" name="package_id" value="<?= gn_um_create_h((string) ($result['package_id'] ?? 0)) ?>">

                    <div class="gn-um-create-form-grid">
                        <div class="gn-um-create-field">
                            <label>User Manager Password</label>
                            <input type="password" name="password" required autocomplete="new-password" placeholder="Password">
                        </div>

                        <div class="gn-um-create-field">
                            <label>Confirmation</label>
                            <input type="text" name="confirm_create" placeholder="CREATE" dir="ltr" autocomplete="off" required>
                        </div>

                        <div>
                            <button class="gn-btn gn-btn-danger gn-btn-lg" type="submit" style="width:100%;">
                                Execute Real Create
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($result['notes']) && is_array($result['notes'])): ?>
            <div class="gn-um-create-box">
                <h3>Notes</h3>
                <?php foreach ($result['notes'] as $note): ?>
                    <div class="gn-um-create-info"><?= gn_um_create_h((string) $note) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <details style="margin-top:18px;">
            <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw Result</summary>
            <pre class="gn-um-create-code"><?= gn_um_create_h(gn_um_create_pretty($result)) ?></pre>
        </details>
    </section>
<?php endif; ?>