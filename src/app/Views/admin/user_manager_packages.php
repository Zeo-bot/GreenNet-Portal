<?php

declare(strict_types=1);

if (!function_exists('gn_um_pkg_h')) {
    function gn_um_pkg_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_um_pkg_pretty')) {
    function gn_um_pkg_pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

$result = is_array($result ?? null) ? $result : [];
$message = (string) ($message ?? '');
$messageType = (string) ($message_type ?? 'info');

$summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
$profiles = is_array($result['profiles'] ?? null) ? $result['profiles'] : [];
$limitations = is_array($result['limitations'] ?? null) ? $result['limitations'] : [];
$profileLinks = is_array($result['profile_links'] ?? null) ? $result['profile_links'] : [];
$linkAttempts = is_array($result['link_attempts'] ?? null) ? $result['link_attempts'] : [];
$candidates = is_array($result['package_candidates'] ?? null) ? $result['package_candidates'] : [];

$statusClass = static function (string $status): string {
    return match ($status) {
        'linked' => 'is-success',
        'name_match' => 'is-info',
        'profile_only' => 'is-warning',
        'limitation_only' => 'is-danger',
        default => 'is-info',
    };
};

$statusLabel = static function (string $status): string {
    return match ($status) {
        'linked' => 'Linked',
        'name_match' => 'Name Match',
        'profile_only' => 'Profile Only',
        'limitation_only' => 'Limitation Only',
        default => $status,
    };
};
?>

<style>
    .gn-ump-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-top: 18px;
    }

    .gn-ump-card {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-ump-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-ump-card.is-success::before { background: var(--gn-success); }
    .gn-ump-card.is-warning::before { background: var(--gn-warning); }
    .gn-ump-card.is-danger::before { background: var(--gn-danger); }
    .gn-ump-card.is-info::before { background: var(--gn-info); }

    .gn-ump-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-ump-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        line-height: 1.25;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-ump-section {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-ump-section h3 {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-ump-section p {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        font-weight: 850;
    }

    .gn-ump-packages {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 16px;
    }

    .gn-ump-package {
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-ump-package-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }

    .gn-ump-package-title {
        color: var(--gn-text);
        font-weight: 950;
        font-size: 18px;
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-ump-badge {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 950;
        white-space: nowrap;
    }

    .gn-ump-badge.is-success { background: var(--gn-success-soft); color: var(--gn-success); }
    .gn-ump-badge.is-warning { background: var(--gn-warning-soft); color: var(--gn-warning); }
    .gn-ump-badge.is-danger { background: var(--gn-danger-soft); color: var(--gn-danger); }
    .gn-ump-badge.is-info { background: var(--gn-info-soft); color: var(--gn-info); }

    .gn-ump-rows {
        margin-top: 14px;
        display: grid;
        gap: 8px;
    }

    .gn-ump-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 9px 11px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        color: var(--gn-text);
        font-weight: 850;
    }

    .gn-ump-row strong {
        direction: ltr;
        unicode-bidi: plaintext;
    }

    .gn-ump-alert {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
        border: 1px solid rgba(245, 158, 11, 0.25);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-ump-success {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.25);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-ump-info {
        margin-top: 18px;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        border: 1px solid rgba(37, 99, 235, 0.20);
        line-height: 1.8;
        font-weight: 950;
    }

    .gn-ump-code {
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

    .gn-ump-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
    }

    @media (max-width: 1100px) {
        .gn-ump-grid,
        .gn-ump-packages {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">User Manager Packages</h1>
        <p class="admin-page-description">
            S10.5 — استيراد User Manager Profiles / Limitations إلى GreenNet Packages.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/packages">GreenNet Packages</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/mikrotik-dry-run">MikroTik Dry Run</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/api/diagnostics">API Diagnostics</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="<?= $messageType === 'success' ? 'gn-ump-success' : 'gn-ump-alert' ?>">
        <?= gn_um_pkg_h($message) ?>
    </div>
<?php endif; ?>

<div class="gn-ump-info">
    الاستيراد هنا يكتب داخل GreenNet فقط. لا يتم تعديل MikroTik.
</div>

<?php if (!empty($result['error'])): ?>
    <div class="gn-ump-alert">
        <?= gn_um_pkg_h((string) $result['error']) ?>
    </div>
<?php endif; ?>

<section class="gn-ump-grid">
    <article class="gn-ump-card <?= !empty($profiles['ok']) ? 'is-success' : 'is-danger' ?>">
        <div class="gn-ump-label">Profiles</div>
        <div class="gn-ump-value"><?= gn_um_pkg_h((string) ($summary['profiles_count'] ?? 0)) ?></div>
    </article>

    <article class="gn-ump-card <?= !empty($limitations['ok']) ? 'is-success' : 'is-danger' ?>">
        <div class="gn-ump-label">Limitations</div>
        <div class="gn-ump-value"><?= gn_um_pkg_h((string) ($summary['limitations_count'] ?? 0)) ?></div>
    </article>

    <article class="gn-ump-card <?= ((int) ($summary['links_count'] ?? 0) > 0) ? 'is-success' : 'is-warning' ?>">
        <div class="gn-ump-label">Profile Links</div>
        <div class="gn-ump-value"><?= gn_um_pkg_h((string) ($summary['links_count'] ?? 0)) ?></div>
    </article>

    <article class="gn-ump-card is-info">
        <div class="gn-ump-label">Importable</div>
        <div class="gn-ump-value"><?= gn_um_pkg_h((string) ($summary['importable_count'] ?? 0)) ?></div>
    </article>
</section>

<?php if (!empty($candidates)): ?>
    <section class="gn-ump-section">
        <h3>Import Actions</h3>
        <p>
            يمكنك استيراد كل الباقات القابلة للاستيراد دفعة واحدة، أو استيراد باقة محددة.
        </p>

        <form method="post" action="/admin/user-manager-packages/import" onsubmit="return confirm('سيتم استيراد كل الباقات القابلة للاستيراد إلى GreenNet فقط. متابعة؟');">
            <input type="hidden" name="mode" value="all">
            <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                Import All to GreenNet
            </button>
        </form>
    </section>
<?php endif; ?>

<section class="gn-ump-section">
    <h3>Discovered Package Candidates</h3>
    <p>
        الباقات التالية مكتشفة من MikroTik. الاستيراد سيحفظها في service_packages.
    </p>

    <?php if (empty($candidates)): ?>
        <div class="gn-ump-alert">
            لا يوجد Candidates بعد. تأكد أن User Manager يحتوي Profiles أو Limitations.
        </div>
    <?php else: ?>
        <div class="gn-ump-packages">
            <?php foreach ($candidates as $candidate): ?>
                <?php
                    $matchStatus = (string) ($candidate['match_status'] ?? '');
                    $badgeClass = $statusClass($matchStatus);
                    $canImport = !empty($candidate['can_import_to_greennet']);
                ?>

                <article class="gn-ump-package">
                    <div class="gn-ump-package-head">
                        <div>
                            <div class="gn-ump-package-title">
                                <?= gn_um_pkg_h((string) ($candidate['suggested_greennet_name'] ?? '-')) ?>
                            </div>
                            <div class="gn-ump-label" style="margin-top:6px;">
                                Source Profile:
                                <span dir="ltr"><?= gn_um_pkg_h((string) ($candidate['suggested_source_profile'] ?? '-')) ?></span>
                            </div>
                        </div>

                        <span class="gn-ump-badge <?= gn_um_pkg_h($badgeClass) ?>">
                            <?= gn_um_pkg_h($statusLabel($matchStatus)) ?>
                        </span>
                    </div>

                    <div class="gn-ump-rows">
                        <div class="gn-ump-row">
                            <span>Profile</span>
                            <strong><?= gn_um_pkg_h((string) ($candidate['profile_name'] ?? '-')) ?></strong>
                        </div>

                        <div class="gn-ump-row">
                            <span>Limitation</span>
                            <strong><?= gn_um_pkg_h((string) ($candidate['limitation_name'] ?? '-')) ?></strong>
                        </div>

                        <div class="gn-ump-row">
                            <span>Quota</span>
                            <strong><?= gn_um_pkg_h((string) ($candidate['quota_human'] ?? '-')) ?></strong>
                        </div>

                        <div class="gn-ump-row">
                            <span>Rate Limit</span>
                            <strong><?= gn_um_pkg_h((string) ($candidate['rate_limit'] ?? '-')) ?></strong>
                        </div>

                        <div class="gn-ump-row">
                            <span>Uptime Limit</span>
                            <strong><?= gn_um_pkg_h((string) ($candidate['uptime_limit'] ?? '-')) ?></strong>
                        </div>

                        <div class="gn-ump-row">
                            <span>Validity</span>
                            <strong><?= gn_um_pkg_h((string) ($candidate['profile_validity'] ?? '-')) ?></strong>
                        </div>

                        <div class="gn-ump-row">
                            <span>Can Import</span>
                            <strong><?= $canImport ? 'YES' : 'NO' ?></strong>
                        </div>
                    </div>

                    <div class="gn-ump-actions">
                        <?php if ($canImport): ?>
                            <form method="post" action="/admin/user-manager-packages/import" onsubmit="return confirm('استيراد هذه الباقة إلى GreenNet فقط؟');">
                                <input type="hidden" name="mode" value="one">
                                <input type="hidden" name="profile_id" value="<?= gn_um_pkg_h((string) ($candidate['profile_id'] ?? '')) ?>">
                                <input type="hidden" name="profile_name" value="<?= gn_um_pkg_h((string) ($candidate['profile_name'] ?? '')) ?>">
                                <input type="hidden" name="limitation_id" value="<?= gn_um_pkg_h((string) ($candidate['limitation_id'] ?? '')) ?>">

                                <button class="gn-btn gn-btn-primary gn-btn-sm" type="submit">
                                    Import
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="gn-ump-badge is-warning">Not Importable</span>
                        <?php endif; ?>

                        <details>
                            <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Raw</summary>
                            <pre class="gn-ump-code"><?= gn_um_pkg_h(gn_um_pkg_pretty($candidate)) ?></pre>
                        </details>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="gn-ump-section">
    <h3>Read Status</h3>

    <div class="gn-ump-rows">
        <div class="gn-ump-row">
            <span>Profiles Command</span>
            <strong><?= gn_um_pkg_h((string) ($profiles['command'] ?? '-')) ?></strong>
        </div>

        <div class="gn-ump-row">
            <span>Profiles Status</span>
            <strong><?= gn_um_pkg_h((string) ($profiles['status'] ?? '-')) ?></strong>
        </div>

        <div class="gn-ump-row">
            <span>Limitations Command</span>
            <strong><?= gn_um_pkg_h((string) ($limitations['command'] ?? '-')) ?></strong>
        </div>

        <div class="gn-ump-row">
            <span>Limitations Status</span>
            <strong><?= gn_um_pkg_h((string) ($limitations['status'] ?? '-')) ?></strong>
        </div>

        <div class="gn-ump-row">
            <span>Profile Link Command</span>
            <strong><?= gn_um_pkg_h((string) ($profileLinks['command'] ?? '-')) ?></strong>
        </div>

        <div class="gn-ump-row">
            <span>Profile Link Status</span>
            <strong><?= gn_um_pkg_h((string) ($profileLinks['status'] ?? '-')) ?></strong>
        </div>
    </div>

    <?php if (!empty($profiles['error'])): ?>
        <div class="gn-ump-alert">Profiles Error: <?= gn_um_pkg_h((string) $profiles['error']) ?></div>
    <?php endif; ?>

    <?php if (!empty($limitations['error'])): ?>
        <div class="gn-ump-alert">Limitations Error: <?= gn_um_pkg_h((string) $limitations['error']) ?></div>
    <?php endif; ?>
</section>

<section class="gn-ump-section">
    <h3>Raw Discovery</h3>

    <details style="margin-top:14px;">
        <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Profiles Preview</summary>
        <pre class="gn-ump-code"><?= gn_um_pkg_h(gn_um_pkg_pretty($profiles['rows_preview'] ?? [])) ?></pre>
    </details>

    <details style="margin-top:14px;">
        <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Limitations Preview</summary>
        <pre class="gn-ump-code"><?= gn_um_pkg_h(gn_um_pkg_pretty($limitations['rows_preview'] ?? [])) ?></pre>
    </details>

    <details style="margin-top:14px;">
        <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">Profile Link Attempts</summary>
        <pre class="gn-ump-code"><?= gn_um_pkg_h(gn_um_pkg_pretty($linkAttempts)) ?></pre>
    </details>
</section>