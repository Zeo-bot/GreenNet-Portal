<link rel="stylesheet" href="/css/subscriber-app.css">

<?php
    $customer = is_array($customer ?? null) ? $customer : [];
    $package = is_array($package ?? null) ? $package : [];
    $latestPayment = is_array($latest_payment ?? null) ? $latest_payment : [];
    $payments = is_array($payments ?? null) ? $payments : [];
    $connection = is_array($connection ?? null) ? $connection : [];
    $flash = is_array($flash ?? null) ? $flash : [];

    $username = (string) ($username ?? ($customer['username'] ?? '-'));
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
                <div class="subscriber-hello">مرحباً</div>
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
                <span>الاستهلاك الحالي</span>
                <strong><?= htmlspecialchars((string) ($connection['bytes_total_human'] ?? '0 B')) ?></strong>
            </div>

            <div class="subscriber-hero-mini">
                <span>نوع الاتصال</span>
                <strong><?= htmlspecialchars((string) ($connection['source'] ?? '-')) ?></strong>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">تفاصيل الاستهلاك</h2>

        <?php if (!empty($connection['error'])): ?>
            <div class="subscriber-notice warning">
                تعذر قراءة الاتصال من MikroTik:
                <?= htmlspecialchars((string) $connection['error']) ?>
            </div>
        <?php endif; ?>

        <div class="subscriber-grid">
            <div class="subscriber-stat">
                <span>Download</span>
                <strong><?= htmlspecialchars((string) ($connection['bytes_out_human'] ?? '0 B')) ?></strong>
            </div>

            <div class="subscriber-stat">
                <span>Upload</span>
                <strong><?= htmlspecialchars((string) ($connection['bytes_in_human'] ?? '0 B')) ?></strong>
            </div>

            <div class="subscriber-stat">
                <span>IP</span>
                <strong><?= htmlspecialchars((string) (($connection['ip'] ?? '') !== '' ? $connection['ip'] : '-')) ?></strong>
            </div>

            <div class="subscriber-stat">
                <span>Uptime</span>
                <strong><?= htmlspecialchars((string) (($connection['uptime'] ?? '') !== '' ? $connection['uptime'] : '-')) ?></strong>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">الاشتراك الحالي</h2>

        <div class="subscriber-list">
            <div class="subscriber-row">
                <div class="subscriber-row-label">الباقة</div>
                <div class="subscriber-row-value">
                    <?= htmlspecialchars((string) (($package['name'] ?? '') !== '' ? $package['name'] : 'غير محددة')) ?>
                </div>
            </div>

            <div class="subscriber-row">
                <div class="subscriber-row-label">السرعة</div>
                <div class="subscriber-row-value">
                    <?= htmlspecialchars((string) (($package['rate_limit'] ?? '') !== '' ? $package['rate_limit'] : '-')) ?>
                </div>
            </div>

            <div class="subscriber-row">
                <div class="subscriber-row-label">تاريخ الانتهاء</div>
                <div class="subscriber-row-value">
                    <?= htmlspecialchars((string) (($latestPayment['expires_at'] ?? '') !== '' ? $latestPayment['expires_at'] : '-')) ?>
                </div>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">آخر الدفعات</h2>

        <?php if (count($payments) === 0): ?>
            <div class="subscriber-empty">
                لا توجد دفعات مسجلة بعد.
            </div>
        <?php else: ?>
            <div class="subscriber-list">
                <?php foreach ($payments as $payment): ?>
                    <div class="subscriber-row">
                        <div>
                            <div class="subscriber-row-label">
                                <?= htmlspecialchars((string) (($payment['package_name'] ?? '') !== '' ? $payment['package_name'] : 'دفعة')) ?>
                            </div>
                            <div class="subscriber-muted">
                                <?= htmlspecialchars((string) (($payment['created_at'] ?? '') !== '' ? $payment['created_at'] : ($payment['paid_at'] ?? '-'))) ?>
                            </div>
                        </div>

                        <div class="subscriber-row-value">
                            <?= htmlspecialchars((string) ($payment['amount'] ?? '0')) ?>
                            <?= htmlspecialchars((string) ($payment['currency'] ?? '')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="subscriber-card">
        <div class="subscriber-actions">
            <a class="subscriber-btn primary" href="/my/renew">طلب تجديد</a>
            <a class="subscriber-btn" href="/my/package">تفاصيل الباقة</a>
            <a class="subscriber-btn" href="/announcements">الإعلانات</a>
        </div>
    </section>

</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>