<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_renew_h')) {
    function gn_renew_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_renew_pdo')) {
    function gn_renew_pdo(): PDO
    {
        Database::migrate();
        return Database::connection();
    }
}

if (!function_exists('gn_renew_ensure')) {
    function gn_renew_ensure(): void
    {
        gn_renew_pdo()->exec("
            CREATE TABLE IF NOT EXISTS renewal_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT DEFAULT '',
                full_name TEXT DEFAULT '',
                phone TEXT DEFAULT '',
                package_id INTEGER DEFAULT NULL,
                package_name TEXT DEFAULT '',
                message TEXT DEFAULT '',
                status TEXT DEFAULT 'pending',
                admin_note TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('gn_renew_short')) {
    function gn_renew_short(string $text, int $limit = 180): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit) . '...';
    }
}

if (!function_exists('gn_renew_status_class')) {
    function gn_renew_status_class(string $status): string
    {
        return match (strtolower(trim($status))) {
            'approved', 'done', 'paid', 'completed' => 'is-success',
            'denied', 'rejected', 'failed', 'cancelled' => 'is-danger',
            'processing', 'review' => 'is-info',
            default => 'is-warning',
        };
    }
}

if (!function_exists('gn_renew_status_label')) {
    function gn_renew_status_label(string $status): string
    {
        return match (strtolower(trim($status))) {
            'approved' => 'مقبول',
            'done', 'completed' => 'مكتمل',
            'paid' => 'مدفوع',
            'denied', 'rejected' => 'مرفوض',
            'failed' => 'فشل',
            'cancelled' => 'ملغي',
            'processing', 'review' => 'قيد المراجعة',
            default => 'بانتظار المعالجة',
        };
    }
}

if (!function_exists('gn_renew_format_time')) {
    function gn_renew_format_time(string $value): string
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

gn_renew_ensure();

$query = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));

$where = ['1=1'];
$params = [];

if ($query !== '') {
    $where[] = '(username LIKE :q OR full_name LIKE :q OR phone LIKE :q OR package_name LIKE :q OR message LIKE :q)';
    $params['q'] = '%' . $query . '%';
}

if ($statusFilter !== 'all' && $statusFilter !== '') {
    $where[] = 'status = :status';
    $params['status'] = $statusFilter;
}

$sql = "
    SELECT *
    FROM renewal_requests
    WHERE " . implode(' AND ', $where) . "
    ORDER BY
        CASE LOWER(status)
            WHEN 'pending' THEN 0
            WHEN 'processing' THEN 1
            WHEN 'review' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'done' THEN 3
            WHEN 'completed' THEN 3
            ELSE 4
        END ASC,
        datetime(created_at) DESC,
        id DESC
    LIMIT 200
";

try {
    $stmt = gn_renew_pdo()->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable) {
    $requests = [];
}

