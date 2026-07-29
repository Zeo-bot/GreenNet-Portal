<?php
$ops = is_array($operations ?? null) ? $operations : [];
$subscriptions = $ops['subscription'] ?? [];
$renewals = $ops['renewals'] ?? [];
$sessions = $ops['sessions'] ?? [];
$h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$customerUrl = static fn (array $query = []): string => '/admin/customers/table' . ($query ? '?' . http_build_query($query) : '');
$statusLabel = static fn (string $status): string => match (strtolower($status)) {
    'available' => 'متاح',
    'unreachable' => 'غير متاح',
    default => 'غير معروف',
};
$backendLabel = static fn (string $backend): string => match ($backend) {
    'native-hotspot' => 'Hotspot أصلي',
    'native-pppoe' => 'PPPoE أصلي',
    default => 'User Manager',
};
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">لوحة العمليات</h1>
        <p class="admin-page-description">نظرة يومية على المشتركين والموجّهات والتجديدات والحالات التي تحتاج متابعة.</p>
    </div>
    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/renewal-requests">طلبات التجديد</a>
        <a class="admin-mini-btn" href="/admin/routers">الموجّهات</a>
        <a class="admin-mini-btn danger" href="/admin/logout">خروج</a>
    </div>
</div>

<form method="get" action="/admin/customers/table" style="margin-bottom:18px">
    <div class="form-group">
        <label>بحث سريع عن مشترك</label>
        <input type="text" name="q" placeholder="اسم المستخدم، الاسم، الهاتف أو الملاحظة">
    </div>
    <button class="btn btn-primary" type="submit">بحث</button>
</form>

<div class="admin-stats-grid">
    <a class="admin-stat-card" href="<?= $h($customerUrl()) ?>">
        <div class="admin-stat-label">إجمالي المشتركين</div>
        <div class="admin-stat-value"><?= (int) ($subscriptions['total_count'] ?? 0) ?></div>
        <div class="admin-stat-note">كل المشتركين المحليين</div>
    </a>
    <a class="admin-stat-card" href="<?= $h($customerUrl(['subscription_status' => 'active'])) ?>">
        <div class="admin-stat-label">فعال</div>
        <div class="admin-stat-value"><?= (int) ($subscriptions['active_count'] ?? 0) ?></div>
        <div class="admin-stat-note">اشتراك ساري</div>
    </a>
    <a class="admin-stat-card" href="<?= $h($customerUrl(['subscription_status' => 'expiring'])) ?>">
        <div class="admin-stat-label">قريب الانتهاء</div>
        <div class="admin-stat-value"><?= (int) ($subscriptions['soon_7_count'] ?? 0) ?></div>
        <div class="admin-stat-note">خلال سبعة أيام</div>
    </a>
    <a class="admin-stat-card" href="<?= $h($customerUrl(['subscription_status' => 'expired'])) ?>">
        <div class="admin-stat-label">منتهي</div>
        <div class="admin-stat-value"><?= (int) ($subscriptions['expired_count'] ?? 0) ?></div>
        <div class="admin-stat-note">يحتاج تجديداً</div>
    </a>
    <a class="admin-stat-card" href="<?= $h($customerUrl(['service_status' => 'suspended'])) ?>">
        <div class="admin-stat-label">موقوف</div>
        <div class="admin-stat-value"><?= (int) ($ops['suspended_count'] ?? 0) ?></div>
        <div class="admin-stat-note">خدمة معلّقة محلياً</div>
    </a>
    <a class="admin-stat-card" href="/admin/renewal-requests?status=pending">
        <div class="admin-stat-label">تجديد بانتظار الإجراء</div>
        <div class="admin-stat-value"><?= (int) ($renewals['pending'] ?? 0) ?></div>
        <div class="admin-stat-note">أولوية فريق التشغيل</div>
    </a>
</div>

<div class="admin-two-columns">
    <section class="admin-section-card">
        <div class="admin-page-header">
            <div>
                <h2 class="admin-section-title">حالة الموجّهات</h2>
                <p class="admin-section-subtitle">آخر حالة محفوظة؛ لا يتم الاتصال بالموجّهات عند فتح اللوحة.</p>
            </div>
            <a class="admin-mini-btn" href="/admin/routers">إدارة الموجّهات</a>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
            <span class="admin-badge">الإجمالي: <?= (int) ($ops['router_summary']['total'] ?? 0) ?></span>
            <span class="admin-badge admin-badge-success">المفعّل: <?= (int) ($ops['router_summary']['enabled'] ?? 0) ?></span>
            <span class="admin-badge admin-badge-success">المتاح: <?= (int) ($ops['router_summary']['available'] ?? 0) ?></span>
            <span class="admin-badge admin-badge-danger">غير المتاح: <?= (int) ($ops['router_summary']['unavailable'] ?? 0) ?></span>
        </div>
        <div class="admin-payment-list">
            <?php if (($ops['routers'] ?? []) === []): ?>
                <div class="admin-payment-item">لم تتم إضافة موجّهات بعد.</div>
            <?php endif; ?>
            <?php foreach (($ops['routers'] ?? []) as $router): ?>
                <div class="admin-payment-item">
                    <div>
                        <strong><?= $h($router['name'] ?? '-') ?><?= !empty($router['is_default']) ? ' · الافتراضي' : '' ?></strong>
                        <div style="color:#6b7280;font-size:12px" dir="ltr">
                            <?= $h($router['host'] ?? '-') ?> · RouterOS <?= $h($router['routeros_version'] ?: '—') ?>
                        </div>
                    </div>
                    <div style="text-align:left">
                        <span class="admin-badge <?= ($router['last_status'] ?? '') === 'available' ? 'admin-badge-success' : (($router['last_status'] ?? '') === 'unreachable' ? 'admin-badge-danger' : '') ?>">
                            <?= $h($statusLabel((string) ($router['last_status'] ?? 'unknown'))) ?>
                        </span>
                        <div style="font-size:12px;margin-top:5px"><?= (int) ($router['customer_count'] ?? 0) ?> مشترك</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">توزيع أنظمة الخدمة</h2>
        <p class="admin-section-subtitle">النظام الذي تُدار منه حسابات المشتركين.</p>
        <?php foreach (($ops['backend_counts'] ?? []) as $backend => $count): ?>
            <a class="admin-payment-item" href="<?= $h($customerUrl(['backend' => $backend])) ?>" style="margin-bottom:8px">
                <strong><?= $h($backendLabel((string) $backend)) ?></strong>
                <span class="admin-payment-amount"><?= (int) $count ?></span>
            </a>
        <?php endforeach; ?>

        <h2 class="admin-section-title" style="margin-top:22px">الجلسات النشطة</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <span class="admin-badge">Hotspot: غير متاح</span>
            <span class="admin-badge">PPPoE: غير متاح</span>
        </div>
        <p class="admin-section-subtitle" style="margin-top:10px"><?= $h($sessions['message'] ?? '') ?></p>
        <a class="admin-mini-btn" href="/admin/routeros/active-users">فحص الجلسات الآن</a>
    </section>
