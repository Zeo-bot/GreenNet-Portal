<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_pkg_h')) {
    function gn_pkg_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_pkg_pdo')) {
    function gn_pkg_pdo(): PDO
    {
        Database::migrate();

        return Database::connection();
    }
}

if (!function_exists('gn_pkg_column_exists')) {
    function gn_pkg_column_exists(string $table, string $column): bool
    {
        try {
            $rows = gn_pkg_pdo()->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

if (!function_exists('gn_pkg_ensure')) {
    function gn_pkg_ensure(): void
    {
        $pdo = gn_pkg_pdo();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_packages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                source_type TEXT DEFAULT 'manual',
                source_profile TEXT DEFAULT '',
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

        $columns = [
            'source_type' => "ALTER TABLE service_packages ADD COLUMN source_type TEXT DEFAULT 'manual'",
            'source_profile' => "ALTER TABLE service_packages ADD COLUMN source_profile TEXT DEFAULT ''",
            'access_type' => "ALTER TABLE service_packages ADD COLUMN access_type TEXT DEFAULT 'hybrid'",
            'rate_limit' => "ALTER TABLE service_packages ADD COLUMN rate_limit TEXT DEFAULT ''",
            'duration_days' => "ALTER TABLE service_packages ADD COLUMN duration_days INTEGER DEFAULT 30",
            'quota_gb' => "ALTER TABLE service_packages ADD COLUMN quota_gb REAL DEFAULT 0",
            'price' => "ALTER TABLE service_packages ADD COLUMN price REAL DEFAULT 0",
            'currency' => "ALTER TABLE service_packages ADD COLUMN currency TEXT DEFAULT 'SYP'",
            'is_active' => "ALTER TABLE service_packages ADD COLUMN is_active INTEGER DEFAULT 1",
            'notes' => "ALTER TABLE service_packages ADD COLUMN notes TEXT DEFAULT ''",
            'created_at' => "ALTER TABLE service_packages ADD COLUMN created_at TEXT DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ALTER TABLE service_packages ADD COLUMN updated_at TEXT DEFAULT CURRENT_TIMESTAMP",
        ];

        foreach ($columns as $column => $sql) {
            if (!gn_pkg_column_exists('service_packages', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable) {
                    // Ignore old SQLite edge cases.
                }
            }
        }
    }
}

if (!function_exists('gn_pkg_money')) {
    function gn_pkg_money(mixed $amount, string $currency): string
    {
        $number = is_numeric($amount) ? (float) $amount : 0.0;
        $formatted = number_format($number, $number == floor($number) ? 0 : 2);

        return $formatted . ' ' . ($currency !== '' ? $currency : 'SYP');
    }
}

if (!function_exists('gn_pkg_access_label')) {
    function gn_pkg_access_label(string $type): string
    {
        return match (strtolower(trim($type))) {
            'hotspot' => 'Hotspot',
            'ppp', 'pppoe' => 'PPPoE',
            'hybrid' => 'Hybrid',
            default => $type !== '' ? $type : 'Hybrid',
        };
    }
}

if (!function_exists('gn_pkg_access_class')) {
    function gn_pkg_access_class(string $type): string
    {
        return match (strtolower(trim($type))) {
            'hotspot' => 'is-info',
            'ppp', 'pppoe' => 'is-warning',
            'hybrid' => 'is-success',
            default => 'is-muted',
        };
    }
}

if (!function_exists('gn_pkg_source_label')) {
    function gn_pkg_source_label(string $source): string
    {
        return match (strtolower(trim($source))) {
            'manual' => 'Manual',
            'hotspot_profile' => 'Hotspot Profile',
            'ppp_profile' => 'PPP Profile',
            'routeros' => 'RouterOS',
            default => $source !== '' ? $source : 'Manual',
        };
    }
}

if (!function_exists('gn_pkg_bool')) {
    function gn_pkg_bool(mixed $value): bool
    {
        return (string) $value === '1' || $value === 1 || $value === true;
    }
}

if (!function_exists('gn_pkg_redirect')) {
    function gn_pkg_redirect(string $query = ''): never
    {
        $url = '/admin/packages' . ($query !== '' ? '?' . $query : '');

        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }

        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        exit;
    }
}

gn_pkg_ensure();

$error = '';
$success = (string) ($_GET['success'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['gn_package_action'])) {
    $action = (string) ($_POST['gn_package_action'] ?? '');

    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $sourceType = trim((string) ($_POST['source_type'] ?? 'manual'));
            $sourceProfile = trim((string) ($_POST['source_profile'] ?? ''));
            $accessType = trim((string) ($_POST['access_type'] ?? 'hybrid'));
            $rateLimit = trim((string) ($_POST['rate_limit'] ?? ''));
            $durationDays = max(0, (int) ($_POST['duration_days'] ?? 30));
            $quotaGb = is_numeric($_POST['quota_gb'] ?? null) ? (float) $_POST['quota_gb'] : 0.0;
            $price = is_numeric($_POST['price'] ?? null) ? (float) $_POST['price'] : 0.0;
            $currency = trim((string) ($_POST['currency'] ?? 'SYP'));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('اسم الباقة مطلوب.');
            }

            if ($id > 0) {
                $stmt = gn_pkg_pdo()->prepare("
                    UPDATE service_packages
                    SET
                        name = :name,
                        source_type = :source_type,
                        source_profile = :source_profile,
                        access_type = :access_type,
                        rate_limit = :rate_limit,
                        duration_days = :duration_days,
                        quota_gb = :quota_gb,
                        price = :price,
                        currency = :currency,
                        is_active = :is_active,
                        notes = :notes,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");

                $stmt->execute([
                    'id' => $id,
                    'name' => $name,
                    'source_type' => $sourceType,
                    'source_profile' => $sourceProfile,
                    'access_type' => $accessType,
                    'rate_limit' => $rateLimit,
                    'duration_days' => $durationDays,
                    'quota_gb' => $quotaGb,
                    'price' => $price,
                    'currency' => $currency !== '' ? $currency : 'SYP',
                    'is_active' => $isActive,
                    'notes' => $notes,
                ]);

                gn_pkg_redirect('success=updated');
            }

            $stmt = gn_pkg_pdo()->prepare("
                INSERT INTO service_packages (
                    name,
                    source_type,
                    source_profile,
                    access_type,
                    rate_limit,
                    duration_days,
                    quota_gb,
                    price,
                    currency,
                    is_active,
                    notes,
                    created_at,
                    updated_at
                )
                VALUES (
                    :name,
                    :source_type,
                    :source_profile,
                    :access_type,
                    :rate_limit,
                    :duration_days,
                    :quota_gb,
                    :price,
                    :currency,
                    :is_active,
                    :notes,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $stmt->execute([
                'name' => $name,
                'source_type' => $sourceType,
                'source_profile' => $sourceProfile,
                'access_type' => $accessType,
                'rate_limit' => $rateLimit,
                'duration_days' => $durationDays,
                'quota_gb' => $quotaGb,
                'price' => $price,
                'currency' => $currency !== '' ? $currency : 'SYP',
                'is_active' => $isActive,
                'notes' => $notes,
            ]);

            gn_pkg_redirect('success=created');
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('رقم الباقة غير صحيح.');
            }

            $stmt = gn_pkg_pdo()->prepare("
                UPDATE service_packages
                SET is_active = CASE WHEN COALESCE(is_active, 0) = 1 THEN 0 ELSE 1 END,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);

            gn_pkg_redirect('success=toggled');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('رقم الباقة غير صحيح.');
            }

            $stmt = gn_pkg_pdo()->prepare("DELETE FROM service_packages WHERE id = :id");
            $stmt->execute(['id' => $id]);

            gn_pkg_redirect('success=deleted');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$accessFilter = trim((string) ($_GET['access'] ?? 'all'));
$activeFilter = trim((string) ($_GET['active'] ?? 'all'));
$editId = (int) ($_GET['edit'] ?? 0);

$where = ['1=1'];
$params = [];

if ($query !== '') {
    $where[] = '(name LIKE :q OR source_type LIKE :q OR source_profile LIKE :q OR access_type LIKE :q OR rate_limit LIKE :q OR notes LIKE :q)';
    $params['q'] = '%' . $query . '%';
}

if ($accessFilter !== 'all' && $accessFilter !== '') {
    $where[] = 'access_type = :access_type';
    $params['access_type'] = $accessFilter;
}

if ($activeFilter === 'active') {
    $where[] = 'COALESCE(is_active, 0) = 1';
} elseif ($activeFilter === 'inactive') {
    $where[] = 'COALESCE(is_active, 0) = 0';
}

$stmt = gn_pkg_pdo()->prepare("
    SELECT *
    FROM service_packages
    WHERE " . implode(' AND ', $where) . "
    ORDER BY COALESCE(is_active, 0) DESC, id DESC
");
$stmt->execute($params);
$packages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$editing = null;

if ($editId > 0) {
    $stmt = gn_pkg_pdo()->prepare("SELECT * FROM service_packages WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$total = (int) gn_pkg_pdo()->query("SELECT COUNT(*) FROM service_packages")->fetchColumn();
$active = (int) gn_pkg_pdo()->query("SELECT COUNT(*) FROM service_packages WHERE COALESCE(is_active, 0) = 1")->fetchColumn();
$hotspot = (int) gn_pkg_pdo()->query("SELECT COUNT(*) FROM service_packages WHERE LOWER(access_type) = 'hotspot'")->fetchColumn();
$ppp = (int) gn_pkg_pdo()->query("SELECT COUNT(*) FROM service_packages WHERE LOWER(access_type) IN ('ppp','pppoe')")->fetchColumn();
$hybrid = (int) gn_pkg_pdo()->query("SELECT COUNT(*) FROM service_packages WHERE LOWER(access_type) = 'hybrid'")->fetchColumn();

$successMap = [
    'created' => 'تمت إضافة الباقة بنجاح.',
    'updated' => 'تم تعديل الباقة بنجاح.',
    'toggled' => 'تم تغيير حالة الباقة.',
    'deleted' => 'تم حذف الباقة.',
];

$formData = [
    'id' => (int) ($editing['id'] ?? 0),
    'name' => (string) ($editing['name'] ?? ''),
    'source_type' => (string) ($editing['source_type'] ?? 'manual'),
    'source_profile' => (string) ($editing['source_profile'] ?? ''),
    'access_type' => (string) ($editing['access_type'] ?? 'hybrid'),
    'rate_limit' => (string) ($editing['rate_limit'] ?? ''),
    'duration_days' => (string) ($editing['duration_days'] ?? '30'),
    'quota_gb' => (string) ($editing['quota_gb'] ?? '0'),
    'price' => (string) ($editing['price'] ?? '0'),
    'currency' => (string) ($editing['currency'] ?? 'SYP'),
    'is_active' => gn_pkg_bool($editing['is_active'] ?? 1),
    'notes' => (string) ($editing['notes'] ?? ''),
];

?>

<style>
    .gn-pkg-page {
        display: grid;
        gap: 18px;
    }

    .gn-pkg-hero {
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

    .gn-pkg-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-pkg-hero p {
        margin: 10px 0 0;
        max-width: 920px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-pkg-hero-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-pkg-kpis {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-pkg-kpi {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-pkg-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-pkg-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-pkg-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-pkg-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-pkg-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-pkg-kpi-value {
        margin-top: 7px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .gn-pkg-layout {
        display: grid;
        grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.35fr);
        gap: 18px;
        align-items: start;
    }

    .gn-pkg-card {
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        border-radius: var(--gn-radius-xl);
        box-shadow: var(--gn-shadow-sm);
        padding: 20px;
    }

    .gn-pkg-card h2 {
        margin: 0 0 14px;
        color: var(--gn-text);
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-pkg-form {
        display: grid;
        gap: 14px;
    }

    .gn-pkg-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-pkg-field {
        display: grid;
        gap: 7px;
    }

    .gn-pkg-field.is-wide {
        grid-column: 1 / -1;
    }

    .gn-pkg-field label {
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 950;
    }

    .gn-pkg-field input,
    .gn-pkg-field select,
    .gn-pkg-field textarea {
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

    .gn-pkg-field textarea {
        min-height: 96px;
        resize: vertical;
        line-height: 1.7;
    }

    .gn-pkg-field input:focus,
    .gn-pkg-field select:focus,
    .gn-pkg-field textarea:focus {
        border-color: var(--gn-primary);
        box-shadow: 0 0 0 4px rgba(17, 148, 90, 0.14);
    }

    .gn-pkg-switch-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
        color: var(--gn-text);
        font-weight: 900;
    }

    .gn-pkg-switch-row input {
        width: 18px;
        height: 18px;
    }

    .gn-pkg-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 4px;
    }

    .gn-pkg-filters {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 170px 160px auto auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 14px;
        padding: 14px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
    }

    .gn-pkg-list {
        display: grid;
        gap: 12px;
    }

    .gn-pkg-item {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 230px;
        gap: 16px;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        overflow: hidden;
    }

    .gn-pkg-item::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-pkg-item.is-inactive::before {
        background: var(--gn-muted);
    }

    .gn-pkg-item-main {
        min-width: 0;
    }

    .gn-pkg-topline {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .gn-pkg-title {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-pkg-desc {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.75;
    }

    .gn-pkg-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .gn-pkg-badge {
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

    .gn-pkg-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-pkg-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-pkg-badge.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-pkg-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-pkg-badge.is-muted {
        background: var(--gn-surface-3);
        color: var(--gn-muted);
    }

    .gn-pkg-side {
        display: grid;
        align-content: start;
        gap: 9px;
    }

    .gn-pkg-side-item {
        padding: 10px 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-pkg-side-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .gn-pkg-side-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        line-height: 1.55;
        word-break: break-word;
    }

    .gn-pkg-item-actions {
        display: flex;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .gn-pkg-inline-form {
        display: inline-flex;
        margin: 0;
    }

    .gn-pkg-alert {
        padding: 14px 16px;
        border-radius: var(--gn-radius-lg);
        font-weight: 900;
        line-height: 1.7;
    }

    .gn-pkg-alert.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.22);
    }

    .gn-pkg-alert.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.22);
    }

    .gn-pkg-empty {
        padding: 24px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        text-align: center;
        color: var(--gn-muted);
        font-weight: 900;
    }

    html[data-theme="greennet-dark"] .gn-pkg-hero,
    html[data-theme="greennet-dark"] .gn-pkg-card,
    html[data-theme="greennet-dark"] .gn-pkg-kpi,
    html[data-theme="greennet-dark"] .gn-pkg-filters,
    html[data-theme="greennet-dark"] .gn-pkg-item,
    html[data-theme="greennet-dark"] .gn-pkg-empty {
        background:
            radial-gradient(circle at 0% 0%, rgba(46, 230, 139, 0.07), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2)) !important;
    }

    @media (max-width: 1200px) {
        .gn-pkg-kpis {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .gn-pkg-layout,
        .gn-pkg-item {
            grid-template-columns: 1fr;
        }

        .gn-pkg-side {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-pkg-kpis,
        .gn-pkg-form-grid,
        .gn-pkg-filters,
        .gn-pkg-side {
            grid-template-columns: 1fr;
        }

        .gn-pkg-hero h1 {
            font-size: 26px;
        }
    }
</style>

<div class="gn-pkg-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">إدارة الباقات</h1>
            <p class="admin-page-description">
                إنشاء وتعديل باقات Hotspot / PPPoE / Hybrid بشكل واضح قبل ربطها الكامل مع MikroTik.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers/table">المشتركين</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/payments">الدفعات</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/packages">Refresh</a>
        </div>
    </div>

    <?php if ($success !== '' && isset($successMap[$success])): ?>
        <div class="gn-pkg-alert is-success">
            <?= gn_pkg_h($successMap[$success]) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="gn-pkg-alert is-danger">
            <?= gn_pkg_h($error) ?>
        </div>
    <?php endif; ?>

    <section class="gn-pkg-hero">
        <h1>باقات الخدمة</h1>
        <p>
            هذه الصفحة أصبحت مخصصة لإدارة الباقات محلياً بشكل مرتب.
            استيراد بروفايلات MikroTik نؤجله لمرحلة الربط والكتابة الحقيقية حتى لا يسبب أي Timeout أو تداخل.
        </p>

        <div class="gn-pkg-hero-actions">
            <a class="gn-btn gn-btn-primary" href="#package-form">
                <?= $editing ? 'تعديل الباقة المحددة' : 'إضافة باقة جديدة' ?>
            </a>

            <button class="gn-btn gn-btn-secondary" type="button" disabled>
                استيراد MikroTik لاحقاً
            </button>
        </div>
    </section>

    <section class="gn-pkg-kpis">
        <div class="gn-pkg-kpi">
            <div class="gn-pkg-kpi-label">إجمالي الباقات</div>
            <div class="gn-pkg-kpi-value"><?= gn_pkg_h($total) ?></div>
        </div>

        <div class="gn-pkg-kpi is-success">
            <div class="gn-pkg-kpi-label">الباقات الفعالة</div>
            <div class="gn-pkg-kpi-value"><?= gn_pkg_h($active) ?></div>
        </div>

        <div class="gn-pkg-kpi is-info">
            <div class="gn-pkg-kpi-label">Hotspot</div>
            <div class="gn-pkg-kpi-value"><?= gn_pkg_h($hotspot) ?></div>
        </div>

        <div class="gn-pkg-kpi is-warning">
            <div class="gn-pkg-kpi-label">PPPoE</div>
            <div class="gn-pkg-kpi-value"><?= gn_pkg_h($ppp) ?></div>
        </div>

        <div class="gn-pkg-kpi">
            <div class="gn-pkg-kpi-label">Hybrid</div>
            <div class="gn-pkg-kpi-value"><?= gn_pkg_h($hybrid) ?></div>
        </div>
    </section>

    <section class="gn-pkg-layout">

        <div class="gn-pkg-card" id="package-form">
            <h2><?= $editing ? 'تعديل باقة' : 'إضافة باقة جديدة' ?></h2>

            <form
                class="gn-pkg-form"
                method="post"
                action="/admin/packages"
                data-gn-form-wrapped="1"
                data-gn-form-enhanced="1"
                data-gn-fields-grouped="1"
            >
                <input type="hidden" name="gn_package_action" value="save">
                <input type="hidden" name="id" value="<?= gn_pkg_h($formData['id']) ?>">

                <div class="gn-pkg-form-grid">
                    <div class="gn-pkg-field is-wide">
                        <label>اسم الباقة</label>
                        <input type="text" name="name" value="<?= gn_pkg_h($formData['name']) ?>" placeholder="مثال: GIGA-DAY 1" required>
                    </div>

                    <div class="gn-pkg-field">
                        <label>نوع الوصول</label>
                        <select name="access_type">
                            <option value="hybrid" <?= $formData['access_type'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                            <option value="hotspot" <?= $formData['access_type'] === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                            <option value="ppp" <?= in_array($formData['access_type'], ['ppp', 'pppoe'], true) ? 'selected' : '' ?>>PPPoE</option>
                        </select>
                    </div>

                    <div class="gn-pkg-field">
                        <label>المصدر</label>
                        <select name="source_type">
                            <option value="manual" <?= $formData['source_type'] === 'manual' ? 'selected' : '' ?>>Manual</option>
                            <option value="hotspot_profile" <?= $formData['source_type'] === 'hotspot_profile' ? 'selected' : '' ?>>Hotspot Profile</option>
                            <option value="ppp_profile" <?= $formData['source_type'] === 'ppp_profile' ? 'selected' : '' ?>>PPP Profile</option>
                            <option value="routeros" <?= $formData['source_type'] === 'routeros' ? 'selected' : '' ?>>RouterOS</option>
                        </select>
                    </div>

                    <div class="gn-pkg-field">
                        <label>RouterOS Profile</label>
                        <input type="text" name="source_profile" value="<?= gn_pkg_h($formData['source_profile']) ?>" placeholder="default" dir="ltr">
                    </div>

                    <div class="gn-pkg-field">
                        <label>Rate Limit</label>
                        <input type="text" name="rate_limit" value="<?= gn_pkg_h($formData['rate_limit']) ?>" placeholder="1M/1M" dir="ltr">
                    </div>

                    <div class="gn-pkg-field">
                        <label>الصلاحية بالأيام</label>
                        <input type="number" name="duration_days" value="<?= gn_pkg_h($formData['duration_days']) ?>" min="0" dir="ltr">
                    </div>

                    <div class="gn-pkg-field">
                        <label>الحجم GB</label>
                        <input type="number" step="0.01" name="quota_gb" value="<?= gn_pkg_h($formData['quota_gb']) ?>" min="0" dir="ltr">
                    </div>

                    <div class="gn-pkg-field">
                        <label>السعر</label>
                        <input type="number" step="0.01" name="price" value="<?= gn_pkg_h($formData['price']) ?>" min="0" dir="ltr">
                    </div>

                    <div class="gn-pkg-field">
                        <label>العملة</label>
                        <input type="text" name="currency" value="<?= gn_pkg_h($formData['currency']) ?>" placeholder="SYP" dir="ltr">
                    </div>

                    <div class="gn-pkg-field is-wide">
                        <label>ملاحظات</label>
                        <textarea name="notes" placeholder="ملاحظات داخلية عن الباقة..."><?= gn_pkg_h($formData['notes']) ?></textarea>
                    </div>

                    <div class="gn-pkg-field is-wide">
                        <label class="gn-pkg-switch-row">
                            <input type="checkbox" name="is_active" value="1" <?= $formData['is_active'] ? 'checked' : '' ?>>
                            الباقة فعالة
                        </label>
                    </div>
                </div>

                <div class="gn-pkg-actions">
                    <button class="gn-btn gn-btn-primary" type="submit">
                        <?= $editing ? 'حفظ التعديل' : 'إضافة الباقة' ?>
                    </button>

                    <?php if ($editing): ?>
                        <a class="gn-btn gn-btn-secondary gn-cancel-action" href="/admin/packages">إلغاء</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div>
            <form
                class="gn-pkg-filters"
                method="get"
                action="/admin/packages"
                data-gn-form-wrapped="1"
                data-gn-form-enhanced="1"
                data-gn-fields-grouped="1"
            >
                <div class="gn-pkg-field">
                    <label>بحث</label>
                    <input type="search" name="q" value="<?= gn_pkg_h($query) ?>" placeholder="اسم الباقة / profile / ملاحظة...">
                </div>

                <div class="gn-pkg-field">
                    <label>نوع الوصول</label>
                    <select name="access">
                        <option value="all" <?= $accessFilter === 'all' ? 'selected' : '' ?>>الكل</option>
                        <option value="hybrid" <?= $accessFilter === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                        <option value="hotspot" <?= $accessFilter === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                        <option value="ppp" <?= $accessFilter === 'ppp' ? 'selected' : '' ?>>PPPoE</option>
                    </select>
                </div>

                <div class="gn-pkg-field">
                    <label>الحالة</label>
                    <select name="active">
                        <option value="all" <?= $activeFilter === 'all' ? 'selected' : '' ?>>الكل</option>
                        <option value="active" <?= $activeFilter === 'active' ? 'selected' : '' ?>>فعالة</option>
                        <option value="inactive" <?= $activeFilter === 'inactive' ? 'selected' : '' ?>>معطلة</option>
                    </select>
                </div>

                <div class="gn-pkg-field">
                    <label>&nbsp;</label>
                    <button class="gn-btn gn-btn-primary" type="submit">تطبيق</button>
                </div>

                <div class="gn-pkg-field">
                    <label>&nbsp;</label>
                    <a class="gn-btn gn-btn-secondary gn-cancel-action" href="/admin/packages">مسح</a>
                </div>
            </form>

            <?php if (count($packages) === 0): ?>
                <div class="gn-pkg-empty">
                    لا توجد باقات مطابقة للفلتر الحالي.
                </div>
            <?php else: ?>
                <section class="gn-pkg-list">
                    <?php foreach ($packages as $package): ?>
                        <?php
                            $id = (int) ($package['id'] ?? 0);
                            $name = (string) ($package['name'] ?? '');
                            $sourceType = (string) ($package['source_type'] ?? 'manual');
                            $sourceProfile = (string) ($package['source_profile'] ?? '');
                            $accessType = (string) ($package['access_type'] ?? 'hybrid');
                            $rateLimit = (string) ($package['rate_limit'] ?? '');
                            $durationDays = (int) ($package['duration_days'] ?? 0);
                            $quotaGb = (float) ($package['quota_gb'] ?? 0);
                            $price = $package['price'] ?? 0;
                            $currency = (string) ($package['currency'] ?? 'SYP');
                            $isActive = gn_pkg_bool($package['is_active'] ?? 0);
                            $notes = (string) ($package['notes'] ?? '');
                            $itemClass = $isActive ? '' : 'is-inactive';
                        ?>

                        <article class="gn-pkg-item <?= gn_pkg_h($itemClass) ?>">
                            <div class="gn-pkg-item-main">
                                <div class="gn-pkg-topline">
                                    <span class="gn-pkg-badge <?= $isActive ? 'is-success' : 'is-muted' ?>">
                                        <?= $isActive ? 'فعالة' : 'معطلة' ?>
                                    </span>

                                    <span class="gn-pkg-badge <?= gn_pkg_h(gn_pkg_access_class($accessType)) ?>">
                                        <?= gn_pkg_h(gn_pkg_access_label($accessType)) ?>
                                    </span>

                                    <span class="gn-pkg-badge is-muted" dir="ltr">
                                        #<?= gn_pkg_h($id) ?>
                                    </span>
                                </div>

                                <h2 class="gn-pkg-title"><?= gn_pkg_h($name !== '' ? $name : 'باقة بدون اسم') ?></h2>

                                <p class="gn-pkg-desc">
                                    المصدر: <?= gn_pkg_h(gn_pkg_source_label($sourceType)) ?>
                                    <?= $sourceProfile !== '' ? ' — Profile: ' . gn_pkg_h($sourceProfile) : '' ?>
                                    <?= $notes !== '' ? ' — ' . gn_pkg_h($notes) : '' ?>
                                </p>

                                <div class="gn-pkg-meta">
                                    <span class="gn-pkg-badge is-info" dir="ltr">
                                        Rate: <?= gn_pkg_h($rateLimit !== '' ? $rateLimit : '-') ?>
                                    </span>

                                    <span class="gn-pkg-badge is-muted">
                                        <?= gn_pkg_h($durationDays) ?> يوم
                                    </span>

                                    <span class="gn-pkg-badge is-muted" dir="ltr">
                                        <?= gn_pkg_h($quotaGb) ?> GB
                                    </span>

                                    <span class="gn-pkg-badge is-success">
                                        <?= gn_pkg_h(gn_pkg_money($price, $currency)) ?>
                                    </span>
                                </div>

                                <div class="gn-pkg-item-actions">
                                    <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/packages?edit=<?= gn_pkg_h($id) ?>#package-form">
                                        تعديل
                                    </a>

                                    <form
                                        class="gn-pkg-inline-form"
                                        method="post"
                                        action="/admin/packages"
                                        data-gn-form-wrapped="1"
                                        data-gn-form-enhanced="1"
                                        data-gn-fields-grouped="1"
                                    >
                                        <input type="hidden" name="gn_package_action" value="toggle">
                                        <input type="hidden" name="id" value="<?= gn_pkg_h($id) ?>">
                                        <button class="gn-btn gn-btn-secondary gn-btn-sm" type="submit">
                                            <?= $isActive ? 'تعطيل' : 'تفعيل' ?>
                                        </button>
                                    </form>

                                    <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/global-search?q=<?= rawurlencode($name) ?>">
                                        بحث عن مستخدمي الباقة
                                    </a>

                                    <form
                                        class="gn-pkg-inline-form"
                                        method="post"
                                        action="/admin/packages"
                                        data-gn-form-wrapped="1"
                                        data-gn-form-enhanced="1"
                                        data-gn-fields-grouped="1"
                                        onsubmit="return confirm('هل تريد حذف هذه الباقة؟');"
                                    >
                                        <input type="hidden" name="gn_package_action" value="delete">
                                        <input type="hidden" name="id" value="<?= gn_pkg_h($id) ?>">
                                        <button class="gn-btn gn-btn-danger gn-btn-sm" type="submit">
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <aside class="gn-pkg-side">
                                <div class="gn-pkg-side-item">
                                    <span class="gn-pkg-side-label">السعر</span>
                                    <span class="gn-pkg-side-value"><?= gn_pkg_h(gn_pkg_money($price, $currency)) ?></span>
                                </div>

                                <div class="gn-pkg-side-item">
                                    <span class="gn-pkg-side-label">الصلاحية</span>
                                    <span class="gn-pkg-side-value"><?= gn_pkg_h($durationDays) ?> يوم</span>
                                </div>

                                <div class="gn-pkg-side-item">
                                    <span class="gn-pkg-side-label">الحجم</span>
                                    <span class="gn-pkg-side-value" dir="ltr"><?= gn_pkg_h($quotaGb) ?> GB</span>
                                </div>

                                <div class="gn-pkg-side-item">
                                    <span class="gn-pkg-side-label">RouterOS Profile</span>
                                    <span class="gn-pkg-side-value" dir="ltr"><?= gn_pkg_h($sourceProfile !== '' ? $sourceProfile : '-') ?></span>
                                </div>
                            </aside>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </div>

    </section>

</div>