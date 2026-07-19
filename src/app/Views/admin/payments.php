<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_pay_h')) {
    function gn_pay_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_pay_pdo')) {
    function gn_pay_pdo(): PDO
    {
        Database::migrate();
        return Database::connection();
    }
}

if (!function_exists('gn_pay_ensure')) {
    function gn_pay_ensure(): void
    {
        gn_pay_pdo()->exec("
            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT DEFAULT '',
                amount REAL DEFAULT 0,
                currency TEXT DEFAULT 'SYP',
                package_id INTEGER DEFAULT NULL,
                package_name TEXT DEFAULT '',
                duration_days INTEGER DEFAULT 0,
                quota_gb REAL DEFAULT 0,
                starts_at TEXT DEFAULT '',
                expires_at TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('gn_pay_money')) {
    function gn_pay_money(mixed $amount, string $currency): string
    {
        $number = is_numeric($amount) ? (float) $amount : 0.0;
        $formatted = number_format($number, $number == floor($number) ? 0 : 2);

        return $formatted . ' ' . ($currency !== '' ? $currency : 'SYP');
    }
}

if (!function_exists('gn_pay_date')) {
    function gn_pay_date(string $value): string
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

if (!function_exists('gn_pay_status')) {
    function gn_pay_status(string $expiresAt): array
    {
        $expiresAt = trim($expiresAt);

        if ($expiresAt === '') {
            return ['unknown', 'غير معروف', 'is-muted'];
        }

        $timestamp = strtotime($expiresAt);

        if ($timestamp === false) {
            return ['unknown', 'غير معروف', 'is-muted'];
        }

        if ($timestamp < time()) {
            return ['expired', 'منتهي', 'is-danger'];
        }

        $daysLeft = (int) ceil(($timestamp - time()) / 86400);

        if ($daysLeft <= 2) {
            return ['soon', 'قارب الانتهاء', 'is-warning'];
        }

        return ['active', 'فعال', 'is-success'];
    }
}

gn_pay_ensure();

$query = trim((string) ($_GET['q'] ?? ''));
$currencyFilter = trim((string) ($_GET['currency'] ?? 'all'));

$where = ['1=1'];
$params = [];

if ($query !== '') {
    $where[] = '(username LIKE :q OR package_name LIKE :q OR currency LIKE :q)';
    $params['q'] = '%' . $query . '%';
}

if ($currencyFilter !== 'all' && $currencyFilter !== '') {
    $where[] = 'currency = :currency';
    $params['currency'] = $currencyFilter;
}

$sql = "
    SELECT *
    FROM payments
    WHERE " . implode(' AND ', $where) . "
    ORDER BY datetime(created_at) DESC, id DESC
    LIMIT 250
";

try {
    $stmt = gn_pay_pdo()->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable) {
    $payments = [];
}

try {
    $totalCount = (int) gn_pay_pdo()->query("SELECT COUNT(*) FROM payments")->fetchColumn();
    $totalAmount = (float) gn_pay_pdo()->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();
    $todayAmount = (float) gn_pay_pdo()->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE date(created_at) = date('now')")->fetchColumn();
    $activeCount = 0;

    $allExpiryRows = gn_pay_pdo()->query("SELECT expires_at FROM payments")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($allExpiryRows as $expiresAt) {
        $status = gn_pay_status((string) $expiresAt);
        if ($status[0] === 'active' || $status[0] === 'soon') {
            $activeCount++;
        }
    }

    $currencies = gn_pay_pdo()->query("
        SELECT DISTINCT currency
        FROM payments
        WHERE currency IS NOT NULL AND currency != ''
        ORDER BY currency ASC
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable) {
    $totalCount = count($payments);
    $totalAmount = 0.0;
    $todayAmount = 0.0;
    $activeCount = 0;
    $currencies = [];
}

?>

<style>
    .gn-pay-page {
        display: grid;
        gap: 18px;
    }

    .gn-pay-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .gn-pay-kpi {
        position: relative;
        overflow: hidden;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2));
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-pay-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--gn-primary), var(--gn-primary-3));
    }

    .gn-pay-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-pay-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-pay-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-pay-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-pay-kpi-value {
        margin-top: 8px;
        color: var(--gn-text);
        font-size: 30px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .gn-pay-kpi-note {
        margin-top: 4px;
        color: var(--gn-muted);
        font-size: 12px;
    }

    .gn-pay-filters {
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

    .gn-pay-list {
        display: grid;
        gap: 12px;
    }

    .gn-pay-card {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 280px;
        gap: 18px;
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        overflow: hidden;
    }

    .gn-pay-card::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-success);
    }

    .gn-pay-card.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-pay-card.is-danger::before {
        background: var(--gn-danger);
    }

    .gn-pay-card.is-muted::before {
        background: var(--gn-muted);
    }

    .gn-pay-main {
        min-width: 0;
    }

    .gn-pay-topline {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .gn-pay-title {
        margin: 0;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
        letter-spacing: -0.025em;
        line-height: 1.35;
    }

    .gn-pay-amount {
        margin-top: 10px;
        color: var(--gn-text);
        font-size: 34px;
        font-weight: 950;
        letter-spacing: -0.05em;
        direction: ltr;
        text-align: left;
    }

    html[dir="rtl"] .gn-pay-amount {
        text-align: right;
    }

    .gn-pay-message {
        margin: 8px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-pay-badge {
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

    .gn-pay-badge.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-pay-badge.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-pay-badge.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-pay-badge.is-info {
        background: var(--gn-info-soft);
        color: var(--gn-info);
    }

    .gn-pay-badge.is-muted {
        background: var(--gn-surface-3);
        color: var(--gn-muted);
    }

    .gn-pay-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .gn-pay-side {
        display: grid;
        align-content: start;
        gap: 10px;
        min-width: 0;
    }

    .gn-pay-side-item {
        padding: 10px 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-pay-side-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .gn-pay-side-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        line-height: 1.55;
        word-break: break-word;
    }

    .gn-pay-time {
        direction: ltr !important;
        text-align: left !important;
        unicode-bidi: plaintext !important;
        font-family: Consolas, "Cascadia Code", "Courier New", monospace;
        white-space: nowrap;
    }

    .gn-pay-empty {
        padding: 28px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        text-align: center;
        color: var(--gn-muted);
        font-weight: 900;
    }

    html[data-theme="greennet-dark"] .gn-pay-card,
    html[data-theme="greennet-dark"] .gn-pay-kpi,
    html[data-theme="greennet-dark"] .gn-pay-filters,
    html[data-theme="greennet-dark"] .gn-pay-empty {
        background: linear-gradient(180deg, rgba(16, 32, 25, 0.94), rgba(10, 25, 17, 0.92));
    }

    @media (max-width: 1200px) {
        .gn-pay-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gn-pay-card {
            grid-template-columns: 1fr;
        }

        .gn-pay-side {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .gn-pay-kpis,
        .gn-pay-filters,
        .gn-pay-side {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="gn-pay-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">الدفعات</h1>
            <p class="admin-page-description">
                عرض الدفعات ككروت مالية واضحة مع حالة الاشتراك وتفاصيل الباقة.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/renewal-requests">طلبات التجديد</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/customers/table">المشتركين</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/payments">Refresh</a>
        </div>
    </div>

    <section class="gn-pay-kpis">
        <div class="gn-pay-kpi">
            <div class="gn-pay-kpi-label">Payments</div>
            <div class="gn-pay-kpi-value"><?= gn_pay_h($totalCount) ?></div>
            <div class="gn-pay-kpi-note">إجمالي الدفعات</div>
        </div>

        <div class="gn-pay-kpi is-success">
            <div class="gn-pay-kpi-label">Total Amount</div>
            <div class="gn-pay-kpi-value"><?= gn_pay_h(gn_pay_money($totalAmount, 'SYP')) ?></div>
            <div class="gn-pay-kpi-note">مجموع الدخل المسجل</div>
        </div>

        <div class="gn-pay-kpi is-info">
            <div class="gn-pay-kpi-label">Today</div>
            <div class="gn-pay-kpi-value"><?= gn_pay_h(gn_pay_money($todayAmount, 'SYP')) ?></div>
            <div class="gn-pay-kpi-note">دفعات اليوم</div>
        </div>

        <div class="gn-pay-kpi is-warning">
            <div class="gn-pay-kpi-label">Active</div>
            <div class="gn-pay-kpi-value"><?= gn_pay_h($activeCount) ?></div>
            <div class="gn-pay-kpi-note">دفعات باشتراك غير منتهٍ</div>
        </div>
    </section>

    <form class="gn-pay-filters" method="get" action="/admin/payments">
        <div class="form-group">
            <label>بحث</label>
            <input type="search" name="q" value="<?= gn_pay_h($query) ?>" placeholder="اسم المستخدم، الباقة، العملة..." dir="rtl">
        </div>

        <div class="form-group">
            <label>العملة</label>
            <select name="currency">
                <option value="all" <?= $currencyFilter === 'all' ? 'selected' : '' ?>>الكل</option>
                <?php foreach ($currencies as $currency): ?>
                    <option value="<?= gn_pay_h((string) $currency) ?>" <?= $currencyFilter === (string) $currency ? 'selected' : '' ?>>
                        <?= gn_pay_h((string) $currency) ?>
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
            <a class="gn-btn gn-btn-secondary" href="/admin/payments">مسح</a>
        </div>
    </form>

    <?php if (count($payments) === 0): ?>
        <div class="gn-pay-empty">
            لا توجد دفعات مطابقة للفلتر الحالي.
        </div>
    <?php else: ?>
        <section class="gn-pay-list">
            <?php foreach ($payments as $payment): ?>
                <?php
                    $id = (int) ($payment['id'] ?? 0);
                    $username = trim((string) ($payment['username'] ?? ''));
                    $amount = $payment['amount'] ?? 0;
                    $currency = trim((string) ($payment['currency'] ?? 'SYP'));
                    $packageName = trim((string) ($payment['package_name'] ?? ''));
                    $durationDays = (int) ($payment['duration_days'] ?? 0);
                    $quotaGb = (float) ($payment['quota_gb'] ?? 0);
                    $startsAt = gn_pay_date((string) ($payment['starts_at'] ?? ''));
                    $expiresAtRaw = (string) ($payment['expires_at'] ?? '');
                    $expiresAt = gn_pay_date($expiresAtRaw);
                    $createdAt = gn_pay_date((string) ($payment['created_at'] ?? ''));
                    $status = gn_pay_status($expiresAtRaw);
                ?>

                <article class="gn-pay-card <?= gn_pay_h($status[2]) ?>">
                    <div class="gn-pay-main">
                        <div class="gn-pay-topline">
                            <span class="gn-pay-badge <?= gn_pay_h($status[2]) ?>">
                                <?= gn_pay_h($status[1]) ?>
                            </span>

                            <span class="gn-pay-badge is-muted" dir="ltr">
                                #<?= gn_pay_h($id) ?>
                            </span>

                            <?php if ($username !== ''): ?>
                                <span class="gn-pay-badge is-info" dir="ltr">
                                    <?= gn_pay_h($username) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h2 class="gn-pay-title">
                            <?= gn_pay_h($packageName !== '' ? $packageName : 'دفعة اشتراك') ?>
                        </h2>

                        <div class="gn-pay-amount">
                            <?= gn_pay_h(gn_pay_money($amount, $currency)) ?>
                        </div>

                        <p class="gn-pay-message">
                            <?= $durationDays > 0 ? 'مدة الاشتراك: ' . gn_pay_h($durationDays) . ' يوم.' : '' ?>
                            <?= $quotaGb > 0 ? ' الكوتا: ' . gn_pay_h($quotaGb) . ' GB.' : '' ?>
                        </p>

                        <div class="gn-pay-meta">
                            <?php if ($username !== ''): ?>
                                <a class="gn-pay-badge is-muted" href="/admin/customers/timeline?username=<?= rawurlencode($username) ?>" dir="ltr">
                                    Timeline
                                </a>
                            <?php endif; ?>

                            <span class="gn-pay-badge is-muted" dir="ltr">
                                Created: <?= gn_pay_h($createdAt) ?>
                            </span>
                        </div>
                    </div>

                    <aside class="gn-pay-side">
                        <div class="gn-pay-side-item">
                            <span class="gn-pay-side-label">بداية الاشتراك</span>
                            <span class="gn-pay-side-value gn-pay-time"><?= gn_pay_h($startsAt) ?></span>
                        </div>

                        <div class="gn-pay-side-item">
                            <span class="gn-pay-side-label">نهاية الاشتراك</span>
                            <span class="gn-pay-side-value gn-pay-time"><?= gn_pay_h($expiresAt) ?></span>
                        </div>

                        <div class="gn-pay-side-item">
                            <span class="gn-pay-side-label">المستخدم</span>
                            <span class="gn-pay-side-value" dir="ltr"><?= gn_pay_h($username !== '' ? $username : '-') ?></span>
                        </div>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</div>