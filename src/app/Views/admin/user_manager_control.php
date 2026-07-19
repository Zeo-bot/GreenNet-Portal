<?php

declare(strict_types=1);

if (!function_exists('gn_um_ctl_h')) {
    function gn_um_ctl_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_um_ctl_pretty')) {
    function gn_um_ctl_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (!function_exists('gn_um_ctl_bytes')) {
    function gn_um_ctl_bytes(mixed $value): string
    {
        $bytes = (float) $value;

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1000 && $index < count($units) - 1) {
            $bytes /= 1000;
            $index++;
        }

        return number_format($bytes, $index === 0 ? 0 : 2) . ' ' . $units[$index];
    }
}

$customers = is_array($customers ?? null) ? $customers : [];
$selectedUsername = (string) ($selected_username ?? '');
$snapshot = is_array($snapshot ?? null) ? $snapshot : null;

$customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : null;
$package = is_array($snapshot['package'] ?? null) ? $snapshot['package'] : null;
$router = is_array($snapshot['router'] ?? null) ? $snapshot['router'] : [];

$user = is_array($router['user'] ?? null) ? $router['user'] : [];
$monitor = is_array($router['monitor'] ?? null) ? $router['monitor'] : [];
$monitorRow = is_array($monitor['row'] ?? null) ? $monitor['row'] : [];

$userProfiles = is_array($router['user_profiles']['rows'] ?? null) ? $router['user_profiles']['rows'] : [];
$hotspotRows = is_array($router['hotspot_active']['rows'] ?? null) ? $router['hotspot_active']['rows'] : [];
$pppRows = is_array($router['ppp_active']['rows'] ?? null) ? $router['ppp_active']['rows'] : [];
$umSessions = is_array($router['user_manager_sessions']['rows'] ?? null) ? $router['user_manager_sessions']['rows'] : [];

$userFound = !empty($user['found']);
$userDisabled = strtolower((string) ($user['disabled'] ?? '')) === 'true'
    || strtolower((string) ($user['row']['disabled'] ?? '')) === 'true'
    || strtolower((string) ($user['row']['disabled'] ?? '')) === 'yes';

$download = (string) ($monitorRow['download-used'] ?? $monitorRow['download'] ?? $monitorRow['total-download'] ?? '0');
$upload = (string) ($monitorRow['upload-used'] ?? $monitorRow['upload'] ?? $monitorRow['total-upload'] ?? '0');
$total = (string) ($monitorRow['transfer-used'] ?? $monitorRow['total-used'] ?? '');
if ($total === '' && is_numeric($download) && is_numeric($upload)) {
    $total = (string) ((int) $download + (int) $upload);
}
?>