</div>

<div class="admin-two-columns">
    <section class="admin-section-card">
        <div class="admin-page-header">
            <div>
                <h2 class="admin-section-title">التجديدات والمدفوعات</h2>
                <p class="admin-section-subtitle">طلبات حديثة وآخر عمليات الدفع المسجلة.</p>
            </div>
            <a class="admin-mini-btn" href="/admin/renewal-requests">إدارة الطلبات</a>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
            <span class="admin-badge admin-badge-warning">معلّق: <?= (int) ($renewals['pending'] ?? 0) ?></span>
            <span class="admin-badge admin-badge-success">مكتمل: <?= (int) ($renewals['completed'] ?? 0) ?></span>
            <span class="admin-badge admin-badge-danger">مرفوض: <?= (int) ($renewals['rejected'] ?? 0) ?></span>
        </div>
        <div class="admin-payment-list">
            <?php foreach (($ops['recent_payments'] ?? []) as $payment): ?>
                <div class="admin-payment-item">
                    <div><strong><?= $h($payment['username'] ?? '-') ?></strong><div style="font-size:12px;color:#6b7280"><?= $h($payment['paid_at'] ?? '-') ?></div></div>
                    <span class="admin-payment-amount"><?= $h($payment['amount'] ?? 0) ?> <?= $h($payment['currency'] ?? 'SYP') ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (($ops['recent_payments'] ?? []) === []): ?><div class="admin-payment-item">لا توجد مدفوعات مسجلة.</div><?php endif; ?>
        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">تحتاج متابعة</h2>
        <p class="admin-section-subtitle">مشكلات محلية واضحة يمكن لفريق التشغيل معالجتها.</p>
        <?php
        $attention = [
            ['مشتركون بلا موجّه', $ops['unassigned_count'] ?? 0, $customerUrl(['router_id' => 'none'])],
            ['ربط الباقة بالنظام مفقود', $ops['mapping_missing_count'] ?? 0, '/admin/routers'],
            ['مزامنة معلّقة', $ops['sync_pending_count'] ?? 0, $customerUrl(['service_status' => 'pending'])],
            ['مزامنة فاشلة أو سجل مفقود', $ops['sync_failed_count'] ?? 0, $customerUrl(['service_status' => 'failed'])],
            ['موجّهات غير متاحة', $ops['router_summary']['unavailable'] ?? 0, '/admin/routers'],
        ];
        ?>
        <?php foreach ($attention as [$label, $count, $url]): ?>
            <a class="admin-payment-item" href="<?= $h($url) ?>" style="margin-bottom:8px">
                <strong><?= $h($label) ?></strong>
                <span class="admin-badge <?= (int) $count > 0 ? 'admin-badge-danger' : 'admin-badge-success' ?>"><?= (int) $count ?></span>
            </a>
        <?php endforeach; ?>
    </section>
</div>

<section class="admin-section-card">
    <div class="admin-page-header">
        <div>
            <h2 class="admin-section-title">اشتراكات قريبة الانتهاء</h2>
            <p class="admin-section-subtitle">الاشتراكات التي تنتهي خلال سبعة أيام.</p>
        </div>
        <a class="admin-mini-btn" href="<?= $h($customerUrl(['subscription_status' => 'expiring'])) ?>">عرض الكل</a>
    </div>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead><tr><th>المشترك</th><th>الموجّه</th><th>النظام</th><th>الباقة</th><th>الانتهاء</th><th>المتبقي</th><th></th></tr></thead>
            <tbody>
            <?php foreach (($ops['expiring'] ?? []) as $row): ?>
                <tr>
                    <td><?= $h($row['display_name'] ?: $row['username']) ?></td>
                    <td><?= $h($row['router_name'] ?: 'غير معيّن') ?></td>
                    <td><?= $h($backendLabel((string) ($row['service_backend'] ?? 'user-manager'))) ?></td>
                    <td><?= $h($row['package_name'] ?? '-') ?></td>
                    <td dir="ltr"><?= $h($row['expires_at'] ?? '-') ?></td>
                    <td><?= $h($row['days_left_label'] ?? '-') ?></td>
                    <td><a class="admin-mini-btn" href="/admin/customers/profile?username=<?= urlencode((string) ($row['username'] ?? '')) ?>">فتح</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($ops['expiring'] ?? []) === []): ?><tr><td colspan="7">لا توجد اشتراكات تنتهي خلال سبعة أيام.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
