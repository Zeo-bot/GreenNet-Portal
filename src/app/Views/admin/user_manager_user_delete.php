<?php

declare(strict_types=1);

if (!function_exists('gn_um_del_h')) {
    function gn_um_del_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_um_del_pretty')) {
    function gn_um_del_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (!function_exists('gn_um_del_bool')) {
    function gn_um_del_bool(mixed $value): bool
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

$safeMode = gn_um_del_bool($preflight['greennet_safe_mode'] ?? $preflight['safe_mode'] ?? true);
$writeEnabled = gn_um_del_bool($preflight['mikrotik_write_enabled'] ?? $preflight['write_enabled'] ?? false);
$freshBackup = gn_um_del_bool($preflight['fresh_backup'] ?? $preflight['has_fresh_backup'] ?? false);

$selectedUsername = (string) ($result['username'] ?? $requested_username ?? '');
$router = is_array($result['router'] ?? null) ? $result['router'] : [];
$userProfiles = is_array($router['user_profiles']['rows'] ?? null) ? $router['user_profiles']['rows'] : [];
$sessions = is_array($router['sessions']['rows'] ?? null) ? $router['sessions']['rows'] : [];
$operations = is_array($result['operations'] ?? null) ? $result['operations'] : [];
$realResult = is_array($result['real_result'] ?? null) ? $result['real_result'] : [];
$canExecute = $result !== null && !empty($result['can_execute_later']) && empty($result['executed']) && empty($result['execute_error']);
?>

<style>
    .gn-del-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-del-card,
    .gn-del-box {
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        border-radius: var(--gn-radius-xl);
    }

    .gn-del-card {
        position: relative;
        padding: 16px;
        overflow: hidden;
    }

    .gn-del-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-del-card.is-success::before { background: var(--gn-success); }
    .gn-del-card.is-warning::before { background: var(--gn-warning); }
    .gn-del-card.is-danger::before { background: var(--gn-danger); }

    .gn-del-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-del-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-del-box {
        margin-top: 18px;
        padding: 16px;
    }

    .gn-del-box h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-del-box p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-del-form-grid {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: end;
        margin-top: 14px;
    }

    .gn-del-field label {
        display: block;
        color: var(--gn-text);
        font-weight: 950;
        margin-bottom: 8px;
    }

    .gn-del-field select,
    .gn-del-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        box-sizing: border-box;
    }

    .gn-del-row {
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

    .gn-del-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
        text-align: left;
    }

    .gn-del-alert,
    .gn-del-success,
    .gn-del-danger,
    .gn-del-info {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-del-alert {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    .gn-del-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
    }

    .gn-del-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.25);
    }

    .gn-del-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
    }

    .gn-del-code {
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
        .gn-del-grid,
        .gn-del-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Delete User Manager User</h1>
        <p class="admin-page-description">
            S10.11 — حذف مستخدم MikroTik User Manager وتنظيف user-profile والجلسات.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers">Customers</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/user-manager-user-create">Create UM User</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/user-manager-password">UM Password</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="<?= $messageType === 'success' ? 'gn-del-success' : 'gn-del-alert' ?>">
        <?= gn_um_del_h($message) ?>
    </div>
<?php endif; ?>

<section class="gn-del-grid">
    <article class="gn-del-card <?= $safeMode ? 'is-warning' : 'is-success' ?>">
        <div class="gn-del-label">Safe Mode</div>
        <div class="gn-del-value"><?= $safeMode ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-del-card <?= $writeEnabled ? 'is-success' : 'is-warning' ?>">
        <div class="gn-del-label">Write Enabled</div>
        <div class="gn-del-value"><?= $writeEnabled ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-del-card <?= $freshBackup ? 'is-success' : 'is-warning' ?>">
        <div class="gn-del-label">Fresh Backup</div>
        <div class="gn-del-value"><?= $freshBackup ? 'ON' : 'OFF' ?></div>
    </article>
</section>

<div class="gn-del-danger">
    هذه الصفحة تحذف المستخدم من MikroTik User Manager فقط، ولا تحذفه من قاعدة GreenNet المحلية.
</div>

<section class="gn-del-box">
    <h3>Create Dry Run</h3>
    <p>اختر مستخدماً من GreenNet، وسيتم البحث عنه داخل MikroTik User Manager قبل تنفيذ الحذف.</p>

    <?php if (empty($customers)): ?>
        <div class="gn-del-alert">لا يوجد مشتركين داخل GreenNet.</div>
    <?php else: ?>
        <form method="post" action="/admin/user-manager-user-delete/preview">
            <div class="gn-del-form-grid">
                <div class="gn-del-field">
                    <label>Customer</label>
                    <select name="username" required>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                                $username = (string) ($customer['username'] ?? '');
                                $name = (string) ($customer['full_name'] ?? $customer['display_name'] ?? '');
                            ?>
                            <option value="<?= gn_um_del_h($username) ?>" <?= $username === $selectedUsername ? 'selected' : '' ?>>
                                <?= gn_um_del_h($username) ?>
                                <?= $name !== '' ? ' - ' . gn_um_del_h($name) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="gn-btn gn-btn-danger gn-btn-lg" type="submit">
                    Delete Dry Run
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($result !== null): ?>
    <section class="gn-del-box">
        <h3>Dry Run Result</h3>

        <?php if (!empty($result['error'])): ?>
            <div class="gn-del-alert"><?= gn_um_del_h((string) $result['error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($result['execute_error'])): ?>
            <div class="gn-del-danger"><?= gn_um_del_h((string) $result['execute_error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($realResult)): ?>
            <div class="<?= !empty($realResult['ok']) ? 'gn-del-success' : 'gn-del-danger' ?>">
                التنفيذ الحقيقي:
                <strong><?= !empty($realResult['ok']) ? 'نجح' : 'فشل' ?></strong>
                <br>
                <?= gn_um_del_h((string) ($realResult['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <div class="gn-del-grid">
            <article class="gn-del-card <?= !empty($result['user_found']) ? 'is-success' : 'is-danger' ?>">
                <div class="gn-del-label">User Found</div>
                <div class="gn-del-value"><?= !empty($result['user_found']) ? 'YES' : 'NO' ?></div>
            </article>

            <article class="gn-del-card is-warning">
                <div class="gn-del-label">User Profiles</div>
                <div class="gn-del-value"><?= gn_um_del_h((string) ($result['user_profiles_count'] ?? 0)) ?></div>
            </article>

            <article class="gn-del-card is-warning">
                <div class="gn-del-label">Sessions</div>
                <div class="gn-del-value"><?= gn_um_del_h((string) ($result['sessions_count'] ?? 0)) ?></div>
            </article>
        </div>

        <div class="gn-del-grid">
            <article class="gn-del-card is-danger">
                <div class="gn-del-label">Operations</div>
                <div class="gn-del-value"><?= gn_um_del_h((string) ($result['operations_count'] ?? 0)) ?></div>
            </article>

            <article class="gn-del-card is-success">
                <div class="gn-del-label">Router User ID</div>
                <div class="gn-del-value"><?= gn_um_del_h((string) ($result['router_user_id'] ?? '-')) ?></div>
            </article>

            <article class="gn-del-card is-warning">
                <div class="gn-del-label">Will Delete Local?</div>
                <div class="gn-del-value">NO</div>
            </article>
        </div>

        <div class="gn-del-box">
            <h3>Target</h3>

            <div class="gn-del-row">
                <span>Username</span>
                <strong><?= gn_um_del_h((string) ($result['username'] ?? '-')) ?></strong>
            </div>

            <div class="gn-del-row">
                <span>Main Command</span>
                <strong>/user-manager/user/remove</strong>
            </div>
        </div>

        <?php if (!empty($result['block_reason'])): ?>
            <div class="gn-del-alert"><?= gn_um_del_h((string) $result['block_reason']) ?></div>
        <?php endif; ?>

        <div class="gn-del-box">
            <h3>User Profiles Found</h3>

            <?php if (empty($userProfiles)): ?>
                <div class="gn-del-info">لا يوجد user-profile لهذا المستخدم.</div>
            <?php else: ?>
                <?php foreach ($userProfiles as $profile): ?>
                    <pre class="gn-del-code"><?= gn_um_del_h(gn_um_del_pretty($profile)) ?></pre>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="gn-del-box">
            <h3>Sessions Found</h3>

            <?php if (empty($sessions)): ?>
                <div class="gn-del-info">لا توجد sessions لهذا المستخدم.</div>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <pre class="gn-del-code"><?= gn_um_del_h(gn_um_del_pretty($session)) ?></pre>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="gn-del-box">
            <h3>Planned Operations</h3>

            <?php if (empty($operations)): ?>
                <div class="gn-del-info">لا توجد عمليات مطلوبة.</div>
            <?php else: ?>
                <?php foreach ($operations as $operation): ?>
                    <div class="gn-del-row">
                        <span><?= gn_um_del_h((string) ($operation['type'] ?? '-')) ?></span>
                        <strong><?= gn_um_del_h((string) ($operation['command'] ?? '-')) ?></strong>
                    </div>

                    <pre class="gn-del-code"><?= gn_um_del_h(gn_um_del_pretty($operation['params'] ?? [])) ?></pre>

                    <?php if (!empty($operation['display'])): ?>
                        <pre class="gn-del-code"><?= gn_um_del_h(gn_um_del_pretty($operation['display'])) ?></pre>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canExecute): ?>
            <div class="gn-del-box">
                <h3>Execute Real Delete</h3>

                <div class="gn-del-danger">
                    سيتم حذف المستخدم من MikroTik User Manager وتنظيف user-profile والجلسات المرتبطة به.
                    <br>
                    للتنفيذ اكتب:
                    <strong dir="ltr">DELETE</strong>
                </div>

                <form method="post" action="/admin/user-manager-user-delete/execute" onsubmit="return confirm('سيتم حذف مستخدم حقيقي من MikroTik. متابعة؟');">
                    <input type="hidden" name="username" value="<?= gn_um_del_h((string) ($result['username'] ?? '')) ?>">

                    <div class="gn-del-form-grid">
                        <div class="gn-del-field">
                            <label>Confirmation</label>
                            <input type="text" name="confirm_delete" placeholder="DELETE" dir="ltr" autocomplete="off" required>
                        </div>

                        <button class="gn-btn gn-btn-danger gn-btn-lg" type="submit">
                            Execute Real Delete
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($result['notes']) && is_array($result['notes'])): ?>
            <div class="gn-del-box">
                <h3>Notes</h3>
                <?php foreach ($result['notes'] as $note): ?>
                    <div class="gn-del-info"><?= gn_um_del_h((string) $note) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <details style="margin-top:18px;">
            <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw Result</summary>
            <pre class="gn-del-code"><?= gn_um_del_h(gn_um_del_pretty($result)) ?></pre>
        </details>
    </section>
<?php endif; ?>
