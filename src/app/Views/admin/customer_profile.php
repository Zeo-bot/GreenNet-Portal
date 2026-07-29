<?php
$customer = is_array($customer ?? null) ? $customer : [];
$package = is_array($package ?? null) ? $package : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$payments = is_array($payments ?? null) ? $payments : [];
$renewalRequests = is_array($renewal_requests ?? null) ? $renewal_requests : [];
$assignedRouter = is_array($assigned_router ?? null) ? $assigned_router : [];

$username = (string) ($username ?? $customer['username'] ?? '');
$u = urlencode($username);
$packageId = (int) ($package['id'] ?? $dashboard['package_id'] ?? 0);
$displayName = trim((string) ($customer['display_name'] ?? $dashboard['customer_display_name'] ?? ''));
$displayName = $displayName !== '' ? $displayName : $username;
$subscriptionStatus = (string) ($dashboard['subscription_status'] ?? 'none');
$routerFound = !empty($dashboard['routeros_found']);
$routerDisabled = (string) ($dashboard['disabled'] ?? $dashboard['routeros_disabled'] ?? '') === 'true';
$packageFound = $packageId > 0;

$statusLabel = match ($subscriptionStatus) {
    'active' => 'اشتراك فعّال',
    'expired' => 'اشتراك منتهي',
    default => $packageFound ? 'بانتظار تسجيل التجديد' : 'بانتظار اختيار باقة',
};
$statusClass = $subscriptionStatus === 'active'
    ? 'admin-badge admin-badge-success'
    : ($subscriptionStatus === 'expired' ? 'admin-badge admin-badge-danger' : 'admin-badge admin-badge-warning');

$requestLabels = [
    'pending' => 'جديد',
    'in_review' => 'قيد المراجعة',
    'completed' => 'مكتمل',
    'rejected' => 'مرفوض',
];
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">إدارة المشترك</h1>
        <p class="admin-page-description">ملف تشغيلي واحد للبيانات المحلية، الاشتراك، وحالة الشبكة.</p>
    </div>
    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/customers/table">قائمة المشتركين</a>
        <?php if ($username !== ''): ?>
            <a class="admin-mini-btn" href="/dashboard?username=<?= htmlspecialchars($u) ?>">معاينة حساب المشترك</a>
        <?php endif; ?>
    </div>
</div>

<?php if (($error ?? '') !== ''): ?>
    <div class="notice" style="background:#fee2e2;color:#991b1b;"><?= htmlspecialchars((string) $error) ?></div>
