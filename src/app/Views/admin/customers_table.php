<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_ct_h')) {
    function gn_ct_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_ct_pdo')) {
    function gn_ct_pdo(): PDO
    {
        Database::migrate();

        return Database::connection();
    }
}

if (!function_exists('gn_ct_column_exists')) {
    function gn_ct_column_exists(string $table, string $column): bool
    {
        try {
            $rows = gn_ct_pdo()->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                if ((string) ($row['name'] ?? '') === $column) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}

if (!function_exists('gn_ct_ensure')) {
    function gn_ct_ensure(): void
    {
        $pdo = gn_ct_pdo();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS customers_local (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                full_name TEXT DEFAULT '',
                phone TEXT DEFAULT '',
                payment_status TEXT DEFAULT 'unpaid',
                package_id INTEGER DEFAULT NULL,
                access_type TEXT DEFAULT 'hybrid',
                notes TEXT DEFAULT '',
                subscriber_password_hash TEXT DEFAULT '',
                password_changed_at TEXT DEFAULT NULL,
                last_login_at TEXT DEFAULT NULL,
                failed_login_attempts INTEGER DEFAULT 0,
                locked_until TEXT DEFAULT NULL,
                must_change_password INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $columns = [
            'full_name' => "ALTER TABLE customers_local ADD COLUMN full_name TEXT DEFAULT ''",
            'phone' => "ALTER TABLE customers_local ADD COLUMN phone TEXT DEFAULT ''",
            'payment_status' => "ALTER TABLE customers_local ADD COLUMN payment_status TEXT DEFAULT 'unpaid'",
            'package_id' => "ALTER TABLE customers_local ADD COLUMN package_id INTEGER DEFAULT NULL",
            'access_type' => "ALTER TABLE customers_local ADD COLUMN access_type TEXT DEFAULT 'hybrid'",
            'notes' => "ALTER TABLE customers_local ADD COLUMN notes TEXT DEFAULT ''",
            'subscriber_password_hash' => "ALTER TABLE customers_local ADD COLUMN subscriber_password_hash TEXT DEFAULT ''",
            'password_changed_at' => "ALTER TABLE customers_local ADD COLUMN password_changed_at TEXT DEFAULT NULL",
            'last_login_at' => "ALTER TABLE customers_local ADD COLUMN last_login_at TEXT DEFAULT NULL",
            'failed_login_attempts' => "ALTER TABLE customers_local ADD COLUMN failed_login_attempts INTEGER DEFAULT 0",
            'locked_until' => "ALTER TABLE customers_local ADD COLUMN locked_until TEXT DEFAULT NULL",
            'must_change_password' => "ALTER TABLE customers_local ADD COLUMN must_change_password INTEGER DEFAULT 0",
            'created_at' => "ALTER TABLE customers_local ADD COLUMN created_at TEXT DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ALTER TABLE customers_local ADD COLUMN updated_at TEXT DEFAULT CURRENT_TIMESTAMP",
        ];

        foreach ($columns as $column => $sql) {
            if (!gn_ct_column_exists('customers_local', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable) {
                    // Ignore old SQLite edge cases.
                }
            }
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_packages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                access_type TEXT DEFAULT 'hybrid',
                rate_limit TEXT DEFAULT '',
                duration_days INTEGER DEFAULT 30,
                quota_gb REAL DEFAULT 0,
                price REAL DEFAULT 0,
                currency TEXT DEFAULT 'SYP',
                is_active INTEGER DEFAULT 1,
                notes TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('gn_ct_status_label')) {
    function gn_ct_status_label(string $status): string
    {
        return match (strtolower(trim($status))) {
            'paid' => 'مدفوع',
            'due' => 'مستحق',
            'pending' => 'معلق',
            'free' => 'مجاني',
            default => 'غير مدفوع',
        };
    }
}

if (!function_exists('gn_ct_status_class')) {
    function gn_ct_status_class(string $status): string
    {
        return match (strtolower(trim($status))) {
            'paid', 'free' => 'is-success',
            'due', 'pending' => 'is-warning',
            default => 'is-danger',
        };
    }
}

if (!function_exists('gn_ct_access_label')) {
    function gn_ct_access_label(string $type): string
    {
        return match (strtolower(trim($type))) {
            'hotspot' => 'Hotspot',
            'ppp', 'pppoe' => 'PPPoE',
            'hybrid' => 'Hybrid',
            default => $type !== '' ? $type : 'Hybrid',
        };
    }
}

if (!function_exists('gn_ct_access_class')) {
    function gn_ct_access_class(string $type): string
    {
        return match (strtolower(trim($type))) {
            'hotspot' => 'is-info',
            'ppp', 'pppoe' => 'is-warning',
            'hybrid' => 'is-success',
            default => 'is-muted',
        };
    }
}

if (!function_exists('gn_ct_format_date')) {
    function gn_ct_format_date(string $value): string
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

if (!function_exists('gn_ct_short')) {
    function gn_ct_short(string $value, int $limit = 95): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit) . '...';
        }

        return strlen($value) <= $limit ? $value : substr($value, 0, $limit) . '...';
    }
}

gn_ct_ensure();

$query = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$accessFilter = trim((string) ($_GET['access'] ?? 'all'));
$packageFilter = trim((string) ($_GET['package_id'] ?? 'all'));
$sort = trim((string) ($_GET['sort'] ?? 'updated_desc'));

$where = ['1=1'];
$params = [];

if ($query !== '') {
    $where[] = '(
        c.username LIKE :q
        OR c.full_name LIKE :q
        OR c.phone LIKE :q
        OR c.notes LIKE :q
        OR p.name LIKE :q
    )';
    $params['q'] = '%' . $query . '%';
}

if ($statusFilter !== 'all' && $statusFilter !== '') {
    if ($statusFilter === 'unpaid_or_due') {
        $where[] = "(c.payment_status IS NULL OR c.payment_status = '' OR c.payment_status IN ('unpaid','due','pending'))";
    } else {
        $where[] = 'c.payment_status = :status';
        $params['status'] = $statusFilter;
    }
}

if ($accessFilter !== 'all' && $accessFilter !== '') {
    $where[] = 'c.access_type = :access_type';
    $params['access_type'] = $accessFilter;
}

if ($packageFilter !== 'all' && $packageFilter !== '') {
    if ($packageFilter === 'none') {
        $where[] = '(c.package_id IS NULL OR c.package_id = 0)';
    } else {
        $where[] = 'c.package_id = :package_id';
        $params['package_id'] = (int) $packageFilter;
    }
}

$orderBy = match ($sort) {
    'username_asc' => 'LOWER(c.username) ASC',
    'username_desc' => 'LOWER(c.username) DESC',
    'created_desc' => 'datetime(c.created_at) DESC, c.id DESC',
    'paid_first' => "CASE WHEN c.payment_status = 'paid' THEN 0 ELSE 1 END ASC, datetime(c.updated_at) DESC",
    'unpaid_first' => "CASE WHEN c.payment_status = 'paid' THEN 1 ELSE 0 END ASC, datetime(c.updated_at) DESC",
    default => 'datetime(c.updated_at) DESC, datetime(c.created_at) DESC, c.id DESC',
};

$stmt = gn_ct_pdo()->prepare("
    SELECT
        c.*,
        p.name AS package_name,
        p.rate_limit AS package_rate_limit,
        p.duration_days AS package_duration_days,
        p.quota_gb AS package_quota_gb,
        p.price AS package_price,
        p.currency AS package_currency,
        r.name AS router_name,
        (
            SELECT pay.expires_at FROM payments pay
            WHERE lower(pay.username) = lower(c.username)
              AND pay.status = 'paid' AND pay.package_id > 0
            ORDER BY pay.paid_at DESC, pay.id DESC LIMIT 1
        ) AS subscription_expires_at
    FROM customers_local c
    LEFT JOIN service_packages p ON p.id = c.package_id
    LEFT JOIN routers r ON r.id = c.router_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY {$orderBy}
    LIMIT 500
");
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$packages = gn_ct_pdo()->query("
    SELECT id, name, access_type
    FROM service_packages
    ORDER BY COALESCE(is_active, 0) DESC, name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$total = (int) gn_ct_pdo()->query("SELECT COUNT(*) FROM customers_local")->fetchColumn();
$paid = (int) gn_ct_pdo()->query("SELECT COUNT(*) FROM customers_local WHERE payment_status = 'paid'")->fetchColumn();
$unpaid = (int) gn_ct_pdo()->query("SELECT COUNT(*) FROM customers_local WHERE payment_status != 'paid' OR payment_status IS NULL")->fetchColumn();
$hotspot = (int) gn_ct_pdo()->query("SELECT COUNT(*) FROM customers_local WHERE LOWER(access_type) = 'hotspot'")->fetchColumn();
$ppp = (int) gn_ct_pdo()->query("SELECT COUNT(*) FROM customers_local WHERE LOWER(access_type) IN ('ppp','pppoe')")->fetchColumn();
$hybrid = (int) gn_ct_pdo()->query("SELECT COUNT(*) FROM customers_local WHERE LOWER(access_type) = 'hybrid'")->fetchColumn();

?>

<style>
    .gn-ct-page {
        display: grid;
        gap: 18px;
        max-width: 100%;
        overflow: hidden;
    }

    .gn-ct-hero {
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

    .gn-ct-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-ct-hero p {
        margin: 10px 0 0;
        max-width: 950px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-ct-hero-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-ct-kpis {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-ct-kpi {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-ct-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-ct-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-ct-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-ct-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-ct-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-ct-kpi-value {
        margin-top: 7px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .gn-ct-filters {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        align-items: end;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-ct-field {
        display: grid;
        gap: 7px;
        min-width: 0;
    }

    .gn-ct-field label {
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 950;
    }

    .gn-ct-field input,
    .gn-ct-field select {
        width: 100%;
        min-height: 44px;
        padding: 9px 13px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        outline: none;
        box-sizing: border-box;
    }

    .gn-ct-filter-actions {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        flex-wrap: wrap;
    }

    .gn-ct-results-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 16px 18px;
        border-radius: var(--gn-radius-xl);
        background:
            radial-gradient(circle at 0% 0%, rgba(32, 201, 120, 0.08), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2));
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-ct-results-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
    }

    .gn-ct-results-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 4px 11px;
        border-radius: 999px;
        background: var(--gn-primary-soft);
        color: var(--gn-primary);
        font-size: 12px;
        font-weight: 950;
    }

    .gn-ct-results-note {
        color: var(--gn-muted);
        font-size: 13px;
        font-weight: 850;
    }

    .gn-ct-list {
        display: grid;
        gap: 14px;
    }

    .gn-ct-card {
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(260px, 0.42fr);
        gap: 16px;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-ct-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-ct-card.is-paid::before {
        background: var(--gn-success);
    }

    .gn-ct-card.is-unpaid::before {
        background: var(--gn-danger);
    }

    .gn-ct-card-main {
        min-width: 0;
        display: grid;
        gap: 12px;
    }

    .gn-ct-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .gn-ct-id {
        display: grid;
        gap: 5px;
        min-width: 0;
    }

    .gn-ct-username {
        color: var(--gn-text);
        font-size: 21px;
        font-weight: 950;
        letter-spacing: -0.035em;
        direction: ltr;
        text-align: left;
        unicode-bidi: plaintext;
        word-break: break-word;
    }

    html[dir="rtl"] .gn-ct-username {
        text-align: right;
    }

    .gn-ct-fullname {
        color: var(--gn-text-soft);
        font-size: 13px;
        font-weight: 850;
        line-height: 1.6;
    }

    .gn-ct-badges {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        flex-wrap: wrap;
    }

    .gn-ct-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        width: fit-content;
        max-width: 100%;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 950;
        white-space: nowrap;
    }

    .gn-ct-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-ct-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-ct-badge.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-ct-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-ct-badge.is-muted {
        background: var(--gn-surface-3);
        color: var(--gn-muted);
    }

    .gn-ct-info-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .gn-ct-info {
        padding: 11px 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
        min-width: 0;
    }

    .gn-ct-info-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 5px;
    }

    .gn-ct-info-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        line-height: 1.55;
        word-break: break-word;
    }

    .gn-ct-code {
        direction: ltr;
        text-align: left;
        unicode-bidi: plaintext;
        font-family: Consolas, "Cascadia Code", monospace;
    }

    html[dir="rtl"] .gn-ct-code {
        text-align: right;
    }

    .gn-ct-notes {
        padding: 12px 14px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-primary-soft);
        color: var(--gn-primary);
        line-height: 1.75;
        font-size: 13px;
        font-weight: 850;
    }

    .gn-ct-side {
        min-width: 0;
        display: grid;
        align-content: start;
        gap: 10px;
    }

    .gn-ct-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .gn-ct-actions .gn-btn,
    .gn-ct-actions button {
        width: 100% !important;
        max-width: none !important;
        justify-content: center !important;
        min-height: 38px !important;
        white-space: nowrap !important;
    }

    .gn-ct-inline-form {
        display: contents;
        margin: 0;
    }

    .gn-ct-danger-action {
        grid-column: 1 / -1;
    }

    .gn-ct-empty {
        padding: 28px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        text-align: center;
        color: var(--gn-muted);
        font-weight: 900;
    }

    html[data-theme="greennet-dark"] .gn-ct-hero,
    html[data-theme="greennet-dark"] .gn-ct-kpi,
    html[data-theme="greennet-dark"] .gn-ct-filters,
    html[data-theme="greennet-dark"] .gn-ct-results-head,
    html[data-theme="greennet-dark"] .gn-ct-card,
    html[data-theme="greennet-dark"] .gn-ct-empty {
        background:
            radial-gradient(circle at 0% 0%, rgba(46, 230, 139, 0.07), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2)) !important;
    }

    @media (max-width: 1280px) {
        .gn-ct-kpis {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .gn-ct-filters {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gn-ct-card {
            grid-template-columns: 1fr;
        }

        .gn-ct-side {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 820px) {
        .gn-ct-kpis,
        .gn-ct-filters,
        .gn-ct-info-grid,
        .gn-ct-actions {
            grid-template-columns: 1fr;
        }

        .gn-ct-hero h1 {
            font-size: 26px;
        }
    }
</style>

<div class="gn-ct-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">جدول المشتركين</h1>
            <p class="admin-page-description">
                عرض منظم للمشتركين المحليين بدون جداول عريضة أو تداخل أعمدة.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/customers">إضافة مشترك</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/packages">الباقات</a>
        </div>
    </div>

    <section class="gn-ct-hero">
        <h1>المشتركون</h1>
        <p>
            نقطة الدخول اليومية لإدارة الاشتراك والحساب والراوتر، مع إبقاء معاينة تطبيق المشترك كإجراء منفصل.
        </p>

        <div class="gn-ct-hero-actions">
            <a class="gn-btn gn-btn-primary" href="/admin/customers">إضافة مشترك جديد</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/global-search">بحث شامل</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/renewal-requests">طلبات التجديد</a>
        </div>
    </section>

    <section class="gn-ct-kpis">
        <div class="gn-ct-kpi">
            <div class="gn-ct-kpi-label">إجمالي المشتركين</div>
            <div class="gn-ct-kpi-value"><?= gn_ct_h($total) ?></div>
        </div>

        <div class="gn-ct-kpi is-success">
            <div class="gn-ct-kpi-label">مدفوع</div>
            <div class="gn-ct-kpi-value"><?= gn_ct_h($paid) ?></div>
        </div>

        <div class="gn-ct-kpi is-warning">
            <div class="gn-ct-kpi-label">غير مدفوع / مستحق</div>
            <div class="gn-ct-kpi-value"><?= gn_ct_h($unpaid) ?></div>
        </div>

        <div class="gn-ct-kpi is-info">
            <div class="gn-ct-kpi-label">Hotspot</div>
            <div class="gn-ct-kpi-value"><?= gn_ct_h($hotspot) ?></div>
        </div>

        <div class="gn-ct-kpi is-warning">
            <div class="gn-ct-kpi-label">PPPoE</div>
            <div class="gn-ct-kpi-value"><?= gn_ct_h($ppp) ?></div>
        </div>

        <div class="gn-ct-kpi">
            <div class="gn-ct-kpi-label">Hybrid</div>
            <div class="gn-ct-kpi-value"><?= gn_ct_h($hybrid) ?></div>
        </div>
    </section>

    <form
        class="gn-ct-filters"
        method="get"
        action="/admin/customers/table"
        data-gn-form-wrapped="1"
        data-gn-form-enhanced="1"
        data-gn-fields-grouped="1"
    >
        <div class="gn-ct-field">
            <label>بحث</label>
            <input type="search" name="q" value="<?= gn_ct_h($query) ?>" placeholder="اسم المستخدم / الهاتف / الباقة...">
        </div>

        <div class="gn-ct-field">
            <label>الدفع</label>
            <select name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>الكل</option>
                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>مدفوع</option>
                <option value="unpaid" <?= $statusFilter === 'unpaid' ? 'selected' : '' ?>>غير مدفوع</option>
                <option value="due" <?= $statusFilter === 'due' ? 'selected' : '' ?>>مستحق</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>معلق</option>
                <option value="unpaid_or_due" <?= $statusFilter === 'unpaid_or_due' ? 'selected' : '' ?>>غير مدفوع / مستحق</option>
            </select>
        </div>

        <div class="gn-ct-field">
            <label>الوصول</label>
            <select name="access">
                <option value="all" <?= $accessFilter === 'all' ? 'selected' : '' ?>>الكل</option>
                <option value="hybrid" <?= $accessFilter === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                <option value="hotspot" <?= $accessFilter === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                <option value="ppp" <?= $accessFilter === 'ppp' ? 'selected' : '' ?>>PPPoE</option>
            </select>
        </div>

        <div class="gn-ct-field">
            <label>الباقة</label>
            <select name="package_id">
                <option value="all" <?= $packageFilter === 'all' ? 'selected' : '' ?>>كل الباقات</option>
                <option value="none" <?= $packageFilter === 'none' ? 'selected' : '' ?>>بدون باقة</option>
                <?php foreach ($packages as $package): ?>
                    <?php $pkgId = (string) ($package['id'] ?? ''); ?>
                    <option value="<?= gn_ct_h($pkgId) ?>" <?= $packageFilter === $pkgId ? 'selected' : '' ?>>
                        <?= gn_ct_h((string) ($package['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="gn-ct-field">
            <label>الترتيب</label>
            <select name="sort">
                <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>آخر تحديث</option>
                <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>الأحدث إضافة</option>
                <option value="username_asc" <?= $sort === 'username_asc' ? 'selected' : '' ?>>الاسم تصاعدي</option>
                <option value="username_desc" <?= $sort === 'username_desc' ? 'selected' : '' ?>>الاسم تنازلي</option>
                <option value="paid_first" <?= $sort === 'paid_first' ? 'selected' : '' ?>>المدفوع أولاً</option>
                <option value="unpaid_first" <?= $sort === 'unpaid_first' ? 'selected' : '' ?>>غير المدفوع أولاً</option>
            </select>
        </div>

        <div class="gn-ct-filter-actions">
            <button class="gn-btn gn-btn-primary" type="submit">تطبيق الفلتر</button>
            <a class="gn-btn gn-btn-secondary gn-cancel-action" href="/admin/customers/table">مسح الفلتر</a>
        </div>
    </form>

    <section class="gn-ct-results-head">
        <div class="gn-ct-results-title">
            <span>☷</span>
            <span>قائمة المشتركين</span>
            <span class="gn-ct-results-count"><?= gn_ct_h(count($customers)) ?></span>
        </div>

        <div class="gn-ct-results-note">
            يظهر حتى 500 مشترك حسب الفلتر الحالي.
        </div>
    </section>

    <?php if (count($customers) === 0): ?>
        <div class="gn-ct-empty">
            لا يوجد مشتركين مطابقين للفلتر الحالي.
        </div>
    <?php else: ?>
        <section class="gn-ct-list">
            <?php foreach ($customers as $customer): ?>
                <?php
                    $username = (string) ($customer['username'] ?? '');
                    $fullName = (string) ($customer['full_name'] ?? '');
                    $phone = (string) ($customer['phone'] ?? '');
                    $status = (string) ($customer['payment_status'] ?? 'unpaid');
                    $access = (string) ($customer['access_type'] ?? 'hybrid');
                    $packageName = (string) ($customer['package_name'] ?? '');
                    $rateLimit = (string) ($customer['package_rate_limit'] ?? '');
                    $routerName = (string) ($customer['router_name'] ?? '');
                    $backend = (string) ($customer['service_backend'] ?? 'user-manager');
                    $serviceStatus = strtolower((string) ($customer['service_status'] ?? 'active'));
                    $expiresAt = (string) ($customer['subscription_expires_at'] ?? '');
                    $expiryTimestamp = $expiresAt !== '' ? strtotime($expiresAt) : false;
                    $daysRemaining = $expiryTimestamp === false ? null : (int) floor(($expiryTimestamp - time()) / 86400);
                    $subscriptionLabel = match (true) {
                        in_array($serviceStatus, ['suspended', 'disabled'], true) => 'موقوف',
                        $expiryTimestamp !== false && $expiryTimestamp < time() => 'منتهي',
                        $expiryTimestamp !== false => 'نشط',
                        default => 'غير مفعّل',
                    };
                    $subscriptionClass = match ($subscriptionLabel) {
                        'نشط' => 'is-success',
                        'منتهي', 'موقوف' => 'is-danger',
                        default => 'is-muted',
                    };
                    $updatedAt = gn_ct_format_date((string) ($customer['updated_at'] ?? ''));
                    $createdAt = gn_ct_format_date((string) ($customer['created_at'] ?? ''));
                    $notes = (string) ($customer['notes'] ?? '');
                    $paidClass = strtolower($status) === 'paid' ? 'is-paid' : 'is-unpaid';
                ?>

                <article class="gn-ct-card <?= gn_ct_h($paidClass) ?>">
                    <div class="gn-ct-card-main">
                        <div class="gn-ct-card-top">
                            <div class="gn-ct-id">
                                <div class="gn-ct-username"><?= gn_ct_h($username) ?></div>
                                <div class="gn-ct-fullname"><?= gn_ct_h($fullName !== '' ? $fullName : 'بدون اسم') ?></div>
                            </div>

                            <div class="gn-ct-badges">
                                <span class="gn-ct-badge <?= gn_ct_h(gn_ct_status_class($status)) ?>">
                                    <?= gn_ct_h(gn_ct_status_label($status)) ?>
                                </span>

                                <span class="gn-ct-badge <?= gn_ct_h(gn_ct_access_class($access)) ?>">
                                    <?= gn_ct_h(match ($backend) { 'native-hotspot' => 'Hotspot', 'native-pppoe' => 'PPPoE', default => 'User Manager' }) ?>
                                </span>
                                <span class="gn-ct-badge <?= gn_ct_h($subscriptionClass) ?>">
                                    <?= gn_ct_h($subscriptionLabel) ?>
                                </span>
                            </div>
                        </div>

                        <div class="gn-ct-info-grid">
                            <div class="gn-ct-info">
                                <span class="gn-ct-info-label">الهاتف</span>
                                <span class="gn-ct-info-value gn-ct-code"><?= gn_ct_h($phone !== '' ? $phone : '-') ?></span>
                            </div>

                            <div class="gn-ct-info">
                                <span class="gn-ct-info-label">الباقة</span>
                                <span class="gn-ct-info-value"><?= gn_ct_h($packageName !== '' ? $packageName : 'بدون باقة') ?></span>
                            </div>

                            <div class="gn-ct-info">
                                <span class="gn-ct-info-label">السرعة</span>
                                <span class="gn-ct-info-value gn-ct-code"><?= gn_ct_h($rateLimit !== '' ? $rateLimit : '-') ?></span>
                            </div>

                            <div class="gn-ct-info">
                                <span class="gn-ct-info-label">الراوتر</span>
                                <span class="gn-ct-info-value"><?= gn_ct_h($routerName !== '' ? $routerName : 'غير معيّن') ?></span>
                            </div>

                            <div class="gn-ct-info">
                                <span class="gn-ct-info-label">انتهاء الاشتراك</span>
                                <span class="gn-ct-info-value gn-ct-code"><?= gn_ct_h($expiresAt !== '' ? gn_ct_format_date($expiresAt) : '-') ?></span>
                            </div>

                            <div class="gn-ct-info">
                                <span class="gn-ct-info-label">المتبقي</span>
                                <span class="gn-ct-info-value"><?= $daysRemaining === null ? '-' : gn_ct_h($daysRemaining >= 0 ? $daysRemaining . ' يوم' : 'منتهي') ?></span>
                            </div>
                        </div>

                        <?php if ($notes !== ''): ?>
                            <div class="gn-ct-notes">
                                <?= gn_ct_h(gn_ct_short($notes, 140)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="gn-ct-side">
                        <div class="gn-ct-actions">
                            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/customers/profile?username=<?= rawurlencode($username) ?>">إدارة المشترك</a>
                            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/dashboard?username=<?= rawurlencode($username) ?>" target="_blank">معاينة التطبيق</a>
                        </div>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</div>
