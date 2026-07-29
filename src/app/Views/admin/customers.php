<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_cust_h')) {
    function gn_cust_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_cust_pdo')) {
    function gn_cust_pdo(): PDO
    {
        Database::migrate();

        return Database::connection();
    }
}

if (!function_exists('gn_cust_column_exists')) {
    function gn_cust_column_exists(string $table, string $column): bool
    {
        try {
            $rows = gn_cust_pdo()->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

if (!function_exists('gn_cust_ensure')) {
    function gn_cust_ensure(): void
    {
        $pdo = gn_cust_pdo();

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
            if (!gn_cust_column_exists('customers_local', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable) {
                    // Ignore older SQLite edge cases.
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

if (!function_exists('gn_cust_redirect')) {
    function gn_cust_redirect(string $query = ''): never
    {
        $url = '/admin/customers' . ($query !== '' ? '?' . $query : '');

        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }

        echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        exit;
    }
}

if (!function_exists('gn_cust_status_label')) {
    function gn_cust_status_label(string $status): string
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

if (!function_exists('gn_cust_status_class')) {
    function gn_cust_status_class(string $status): string
    {
        return match (strtolower(trim($status))) {
            'paid', 'free' => 'is-success',
            'due', 'pending' => 'is-warning',
            default => 'is-danger',
        };
    }
}

if (!function_exists('gn_cust_access_label')) {
    function gn_cust_access_label(string $type): string
    {
        return match (strtolower(trim($type))) {
            'hotspot' => 'Hotspot',
            'ppp', 'pppoe' => 'PPPoE',
            'hybrid' => 'Hybrid',
            default => $type !== '' ? $type : 'Hybrid',
        };
    }
}

if (!function_exists('gn_cust_format_date')) {
    function gn_cust_format_date(string $value): string
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

gn_cust_ensure();

$error = '';
$success = (string) ($_GET['success'] ?? '');
$editUsername = trim((string) ($_GET['edit'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['gn_customer_action'])) {
    $action = (string) ($_POST['gn_customer_action'] ?? '');

    try {
        if ($action === 'save') {
            $originalUsername = trim((string) ($_POST['original_username'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $accessType = trim((string) ($_POST['access_type'] ?? 'hybrid'));
            $paymentStatus = trim((string) ($_POST['payment_status'] ?? 'unpaid'));
            $packageIdRaw = trim((string) ($_POST['package_id'] ?? ''));
            $packageId = $packageIdRaw !== '' ? (int) $packageIdRaw : null;
            $password = (string) ($_POST['subscriber_password'] ?? '');
            $mustChangePassword = isset($_POST['must_change_password']) ? 1 : 0;
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if ($username === '') {
                throw new RuntimeException('اسم المستخدم مطلوب.');
            }

            if (!preg_match('/^[A-Za-z0-9_.@:-]{2,64}$/', $username)) {
                throw new RuntimeException('اسم المستخدم يجب أن يكون من أحرف وأرقام ورموز بسيطة فقط.');
            }

            if ($originalUsername !== '') {
                $existingStmt = gn_cust_pdo()->prepare("
                    SELECT username
                    FROM customers_local
                    WHERE username = :username AND username != :original
                    LIMIT 1
                ");
                $existingStmt->execute([
                    'username' => $username,
                    'original' => $originalUsername,
                ]);

                if ($existingStmt->fetchColumn()) {
                    throw new RuntimeException('اسم المستخدم مستخدم من قبل.');
                }

                if ($password !== '') {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = gn_cust_pdo()->prepare("
                        UPDATE customers_local
                        SET
                            username = :username,
                            full_name = :full_name,
                            phone = :phone,
                            access_type = :access_type,
                            payment_status = :payment_status,
                            package_id = :package_id,
                            notes = :notes,
                            subscriber_password_hash = :subscriber_password_hash,
                            password_changed_at = CURRENT_TIMESTAMP,
                            must_change_password = :must_change_password,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE username = :original_username
                    ");

                    $stmt->execute([
                        'username' => $username,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'access_type' => $accessType,
                        'payment_status' => $paymentStatus,
                        'package_id' => $packageId,
                        'notes' => $notes,
                        'subscriber_password_hash' => $passwordHash,
                        'must_change_password' => $mustChangePassword,
                        'original_username' => $originalUsername,
                    ]);
                } else {
                    $stmt = gn_cust_pdo()->prepare("
                        UPDATE customers_local
                        SET
                            username = :username,
                            full_name = :full_name,
                            phone = :phone,
                            access_type = :access_type,
                            payment_status = :payment_status,
                            package_id = :package_id,
                            notes = :notes,
                            must_change_password = :must_change_password,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE username = :original_username
                    ");

                    $stmt->execute([
                        'username' => $username,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'access_type' => $accessType,
                        'payment_status' => $paymentStatus,
                        'package_id' => $packageId,
                        'notes' => $notes,
                        'must_change_password' => $mustChangePassword,
                        'original_username' => $originalUsername,
                    ]);
                }

                gn_cust_redirect('success=updated');
            }

            $existingStmt = gn_cust_pdo()->prepare("SELECT username FROM customers_local WHERE username = :username LIMIT 1");
            $existingStmt->execute(['username' => $username]);

            if ($existingStmt->fetchColumn()) {
                throw new RuntimeException('اسم المستخدم موجود مسبقاً.');
            }

            $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '';

            $stmt = gn_cust_pdo()->prepare("
                INSERT INTO customers_local (
                    username,
                    full_name,
                    phone,
                    access_type,
                    payment_status,
                    package_id,
                    notes,
                    subscriber_password_hash,
                    password_changed_at,
                    must_change_password,
                    created_at,
                    updated_at
                )
                VALUES (
                    :username,
                    :full_name,
                    :phone,
                    :access_type,
                    :payment_status,
                    :package_id,
                    :notes,
                    :subscriber_password_hash,
                    CASE WHEN :subscriber_password_hash != '' THEN CURRENT_TIMESTAMP ELSE NULL END,
                    :must_change_password,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $stmt->execute([
                'username' => $username,
                'full_name' => $fullName,
                'phone' => $phone,
                'access_type' => $accessType,
                'payment_status' => $paymentStatus,
                'package_id' => $packageId,
                'notes' => $notes,
                'subscriber_password_hash' => $passwordHash,
                'must_change_password' => $mustChangePassword,
            ]);

            gn_cust_redirect('success=created');
        }

        if ($action === 'delete') {
            $username = trim((string) ($_POST['username'] ?? ''));

            if ($username === '') {
                throw new RuntimeException('اسم المستخدم غير صحيح.');
            }

            $stmt = gn_cust_pdo()->prepare("DELETE FROM customers_local WHERE username = :username");
            $stmt->execute(['username' => $username]);

            gn_cust_redirect('success=deleted');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$packages = gn_cust_pdo()->query("
    SELECT id, name, access_type, rate_limit, duration_days, quota_gb, price, currency, is_active
    FROM service_packages
    ORDER BY COALESCE(is_active, 0) DESC, name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$editing = null;

if ($editUsername !== '') {
    $stmt = gn_cust_pdo()->prepare("SELECT * FROM customers_local WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $editUsername]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$total = (int) gn_cust_pdo()->query("SELECT COUNT(*) FROM customers_local")->fetchColumn();
$paid = (int) gn_cust_pdo()->query("SELECT COUNT(*) FROM customers_local WHERE payment_status = 'paid'")->fetchColumn();
$unpaid = (int) gn_cust_pdo()->query("SELECT COUNT(*) FROM customers_local WHERE payment_status != 'paid' OR payment_status IS NULL")->fetchColumn();
$withPassword = (int) gn_cust_pdo()->query("SELECT COUNT(*) FROM customers_local WHERE subscriber_password_hash IS NOT NULL AND subscriber_password_hash != ''")->fetchColumn();

$recent = gn_cust_pdo()->query("
    SELECT c.*, p.name AS package_name
    FROM customers_local c
    LEFT JOIN service_packages p ON p.id = c.package_id
    ORDER BY datetime(c.updated_at) DESC, datetime(c.created_at) DESC, c.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$successMap = [
    'created' => 'تمت إضافة المشترك بنجاح.',
    'updated' => 'تم تعديل بيانات المشترك بنجاح.',
    'deleted' => 'تم حذف المشترك محلياً.',
];

$formData = [
    'original_username' => (string) ($editing['username'] ?? ''),
    'username' => (string) ($editing['username'] ?? ''),
    'full_name' => (string) ($editing['full_name'] ?? ''),
    'phone' => (string) ($editing['phone'] ?? ''),
    'access_type' => (string) ($editing['access_type'] ?? 'hybrid'),
    'payment_status' => (string) ($editing['payment_status'] ?? 'unpaid'),
    'package_id' => (string) ($editing['package_id'] ?? ''),
    'router_id' => (string) ($editing['router_id'] ?? ''),
    'must_change_password' => (int) ($editing['must_change_password'] ?? 0) === 1,
    'notes' => (string) ($editing['notes'] ?? ''),
];

?>

<style>
    .gn-cust-page {
        display: grid;
        gap: 18px;
    }

    .gn-cust-hero {
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

    .gn-cust-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-cust-hero p {
        margin: 10px 0 0;
        max-width: 940px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-cust-hero-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-cust-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-cust-kpi {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-cust-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-cust-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-cust-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-cust-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-cust-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-cust-kpi-value {
        margin-top: 7px;
        color: var(--gn-text);
        font-size: 30px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .gn-cust-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(360px, 0.85fr);
        gap: 18px;
        align-items: start;
    }

    .gn-cust-card {
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        border-radius: var(--gn-radius-xl);
        box-shadow: var(--gn-shadow-sm);
        padding: 20px;
    }

    .gn-cust-card h2 {
        margin: 0 0 14px;
        color: var(--gn-text);
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-cust-form {
        display: grid;
        gap: 14px;
    }

    .gn-cust-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-cust-field {
        display: grid;
        gap: 7px;
    }

    .gn-cust-field.is-wide {
        grid-column: 1 / -1;
    }

    .gn-cust-field label {
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 950;
    }

    .gn-cust-field input,
    .gn-cust-field select,
    .gn-cust-field textarea {
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

    .gn-cust-field textarea {
        min-height: 100px;
        resize: vertical;
        line-height: 1.7;
    }

    .gn-cust-field input:focus,
    .gn-cust-field select:focus,
    .gn-cust-field textarea:focus {
        border-color: var(--gn-primary);
        box-shadow: 0 0 0 4px rgba(17, 148, 90, 0.14);
    }

    .gn-cust-switch-row {
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

    .gn-cust-switch-row input {
        width: 18px;
        height: 18px;
    }

    .gn-cust-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 4px;
    }

    .gn-cust-alert {
        padding: 14px 16px;
        border-radius: var(--gn-radius-lg);
        font-weight: 900;
        line-height: 1.7;
    }

    .gn-cust-alert.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.22);
    }

    .gn-cust-alert.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.22);
    }

    .gn-cust-recent-list {
        display: grid;
        gap: 10px;
    }

    .gn-cust-mini {
        position: relative;
        overflow: hidden;
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-cust-mini::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-cust-mini-title {
        margin: 0;
        color: var(--gn-text);
        font-size: 15px;
        font-weight: 950;
        direction: ltr;
        text-align: left;
    }

    html[dir="rtl"] .gn-cust-mini-title {
        text-align: right;
    }

    .gn-cust-mini-sub {
        margin-top: 5px;
        color: var(--gn-text-soft);
        font-size: 12px;
        line-height: 1.6;
    }

    .gn-cust-mini-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .gn-cust-badge {
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

    .gn-cust-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-cust-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-cust-badge.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-cust-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-cust-badge.is-muted {
        background: var(--gn-surface-3);
        color: var(--gn-muted);
    }

    .gn-cust-mini-actions {
        display: flex;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .gn-cust-inline-form {
        display: inline-flex;
        margin: 0;
    }

    .gn-cust-empty {
        padding: 22px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-surface-2);
        color: var(--gn-muted);
        font-weight: 900;
        text-align: center;
    }

    .gn-cust-note {
        margin-top: 14px;
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        line-height: 1.7;
        font-weight: 850;
    }

    html[data-theme="greennet-dark"] .gn-cust-hero,
    html[data-theme="greennet-dark"] .gn-cust-card,
    html[data-theme="greennet-dark"] .gn-cust-kpi,
    html[data-theme="greennet-dark"] .gn-cust-mini,
    html[data-theme="greennet-dark"] .gn-cust-empty {
        background:
            radial-gradient(circle at 0% 0%, rgba(46, 230, 139, 0.07), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2)) !important;
    }

    @media (max-width: 1200px) {
        .gn-cust-layout {
            grid-template-columns: 1fr;
        }

        .gn-cust-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-cust-form-grid,
        .gn-cust-kpis {
            grid-template-columns: 1fr;
        }

        .gn-cust-hero h1 {
            font-size: 26px;
        }
    }
</style>

<div class="gn-cust-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">إدارة المشتركين</h1>
            <p class="admin-page-description">
                إضافة وتعديل بيانات المشتركين محلياً قبل تنفيذ أي عمليات حقيقية على MikroTik.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers/table">جدول المشتركين</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers/password">كلمات المرور</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/customers">Refresh</a>
        </div>
    </div>

    <?php if ($success !== '' && isset($successMap[$success])): ?>
        <div class="gn-cust-alert is-success">
            <?= gn_cust_h($successMap[$success]) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="gn-cust-alert is-danger">
            <?= gn_cust_h($error) ?>
        </div>
    <?php endif; ?>

    <section class="gn-cust-hero">
        <h1><?= $editing ? 'تعديل مشترك' : 'إضافة مشترك جديد' ?></h1>
        <p>
            هذه الصفحة تعمل على قاعدة بيانات GreenNet المحلية فقط.
            إنشاء المستخدم فعلياً داخل Hotspot أو PPPoE سيتم لاحقاً ضمن مرحلة MikroTik Write بعد Dry Run وطبقة الأمان.
        </p>

        <div class="gn-cust-hero-actions">
            <a class="gn-btn gn-btn-primary" href="#customer-form">
                <?= $editing ? 'تعديل المشترك المحدد' : 'إضافة مشترك الآن' ?>
            </a>

            <a class="gn-btn gn-btn-secondary" href="/admin/customers/table">
                عرض جدول المشتركين
            </a>
        </div>
    </section>

    <section class="gn-cust-kpis">
        <div class="gn-cust-kpi">
            <div class="gn-cust-kpi-label">إجمالي المشتركين</div>
            <div class="gn-cust-kpi-value"><?= gn_cust_h($total) ?></div>
        </div>

        <div class="gn-cust-kpi is-success">
            <div class="gn-cust-kpi-label">مدفوع</div>
            <div class="gn-cust-kpi-value"><?= gn_cust_h($paid) ?></div>
        </div>

        <div class="gn-cust-kpi is-warning">
            <div class="gn-cust-kpi-label">غير مدفوع / مستحق</div>
            <div class="gn-cust-kpi-value"><?= gn_cust_h($unpaid) ?></div>
        </div>

        <div class="gn-cust-kpi is-info">
            <div class="gn-cust-kpi-label">لديهم كلمة مرور</div>
            <div class="gn-cust-kpi-value"><?= gn_cust_h($withPassword) ?></div>
        </div>
    </section>

    <section class="gn-cust-layout">

        <div class="gn-cust-card" id="customer-form">
            <h2><?= $editing ? 'تعديل بيانات مشترك' : 'بيانات مشترك جديد' ?></h2>

            <form
                class="gn-cust-form"
                method="post"
                action="/admin/customers"
                data-gn-form-wrapped="1"
                data-gn-form-enhanced="1"
                data-gn-fields-grouped="1"
            >
                <input type="hidden" name="gn_customer_action" value="save">
                <input type="hidden" name="original_username" value="<?= gn_cust_h($formData['original_username']) ?>">

                <div class="gn-cust-form-grid">
                    <div class="gn-cust-field">
                        <label>اسم المستخدم</label>
                        <input type="text" name="username" value="<?= gn_cust_h($formData['username']) ?>" placeholder="مثال: ahmad01" dir="ltr" required>
                    </div>

                    <div class="gn-cust-field">
                        <label>اسم الزبون</label>
                        <input type="text" name="full_name" value="<?= gn_cust_h($formData['full_name']) ?>" placeholder="مثال: أحمد محمد">
                    </div>

                    <div class="gn-cust-field">
                        <label>رقم الهاتف</label>
                        <input type="text" name="phone" value="<?= gn_cust_h($formData['phone']) ?>" placeholder="09xxxxxxxx" dir="ltr">
                    </div>

                    <div class="gn-cust-field">
                        <label>نوع الوصول</label>
                        <select name="access_type">
                            <option value="hybrid" <?= $formData['access_type'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                            <option value="hotspot" <?= $formData['access_type'] === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                            <option value="ppp" <?= in_array($formData['access_type'], ['ppp', 'pppoe'], true) ? 'selected' : '' ?>>PPPoE</option>
                        </select>
                    </div>

                    <div class="gn-cust-field">
                        <label>حالة الدفع</label>
                        <select name="payment_status">
                            <option value="unpaid" <?= $formData['payment_status'] === 'unpaid' ? 'selected' : '' ?>>غير مدفوع</option>
                            <option value="paid" <?= $formData['payment_status'] === 'paid' ? 'selected' : '' ?>>مدفوع</option>
                            <option value="due" <?= $formData['payment_status'] === 'due' ? 'selected' : '' ?>>مستحق</option>
                            <option value="pending" <?= $formData['payment_status'] === 'pending' ? 'selected' : '' ?>>معلق</option>
                            <option value="free" <?= $formData['payment_status'] === 'free' ? 'selected' : '' ?>>مجاني</option>
                        </select>
                    </div>

                    <div class="gn-cust-field">
                        <label>الباقة</label>
                        <select name="package_id">
                            <option value="">بدون باقة</option>
                            <?php foreach ($packages as $package): ?>
                                <?php
                                    $pkgId = (string) ($package['id'] ?? '');
                                    $pkgName = (string) ($package['name'] ?? '');
                                    $pkgAccess = (string) ($package['access_type'] ?? 'hybrid');
                                ?>
                                <option value="<?= gn_cust_h($pkgId) ?>" <?= $formData['package_id'] === $pkgId ? 'selected' : '' ?>>
                                    <?= gn_cust_h($pkgName) ?> — <?= gn_cust_h(gn_cust_access_label($pkgAccess)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="gn-cust-field">
                        <label>الراوتر المستهدف</label>
                        <select name="router_id">
                            <option value="">الراوتر الافتراضي / إعداد التوافق القديم</option>
                            <?php foreach (($routers ?? []) as $router): ?>
                                <?php $routerId = (string) ($router['id'] ?? ''); ?>
                                <option value="<?= gn_cust_h($routerId) ?>" <?= $formData['router_id'] === $routerId ? 'selected' : '' ?>>
                                    <?= gn_cust_h($router['name'] ?? '') ?> — <?= gn_cust_h($router['host'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="gn-cust-field">
                        <label><?= $editing ? 'كلمة مرور جديدة' : 'كلمة مرور المشترك' ?></label>
                        <input type="password" name="subscriber_password" value="" placeholder="<?= $editing ? 'اتركها فارغة إن لم ترد تغييرها' : 'اختياري حالياً' ?>" dir="ltr">
                    </div>

                    <div class="gn-cust-field">
                        <label>&nbsp;</label>
                        <label class="gn-cust-switch-row">
                            <input type="checkbox" name="must_change_password" value="1" <?= $formData['must_change_password'] ? 'checked' : '' ?>>
                            فرض تغيير كلمة المرور
                        </label>
                    </div>

                    <div class="gn-cust-field is-wide">
                        <label>ملاحظات</label>
                        <textarea name="notes" placeholder="ملاحظات داخلية عن المشترك..."><?= gn_cust_h($formData['notes']) ?></textarea>
                    </div>
                </div>

                <div class="gn-cust-actions">
                    <button class="gn-btn gn-btn-primary" type="submit">
                        <?= $editing ? 'حفظ التعديل' : 'إضافة المشترك' ?>
                    </button>

                    <?php if ($editing): ?>
                        <a class="gn-btn gn-btn-secondary gn-cancel-action" href="/admin/customers">إلغاء</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="gn-cust-note">
                ملاحظة: إضافة المشترك هنا لا تنشئه داخل MikroTik بعد. الربط الحقيقي سيكون لاحقاً عبر Dry Run ثم Real Write.
            </div>
        </div>

        <aside class="gn-cust-card">
            <h2>آخر المشتركين</h2>

            <?php if (count($recent) === 0): ?>
                <div class="gn-cust-empty">
                    لا يوجد مشتركين بعد.
                </div>
            <?php else: ?>
                <section class="gn-cust-recent-list">
                    <?php foreach ($recent as $customer): ?>
                        <?php
                            $username = (string) ($customer['username'] ?? '');
                            $fullName = (string) ($customer['full_name'] ?? '');
                            $phone = (string) ($customer['phone'] ?? '');
                            $status = (string) ($customer['payment_status'] ?? 'unpaid');
                            $access = (string) ($customer['access_type'] ?? 'hybrid');
                            $packageName = (string) ($customer['package_name'] ?? '');
                            $updatedAt = gn_cust_format_date((string) ($customer['updated_at'] ?? ''));
                        ?>

                        <article class="gn-cust-mini">
                            <h3 class="gn-cust-mini-title"><?= gn_cust_h($username) ?></h3>

                            <div class="gn-cust-mini-sub">
                                <?= gn_cust_h($fullName !== '' ? $fullName : 'بدون اسم') ?>
                                <?= $phone !== '' ? ' — ' . gn_cust_h($phone) : '' ?>
                            </div>

                            <div class="gn-cust-mini-meta">
                                <span class="gn-cust-badge <?= gn_cust_h(gn_cust_status_class($status)) ?>">
                                    <?= gn_cust_h(gn_cust_status_label($status)) ?>
                                </span>

                                <span class="gn-cust-badge is-info">
                                    <?= gn_cust_h(gn_cust_access_label($access)) ?>
                                </span>

                                <?php if ($packageName !== ''): ?>
                                    <span class="gn-cust-badge is-muted">
                                        <?= gn_cust_h($packageName) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="gn-cust-mini-sub" dir="ltr">
                                Updated: <?= gn_cust_h($updatedAt) ?>
                            </div>

                            <div class="gn-cust-mini-actions">
                                <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers?edit=<?= rawurlencode($username) ?>#customer-form">
                                    تعديل
                                </a>

                                <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers/timeline?username=<?= rawurlencode($username) ?>">
                                    Timeline
                                </a>

                                <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers/password?username=<?= rawurlencode($username) ?>">
                                    كلمة المرور
                                </a>

                                <form
                                    class="gn-cust-inline-form"
                                    method="post"
                                    action="/admin/customers"
                                    data-gn-form-wrapped="1"
                                    data-gn-form-enhanced="1"
                                    data-gn-fields-grouped="1"
                                    onsubmit="return confirm('هل تريد حذف هذا المشترك محلياً؟');"
                                >
                                    <input type="hidden" name="gn_customer_action" value="delete">
                                    <input type="hidden" name="username" value="<?= gn_cust_h($username) ?>">
                                    <button class="gn-btn gn-btn-danger gn-btn-sm" type="submit">
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </aside>

    </section>

</div>