try {
    $total = (int) gn_renew_pdo()->query("SELECT COUNT(*) FROM renewal_requests")->fetchColumn();
    $pending = (int) gn_renew_pdo()->query("SELECT COUNT(*) FROM renewal_requests WHERE LOWER(status) IN ('pending','processing','review')")->fetchColumn();
    $approved = (int) gn_renew_pdo()->query("SELECT COUNT(*) FROM renewal_requests WHERE LOWER(status) IN ('approved','done','completed','paid')")->fetchColumn();
    $denied = (int) gn_renew_pdo()->query("SELECT COUNT(*) FROM renewal_requests WHERE LOWER(status) IN ('denied','rejected','failed','cancelled')")->fetchColumn();

    $statuses = gn_renew_pdo()->query("
        SELECT DISTINCT status
        FROM renewal_requests
        WHERE status IS NOT NULL AND status != ''
        ORDER BY status ASC
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable) {
    $total = count($requests);
    $pending = 0;
    $approved = 0;
    $denied = 0;
    $statuses = [];
}

?>

<style>
    .gn-renew-page {
        display: grid;
        gap: 18px;
    }

    .gn-renew-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .gn-renew-kpi {
        position: relative;
        overflow: hidden;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2));
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-renew-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--gn-primary), var(--gn-primary-3));
    }

    .gn-renew-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-renew-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-renew-kpi.is-danger::before {
        background: var(--gn-danger);
    }

    .gn-renew-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-renew-kpi-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .gn-renew-kpi-note {
        margin-top: 4px;
        color: var(--gn-muted);
        font-size: 12px;
    }

    .gn-renew-filters {
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

    .gn-renew-list {
        display: grid;
        gap: 12px;
    }

    .gn-renew-card {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 260px;
        gap: 18px;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        overflow: hidden;
    }

    .gn-renew-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-warning);
    }

    .gn-renew-card.is-success::before {
        background: var(--gn-success);
    }

    .gn-renew-card.is-danger::before {
        background: var(--gn-danger);
    }

    .gn-renew-card.is-info::before {
        background: var(--gn-info);
    }

    .gn-renew-main {
        min-width: 0;
    }

    .gn-renew-topline {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .gn-renew-title {
        margin: 0;
        color: var(--gn-text);
        font-size: 19px;
        font-weight: 950;
        letter-spacing: -0.025em;
        line-height: 1.4;
    }

    .gn-renew-message {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
        max-width: 900px;
    }

    .gn-renew-badge {
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

    .gn-renew-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-renew-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-renew-badge.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-renew-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-renew-badge.is-muted {
        background: var(--gn-surface-3);
        color: var(--gn-muted);
    }

    .gn-renew-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .gn-renew-side {
        display: grid;
        align-content: start;
        gap: 10px;
        min-width: 0;
    }

    .gn-renew-side-item {
        padding: 10px 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-renew-side-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .gn-renew-side-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        line-height: 1.55;
        word-break: break-word;
    }

    .gn-renew-time {
        direction: ltr !important;
        text-align: left !important;
        unicode-bidi: plaintext !important;
        font-family: Consolas, "Cascadia Code", "Courier New", monospace;
        white-space: nowrap;
    }

    .gn-renew-empty {
        padding: 28px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        text-align: center;
        color: var(--gn-muted);
        font-weight: 900;
    }

    html[data-theme="greennet-dark"] .gn-renew-card,
    html[data-theme="greennet-dark"] .gn-renew-kpi,
    html[data-theme="greennet-dark"] .gn-renew-filters,
    html[data-theme="greennet-dark"] .gn-renew-empty {
        background: linear-gradient(180deg, rgba(16, 32, 25, 0.94), rgba(10, 25, 17, 0.92));
    }

    @media (max-width: 1200px) {
        .gn-renew-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gn-renew-card {
            grid-template-columns: 1fr;
        }

        .gn-renew-side {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-renew-kpis,
        .gn-renew-filters,
        .gn-renew-side {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="gn-renew-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">طلبات التجديد</h1>
            <p class="admin-page-description">
                متابعة طلبات تجديد الاشتراك بشكل بطاقات واضحة بدل الجداول الطويلة.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/payments">الدفعات</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers/table">المشتركين</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/renewal-requests">Refresh</a>
        </div>
    </div>

    <section class="gn-renew-kpis">
        <div class="gn-renew-kpi">
            <div class="gn-renew-kpi-label">Total</div>
            <div class="gn-renew-kpi-value"><?= gn_renew_h($total) ?></div>
            <div class="gn-renew-kpi-note">إجمالي الطلبات</div>
        </div>

        <div class="gn-renew-kpi is-warning">
            <div class="gn-renew-kpi-label">Pending</div>
            <div class="gn-renew-kpi-value"><?= gn_renew_h($pending) ?></div>
            <div class="gn-renew-kpi-note">بانتظار المعالجة</div>
        </div>

        <div class="gn-renew-kpi is-success">
            <div class="gn-renew-kpi-label">Approved</div>
            <div class="gn-renew-kpi-value"><?= gn_renew_h($approved) ?></div>
            <div class="gn-renew-kpi-note">طلبات مقبولة</div>
        </div>

        <div class="gn-renew-kpi is-danger">
            <div class="gn-renew-kpi-label">Denied</div>
            <div class="gn-renew-kpi-value"><?= gn_renew_h($denied) ?></div>
            <div class="gn-renew-kpi-note">طلبات مرفوضة أو فاشلة</div>
        </div>
    </section>

    <form class="gn-renew-filters" method="get" action="/admin/renewal-requests">
        <div class="form-group">
            <label>بحث</label>
            <input type="search" name="q" value="<?= gn_renew_h($query) ?>" placeholder="اسم المستخدم، الهاتف، الباقة..." dir="rtl">
        </div>

        <div class="form-group">
            <label>الحالة</label>
            <select name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>الكل</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= gn_renew_h((string) $status) ?>" <?= $statusFilter === (string) $status ? 'selected' : '' ?>>
                        <?= gn_renew_h(gn_renew_status_label((string) $status)) ?>
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
            <a class="gn-btn gn-btn-secondary" href="/admin/renewal-requests">مسح</a>
        </div>
    </form>

    <?php if (count($requests) === 0): ?>
        <div class="gn-renew-empty">
            لا توجد طلبات تجديد مطابقة للفلتر الحالي.
        </div>
    <?php else: ?>
        <section class="gn-renew-list">
            <?php foreach ($requests as $request): ?>
                <?php
                    $id = (int) ($request['id'] ?? 0);
                    $username = trim((string) ($request['username'] ?? ''));
                    $fullName = trim((string) ($request['full_name'] ?? ''));
                    $phone = trim((string) ($request['phone'] ?? ''));
                    $packageName = trim((string) ($request['package_name'] ?? ''));
                    $message = trim((string) ($request['message'] ?? ''));
                    $adminNote = trim((string) ($request['admin_note'] ?? ''));
                    $status = trim((string) ($request['status'] ?? 'pending'));
                    $createdAt = gn_renew_format_time((string) ($request['created_at'] ?? ''));
                    $statusClass = gn_renew_status_class($status);
                    $statusLabel = gn_renew_status_label($status);
                    $displayName = $fullName !== '' ? $fullName : ($username !== '' ? $username : 'مشترك');
                ?>

                <article class="gn-renew-card <?= gn_renew_h($statusClass) ?>">
                    <div class="gn-renew-main">
                        <div class="gn-renew-topline">
                            <span class="gn-renew-badge <?= gn_renew_h($statusClass) ?>">
                                <?= gn_renew_h($statusLabel) ?>
                            </span>

                            <span class="gn-renew-badge is-muted" dir="ltr">
                                #<?= gn_renew_h($id) ?>
                            </span>

                            <?php if ($username !== ''): ?>
                                <span class="gn-renew-badge is-info" dir="ltr">
                                    <?= gn_renew_h($username) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h2 class="gn-renew-title">
                            <?= gn_renew_h($displayName) ?>
                        </h2>

                        <p class="gn-renew-message">
                            طلب تجديد<?= $packageName !== '' ? ' للباقة: ' . gn_renew_h($packageName) : '' ?>.
                            <?= $message !== '' ? gn_renew_h(gn_renew_short($message, 220)) : '' ?>
                        </p>

                        <div class="gn-renew-meta">
                            <?php if ($phone !== ''): ?>
                                <span class="gn-renew-badge is-muted" dir="ltr">
                                    Phone: <?= gn_renew_h($phone) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($packageName !== ''): ?>
                                <span class="gn-renew-badge is-info">
                                    <?= gn_renew_h($packageName) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($adminNote !== ''): ?>
                                <span class="gn-renew-badge is-muted">
                                    ملاحظة إدارية
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <aside class="gn-renew-side">
                        <div class="gn-renew-side-item">
                            <span class="gn-renew-side-label">الوقت</span>
                            <span class="gn-renew-side-value gn-renew-time"><?= gn_renew_h($createdAt) ?></span>
                        </div>

                        <div class="gn-renew-side-item">
                            <span class="gn-renew-side-label">الحالة</span>
                            <span class="gn-renew-side-value"><?= gn_renew_h($statusLabel) ?></span>
                        </div>

                        <div class="gn-renew-side-item">
                            <span class="gn-renew-side-label">الباقة</span>
                            <span class="gn-renew-side-value"><?= gn_renew_h($packageName !== '' ? $packageName : '-') ?></span>
                        </div>

                        <?php if ($username !== ''): ?>
                            <?php if (!in_array($status, ['completed', 'rejected'], true)): ?>
                                <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/customers/renew?username=<?= rawurlencode($username) ?>&amp;request_id=<?= $id ?>">
                                    تسجيل الدفعة والتجديد
                                </a>
                            <?php endif; ?>
                            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers/timeline?username=<?= rawurlencode($username) ?>">
                                Timeline
                            </a>
                        <?php endif; ?>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</div>
