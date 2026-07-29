<?php

declare(strict_types=1);

if (!function_exists('gn_assign_h')) {
    function gn_assign_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_assign_pretty')) {
    function gn_assign_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (!function_exists('gn_assign_bool')) {
    function gn_assign_bool(mixed $value): bool
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

$safeMode = gn_assign_bool($preflight['greennet_safe_mode'] ?? $preflight['safe_mode'] ?? true);
$writeEnabled = gn_assign_bool($preflight['mikrotik_write_enabled'] ?? $preflight['write_enabled'] ?? false);
$freshBackup = gn_assign_bool($preflight['fresh_backup'] ?? $preflight['has_fresh_backup'] ?? false);

$selectedUsername = (string) ($result['username'] ?? $requested_username ?? '');
$selectedPackageId = (int) ($result['package_id'] ?? $requested_package_id ?? 0);
$mode = (string) ($result['mode'] ?? $requested_mode ?? 'add');
$modeLabel = (string) ($result['mode_label'] ?? ($mode === 'replace' ? 'Replace Package' : 'Add Only'));
$router = is_array($result['router'] ?? null) ? $result['router'] : [];
$assignment = is_array($router['assignment'] ?? null) ? $router['assignment'] : [];
$operations = is_array($result['operations'] ?? null) ? $result['operations'] : [];
$realResult = is_array($result['real_result'] ?? null) ? $result['real_result'] : [];

$canExecute = $result !== null && !empty($result['can_execute_later']) && empty($result['executed']) && empty($result['execute_error']);
$confirmWord = $mode === 'replace' ? 'REPLACE' : 'ASSIGN';
?>

<style>
    .gn-assign-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-assign-card {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-assign-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-assign-card.is-success::before { background: var(--gn-success); }
    .gn-assign-card.is-warning::before { background: var(--gn-warning); }
    .gn-assign-card.is-danger::before { background: var(--gn-danger); }

    .gn-assign-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-assign-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        line-height: 1.25;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-assign-box {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-assign-box h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-assign-box p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-assign-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        align-items: end;
        margin-top: 14px;
    }

    .gn-assign-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
    }

    .gn-assign-field label {
        display: block;
        color: var(--gn-text);
        font-weight: 950;
        margin-bottom: 8px;
    }

    .gn-assign-field select,
    .gn-assign-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        box-sizing: border-box;
    }

    .gn-assign-row {
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

    .gn-assign-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
        text-align: left;
    }

    .gn-assign-alert,
    .gn-assign-success,
    .gn-assign-danger,
    .gn-assign-info {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-assign-alert {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    .gn-assign-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
    }

    .gn-assign-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.25);
    }

    .gn-assign-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
    }

    .gn-assign-code {
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
        .gn-assign-grid,
        .gn-assign-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Assign / Replace Package</h1>
        <p class="admin-page-description">
            S10.8 — إضافة أو استبدال باقة المشترك على MikroTik User Manager.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers">Customers</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/packages">Packages</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/package-push">Push Packages</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="<?= $messageType === 'success' ? 'gn-assign-success' : 'gn-assign-alert' ?>">
        <?= gn_assign_h($message) ?>
    </div>
<?php endif; ?>

<section class="gn-assign-grid">
    <article class="gn-assign-card <?= $safeMode ? 'is-warning' : 'is-success' ?>">
        <div class="gn-assign-label">Safe Mode</div>
        <div class="gn-assign-value"><?= $safeMode ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-assign-card <?= $writeEnabled ? 'is-success' : 'is-warning' ?>">
        <div class="gn-assign-label">Write Enabled</div>
        <div class="gn-assign-value"><?= $writeEnabled ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-assign-card <?= $freshBackup ? 'is-success' : 'is-warning' ?>">
        <div class="gn-assign-label">Fresh Backup</div>
        <div class="gn-assign-value"><?= $freshBackup ? 'ON' : 'OFF' ?></div>
    </article>
</section>

<div class="gn-assign-info">
    <strong>Add Only</strong>: يضيف الباقة الجديدة بدون حذف القديم.
    <br>
    <strong>Replace Package</strong>: يحذف كل user-profile القديم للمستخدم ثم يضيف الباقة الجديدة.
</div>

<section class="gn-assign-box">
    <h3>تغيير الباقة على الراوتر</h3>
    <p>اختر مشتركاً وباقة. يجب أن تكون الباقة موجودة على MikroTik كـ Profile، يعني اعمل Push لها أولاً إذا كانت محلية فقط.</p>

    <?php if (empty($customers)): ?>
        <div class="gn-assign-alert">لا يوجد مشتركين داخل GreenNet.</div>
    <?php elseif (empty($packages)): ?>
        <div class="gn-assign-alert">لا توجد باقات فعالة داخل GreenNet.</div>
    <?php else: ?>
        <form method="post" action="/admin/package-assign/preview">
            <div class="gn-assign-form-grid">
                <div class="gn-assign-field">
                    <label>Customer</label>
                    <select name="username" required>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                                $username = (string) ($customer['username'] ?? '');
                                $name = (string) ($customer['full_name'] ?? $customer['display_name'] ?? '');
                                $currentPackageId = (string) ($customer['package_id'] ?? '0');
                            ?>
                            <option value="<?= gn_assign_h($username) ?>" <?= $username === $selectedUsername ? 'selected' : '' ?>>
                                <?= gn_assign_h($username) ?>
                                <?= $name !== '' ? ' - ' . gn_assign_h($name) : '' ?>
                                | current package: #<?= gn_assign_h($currentPackageId) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gn-assign-field">
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
                            <option value="<?= gn_assign_h((string) $id) ?>" <?= $id === $selectedPackageId ? 'selected' : '' ?>>
                                #<?= gn_assign_h((string) $id) ?>
                                -
                                <?= gn_assign_h($name) ?>
                                |
                                profile: <?= gn_assign_h($profile !== '' ? $profile : $name) ?>
                                |
                                <?= gn_assign_h($quota) ?>GB
                                |
                                <?= gn_assign_h($days) ?>d
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="gn-assign-actions">
                <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit" name="assign_mode" value="add">
                    فحص الإضافة
                </button>

                <button class="gn-btn gn-btn-danger gn-btn-lg" type="submit" name="assign_mode" value="replace">
                    فحص الاستبدال
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($result !== null): ?>
    <section class="gn-assign-box">
        <h3>جاهزية تغيير الباقة</h3>

        <?php if (!empty($result['error'])): ?>
            <div class="gn-assign-alert"><?= gn_assign_h((string) $result['error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($result['execute_error'])): ?>
            <div class="gn-assign-danger"><?= gn_assign_h((string) $result['execute_error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($realResult)): ?>
            <div class="<?= !empty($realResult['ok']) ? 'gn-assign-success' : 'gn-assign-danger' ?>">
                التنفيذ الحقيقي:
                <strong><?= !empty($realResult['ok']) ? 'نجح' : 'فشل' ?></strong>
                <br>
                <?= gn_assign_h((string) ($realResult['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <div class="gn-assign-grid">
            <article class="gn-assign-card <?= $mode === 'replace' ? 'is-danger' : 'is-success' ?>">
                <div class="gn-assign-label">Mode</div>
                <div class="gn-assign-value"><?= gn_assign_h($modeLabel) ?></div>
            </article>

            <article class="gn-assign-card <?= !empty($result['user_found']) ? 'is-success' : 'is-danger' ?>">
                <div class="gn-assign-label">User Found</div>
                <div class="gn-assign-value"><?= !empty($result['user_found']) ? 'YES' : 'NO' ?></div>
            </article>

            <article class="gn-assign-card <?= !empty($result['profile_found']) ? 'is-success' : 'is-danger' ?>">
                <div class="gn-assign-label">Profile Found</div>
                <div class="gn-assign-value"><?= !empty($result['profile_found']) ? 'YES' : 'NO' ?></div>
            </article>
        </div>

        <div class="gn-assign-grid">
            <article class="gn-assign-card <?= !empty($result['already_assigned']) ? 'is-success' : 'is-warning' ?>">
                <div class="gn-assign-label">Already Assigned</div>
                <div class="gn-assign-value"><?= !empty($result['already_assigned']) ? 'YES' : 'NO' ?></div>
            </article>

            <article class="gn-assign-card <?= !empty($result['local_needs_update']) ? 'is-warning' : 'is-success' ?>">
                <div class="gn-assign-label">Local Update</div>
                <div class="gn-assign-value"><?= !empty($result['local_needs_update']) ? 'YES' : 'NO' ?></div>
            </article>

            <article class="gn-assign-card is-success">
                <div class="gn-assign-label">Operations</div>
                <div class="gn-assign-value"><?= gn_assign_h((string) ($result['operations_count'] ?? 0)) ?></div>
            </article>
        </div>

        <div class="gn-assign-box">
            <h3>Target</h3>

            <div class="gn-assign-row">
                <span>Username</span>
                <strong><?= gn_assign_h((string) ($result['username'] ?? '-')) ?></strong>
            </div>

            <div class="gn-assign-row">
                <span>Package ID</span>
                <strong><?= gn_assign_h((string) ($result['package_id'] ?? '-')) ?></strong>
            </div>

            <div class="gn-assign-row">
                <span>MikroTik Profile</span>
                <strong><?= gn_assign_h((string) ($result['router_profile_name'] ?? '-')) ?></strong>
            </div>

            <div class="gn-assign-row">
                <span>Mode</span>
                <strong><?= gn_assign_h($modeLabel) ?></strong>
            </div>
        </div>

        <?php if (!empty($result['block_reason'])): ?>
            <div class="gn-assign-alert"><?= gn_assign_h((string) $result['block_reason']) ?></div>
        <?php endif; ?>

        <div class="gn-assign-box">
            <h3>Current User Profiles on MikroTik</h3>

            <?php if (empty($assignment['current_profiles'])): ?>
                <div class="gn-assign-alert">لا يوجد User Profiles حالياً لهذا المستخدم.</div>
            <?php else: ?>
                <?php foreach ($assignment['current_profiles'] as $profileRow): ?>
                    <div class="gn-assign-row">
                        <span><?= gn_assign_h((string) ($profileRow['profile'] ?? '-')) ?></span>
                        <strong>
                            id:
                            <?= gn_assign_h((string) ($profileRow['id'] ?? '-')) ?>
                            |
                            state:
                            <?= gn_assign_h((string) ($profileRow['state'] ?? '-')) ?>
                            |
                            end:
                            <?= gn_assign_h((string) ($profileRow['end_time'] ?? '-')) ?>
                        </strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="gn-assign-box">
            <h3>Planned Operations</h3>

            <?php if (empty($operations)): ?>
                <div class="gn-assign-info">لا توجد عمليات مطلوبة.</div>
            <?php else: ?>
                <?php foreach ($operations as $operation): ?>
                    <div class="gn-assign-row">
                        <span><?= gn_assign_h((string) ($operation['type'] ?? '-')) ?></span>
                        <strong><?= gn_assign_h((string) ($operation['command'] ?? '-')) ?></strong>
                    </div>

                    <pre class="gn-assign-code"><?= gn_assign_h(gn_assign_pretty($operation['params'] ?? [])) ?></pre>

                    <?php if (!empty($operation['display'])): ?>
                        <pre class="gn-assign-code"><?= gn_assign_h(gn_assign_pretty($operation['display'])) ?></pre>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canExecute): ?>
            <div class="gn-assign-box">
                <h3>Execute Real <?= gn_assign_h($modeLabel) ?></h3>

                <?php if ($mode === 'replace'): ?>
                    <div class="gn-assign-danger">
                        سيتم حذف كل user-profile الحالي لهذا المستخدم ثم إضافة الباقة الجديدة.
                    </div>
                <?php else: ?>
                    <div class="gn-assign-info">
                        سيتم إضافة الباقة الجديدة فقط بدون حذف القديم.
                    </div>
                <?php endif; ?>

                <div class="gn-assign-alert">
                    للتنفيذ اكتب:
                    <strong dir="ltr"><?= gn_assign_h($confirmWord) ?></strong>
                </div>

                <form method="post" action="/admin/package-assign/execute" onsubmit="return confirm('سيتم تنفيذ Write حقيقي على MikroTik. متابعة؟');">
                    <input type="hidden" name="username" value="<?= gn_assign_h((string) ($result['username'] ?? '')) ?>">
                    <input type="hidden" name="package_id" value="<?= gn_assign_h((string) ($result['package_id'] ?? 0)) ?>">
                    <input type="hidden" name="assign_mode" value="<?= gn_assign_h($mode) ?>">

                    <div class="gn-assign-field" style="margin-top:14px;">
                        <label>Confirmation</label>
                        <input type="text" name="confirm_assign" placeholder="<?= gn_assign_h($confirmWord) ?>" dir="ltr" autocomplete="off" required>
                    </div>

                    <button class="gn-btn <?= $mode === 'replace' ? 'gn-btn-danger' : 'gn-btn-primary' ?> gn-btn-lg" type="submit" style="margin-top:14px;">
                        Execute Real <?= gn_assign_h($modeLabel) ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($result['notes']) && is_array($result['notes'])): ?>
            <div class="gn-assign-box">
                <h3>Notes</h3>
                <?php foreach ($result['notes'] as $note): ?>
                    <div class="gn-assign-info"><?= gn_assign_h((string) $note) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <details style="margin-top:18px;">
            <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw Result</summary>
            <pre class="gn-assign-code"><?= gn_assign_h(gn_assign_pretty($result)) ?></pre>
        </details>
    </section>
<?php endif; ?>
