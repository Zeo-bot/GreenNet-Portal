<?php

declare(strict_types=1);

if (!function_exists('gn_bak_h')) {
    function gn_bak_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_bak_size')) {
    function gn_bak_size(mixed $bytes): string
    {
        $bytes = (int) $bytes;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}

if (!function_exists('gn_bak_date')) {
    function gn_bak_date(mixed $timestamp): string
    {
        $timestamp = (int) $timestamp;

        if ($timestamp <= 0) {
            return '-';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

$backups = is_array($backups ?? null) ? $backups : [];
$success = (string) ($success ?? '');
$error = (string) ($error ?? '');
$dbPath = (string) ($dbPath ?? '');
$backupDir = (string) ($backupDir ?? '');
$uploadsDir = (string) ($uploadsDir ?? '');
$zipAvailable = (bool) ($zipAvailable ?? false);

$successMap = [
    'restored' => 'تمت استعادة قاعدة البيانات بنجاح.',
    'full_restored' => 'تمت استعادة Full Backup بنجاح.',
    'cleaned' => 'تم تنظيف بيانات التطوير بعد إنشاء نسخة احتياطية.',
];

$dbBackups = array_values(array_filter($backups, static fn (array $item): bool => ($item['extension'] ?? '') !== 'zip'));
$zipBackups = array_values(array_filter($backups, static fn (array $item): bool => ($item['extension'] ?? '') === 'zip'));

?>

<style>
    .gn-bak-page {
        display: grid;
        gap: 18px;
    }

    .gn-bak-hero {
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

    .gn-bak-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-bak-hero p {
        margin: 10px 0 0;
        max-width: 950px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-bak-hero-actions,
    .gn-bak-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-bak-alert {
        padding: 14px 16px;
        border-radius: var(--gn-radius-lg);
        font-weight: 900;
        line-height: 1.7;
    }

    .gn-bak-alert.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.22);
    }

    .gn-bak-alert.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.22);
    }

    .gn-bak-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-bak-kpi {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-bak-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-bak-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-bak-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-bak-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-bak-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-bak-kpi-value {
        margin-top: 7px;
        color: var(--gn-text);
        font-size: 24px;
        font-weight: 950;
        line-height: 1.35;
        word-break: break-word;
    }

    .gn-bak-layout {
        display: grid;
        grid-template-columns: minmax(320px, 0.52fr) minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }

    .gn-bak-card,
    .gn-bak-section {
        padding: 20px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-bak-card h2,
    .gn-bak-section h2 {
        margin: 0 0 14px;
        color: var(--gn-text);
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-bak-stack {
        display: grid;
        gap: 14px;
    }

    .gn-bak-form {
        display: grid;
        gap: 12px;
    }

    .gn-bak-field {
        display: grid;
        gap: 7px;
    }

    .gn-bak-field label {
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 950;
    }

    .gn-bak-field input {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        outline: none;
        box-sizing: border-box;
    }

    .gn-bak-note {
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        line-height: 1.75;
        font-weight: 850;
    }

    .gn-bak-danger {
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        line-height: 1.75;
        font-weight: 850;
    }

    .gn-bak-list {
        display: grid;
        gap: 12px;
    }

    .gn-bak-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-bak-name {
        color: var(--gn-text);
        font-weight: 950;
        line-height: 1.5;
        direction: ltr;
        text-align: left;
        unicode-bidi: plaintext;
        word-break: break-all;
    }

    html[dir="rtl"] .gn-bak-name {
        text-align: right;
    }

    .gn-bak-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 7px;
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 850;
    }

    .gn-bak-badge {
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

    .gn-bak-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-bak-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-bak-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-bak-path-list {
        display: grid;
        gap: 10px;
    }

    .gn-bak-path {
        padding: 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-bak-path-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .gn-bak-path-value {
        display: block;
        color: var(--gn-text);
        font-size: 12px;
        font-weight: 850;
        line-height: 1.55;
        direction: ltr;
        text-align: left;
        unicode-bidi: plaintext;
        word-break: break-all;
    }

    html[dir="rtl"] .gn-bak-path-value {
        text-align: right;
    }

    .gn-bak-empty {
        padding: 26px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        text-align: center;
        color: var(--gn-muted);
        font-weight: 900;
    }

    html[data-theme="greennet-dark"] .gn-bak-hero,
    html[data-theme="greennet-dark"] .gn-bak-kpi,
    html[data-theme="greennet-dark"] .gn-bak-card,
    html[data-theme="greennet-dark"] .gn-bak-section,
    html[data-theme="greennet-dark"] .gn-bak-empty {
        background:
            radial-gradient(circle at 0% 0%, rgba(46, 230, 139, 0.07), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2)) !important;
    }

    @media (max-width: 1200px) {
        .gn-bak-layout {
            grid-template-columns: 1fr;
        }

        .gn-bak-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-bak-kpis,
        .gn-bak-item {
            grid-template-columns: 1fr;
        }

        .gn-bak-hero h1 {
            font-size: 26px;
        }
    }
</style>

<div class="gn-bak-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">Backup & Restore</h1>
            <p class="admin-page-description">
                إنشاء نسخ احتياطية، تحميل النسخ السابقة، واستعادة قاعدة البيانات بأمان.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/settings">الإعدادات</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/security">الأمان</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/backup">Refresh</a>
        </div>
    </div>

    <?php if ($success !== '' && isset($successMap[$success])): ?>
        <div class="gn-bak-alert is-success">
            <?= gn_bak_h($successMap[$success]) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="gn-bak-alert is-danger">
            <?= gn_bak_h($error) ?>
        </div>
    <?php endif; ?>

    <section class="gn-bak-hero">
        <h1>مركز النسخ الاحتياطي</h1>
        <p>
            قبل أي مرحلة MikroTik Write أو تنظيف بيانات، يجب أخذ نسخة احتياطية.
            هذه الصفحة لا تتصل بالراوتر ولا تنفذ أوامر MikroTik.
        </p>

        <div class="gn-bak-hero-actions">
            <a class="gn-btn gn-btn-primary" href="/admin/backup/download">تحميل DB Backup الآن</a>

            <?php if ($zipAvailable): ?>
                <a class="gn-btn gn-btn-secondary" href="/admin/backup/full">تحميل Full Backup ZIP</a>
            <?php else: ?>
                <span class="gn-btn gn-btn-secondary" style="opacity: .65; pointer-events: none;">Full ZIP غير متوفر</span>
            <?php endif; ?>
        </div>
    </section>

    <section class="gn-bak-kpis">
        <div class="gn-bak-kpi">
            <div class="gn-bak-kpi-label">Stored Backups</div>
            <div class="gn-bak-kpi-value"><?= gn_bak_h(count($backups)) ?></div>
        </div>

        <div class="gn-bak-kpi is-success">
            <div class="gn-bak-kpi-label">Database Backups</div>
            <div class="gn-bak-kpi-value"><?= gn_bak_h(count($dbBackups)) ?></div>
        </div>

        <div class="gn-bak-kpi is-info">
            <div class="gn-bak-kpi-label">Full ZIP Backups</div>
            <div class="gn-bak-kpi-value"><?= gn_bak_h(count($zipBackups)) ?></div>
        </div>

        <div class="gn-bak-kpi <?= $zipAvailable ? 'is-success' : 'is-warning' ?>">
            <div class="gn-bak-kpi-label">ZipArchive</div>
            <div class="gn-bak-kpi-value"><?= $zipAvailable ? 'Available' : 'Missing' ?></div>
        </div>
    </section>

    <section class="gn-bak-layout">

        <aside class="gn-bak-stack">
            <section class="gn-bak-card">
                <h2>إنشاء نسخة</h2>

                <div class="gn-bak-actions">
                    <a class="gn-btn gn-btn-primary" href="/admin/backup/download">
                        Download DB Backup
                    </a>

                    <?php if ($zipAvailable): ?>
                        <a class="gn-btn gn-btn-secondary" href="/admin/backup/full">
                            Download Full ZIP
                        </a>
                    <?php endif; ?>
                </div>

                <div class="gn-bak-note" style="margin-top: 14px;">
                    DB Backup يحفظ قاعدة البيانات فقط. Full Backup يحاول إضافة مجلد uploads أيضاً داخل ZIP.
                </div>
            </section>

            <section class="gn-bak-card">
                <h2>استعادة قاعدة البيانات</h2>

                <form
                    class="gn-bak-form"
                    method="post"
                    action="/admin/backup/restore"
                    enctype="multipart/form-data"
                    data-gn-form-wrapped="1"
                    data-gn-form-enhanced="1"
                    data-gn-fields-grouped="1"
                >
                    <div class="gn-bak-field">
                        <label>ملف قاعدة البيانات</label>
                        <input type="file" name="backup_file" accept=".sqlite,.db,.bak" required>
                    </div>

                    <div class="gn-bak-field">
                        <label>اكتب RESTORE للتأكيد</label>
                        <input type="text" name="confirm_restore" placeholder="RESTORE" required>
                    </div>

                    <button class="gn-btn gn-btn-danger" type="submit" onclick="return confirm('سيتم استبدال قاعدة البيانات الحالية. هل أنت متأكد؟');">
                        Restore DB
                    </button>
                </form>
            </section>

            <?php if ($zipAvailable): ?>
                <section class="gn-bak-card">
                    <h2>استعادة Full ZIP</h2>

                    <form
                        class="gn-bak-form"
                        method="post"
                        action="/admin/backup/restore-full"
                        enctype="multipart/form-data"
                        data-gn-form-wrapped="1"
                        data-gn-form-enhanced="1"
                        data-gn-fields-grouped="1"
                    >
                        <div class="gn-bak-field">
                            <label>ملف ZIP</label>
                            <input type="file" name="full_backup_file" accept=".zip" required>
                        </div>

                        <div class="gn-bak-field">
                            <label>اكتب RESTORE FULL للتأكيد</label>
                            <input type="text" name="confirm_restore" placeholder="RESTORE FULL" required>
                        </div>

                        <button class="gn-btn gn-btn-danger" type="submit" onclick="return confirm('سيتم استعادة قاعدة البيانات والملفات من ZIP. هل أنت متأكد؟');">
                            Restore Full
                        </button>
                    </form>
                </section>
            <?php endif; ?>

            <section class="gn-bak-card">
                <h2>تنظيف بيانات التطوير</h2>

                <form
                    class="gn-bak-form"
                    method="post"
                    action="/admin/cleanup/development"
                    data-gn-form-wrapped="1"
                    data-gn-form-enhanced="1"
                    data-gn-fields-grouped="1"
                >
                    <div class="gn-bak-field">
                        <label>اكتب CLEAN للتأكيد</label>
                        <input type="text" name="confirm_cleanup" placeholder="CLEAN" required>
                    </div>

                    <button class="gn-btn gn-btn-danger" type="submit" onclick="return confirm('سيتم تنظيف سجلات التطوير بعد أخذ نسخة احتياطية. متابعة؟');">
                        Clean Development Data
                    </button>
                </form>

                <div class="gn-bak-danger" style="margin-top: 14px;">
                    التنظيف الحالي يحذف سجلات التطوير والتنبيهات والطلبات التجريبية فقط، ولا يحذف المشتركين أو الباقات أو المدفوعات.
                </div>
            </section>
        </aside>

        <main class="gn-bak-stack">
            <section class="gn-bak-section">
                <h2>المسارات الحالية</h2>

                <div class="gn-bak-path-list">
                    <div class="gn-bak-path">
                        <span class="gn-bak-path-label">Database</span>
                        <span class="gn-bak-path-value"><?= gn_bak_h($dbPath) ?></span>
                    </div>

                    <div class="gn-bak-path">
                        <span class="gn-bak-path-label">Backup Directory</span>
                        <span class="gn-bak-path-value"><?= gn_bak_h($backupDir) ?></span>
                    </div>

                    <div class="gn-bak-path">
                        <span class="gn-bak-path-label">Uploads Directory</span>
                        <span class="gn-bak-path-value"><?= gn_bak_h($uploadsDir) ?></span>
                    </div>
                </div>
            </section>

            <section class="gn-bak-section">
                <h2>النسخ المخزنة</h2>

                <?php if (count($backups) === 0): ?>
                    <div class="gn-bak-empty">
                        لا توجد نسخ مخزنة بعد.
                    </div>
                <?php else: ?>
                    <div class="gn-bak-list">
                        <?php foreach ($backups as $backup): ?>
                            <?php
                                $name = (string) ($backup['name'] ?? '');
                                $type = (string) ($backup['type'] ?? '');
                                $size = (int) ($backup['size'] ?? 0);
                                $mtime = (int) ($backup['mtime'] ?? 0);
                                $extension = (string) ($backup['extension'] ?? '');
                            ?>

                            <article class="gn-bak-item">
                                <div>
                                    <div class="gn-bak-name"><?= gn_bak_h($name) ?></div>

                                    <div class="gn-bak-meta">
                                        <span class="gn-bak-badge <?= $extension === 'zip' ? 'is-info' : 'is-success' ?>">
                                            <?= gn_bak_h($type) ?>
                                        </span>

                                        <span><?= gn_bak_h(gn_bak_size($size)) ?></span>
                                        <span dir="ltr"><?= gn_bak_h(gn_bak_date($mtime)) ?></span>
                                    </div>
                                </div>

                                <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/backup/file?file=<?= rawurlencode($name) ?>">
                                    Download
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>

    </section>

</div>