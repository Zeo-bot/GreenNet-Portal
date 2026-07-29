<?php

declare(strict_types=1);

if (!function_exists('gn_disc_h')) {
    function gn_disc_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_disc_pretty')) {
    function gn_disc_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (!function_exists('gn_disc_bool')) {
    function gn_disc_bool(mixed $value): bool
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

$safeMode = gn_disc_bool($preflight['greennet_safe_mode'] ?? $preflight['safe_mode'] ?? true);
$writeEnabled = gn_disc_bool($preflight['mikrotik_write_enabled'] ?? $preflight['write_enabled'] ?? false);
$freshBackup = gn_disc_bool($preflight['fresh_backup'] ?? $preflight['has_fresh_backup'] ?? false);

$selectedUsername = (string) ($result['username'] ?? $requested_username ?? '');
$router = is_array($result['router'] ?? null) ? $result['router'] : [];
$hotspotRows = is_array($router['hotspot_active']['rows'] ?? null) ? $router['hotspot_active']['rows'] : [];
$pppRows = is_array($router['ppp_active']['rows'] ?? null) ? $router['ppp_active']['rows'] : [];
$umSessions = is_array($router['user_manager_sessions']['rows'] ?? null) ? $router['user_manager_sessions']['rows'] : [];
$operations = is_array($result['operations'] ?? null) ? $result['operations'] : [];
$realResult = is_array($result['real_result'] ?? null) ? $result['real_result'] : [];
$canExecute = $result !== null && !empty($result['can_execute_later']) && empty($result['executed']) && empty($result['execute_error']);
?>

<style>
    .gn-disc-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-disc-card,
    .gn-disc-box {
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        border-radius: var(--gn-radius-xl);
    }

    .gn-disc-card {
        position: relative;
        padding: 16px;
        overflow: hidden;
    }

    .gn-disc-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-disc-card.is-success::before { background: var(--gn-success); }
    .gn-disc-card.is-warning::before { background: var(--gn-warning); }
    .gn-disc-card.is-danger::before { background: var(--gn-danger); }

    .gn-disc-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-disc-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-disc-box {
        margin-top: 18px;
        padding: 16px;
    }

    .gn-disc-box h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-disc-box p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-disc-form-grid {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: end;
        margin-top: 14px;
    }

    .gn-disc-field label {
        display: block;
        color: var(--gn-text);
        font-weight: 950;
        margin-bottom: 8px;
    }

    .gn-disc-field select,
    .gn-disc-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        box-sizing: border-box;
    }

    .gn-disc-row {
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

    .gn-disc-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
        text-align: left;
    }

    .gn-disc-alert,
    .gn-disc-success,
    .gn-disc-danger,
    .gn-disc-info {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-disc-alert {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    .gn-disc-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
    }

    .gn-disc-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.25);
    }

    .gn-disc-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
    }

    .gn-disc-code {
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
        .gn-disc-grid,
        .gn-disc-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Disconnect Active Sessions</h1>
        <p class="admin-page-description">
            S10.12 — فصل جلسات Hotspot / PPP النشطة للمشترك.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers">Customers</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/package-assign">Assign / Replace</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/user-manager-password">UM Password</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="<?= $messageType === 'success' ? 'gn-disc-success' : 'gn-disc-alert' ?>">
        <?= gn_disc_h($message) ?>
    </div>
<?php endif; ?>

<section class="gn-disc-grid">
    <article class="gn-disc-card <?= $safeMode ? 'is-warning' : 'is-success' ?>">
        <div class="gn-disc-label">Safe Mode</div>
        <div class="gn-disc-value"><?= $safeMode ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-disc-card <?= $writeEnabled ? 'is-success' : 'is-warning' ?>">
        <div class="gn-disc-label">Write Enabled</div>
        <div class="gn-disc-value"><?= $writeEnabled ? 'ON' : 'OFF' ?></div>
    </article>

    <article class="gn-disc-card <?= $freshBackup ? 'is-success' : 'is-warning' ?>">
        <div class="gn-disc-label">Fresh Backup</div>
        <div class="gn-disc-value"><?= $freshBackup ? 'ON' : 'OFF' ?></div>
    </article>
</section>

<div class="gn-disc-info">
    هذه الصفحة تفصل المستخدم من Hotspot Active و PPP Active فقط.  
    User Manager Sessions تظهر للقراءة فقط ولا يتم حذفها هنا.
</div>

<section class="gn-disc-box">
    <h3>فصل الاتصال</h3>
    <p>اختر المستخدم، وسيتم البحث عن أي جلسة نشطة له على MikroTik.</p>

    <?php if (empty($customers)): ?>
        <div class="gn-disc-alert">لا يوجد مشتركين داخل GreenNet.</div>
    <?php else: ?>
        <form method="post" action="/admin/user-disconnect/preview">
            <div class="gn-disc-form-grid">
                <div class="gn-disc-field">
                    <label>Customer</label>
                    <select name="username" required>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                                $username = (string) ($customer['username'] ?? '');
                                $name = (string) ($customer['full_name'] ?? $customer['display_name'] ?? '');
                            ?>
                            <option value="<?= gn_disc_h($username) ?>" <?= $username === $selectedUsername ? 'selected' : '' ?>>
                                <?= gn_disc_h($username) ?>
                                <?= $name !== '' ? ' - ' . gn_disc_h($name) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                    مراجعة الجلسات
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($result !== null): ?>
    <section class="gn-disc-box">
        <h3>الجلسات المستهدفة</h3>

        <?php if (!empty($result['error'])): ?>
            <div class="gn-disc-alert"><?= gn_disc_h((string) $result['error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($result['execute_error'])): ?>
            <div class="gn-disc-danger"><?= gn_disc_h((string) $result['execute_error']) ?></div>
        <?php endif; ?>

        <?php if (!empty($realResult)): ?>
            <div class="<?= !empty($realResult['ok']) ? 'gn-disc-success' : 'gn-disc-danger' ?>">
                التنفيذ الحقيقي:
                <strong><?= !empty($realResult['ok']) ? 'نجح' : 'فشل' ?></strong>
                <br>
                <?= gn_disc_h((string) ($realResult['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <div class="gn-disc-grid">
            <article class="gn-disc-card is-warning">
                <div class="gn-disc-label">Hotspot Active</div>
                <div class="gn-disc-value"><?= gn_disc_h((string) ($result['hotspot_active_count'] ?? 0)) ?></div>
            </article>

            <article class="gn-disc-card is-warning">
                <div class="gn-disc-label">PPP Active</div>
                <div class="gn-disc-value"><?= gn_disc_h((string) ($result['ppp_active_count'] ?? 0)) ?></div>
            </article>

            <article class="gn-disc-card is-success">
                <div class="gn-disc-label">Operations</div>
                <div class="gn-disc-value"><?= gn_disc_h((string) ($result['operations_count'] ?? 0)) ?></div>
            </article>
        </div>

        <div class="gn-disc-grid">
            <article class="gn-disc-card is-info">
                <div class="gn-disc-label">UM Sessions Read Only</div>
                <div class="gn-disc-value"><?= gn_disc_h((string) ($result['user_manager_sessions_count'] ?? 0)) ?></div>
            </article>

            <article class="gn-disc-card is-success">
                <div class="gn-disc-label">Username</div>
                <div class="gn-disc-value"><?= gn_disc_h((string) ($result['username'] ?? '-')) ?></div>
            </article>

            <article class="gn-disc-card is-warning">
                <div class="gn-disc-label">Will Delete UM History?</div>
                <div class="gn-disc-value">NO</div>
            </article>
        </div>

        <?php if (!empty($result['block_reason'])): ?>
            <div class="gn-disc-alert"><?= gn_disc_h((string) $result['block_reason']) ?></div>
        <?php endif; ?>

        <div class="gn-disc-box">
            <h3>Hotspot Active Rows</h3>
            <?php if (empty($hotspotRows)): ?>
                <div class="gn-disc-info">لا توجد Hotspot Active sessions.</div>
            <?php else: ?>
                <?php foreach ($hotspotRows as $row): ?>
                    <pre class="gn-disc-code"><?= gn_disc_h(gn_disc_pretty($row)) ?></pre>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="gn-disc-box">
            <h3>PPP Active Rows</h3>
            <?php if (empty($pppRows)): ?>
                <div class="gn-disc-info">لا توجد PPP Active sessions.</div>
            <?php else: ?>
                <?php foreach ($pppRows as $row): ?>
                    <pre class="gn-disc-code"><?= gn_disc_h(gn_disc_pretty($row)) ?></pre>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="gn-disc-box">
            <h3>User Manager Sessions — Read Only</h3>
            <?php if (empty($umSessions)): ?>
                <div class="gn-disc-info">لا توجد User Manager sessions ظاهرة لهذا المستخدم.</div>
            <?php else: ?>
                <?php foreach ($umSessions as $row): ?>
                    <pre class="gn-disc-code"><?= gn_disc_h(gn_disc_pretty($row)) ?></pre>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="gn-disc-box">
            <h3>Planned Operations</h3>

            <?php if (empty($operations)): ?>
                <div class="gn-disc-info">لا توجد عمليات مطلوبة.</div>
            <?php else: ?>
                <?php foreach ($operations as $operation): ?>
                    <div class="gn-disc-row">
                        <span><?= gn_disc_h((string) ($operation['type'] ?? '-')) ?></span>
                        <strong><?= gn_disc_h((string) ($operation['command'] ?? '-')) ?></strong>
                    </div>

                    <pre class="gn-disc-code"><?= gn_disc_h(gn_disc_pretty($operation['params'] ?? [])) ?></pre>

                    <?php if (!empty($operation['display'])): ?>
                        <pre class="gn-disc-code"><?= gn_disc_h(gn_disc_pretty($operation['display'])) ?></pre>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canExecute): ?>
            <div class="gn-disc-box">
                <h3>Execute Real Disconnect</h3>

                <div class="gn-disc-danger">
                    سيتم فصل الجلسات النشطة لهذا المستخدم من MikroTik.
                    <br>
                    للتنفيذ اكتب:
                    <strong dir="ltr">DISCONNECT</strong>
                </div>

                <form method="post" action="/admin/user-disconnect/execute" onsubmit="return confirm('سيتم فصل جلسات المستخدم النشطة. متابعة؟');">
                    <input type="hidden" name="username" value="<?= gn_disc_h((string) ($result['username'] ?? '')) ?>">

                    <div class="gn-disc-form-grid">
                        <div class="gn-disc-field">
                            <label>Confirmation</label>
                            <input type="text" name="confirm_disconnect" placeholder="DISCONNECT" dir="ltr" autocomplete="off" required>
                        </div>

                        <button class="gn-btn gn-btn-danger gn-btn-lg" type="submit">
                            Execute Real Disconnect
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($result['notes']) && is_array($result['notes'])): ?>
            <div class="gn-disc-box">
                <h3>Notes</h3>
                <?php foreach ($result['notes'] as $note): ?>
                    <div class="gn-disc-info"><?= gn_disc_h((string) $note) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <details style="margin-top:18px;">
            <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw Result</summary>
            <pre class="gn-disc-code"><?= gn_disc_h(gn_disc_pretty($result)) ?></pre>
        </details>
    </section>
<?php endif; ?>
