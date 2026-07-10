<?php
    $widgets = is_array($widgets ?? null) ? $widgets : [];
    $safety = is_array($widgets['safety'] ?? null) ? $widgets['safety'] : [];
    $backup = is_array($widgets['backup'] ?? null) ? $widgets['backup'] : [];
    $latestBackup = is_array($backup['latest'] ?? null) ? $backup['latest'] : null;
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Dashboard Widgets</h1>
        <p class="admin-page-description">
            لوحة مؤشرات مختصرة قبل الدخول إلى مرحلة الربط الفعلي مع MikroTik.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/health">Health</a>
        <a class="admin-mini-btn" href="/admin/readiness">Readiness</a>
        <a class="admin-mini-btn" href="/admin/write-safety">Write Safety</a>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-label">الزبائن</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($widgets['customers_total'] ?? 0)) ?></div>
        <div class="admin-stat-note">Customers</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">بدون PIN</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($widgets['customers_without_pin'] ?? 0)) ?></div>
        <div class="admin-stat-note">يحتاجون كلمة مرور</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">طلبات تجديد جديدة</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($widgets['renewal_pending'] ?? 0)) ?></div>
        <div class="admin-stat-note">Pending Renewal</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">إشعارات غير مقروءة</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($widgets['notifications_unread'] ?? 0)) ?></div>
        <div class="admin-stat-note">Unread</div>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-label">إيراد اليوم</div>
        <div class="admin-stat-value"><?= htmlspecialchars(number_format((float) ($widgets['payments_today'] ?? 0), 2)) ?></div>
        <div class="admin-stat-note">Payments Today</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">إيراد الشهر</div>
        <div class="admin-stat-value"><?= htmlspecialchars(number_format((float) ($widgets['payments_month'] ?? 0), 2)) ?></div>
        <div class="admin-stat-note">Payments Month</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">الباقات</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($widgets['packages_total'] ?? 0)) ?></div>
        <div class="admin-stat-note">Packages</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Queue Pending</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($widgets['queue_pending'] ?? 0)) ?></div>
        <div class="admin-stat-note">جاهز للمستقبل</div>
    </div>
</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">Write Safety Summary</h2>

    <div class="admin-checklist">
        <?php foreach ($safety as $key => $value): ?>
            <div class="admin-check-item">
                <div class="admin-check-icon <?= $value === 'true' ? '' : 'future' ?>">
                    <?= $value === 'true' ? '✓' : '!' ?>
                </div>

                <div>
                    <strong dir="ltr"><?= htmlspecialchars((string) $key) ?></strong>
                    <br>
                    <span class="admin-code"><?= htmlspecialchars((string) $value) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">Backup Summary</h2>

    <div class="admin-checklist">
        <div class="admin-check-item">
            <div class="admin-check-icon"><?= ((int) ($backup['count'] ?? 0) > 0) ? '✓' : '!' ?></div>
            <div>
                <strong>عدد النسخ الاحتياطية</strong>
                <br>
                <?= htmlspecialchars((string) ($backup['count'] ?? 0)) ?>
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">💾</div>
            <div>
                <strong>آخر نسخة</strong>
                <br>
                <?php if ($latestBackup): ?>
                    <?= htmlspecialchars((string) ($latestBackup['name'] ?? '')) ?>
                    <br>
                    <span class="admin-table-subtitle">
                        <?= htmlspecialchars(date('Y-m-d H:i:s', (int) ($latestBackup['time'] ?? time()))) ?>
                    </span>
                <?php else: ?>
                    لا توجد نسخة بعد.
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>