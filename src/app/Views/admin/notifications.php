<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_notif_h')) {
    function gn_notif_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_notif_pdo')) {
    function gn_notif_pdo(): PDO
    {
        Database::migrate();
        return Database::connection();
    }
}

if (!function_exists('gn_notif_table_exists')) {
    function gn_notif_table_exists(string $table): bool
    {
        try {
            $stmt = gn_notif_pdo()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:name LIMIT 1");
            $stmt->execute(['name' => $table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('gn_notif_ensure_schema')) {
    function gn_notif_ensure_schema(): void
    {
        gn_notif_pdo()->exec("
            CREATE TABLE IF NOT EXISTS admin_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_key TEXT DEFAULT '',
                category TEXT DEFAULT 'system',
                severity TEXT DEFAULT 'info',
                title TEXT DEFAULT '',
                message TEXT DEFAULT '',
                link_url TEXT DEFAULT '',
                related_username TEXT DEFAULT '',
                source_table TEXT DEFAULT '',
                source_id INTEGER DEFAULT NULL,
                is_read INTEGER DEFAULT 0,
                is_archived INTEGER DEFAULT 0,
                read_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('gn_notif_short')) {
    function gn_notif_short(string $text, int $limit = 160): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit) . '...';
    }
}

if (!function_exists('gn_notif_severity_class')) {
    function gn_notif_severity_class(string $severity): string
    {
        return match (strtolower($severity)) {
            'danger', 'error', 'critical' => 'is-danger',
            'warning', 'warn' => 'is-warning',
            'success', 'ok' => 'is-success',
            default => 'is-info',
        };
    }
}

if (!function_exists('gn_notif_severity_label')) {
    function gn_notif_severity_label(string $severity): string
    {
        return match (strtolower($severity)) {
            'danger', 'error', 'critical' => 'خطر',
            'warning', 'warn' => 'تحذير',
            'success', 'ok' => 'نجاح',
            default => 'معلومة',
        };
    }
}

if (!function_exists('gn_notif_category_label')) {
    function gn_notif_category_label(string $category): string
    {
        return match (strtolower($category)) {
            'router', 'mikrotik' => 'Router / MikroTik',
            'api' => 'API',
            'payment', 'payments' => 'الدفعات',
            'renewal', 'renewals' => 'التجديد',
            'customer', 'customers' => 'المشتركين',
            'security' => 'الأمان',
            default => $category !== '' ? $category : 'system',
        };
    }
}

if (!function_exists('gn_notif_format_time')) {
    function gn_notif_format_time(string $value): string
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

gn_notif_ensure_schema();

$query = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? 'all'));
$category = trim((string) ($_GET['category'] ?? 'all'));

$where = ['COALESCE(is_archived, 0) = 0'];
$params = [];

if ($query !== '') {
    $where[] = '(title LIKE :q OR message LIKE :q OR related_username LIKE :q OR category LIKE :q OR source_table LIKE :q)';
    $params['q'] = '%' . $query . '%';
}

if ($status === 'unread') {
    $where[] = 'COALESCE(is_read, 0) = 0';
} elseif ($status === 'read') {
    $where[] = 'COALESCE(is_read, 0) = 1';
}

if ($category !== 'all' && $category !== '') {
    $where[] = 'category = :category';
    $params['category'] = $category;
}

$sql = "
    SELECT *
    FROM admin_notifications
    WHERE " . implode(' AND ', $where) . "
    ORDER BY COALESCE(is_read, 0) ASC, datetime(created_at) DESC, id DESC
    LIMIT 200
";

try {
    $stmt = gn_notif_pdo()->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $notifications = [];
}

try {
    $total = (int) gn_notif_pdo()->query("SELECT COUNT(*) FROM admin_notifications WHERE COALESCE(is_archived, 0) = 0")->fetchColumn();
    $unread = (int) gn_notif_pdo()->query("SELECT COUNT(*) FROM admin_notifications WHERE COALESCE(is_archived, 0) = 0 AND COALESCE(is_read, 0) = 0")->fetchColumn();
    $danger = (int) gn_notif_pdo()->query("SELECT COUNT(*) FROM admin_notifications WHERE COALESCE(is_archived, 0) = 0 AND severity IN ('danger','error','critical')")->fetchColumn();
    $router = (int) gn_notif_pdo()->query("SELECT COUNT(*) FROM admin_notifications WHERE COALESCE(is_archived, 0) = 0 AND (category IN ('router','mikrotik') OR source_table LIKE '%router%' OR source_table LIKE '%mikrotik%')")->fetchColumn();

    $categories = gn_notif_pdo()->query("
        SELECT DISTINCT category
        FROM admin_notifications
        WHERE category IS NOT NULL AND category != ''
        ORDER BY category ASC
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable) {
    $total = count($notifications);
    $unread = count(array_filter($notifications, static fn (array $row): bool => (int) ($row['is_read'] ?? 0) === 0));
    $danger = count(array_filter($notifications, static fn (array $row): bool => in_array((string) ($row['severity'] ?? ''), ['danger', 'error', 'critical'], true)));
    $router = 0;
    $categories = [];
}

?>

<style>
    .gn-notif-page {
        display: grid;
        gap: 18px;
    }

    .gn-notif-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .gn-notif-kpi {
        position: relative;
        overflow: hidden;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2));
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-notif-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--gn-primary), var(--gn-primary-3));
    }

    .gn-notif-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-notif-kpi-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .gn-notif-kpi-note {
        margin-top: 4px;
        color: var(--gn-muted);
        font-size: 12px;
    }

    .gn-notif-filters {
        display: grid;
        grid-template-columns: minmax(260px, 1.5fr) minmax(150px, 0.6fr) minmax(150px, 0.6fr) auto auto;
        gap: 14px;
        align-items: end;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-notif-list {
        display: grid;
        gap: 12px;
    }

    .gn-notif-card {
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

    .gn-notif-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-info);
    }

    .gn-notif-card.is-danger::before {
        background: var(--gn-danger);
    }

    .gn-notif-card.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-notif-card.is-success::before {
        background: var(--gn-success);
    }

    .gn-notif-card.is-unread {
        background:
            radial-gradient(circle at 0% 0%, rgba(20, 184, 110, 0.09), transparent 26%),
            var(--gn-surface);
        border-color: rgba(17, 148, 90, 0.28);
    }

    .gn-notif-main {
        min-width: 0;
    }

    .gn-notif-topline {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .gn-notif-title {
        margin: 0;
        color: var(--gn-text);
        font-size: 18px;
        font-weight: 950;
        letter-spacing: -0.025em;
        line-height: 1.35;
    }

    .gn-notif-message {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        max-width: 920px;
    }

    .gn-notif-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .gn-notif-badge {
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

    .gn-notif-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-notif-badge.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-notif-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-notif-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-notif-badge.is-muted {
        background: var(--gn-surface-3);
        color: var(--gn-muted);
    }

    .gn-notif-side {
        display: grid;
        align-content: start;
        gap: 10px;
        min-width: 0;
    }

    .gn-notif-side-item {
        padding: 10px 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-notif-side-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .gn-notif-side-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        line-height: 1.55;
        word-break: break-word;
    }

    .gn-notif-time {
        direction: ltr !important;
        text-align: left !important;
        unicode-bidi: plaintext !important;
        font-family: Consolas, "Cascadia Code", "Courier New", monospace;
        white-space: nowrap;
    }

    .gn-notif-details {
        margin-top: 12px;
    }

    .gn-notif-details summary {
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

    .gn-notif-details summary::-webkit-details-marker {
        display: none;
    }

    .gn-notif-details[open] summary {
        background: linear-gradient(135deg, var(--gn-primary), var(--gn-primary-2));
        color: #ffffff;
        border-color: transparent;
    }

    .gn-notif-details-body {
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

    .gn-notif-empty {
        padding: 28px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        text-align: center;
        color: var(--gn-muted);
        font-weight: 900;
    }

    html[data-theme="greennet-dark"] .gn-notif-card,
    html[data-theme="greennet-dark"] .gn-notif-kpi,
    html[data-theme="greennet-dark"] .gn-notif-filters,
    html[data-theme="greennet-dark"] .gn-notif-empty {
        background: linear-gradient(180deg, rgba(16, 32, 25, 0.94), rgba(10, 25, 17, 0.92));
    }

    @media (max-width: 1200px) {
        .gn-notif-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gn-notif-card {
            grid-template-columns: 1fr;
        }

        .gn-notif-side {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-notif-kpis,
        .gn-notif-filters,
        .gn-notif-side {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="gn-notif-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">مركز الإشعارات</h1>
            <p class="admin-page-description">
                متابعة أحداث النظام، MikroTik، API، التنبيهات، والرسائل المهمة بدون جداول مكسورة.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/logs">Logs</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/audit">Audit Center</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/notifications">Refresh</a>
        </div>
    </div>

    <section class="gn-notif-kpis">
        <div class="gn-notif-kpi">
            <div class="gn-notif-kpi-label">Total</div>
            <div class="gn-notif-kpi-value"><?= gn_notif_h($total) ?></div>
            <div class="gn-notif-kpi-note">إجمالي الإشعارات</div>
        </div>

        <div class="gn-notif-kpi">
            <div class="gn-notif-kpi-label">Unread</div>
            <div class="gn-notif-kpi-value"><?= gn_notif_h($unread) ?></div>
            <div class="gn-notif-kpi-note">غير مقروءة</div>
        </div>

        <div class="gn-notif-kpi">
            <div class="gn-notif-kpi-label">Danger</div>
            <div class="gn-notif-kpi-value"><?= gn_notif_h($danger) ?></div>
            <div class="gn-notif-kpi-note">بحاجة انتباه</div>
        </div>

        <div class="gn-notif-kpi">
            <div class="gn-notif-kpi-label">Router</div>
            <div class="gn-notif-kpi-value"><?= gn_notif_h($router) ?></div>
            <div class="gn-notif-kpi-note">MikroTik / Router</div>
        </div>
    </section>

    <form class="gn-notif-filters" method="get" action="/admin/notifications">
        <div class="form-group">
            <label>بحث</label>
            <input type="search" name="q" value="<?= gn_notif_h($query) ?>" placeholder="ابحث بالعنوان، الرسالة، المشترك..." dir="rtl">
        </div>

        <div class="form-group">
            <label>الحالة</label>
            <select name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>الكل</option>
                <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>غير مقروءة</option>
                <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>مقروءة</option>
            </select>
        </div>

        <div class="form-group">
            <label>التصنيف</label>
            <select name="category">
                <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>الكل</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= gn_notif_h((string) $cat) ?>" <?= $category === (string) $cat ? 'selected' : '' ?>>
                        <?= gn_notif_h(gn_notif_category_label((string) $cat)) ?>
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
            <a class="gn-btn gn-btn-secondary" href="/admin/notifications">مسح</a>
        </div>
    </form>

    <?php if (count($notifications) === 0): ?>
        <div class="gn-notif-empty">
            لا توجد إشعارات مطابقة للفلتر الحالي.
        </div>
    <?php else: ?>
        <section class="gn-notif-list">
            <?php foreach ($notifications as $notification): ?>
                <?php
                    $id = (int) ($notification['id'] ?? 0);
                    $title = trim((string) ($notification['title'] ?? ''));
                    $message = trim((string) ($notification['message'] ?? ''));
                    $severity = trim((string) ($notification['severity'] ?? 'info'));
                    $categoryValue = trim((string) ($notification['category'] ?? 'system'));
                    $username = trim((string) ($notification['related_username'] ?? ''));
                    $sourceTable = trim((string) ($notification['source_table'] ?? ''));
                    $sourceId = trim((string) ($notification['source_id'] ?? ''));
                    $createdAt = gn_notif_format_time((string) ($notification['created_at'] ?? ''));
                    $isRead = (int) ($notification['is_read'] ?? 0) === 1;
                    $linkUrl = trim((string) ($notification['link_url'] ?? ''));
                    $severityClass = gn_notif_severity_class($severity);
                    $cardClass = trim($severityClass . ' ' . (!$isRead ? 'is-unread' : ''));
                ?>

                <article class="gn-notif-card <?= gn_notif_h($cardClass) ?>">
                    <div class="gn-notif-main">
                        <div class="gn-notif-topline">
                            <span class="gn-notif-badge <?= gn_notif_h($severityClass) ?>">
                                <?= gn_notif_h(gn_notif_severity_label($severity)) ?>
                            </span>

                            <?php if (!$isRead): ?>
                                <span class="gn-notif-badge is-warning">جديد</span>
                            <?php else: ?>
                                <span class="gn-notif-badge is-muted">مقروء</span>
                            <?php endif; ?>

                            <span class="gn-notif-badge is-info">
                                <?= gn_notif_h(gn_notif_category_label($categoryValue)) ?>
                            </span>
                        </div>

                        <h2 class="gn-notif-title">
                            <?= gn_notif_h($title !== '' ? $title : 'إشعار') ?>
                        </h2>

                        <?php if ($message !== ''): ?>
                            <p class="gn-notif-message">
                                <?= gn_notif_h(gn_notif_short($message, 240)) ?>
                            </p>
                        <?php endif; ?>

                        <div class="gn-notif-meta">
                            <?php if ($username !== ''): ?>
                                <span class="gn-notif-badge is-muted" dir="ltr">
                                    USER: <?= gn_notif_h($username) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($sourceTable !== ''): ?>
                                <span class="gn-notif-badge is-muted" dir="ltr">
                                    <?= gn_notif_h($sourceTable) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($sourceId !== ''): ?>
                                <span class="gn-notif-badge is-muted" dir="ltr">
                                    ID: <?= gn_notif_h($sourceId) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($message !== '' && mb_strlen($message) > 240): ?>
                            <details class="gn-notif-details">
                                <summary>عرض كامل الرسالة</summary>
                                <pre class="gn-notif-details-body"><?= gn_notif_h($message) ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>

                    <aside class="gn-notif-side">
                        <div class="gn-notif-side-item">
                            <span class="gn-notif-side-label">الوقت</span>
                            <span class="gn-notif-side-value gn-notif-time"><?= gn_notif_h($createdAt) ?></span>
                        </div>

                        <div class="gn-notif-side-item">
                            <span class="gn-notif-side-label">المصدر</span>
                            <span class="gn-notif-side-value" dir="ltr">
                                <?= gn_notif_h($sourceTable !== '' ? $sourceTable : '-') ?>
                            </span>
                        </div>

                        <div class="gn-notif-side-item">
                            <span class="gn-notif-side-label">رقم الإشعار</span>
                            <span class="gn-notif-side-value" dir="ltr">#<?= gn_notif_h($id) ?></span>
                        </div>

                        <?php if ($linkUrl !== ''): ?>
                            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="<?= gn_notif_h($linkUrl) ?>">
                                فتح الرابط
                            </a>
                        <?php endif; ?>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</div>