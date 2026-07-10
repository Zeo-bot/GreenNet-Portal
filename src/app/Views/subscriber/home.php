<link rel="stylesheet" href="/css/subscriber-app.css">

<?php
    $customer = is_array($customer ?? null) ? $customer : [];
    $package = is_array($package ?? null) ? $package : [];
    $latestPayment = is_array($latest_payment ?? null) ? $latest_payment : [];
    $connection = is_array($connection ?? null) ? $connection : [];
    $flash = is_array($flash ?? null) ? $flash : [];

    $username = (string) ($username ?? ($customer['username'] ?? '-'));

    $previewQuery = '';

    if (($_SESSION['admin_logged_in'] ?? false) === true && isset($_GET['username']) && trim((string) $_GET['username']) !== '') {
        $previewQuery = '?username=' . urlencode(trim((string) $_GET['username']));
    }

    $packageName = (string) (($package['name'] ?? '') !== '' ? $package['name'] : 'غير محددة');
    $expiresAt = (string) (($latestPayment['expires_at'] ?? '') !== '' ? $latestPayment['expires_at'] : '-');
?>

<div class="subscriber-app">

    <?php if (($flash['message'] ?? '') !== ''): ?>
        <div class="subscriber-notice <?= htmlspecialchars((string) ($flash['type'] ?? 'success')) ?>">
            <?= htmlspecialchars((string) ($flash['message'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <section class="subscriber-hero">
        <div class="subscriber-hero-top">
            <div>
                <div class="subscriber-hello">أهلاً بك في GreenNet</div>
                <div class="subscriber-username"><?= htmlspecialchars($username) ?></div>
            </div>

            <?php if (!empty($connection['online'])): ?>
                <div class="subscriber-status-pill">متصل الآن</div>
            <?php else: ?>
                <div class="subscriber-status-pill">غير متصل</div>
            <?php endif; ?>
        </div>

        <div class="subscriber-hero-grid">
            <div class="subscriber-hero-mini">
                <span>الباقة</span>
                <strong><?= htmlspecialchars($packageName) ?></strong>
            </div>

            <div class="subscriber-hero-mini">
                <span>الاستهلاك الحالي</span>
                <strong><?= htmlspecialchars((string) ($connection['bytes_total_human'] ?? '0 B')) ?></strong>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">نظرة سريعة</h2>

        <div class="subscriber-grid">
            <div class="subscriber-stat">
                <span>حالة الاتصال</span>
                <strong><?= !empty($connection['online']) ? 'Online' : 'Offline' ?></strong>
            </div>

            <div class="subscriber-stat">
                <span>نوع الاتصال</span>
                <strong><?= htmlspecialchars((string) ($connection['source'] ?? '-')) ?></strong>
            </div>

            <div class="subscriber-stat">
                <span>IP</span>
                <strong><?= htmlspecialchars((string) (($connection['ip'] ?? '') !== '' ? $connection['ip'] : '-')) ?></strong>
            </div>

            <div class="subscriber-stat">
                <span>ينتهي في</span>
                <strong><?= htmlspecialchars($expiresAt) ?></strong>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">الخدمات</h2>

        <div class="subscriber-list">

            <a class="subscriber-row" href="/my/usage<?= htmlspecialchars($previewQuery) ?>" style="text-decoration:none;">
                <div>
                    <div class="subscriber-row-label">الاستهلاك</div>
                    <div class="subscriber-muted">عرض الرفع والتنزيل وحالة الاتصال</div>
                </div>
                <div class="subscriber-row-value">📊</div>
            </a>

            <a class="subscriber-row" href="/my/package<?= htmlspecialchars($previewQuery) ?>" style="text-decoration:none;">
                <div>
                    <div class="subscriber-row-label">الباقة</div>
                    <div class="subscriber-muted">تفاصيل الباقة والسرعة والمدة</div>
                </div>
                <div class="subscriber-row-value">📦</div>
            </a>

            <a class="subscriber-row" href="/my/renew<?= htmlspecialchars($previewQuery) ?>" style="text-decoration:none;">
                <div>
                    <div class="subscriber-row-label">طلب تجديد</div>
                    <div class="subscriber-muted">إرسال طلب تجديد أو التواصل واتساب</div>
                </div>
                <div class="subscriber-row-value">🔄</div>
            </a>

            <a class="subscriber-row" href="/announcements<?= htmlspecialchars($previewQuery) ?>" style="text-decoration:none;">
                <div>
                    <div class="subscriber-row-label">الإعلانات</div>
                    <div class="subscriber-muted">تنبيهات الشبكة والصيانة</div>
                </div>
                <div class="subscriber-row-value">📢</div>
            </a>

            <a class="subscriber-row" href="/support<?= htmlspecialchars($previewQuery) ?>" style="text-decoration:none;">
                <div>
                    <div class="subscriber-row-label">الدعم</div>
                    <div class="subscriber-muted">تواصل مع إدارة GreenNet</div>
                </div>
                <div class="subscriber-row-value">☎️</div>
            </a>

        </div>
    </section>

    <section class="subscriber-card">
        <div class="subscriber-actions">
            <a class="subscriber-btn primary" href="/my/renew<?= htmlspecialchars($previewQuery) ?>">طلب تجديد</a>
            <a class="subscriber-btn" href="/my/usage<?= htmlspecialchars($previewQuery) ?>">الاستهلاك</a>
            <a class="subscriber-btn" href="/logout">تسجيل خروج</a>
        </div>
    </section>

</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>