<link rel="stylesheet" href="/css/subscriber-app.css">

<?php
    $customer = is_array($customer ?? null) ? $customer : [];
    $package = is_array($package ?? null) ? $package : [];
    $latestPayment = is_array($latest_payment ?? null) ? $latest_payment : [];
    $pendingRequests = is_array($pending_requests ?? null) ? $pending_requests : [];
    $flash = is_array($flash ?? null) ? $flash : [];

    $username = (string) ($username ?? ($customer['username'] ?? '-'));
    $phone = (string) (($customer['phone'] ?? '') !== '' ? $customer['phone'] : '');

    $packageName = (string) (($package['name'] ?? '') !== '' ? $package['name'] : 'غير محددة');

    $whatsappNumber = preg_replace('/[^0-9]/', '', (string) ($support_whatsapp ?? $support_phone ?? ''));
    $whatsappText = 'مرحبا، أريد تجديد اشتراكي. المستخدم: ' . $username . '، الباقة: ' . $packageName;
    $whatsappUrl = $whatsappNumber !== ''
        ? 'https://wa.me/' . $whatsappNumber . '?text=' . urlencode($whatsappText)
        : '';
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
                <div class="subscriber-hello">طلب تجديد</div>
                <div class="subscriber-username"><?= htmlspecialchars($username) ?></div>
            </div>

            <div class="subscriber-status-pill">GreenNet</div>
        </div>

        <div class="subscriber-hero-grid">
            <div class="subscriber-hero-mini">
                <span>الباقة</span>
                <strong><?= htmlspecialchars($packageName) ?></strong>
            </div>

            <div class="subscriber-hero-mini">
                <span>الانتهاء</span>
                <strong><?= htmlspecialchars((string) (($latestPayment['expires_at'] ?? '') !== '' ? $latestPayment['expires_at'] : '-')) ?></strong>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">أرسل طلب تجديد</h2>

        <form class="subscriber-form" method="post" action="/my/renew">
            <div>
                <label>رقم الهاتف</label>
                <input
                    type="text"
                    name="phone"
                    value="<?= htmlspecialchars($phone) ?>"
                    placeholder="مثال: 0966393915"
                    dir="ltr"
                >
            </div>

            <div>
                <label>ملاحظات</label>
                <textarea name="message" placeholder="اكتب ملاحظة للمدير">أريد تجديد اشتراكي على نفس الباقة.</textarea>
            </div>

            <button class="subscriber-btn primary" type="submit">
                إرسال طلب التجديد
            </button>
        </form>

        <?php if ($whatsappUrl !== ''): ?>
            <div style="margin-top:12px;">
                <a class="subscriber-btn whatsapp" href="<?= htmlspecialchars($whatsappUrl) ?>" target="_blank">
                    تواصل واتساب مباشرة
                </a>
            </div>
        <?php endif; ?>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">طلباتك الأخيرة</h2>

        <?php if (count($pendingRequests) === 0): ?>
            <div class="subscriber-empty">
                لا توجد طلبات تجديد سابقة.
            </div>
        <?php else: ?>
            <div class="subscriber-list">
                <?php foreach ($pendingRequests as $request): ?>
                    <div class="subscriber-row">
                        <div>
                            <div class="subscriber-row-label">
                                <?= htmlspecialchars((string) (($request['package_name'] ?? '') !== '' ? $request['package_name'] : 'طلب تجديد')) ?>
                            </div>
                            <div class="subscriber-muted">
                                <?= htmlspecialchars((string) ($request['created_at'] ?? '-')) ?>
                            </div>
                        </div>

                        <div class="subscriber-row-value">
                            <?= htmlspecialchars((string) ($request['status'] ?? 'pending')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>