<style>
    .gn-ctl-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-ctl-card,
    .gn-ctl-box {
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        border-radius: var(--gn-radius-xl);
    }

    .gn-ctl-card {
        position: relative;
        padding: 16px;
        overflow: hidden;
    }

    .gn-ctl-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-ctl-card.is-success::before { background: var(--gn-success); }
    .gn-ctl-card.is-warning::before { background: var(--gn-warning); }
    .gn-ctl-card.is-danger::before { background: var(--gn-danger); }

    .gn-ctl-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-ctl-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 26px;
        font-weight: 950;
        direction: ltr;
        unicode-bidi: plaintext;
        line-height: 1.25;
    }

    .gn-ctl-box {
        margin-top: 18px;
        padding: 16px;
    }

    .gn-ctl-box h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-ctl-box p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-ctl-form-grid {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: end;
        margin-top: 14px;
    }

    .gn-ctl-field label {
        display: block;
        color: var(--gn-text);
        font-weight: 950;
        margin-bottom: 8px;
    }

    .gn-ctl-field select,
    .gn-ctl-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        box-sizing: border-box;
    }

    .gn-ctl-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
    }

    .gn-ctl-row {
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

    .gn-ctl-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
        text-align: left;
    }

    .gn-ctl-info,
    .gn-ctl-alert,
    .gn-ctl-danger,
    .gn-ctl-success {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-ctl-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
    }

    .gn-ctl-alert {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    .gn-ctl-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.25);
    }

    .gn-ctl-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
    }

    .gn-ctl-code {
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

    @media (max-width: 1100px) {
        .gn-ctl-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .gn-ctl-grid,
        .gn-ctl-form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">User Manager Control Center</h1>
        <p class="admin-page-description">
            S10.13 — مركز قراءة وتحكم سريع لمستخدم MikroTik User Manager.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers">Customers</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/package-assign">Assign / Replace</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/user-disconnect">Disconnect</a>
    </div>
</div>

<section class="gn-ctl-box">
    <h3>Choose Customer</h3>
    <p>اختر مشتركاً لمشاهدة حالته المحلية وحالته على MikroTik.</p>

    <?php if (empty($customers)): ?>
        <div class="gn-ctl-alert">لا يوجد مشتركين داخل GreenNet.</div>
    <?php else: ?>
        <form method="get" action="/admin/user-manager-control">
            <div class="gn-ctl-form-grid">
                <div class="gn-ctl-field">
                    <label>Customer</label>
                    <select name="username" required>
                        <?php foreach ($customers as $row): ?>
                            <?php
                                $username = (string) ($row['username'] ?? '');
                                $name = (string) ($row['full_name'] ?? $row['display_name'] ?? '');
                                $packageId = (string) ($row['package_id'] ?? '0');
                            ?>
                            <option value="<?= gn_um_ctl_h($username) ?>" <?= $username === $selectedUsername ? 'selected' : '' ?>>
                                <?= gn_um_ctl_h($username) ?>
                                <?= $name !== '' ? ' - ' . gn_um_ctl_h($name) : '' ?>
                                | package: #<?= gn_um_ctl_h($packageId) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                    Load Snapshot
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($snapshot === null): ?>
    <div class="gn-ctl-info">
        اختر مشتركاً واضغط Load Snapshot.
    </div>
<?php else: ?>
    <section class="gn-ctl-grid">
        <article class="gn-ctl-card <?= $customer !== null ? 'is-success' : 'is-danger' ?>">
            <div class="gn-ctl-label">Local Customer</div>
            <div class="gn-ctl-value"><?= $customer !== null ? 'YES' : 'NO' ?></div>
        </article>

        <article class="gn-ctl-card <?= $userFound ? 'is-success' : 'is-danger' ?>">
            <div class="gn-ctl-label">UM User Found</div>
            <div class="gn-ctl-value"><?= $userFound ? 'YES' : 'NO' ?></div>
        </article>

        <article class="gn-ctl-card <?= $userDisabled ? 'is-danger' : 'is-success' ?>">
            <div class="gn-ctl-label">Disabled</div>
            <div class="gn-ctl-value"><?= $userDisabled ? 'YES' : 'NO' ?></div>
        </article>

        <article class="gn-ctl-card is-warning">
            <div class="gn-ctl-label">Profiles</div>
            <div class="gn-ctl-value"><?= gn_um_ctl_h((string) count($userProfiles)) ?></div>
        </article>
    </section>

    <section class="gn-ctl-grid">
        <article class="gn-ctl-card is-warning">
            <div class="gn-ctl-label">Hotspot Active</div>
            <div class="gn-ctl-value"><?= gn_um_ctl_h((string) count($hotspotRows)) ?></div>
        </article>

        <article class="gn-ctl-card is-warning">
            <div class="gn-ctl-label">PPP Active</div>
            <div class="gn-ctl-value"><?= gn_um_ctl_h((string) count($pppRows)) ?></div>
        </article>

        <article class="gn-ctl-card is-success">
            <div class="gn-ctl-label">UM Sessions</div>
            <div class="gn-ctl-value"><?= gn_um_ctl_h((string) count($umSessions)) ?></div>
        </article>

        <article class="gn-ctl-card is-success">
            <div class="gn-ctl-label">Snapshot</div>
            <div class="gn-ctl-value"><?= gn_um_ctl_h((string) ($snapshot['created_at'] ?? '-')) ?></div>
        </article>
    </section>

    <section class="gn-ctl-grid">
        <article class="gn-ctl-card is-success">
            <div class="gn-ctl-label">Download</div>
            <div class="gn-ctl-value"><?= gn_um_ctl_h(gn_um_ctl_bytes($download)) ?></div>
        </article>

        <article class="gn-ctl-card is-success">
            <div class="gn-ctl-label">Upload</div>
            <div class="gn-ctl-value"><?= gn_um_ctl_h(gn_um_ctl_bytes($upload)) ?></div>
        </article>

        <article class="gn-ctl-card is-success">
            <div class="gn-ctl-label">Total</div>
            <div class="gn-ctl-value"><?= gn_um_ctl_h(gn_um_ctl_bytes($total)) ?></div>
        </article>

        <article class="gn-ctl-card is-warning">
            <div class="gn-ctl-label">Package</div>
            <div class="gn-ctl-value">
                <?= $package !== null ? gn_um_ctl_h((string) ($package['name'] ?? '-')) : '-' ?>
            </div>
        </article>
    </section>

    <section class="gn-ctl-box">
        <h3>Quick Actions</h3>
        <p>روابط سريعة للصفحات التنفيذية. كل صفحة ما زالت تعمل Dry Run قبل التنفيذ.</p>

        <div class="gn-ctl-actions">
            <a class="gn-btn gn-btn-secondary" href="/admin/customers/profile?username=<?= urlencode($selectedUsername) ?>">Customer Profile</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/package-assign">Assign / Replace Package</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/user-manager-user-create">Create UM User</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/user-manager-password">Change UM Password</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/mikrotik-dry-run">Enable / Disable</a>
            <a class="gn-btn gn-btn-primary" href="/admin/user-disconnect">Disconnect User</a>
            <a class="gn-btn gn-btn-danger" href="/admin/user-manager-user-delete">Delete UM User</a>
        </div>
    </section>

    <section class="gn-ctl-box">
        <h3>Local GreenNet Customer</h3>

        <?php if ($customer === null): ?>
            <div class="gn-ctl-alert">المشترك غير موجود محلياً.</div>
        <?php else: ?>
            <div class="gn-ctl-row">
                <span>Username</span>
                <strong><?= gn_um_ctl_h((string) ($customer['username'] ?? '-')) ?></strong>
            </div>
            <div class="gn-ctl-row">
                <span>Full Name</span>
                <strong><?= gn_um_ctl_h((string) ($customer['full_name'] ?? '-')) ?></strong>
            </div>
            <div class="gn-ctl-row">
                <span>Phone</span>
                <strong><?= gn_um_ctl_h((string) ($customer['phone'] ?? '-')) ?></strong>
            </div>
            <div class="gn-ctl-row">
                <span>Payment Status</span>
                <strong><?= gn_um_ctl_h((string) ($customer['payment_status'] ?? '-')) ?></strong>
            </div>
            <div class="gn-ctl-row">
                <span>Package ID</span>
                <strong><?= gn_um_ctl_h((string) ($customer['package_id'] ?? '0')) ?></strong>
            </div>
        <?php endif; ?>
    </section>

    <section class="gn-ctl-box">
        <h3>Local Package</h3>

        <?php if ($package === null): ?>
            <div class="gn-ctl-alert">لا توجد باقة محلية مرتبطة أو لم يتم العثور عليها.</div>
        <?php else: ?>
            <div class="gn-ctl-row">
                <span>Name</span>
                <strong><?= gn_um_ctl_h((string) ($package['name'] ?? '-')) ?></strong>
            </div>
            <div class="gn-ctl-row">
                <span>Source Profile</span>
                <strong><?= gn_um_ctl_h((string) ($package['source_profile'] ?? '-')) ?></strong>
            </div>
            <div class="gn-ctl-row">
                <span>Rate Limit</span>
                <strong><?= gn_um_ctl_h((string) ($package['rate_limit'] ?? '-')) ?></strong>
            </div>
            <div class="gn-ctl-row">
                <span>Quota</span>
                <strong><?= gn_um_ctl_h((string) ($package['quota_gb'] ?? '0')) ?> GB</strong>
            </div>
            <div class="gn-ctl-row">
                <span>Duration</span>
                <strong><?= gn_um_ctl_h((string) ($package['duration_days'] ?? '0')) ?> days</strong>
            </div>
        <?php endif; ?>
    </section>

    <section class="gn-ctl-box">
        <h3>MikroTik User</h3>

        <?php if (!$userFound): ?>
            <div class="gn-ctl-danger">
                المستخدم غير موجود داخل MikroTik User Manager.
                <?= !empty($user['error']) ? '<br>' . gn_um_ctl_h((string) $user['error']) : '' ?>
            </div>
        <?php else: ?>
            <pre class="gn-ctl-code"><?= gn_um_ctl_h(gn_um_ctl_pretty($user['row'] ?? [])) ?></pre>
        <?php endif; ?>
    </section>

    <section class="gn-ctl-box">
        <h3>User Manager Monitor</h3>

        <?php if (empty($monitor['found'])): ?>
            <div class="gn-ctl-alert">
                لا توجد بيانات Monitor.
                <?= !empty($monitor['error']) ? '<br>' . gn_um_ctl_h((string) $monitor['error']) : '' ?>
            </div>
        <?php else: ?>
            <pre class="gn-ctl-code"><?= gn_um_ctl_h(gn_um_ctl_pretty($monitorRow)) ?></pre>
        <?php endif; ?>
    </section>

    <section class="gn-ctl-box">
        <h3>User Profiles</h3>

        <?php if (empty($userProfiles)): ?>
            <div class="gn-ctl-info">لا يوجد User Profiles لهذا المستخدم.</div>
        <?php else: ?>
            <?php foreach ($userProfiles as $row): ?>
                <pre class="gn-ctl-code"><?= gn_um_ctl_h(gn_um_ctl_pretty($row)) ?></pre>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="gn-ctl-box">
        <h3>Hotspot Active</h3>

        <?php if (empty($hotspotRows)): ?>
            <div class="gn-ctl-info">لا توجد Hotspot Active session.</div>
        <?php else: ?>
            <?php foreach ($hotspotRows as $row): ?>
                <pre class="gn-ctl-code"><?= gn_um_ctl_h(gn_um_ctl_pretty($row)) ?></pre>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="gn-ctl-box">
        <h3>PPP Active</h3>

        <?php if (empty($pppRows)): ?>
            <div class="gn-ctl-info">لا توجد PPP Active session.</div>
        <?php else: ?>
            <?php foreach ($pppRows as $row): ?>
                <pre class="gn-ctl-code"><?= gn_um_ctl_h(gn_um_ctl_pretty($row)) ?></pre>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="gn-ctl-box">
        <h3>User Manager Sessions</h3>

        <?php if (empty($umSessions)): ?>
            <div class="gn-ctl-info">لا توجد User Manager sessions ظاهرة لهذا المستخدم.</div>
        <?php else: ?>
            <?php foreach ($umSessions as $row): ?>
                <pre class="gn-ctl-code"><?= gn_um_ctl_h(gn_um_ctl_pretty($row)) ?></pre>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <details style="margin-top:18px;">
        <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw Snapshot</summary>
        <pre class="gn-ctl-code"><?= gn_um_ctl_h(gn_um_ctl_pretty($snapshot)) ?></pre>
    </details>
<?php endif; ?>