<?php elseif ($username !== ''): ?>
    <section class="admin-section-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
            <div>
                <h2 class="admin-section-title"><?= htmlspecialchars($displayName) ?></h2>
                <div style="direction:ltr;text-align:right;color:#4b5563"><?= htmlspecialchars($username) ?></div>
                <div style="color:#6b7280"><?= htmlspecialchars((string) ($customer['phone'] ?? 'لا يوجد رقم مسجل')) ?></div>
                <div style="color:#166534;margin-top:5px">
                    الراوتر: <?= htmlspecialchars((string) ($assignedRouter['name'] ?? 'الافتراضي')) ?>
                    <?php if (!empty($assignedRouter['legacy_fallback'])): ?> (توافق الإعداد القديم)<?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <span class="<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                <span class="<?= $routerFound ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-warning' ?>">
                    <?= $routerFound ? ($routerDisabled ? 'الحساب معطّل على الشبكة' : 'موجود على الشبكة') : 'غير ظاهر على الشبكة' ?>
                </span>
            </div>
        </div>
    </section>

    <div class="admin-stats-grid">
        <div class="admin-stat-card">
            <div class="admin-stat-label">الباقة المحلية</div>
            <div class="admin-stat-value" style="font-size:18px"><?= htmlspecialchars((string) ($package['name'] ?? $dashboard['package_name'] ?? 'لم تُحدد')) ?></div>
            <div class="admin-stat-note"><?= $packageFound ? htmlspecialchars((string) ($package['duration_days'] ?? 0)) . ' يوم' : 'اختر باقة قبل التفعيل' ?></div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-label">بداية الاشتراك</div>
            <div class="admin-stat-value" style="font-size:15px"><?= htmlspecialchars((string) ($dashboard['subscription_starts_at'] ?? '-')) ?></div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-label">انتهاء الاشتراك</div>
            <div class="admin-stat-value" style="font-size:15px"><?= htmlspecialchars((string) ($dashboard['subscription_expires_at'] ?? '-')) ?></div>
            <div class="admin-stat-note"><?= htmlspecialchars((string) ($dashboard['subscription_days_left_label'] ?? '-')) ?></div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-label">حالة الاتصال</div>
            <div class="admin-stat-value" style="font-size:17px"><?= htmlspecialchars((string) ($dashboard['connection_label'] ?? $dashboard['connection_status'] ?? 'غير معروفة')) ?></div>
            <div class="admin-stat-note">الاستهلاك: <?= htmlspecialchars((string) ($dashboard['used'] ?? '-')) ?></div>
        </div>
    </div>

    <section class="admin-section-card">
        <h2 class="admin-section-title">الخطوات التشغيلية</h2>
        <p class="admin-section-subtitle">الإجراءات المحلية تُحفظ مباشرة. إجراءات الشبكة تفتح مسار المعاينة والتنفيذ المحمي للمشترك المحدد.</p>
        <div class="admin-action-grid">
            <a class="admin-action-card" href="/admin/customers/edit?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">✎</div><div class="admin-action-title">بيانات المشترك</div>
                <div class="admin-action-desc">تعديل الاسم والهاتف ونوع الوصول والملاحظات.</div>
            </a>
            <a class="admin-action-card" href="/admin/customers/package?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">▣</div><div class="admin-action-title">اختيار الباقة محلياً</div>
                <div class="admin-action-desc">ربط الباقة بسجل المشترك قبل التجديد أو التطبيق على الشبكة.</div>
            </a>
            <a class="admin-action-card" href="/admin/customers/renew?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">↻</div><div class="admin-action-title">تسجيل دفعة وتجديد</div>
                <div class="admin-action-desc">إنشاء مدة اشتراك جديدة وتاريخ انتهاء وتصفير دورة الاستهلاك المحلية.</div>
            </a>
            <?php if ($packageFound): ?>
                <a class="admin-action-card" href="/admin/package-assign?username=<?= htmlspecialchars($u) ?>&amp;package_id=<?= $packageId ?>&amp;assign_mode=replace">
                    <div class="admin-action-icon">⇄</div><div class="admin-action-title">تطبيق الباقة على الشبكة</div>
                    <div class="admin-action-desc">فتح المعاينة المحمية لتعيين أو استبدال ملف الباقة.</div>
                </a>
                <a class="admin-action-card" href="/admin/user-manager-user-create?username=<?= htmlspecialchars($u) ?>&amp;package_id=<?= $packageId ?>">
                    <div class="admin-action-icon">＋</div><div class="admin-action-title">إنشاء حساب الشبكة</div>
                    <div class="admin-action-desc">للمشترك الجديد غير الموجود في User Manager.</div>
                </a>
            <?php endif; ?>
            <a class="admin-action-card" href="/admin/mikrotik-dry-run?username=<?= htmlspecialchars($u) ?>&amp;action=um_disable_user">
                <div class="admin-action-icon">Ⅱ</div><div class="admin-action-title">تعليق الخدمة</div>
                <div class="admin-action-desc">معاينة تعطيل الحساب ثم تنفيذه عبر بوابة الكتابة المحمية.</div>
            </a>
            <a class="admin-action-card" href="/admin/mikrotik-dry-run?username=<?= htmlspecialchars($u) ?>&amp;action=um_enable_user">
                <div class="admin-action-icon">▶</div><div class="admin-action-title">إعادة التفعيل</div>
                <div class="admin-action-desc">معاينة إعادة تمكين الحساب بعد التجديد أو التسوية.</div>
            </a>
            <a class="admin-action-card" href="/admin/user-disconnect?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">⏏</div><div class="admin-action-title">فصل الجلسات</div>
                <div class="admin-action-desc">فصل الجلسات النشطة فقط باستخدام معرّفاتها الحالية.</div>
            </a>
            <a class="admin-action-card" href="/admin/user-manager-user-delete?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">×</div><div class="admin-action-title">حذف حساب الشبكة</div>
                <div class="admin-action-desc">معاينة حذف حساب User Manager وارتباطاته قبل التنفيذ.</div>
            </a>
            <a class="admin-action-card" href="/admin/customers/delete?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">⌫</div><div class="admin-action-title">حذف السجل المحلي</div>
                <div class="admin-action-desc">خطوة منفصلة بعد تنظيف حساب الشبكة، مع صفحة تأكيد.</div>
            </a>
        </div>
    </section>

    <div class="admin-two-columns">
        <section class="admin-section-card">
            <h2 class="admin-section-title">حالة المزامنة</h2>
            <div class="admin-checklist">
                <div class="admin-check-item"><div class="admin-check-icon <?= !empty($customer) ? 'done' : 'future' ?>">1</div><div><strong>السجل المحلي</strong><br><?= !empty($customer) ? 'موجود وجاهز للإدارة.' : 'غير موجود محلياً.' ?></div></div>
                <div class="admin-check-item"><div class="admin-check-icon <?= $packageFound ? 'done' : 'future' ?>">2</div><div><strong>الباقة</strong><br><?= $packageFound ? 'مرتبطة محلياً. طبّقها على الشبكة إذا كانت مختلفة.' : 'لم تُربط باقة بعد.' ?></div></div>
                <div class="admin-check-item"><div class="admin-check-icon <?= $subscriptionStatus === 'active' ? 'done' : 'future' ?>">3</div><div><strong>الدفع والمدة</strong><br><?= htmlspecialchars($statusLabel) ?>.</div></div>
                <div class="admin-check-item"><div class="admin-check-icon <?= $routerFound ? 'done' : 'future' ?>">4</div><div><strong>الشبكة</strong><br><?= $routerFound ? 'تم العثور على الحساب/الاتصال في القراءة الحالية.' : 'لم يُعثر عليه؛ قد يلزم إنشاء الحساب أو التحقق من الاتصال.' ?></div></div>
            </div>
        </section>

        <section class="admin-section-card">
            <h2 class="admin-section-title">طلبات التجديد</h2>
            <?php if ($renewalRequests === []): ?>
                <div class="notice">لا توجد طلبات تجديد لهذا المشترك.</div>
            <?php else: ?>
                <div class="admin-payment-list">
                    <?php foreach ($renewalRequests as $request): ?>
                        <?php $requestStatus = (string) ($request['status'] ?? 'pending'); ?>
                        <div class="admin-payment-item">
                            <div>
                                <div class="admin-payment-user">طلب #<?= (int) ($request['id'] ?? 0) ?> — <?= htmlspecialchars((string) ($requestLabels[$requestStatus] ?? $requestStatus)) ?></div>
                                <div style="color:#6b7280;font-size:12px"><?= htmlspecialchars((string) ($request['package_name'] ?? '')) ?> · <?= htmlspecialchars((string) ($request['created_at'] ?? '')) ?></div>
                            </div>
                            <?php if (!in_array($requestStatus, ['completed', 'rejected'], true) && $packageFound): ?>
                                <a class="admin-mini-btn" href="/admin/customers/renew?username=<?= htmlspecialchars($u) ?>&amp;request_id=<?= (int) ($request['id'] ?? 0) ?>">معالجة وتجديد</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div style="margin-top:12px"><a class="admin-mini-btn" href="/admin/renewal-requests?q=<?= htmlspecialchars($u) ?>">عرض كل الطلبات</a></div>
        </section>
    </div>

    <section class="admin-section-card">
        <h2 class="admin-section-title">سجل الدفعات</h2>
        <?php if ($payments === []): ?>
            <div class="notice">لا توجد دفعات مسجلة.</div>
        <?php else: ?>
            <div class="admin-payment-list">
                <?php foreach ($payments as $payment): ?>
                    <div class="admin-payment-item">
                        <div>
                            <div class="admin-payment-user"><?= htmlspecialchars((string) ($payment['package_name'] ?? 'دفعة اشتراك')) ?></div>
                            <div style="color:#6b7280;font-size:12px"><?= htmlspecialchars((string) ($payment['paid_at'] ?? $payment['created_at'] ?? '')) ?> · <?= htmlspecialchars((string) ($payment['starts_at'] ?? '-')) ?> ← <?= htmlspecialchars((string) ($payment['expires_at'] ?? '-')) ?></div>
                        </div>
                        <div class="admin-payment-amount"><?= htmlspecialchars((string) ($payment['amount'] ?? 0)) ?> <?= htmlspecialchars((string) ($payment['currency'] ?? 'SYP')) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
