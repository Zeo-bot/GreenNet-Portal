<link rel="stylesheet" href="/css/subscriber-app.css">

<?php
    $customer = is_array($customer ?? null) ? $customer : [];
    $package = is_array($package ?? null) ? $package : [];
    $latestPayment = is_array($latest_payment ?? null) ? $latest_payment : [];
    $connection = is_array($connection ?? null) ? $connection : [];

    $username = (string) ($username ?? ($customer['username'] ?? '-'));
?>

<div class="subscriber-app">

    <section class="subscriber-hero">
        <div class="subscriber-hero-top">
            <div>
                <div class="subscriber-hello">باقتك الحالية</div>
                <div class="subscriber-username">
                    <?= htmlspecialchars((string) (($package['name'] ?? '') !== '' ? $package['name'] : 'غير محددة')) ?>
                </div>
            </div>

            <?php if (!empty($connection['online'])): ?>
                <div class="subscriber-status-pill">متصل</div>
            <?php else: ?>
                <div class="subscriber-status-pill">غير متصل</div>
            <?php endif; ?>
        </div>

        <div class="subscriber-hero-grid">
            <div class="subscriber-hero-mini">
                <span>المستخدم</span>
                <strong><?= htmlspecialchars($username) ?></strong>
            </div>

            <div class="subscriber-hero-mini">
                <span>الانتهاء</span>
                <strong><?= htmlspecialchars((string) (($latestPayment['expires_at'] ?? '') !== '' ? $latestPayment['expires_at'] : '-')) ?></strong>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">تفاصيل الباقة</h2>

        <?php if (count($package) === 0): ?>
            <div class="subscriber-empty">
                لا توجد باقة مربوطة بحسابك حالياً. تواصل مع الإدارة لتحديث بياناتك.
            </div>
        <?php else: ?>
            <div class="subscriber-list">
                <div class="subscriber-row">
                    <div class="subscriber-row-label">اسم الباقة</div>
                    <div class="subscriber-row-value"><?= htmlspecialchars((string) ($package['name'] ?? '-')) ?></div>
                </div>

                <div class="subscriber-row">
                    <div class="subscriber-row-label">السرعة</div>
                    <div class="subscriber-row-value">
                        <?= htmlspecialchars((string) (($package['rate_limit'] ?? '') !== '' ? $package['rate_limit'] : '-')) ?>
                    </div>
                </div>

                <div class="subscriber-row">
                    <div class="subscriber-row-label">المدة</div>
                    <div class="subscriber-row-value">
                        <?= htmlspecialchars((string) ($package['duration_days'] ?? '0')) ?> يوم
                    </div>
                </div>

                <div class="subscriber-row">
                    <div class="subscriber-row-label">الرصيد</div>
                    <div class="subscriber-row-value">
                        <?= htmlspecialchars((string) ($package['quota_gb'] ?? '0')) ?> GB
                    </div>
                </div>

                <div class="subscriber-row">
                    <div class="subscriber-row-label">السعر</div>
                    <div class="subscriber-row-value">
                        <?= htmlspecialchars((string) ($package['price'] ?? '0')) ?>
                        <?= htmlspecialchars((string) ($package['currency'] ?? '')) ?>
                    </div>
                </div>

                <div class="subscriber-row">
                    <div class="subscriber-row-label">Profile</div>
                    <div class="subscriber-row-value">
                        <?= htmlspecialchars((string) (($package['source_profile'] ?? '') !== '' ? $package['source_profile'] : '-')) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">آخر تجديد</h2>

        <?php if (count($latestPayment) === 0): ?>
            <div class="subscriber-empty">
                لا يوجد تجديد مسجل بعد.
            </div>
        <?php else: ?>
            <div class="subscriber-list">
                <div class="subscriber-row">
                    <div class="subscriber-row-label">تاريخ البداية</div>
                    <div class="subscriber-row-value">
                        <?= htmlspecialchars((string) (($latestPayment['starts_at'] ?? '') !== '' ? $latestPayment['starts_at'] : '-')) ?>
                    </div>
                </div>

                <div class="subscriber-row">
                    <div class="subscriber-row-label">تاريخ الانتهاء</div>
                    <div class="subscriber-row-value">
                        <?= htmlspecialchars((string) (($latestPayment['expires_at'] ?? '') !== '' ? $latestPayment['expires_at'] : '-')) ?>
                    </div>
                </div>

                <div class="subscriber-row">
                    <div class="subscriber-row-label">المبلغ</div>
                    <div class="subscriber-row-value">
                        <?= htmlspecialchars((string) ($latestPayment['amount'] ?? '0')) ?>
                        <?= htmlspecialchars((string) ($latestPayment['currency'] ?? '')) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="subscriber-card">
        <div class="subscriber-actions">
            <a class="subscriber-btn primary" href="/my/renew">طلب تجديد</a>
            <a class="subscriber-btn" href="/my/usage">الاستهلاك</a>
        </div>
    </section>

</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>