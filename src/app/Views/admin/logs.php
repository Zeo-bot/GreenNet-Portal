<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_logs_h')) {
    function gn_logs_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_logs_pdo')) {
    function gn_logs_pdo(): PDO
    {
        Database::migrate();
        return Database::connection();
    }
}

if (!function_exists('gn_logs_table_exists')) {
    function gn_logs_table_exists(string $table): bool
    {
        try {
            $stmt = gn_logs_pdo()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:name LIMIT 1");
            $stmt->execute(['name' => $table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('gn_logs_ensure')) {
    function gn_logs_ensure(): void
    {
        gn_logs_pdo()->exec("
            CREATE TABLE IF NOT EXISTS app_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT DEFAULT 'info',
                message TEXT DEFAULT '',
                context TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('gn_logs_short')) {
    function gn_logs_short(string $text, int $limit = 180): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit) . '...';
    }
}

if (!function_exists('gn_logs_pretty')) {
    function gn_logs_pretty(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $value;
    }
}

if (!function_exists('gn_logs_level_class')) {
    function gn_logs_level_class(string $level): string
    {
        return match (strtolower($level)) {
            'error', 'danger', 'critical', 'failed' => 'is-danger',
            'warning', 'warn' => 'is-warning',
            'success', 'ok' => 'is-success',
            'debug' => 'is-debug',
            default => 'is-info',
        };
    }
}

if (!function_exists('gn_logs_level_label')) {
    function gn_logs_level_label(string $level): string
    {
        $level = strtolower(trim($level));

        return $level !== '' ? $level : 'info';
    }
}

if (!function_exists('gn_logs_format_time')) {
    function gn_logs_format_time(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

gn_logs_ensure();

$query = trim((string) ($_GET['q'] ?? ''));
$levelFilter = trim((string) ($_GET['level'] ?? 'all'));

$where = ['1=1'];
$params = [];

if ($query !== '') {
    $where[] = '(level LIKE :q OR message LIKE :q OR context LIKE :q)';
    $params['q'] = '%' . $query . '%';
}

if ($levelFilter !== 'all' && $levelFilter !== '') {
    $where[] = 'level = :level';
    $params['level'] = $levelFilter;
}

$sql = "
    SELECT *
    FROM app_logs
    WHERE " . implode(' AND ', $where) . "
    ORDER BY datetime(created_at) DESC, id DESC
    LIMIT 250
";

try {
    $stmt = gn_logs_pdo()->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable) {
    $logs = [];
}

try {
    $total = (int) gn_logs_pdo()->query("SELECT COUNT(*) FROM app_logs")->fetchColumn();
    $errors = (int) gn_logs_pdo()->query("SELECT COUNT(*) FROM app_logs WHERE level IN ('error','danger','critical','failed')")->fetchColumn();
    $warnings = (int) gn_logs_pdo()->query("SELECT COUNT(*) FROM app_logs WHERE level IN ('warning','warn')")->fetchColumn();
    $info = (int) gn_logs_pdo()->query("SELECT COUNT(*) FROM app_logs WHERE level IN ('info','success','ok','debug') OR level IS NULL OR level = ''")->fetchColumn();

    $levels = gn_logs_pdo()->query("
        SELECT DISTINCT level
        FROM app_logs
        WHERE level IS NOT NULL AND level != ''
        ORDER BY level ASC
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable) {
    $total = count($logs);
    $errors = 0;
    $warnings = 0;
    $info = 0;
    $levels = [];
}

?>

<style>
    .gn-logs-page {
        display: grid;
        gap: 18px;
    }

    .gn-logs-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .gn-logs-kpi {
        position: relative;
        overflow: hidden;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2));
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-logs-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--gn-primary), var(--gn-primary-3));
    }

    .gn-logs-kpi.is-danger::before {
        background: var(--gn-danger);
    }

    .gn-logs-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-logs-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-logs-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-logs-kpi-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .gn-logs-kpi-note {
        margin-top: 4px;
        color: var(--gn-muted);
        font-size: 12px;
    }

    .gn-logs-filters {
        display: grid;
        grid-template-columns: minmax(260px, 1.5fr) minmax(160px, 0.6fr) auto auto;
        gap: 14px;
        align-items: end;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-logs-list {
        display: grid;
        gap: 12px;
    }

    .gn-log-card {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 220px;
        gap: 18px;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        overflow: hidden;
    }

    .gn-log-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-log-card.is-danger::before {
        background: var(--gn-danger);
    }

    .gn-log-card.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-log-card.is-success::before {
        background: var(--gn-success);
    }

    .gn-log-card.is-debug::before {
        background: var(--gn-muted);
    }

    .gn-log-main {
        min-width: 0;
    }

    .gn-log-topline {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .gn-log-title {
        margin: 0;
        color: var(--gn-text);
        font-size: 18px;
        font-weight: 950;
        letter-spacing: -0.025em;
        line-height: 1.4;
    }

    .gn-log-message {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        max-width: 920px;
    }

    .gn-log-badge {
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

    .gn-log-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-log-badge.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-log-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-log-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-log-badge.is-debug {
        background: var(--gn-surface-3);
        color: var(--gn-muted);
    }

    .gn-log-side {
        display: grid;
        align-content: start;
        gap: 10px;
        min-width: 0;
    }

    .gn-log-side-item {
        padding: 10px 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-log-side-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .gn-log-side-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        line-height: 1.55;
        word-break: break-word;
    }

    .gn-log-time {
        direction: ltr !important;
        text-align: left !important;
        unicode-bidi: plaintext !important;
        font-family: Consolas, "Cascadia Code", "Courier New", monospace;
        white-space: nowrap;
    }

    .gn-log-details {
        margin-top: 12px;
    }

    .gn-log-details summary {
        list-style: none;
        cursor: pointer;
        width: fit-content;
        min-height: 30px;
        padding: 5px 11px;
        border-radius: 999px;
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        color: var(--gn-text);
        font-size: 12px;
        font-weight: 950;
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-log-details summary::-webkit-details-marker {
        display: none;
    }

    .gn-log-details[open] summary {
        background: linear-gradient(135deg, var(--gn-primary), var(--gn-primary-2));
        color: #ffffff;
        border-color: transparent;
    }

    .gn-log-details-body {
        margin-top: 10px;
        padding: 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-code-bg);
        color: var(--gn-code-text);
        border: 1px solid rgba(148, 163, 184, 0.22);
        direction: ltr;
        text-align: left;
        unicode-bidi: plaintext;
        font-family: Consolas, "Cascadia Code", monospace;
        font-size: 12px;
        line-height: 1.65;
        white-space: pre-wrap;
        overflow: auto;
        max-height: 260px;
    }

    .gn-logs-empty {
        padding: 28px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        text-align: center;
        color: var(--gn-muted);
        font-weight: 900;
    }

    html[data-theme="greennet-dark"] .gn-log-card,
    html[data-theme="greennet-dark"] .gn-logs-kpi,
    html[data-theme="greennet-dark"] .gn-logs-filters,
    html[data-theme="greennet-dark"] .gn-logs-empty {
        background: linear-gradient(180deg, rgba(16, 32, 25, 0.94), rgba(10, 25, 17, 0.92));
    }

    @media (max-width: 1200px) {
        .gn-logs-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gn-log-card {
            grid-template-columns: 1fr;
        }

        .gn-log-side {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-logs-kpis,
        .gn-logs-filters,
        .gn-log-side {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="gn-logs-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">سجل العمليات</h1>
            <p class="admin-page-description">
                عرض أنيق لأحداث النظام، الأخطاء، عمليات API، ونتائج Dry Run بدون جداول طويلة.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/notifications">الإشعارات</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/audit">Audit Center</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/logs">Refresh</a>
        </div>
    </div>

    <section class="gn-logs-kpis">
        <div class="gn-logs-kpi">
            <div class="gn-logs-kpi-label">Total</div>
            <div class="gn-logs-kpi-value"><?= gn_logs_h($total) ?></div>
            <div class="gn-logs-kpi-note">إجمالي السجلات</div>
        </div>

        <div class="gn-logs-kpi is-danger">
            <div class="gn-logs-kpi-label">Errors</div>
            <div class="gn-logs-kpi-value"><?= gn_logs_h($errors) ?></div>
            <div class="gn-logs-kpi-note">أخطاء تحتاج مراجعة</div>
        </div>

        <div class="gn-logs-kpi is-warning">
            <div class="gn-logs-kpi-label">Warnings</div>
            <div class="gn-logs-kpi-value"><?= gn_logs_h($warnings) ?></div>
            <div class="gn-logs-kpi-note">تحذيرات</div>
        </div>

        <div class="gn-logs-kpi is-info">
            <div class="gn-logs-kpi-label">Info</div>
            <div class="gn-logs-kpi-value"><?= gn_logs_h($info) ?></div>
            <div class="gn-logs-kpi-note">معلومات وتشغيل طبيعي</div>
        </div>
    </section>

    <form class="gn-logs-filters" method="get" action="/admin/logs">
        <div class="form-group">
            <label>بحث</label>
            <input type="search" name="q" value="<?= gn_logs_h($query) ?>" placeholder="ابحث بالرسالة أو السياق..." dir="rtl">
        </div>

        <div class="form-group">
            <label>Level</label>
            <select name="level">
                <option value="all" <?= $levelFilter === 'all' ? 'selected' : '' ?>>الكل</option>
                <?php foreach ($levels as $level): ?>
                    <option value="<?= gn_logs_h((string) $level) ?>" <?= $levelFilter === (string) $level ? 'selected' : '' ?>>
                        <?= gn_logs_h((string) $level) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <button class="gn-btn gn-btn-primary" type="submit">تطبيق</button>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <a class="gn-btn gn-btn-secondary" href="/admin/logs">مسح</a>
        </div>
    </form>

    <?php if (count($logs) === 0): ?>
        <div class="gn-logs-empty">
            لا توجد سجلات مطابقة للفلتر الحالي.
        </div>
    <?php else: ?>
        <section class="gn-logs-list">
            <?php foreach ($logs as $log): ?>
                <?php
                    $id = (int) ($log['id'] ?? 0);
                    $level = trim((string) ($log['level'] ?? 'info'));
                    $message = trim((string) ($log['message'] ?? ''));
                    $context = trim((string) ($log['context'] ?? ''));
                    $createdAt = gn_logs_format_time((string) ($log['created_at'] ?? ''));
                    $levelClass = gn_logs_level_class($level);
                    $levelLabel = gn_logs_level_label($level);
                ?>

                <article class="gn-log-card <?= gn_logs_h($levelClass) ?>">
                    <div class="gn-log-main">
                        <div class="gn-log-topline">
                            <span class="gn-log-badge <?= gn_logs_h($levelClass) ?>">
                                <?= gn_logs_h($levelLabel) ?>
                            </span>

                            <span class="gn-log-badge is-debug" dir="ltr">
                                #<?= gn_logs_h($id) ?>
                            </span>
                        </div>

                        <h2 class="gn-log-title">
                            <?= gn_logs_h($message !== '' ? gn_logs_short($message, 120) : 'Log Event') ?>
                        </h2>

                        <?php if ($message !== '' && mb_strlen($message) > 120): ?>
                            <p class="gn-log-message">
                                <?= gn_logs_h(gn_logs_short($message, 260)) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($context !== ''): ?>
                            <details class="gn-log-details">
                                <summary>عرض التفاصيل</summary>
                                <pre class="gn-log-details-body"><?= gn_logs_h(gn_logs_pretty($context)) ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>

                    <aside class="gn-log-side">
                        <div class="gn-log-side-item">
                            <span class="gn-log-side-label">الوقت</span>
                            <span class="gn-log-side-value gn-log-time"><?= gn_logs_h($createdAt) ?></span>
                        </div>

                        <div class="gn-log-side-item">
                            <span class="gn-log-side-label">Level</span>
                            <span class="gn-log-side-value" dir="ltr"><?= gn_logs_h($levelLabel) ?></span>
                        </div>

                        <div class="gn-log-side-item">
                            <span class="gn-log-side-label">Record</span>
                            <span class="gn-log-side-value" dir="ltr">#<?= gn_logs_h($id) ?></span>
                        </div>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</div>