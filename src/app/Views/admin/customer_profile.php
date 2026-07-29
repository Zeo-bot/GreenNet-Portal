<?php
$customer = is_array($customer ?? null) ? $customer : [];
$package = is_array($package ?? null) ? $package : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$payments = is_array($payments ?? null) ? $payments : [];
$renewalRequests = is_array($renewal_requests ?? null) ? $renewal_requests : [];
$assignedRouter = is_array($assigned_router ?? null) ? $assigned_router : [];
$nativeRecordState = is_array($native_record_state ?? null) ? $native_record_state : [];
$migrationTargets = is_array($migration_targets ?? null) ? $migration_targets : [];
$latestMigration = is_array($latest_migration ?? null) ? $latest_migration : [];

$username = (string) ($username ?? $customer['username'] ?? '');
$u = urlencode($username);
$packageId = (int) ($package['id'] ?? $dashboard['package_id'] ?? 0);
$displayName = trim((string) ($customer['display_name'] ?? $dashboard['customer_display_name'] ?? ''));
$displayName = $displayName !== '' ? $displayName : $username;
$subscriptionStatus = (string) ($dashboard['subscription_status'] ?? 'none');
$routerFound = !empty($dashboard['routeros_found']);
$routerDisabled = (string) ($dashboard['disabled'] ?? $dashboard['routeros_disabled'] ?? '') === 'true';
$packageFound = $packageId > 0;
$serviceBackend = (string) ($customer['service_backend'] ?? 'user-manager');
$serviceStatus = strtolower((string) ($customer['service_status'] ?? 'active'));
$lifecycle = is_array($lifecycle ?? null) ? $lifecycle : [];
$isNativeBackend = in_array($serviceBackend, ['native-hotspot', 'native-pppoe'], true);
$activeSessions = (int) ($dashboard['greennet_baseline_usage']['monitor_active_sessions'] ?? 0);
$quotaExhausted = (string) ($lifecycle['state'] ?? '') === 'quota_exhausted'
    || ((int) ($dashboard['used_percent'] ?? 0) >= 100 && (float) ($package['quota_gb'] ?? 0) > 0);

$statusLabel = match (true) {
    in_array($serviceStatus, ['suspended', 'disabled'], true) => 'موقوف',
    $quotaExhausted => 'استهلك الباقة',
    $subscriptionStatus === 'active' => 'نشط',
    $subscriptionStatus === 'expired' => 'منتهي',
    $packageFound => 'بانتظار التجديد',
    default => 'غير مفعّل',
};
$statusClass = $subscriptionStatus === 'active' && !$quotaExhausted && !in_array($serviceStatus, ['suspended', 'disabled'], true)
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
            <a class="admin-mini-btn" href="/dashboard?username=<?= htmlspecialchars($u) ?>">معاينة تطبيق المشترك</a>
        <?php endif; ?>
    </div>
</div>

<?php if (($error ?? '') !== ''): ?>
    <div class="notice" style="background:#fee2e2;color:#991b1b;"><?= htmlspecialchars((string) $error) ?></div>
<?php elseif ($username !== ''): ?>
    <?php if (in_array((string) ($latestMigration['status'] ?? ''), ['target_failed', 'cutover_failed', 'cleanup_pending'], true)): ?>
        <div class="notice" style="background:#fff7ed;color:#9a3412">
            عملية نقل تحتاج متابعة: <?= htmlspecialchars((string) ($latestMigration['failure_reason'] ?? 'تنظيف الحساب المصدر ما زال معلقاً.')) ?>
            <a href="/admin/customers/migrate?username=<?= htmlspecialchars($u) ?>">فتح النقل</a>
        </div>
    <?php endif; ?>
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
                <div style="color:#4b5563">نظام الخدمة: <?= htmlspecialchars(match ($serviceBackend) { 'native-hotspot' => 'Hotspot', 'native-pppoe' => 'PPPoE', default => 'User Manager' }) ?></div>
                <?php if ($isNativeBackend): ?>
                    <div style="color:#4b5563">سجل RouterOS: <?= htmlspecialchars(match ($nativeRecordState['status'] ?? '') { 'found' => 'موجود', 'missing' => 'مفقود', default => 'الراوتر غير متاح' }) ?></div>
                <?php endif; ?>
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
            <div class="admin-stat-label">الباقة</div>
            <div class="admin-stat-value" style="font-size:18px"><?= htmlspecialchars((string) ($package['name'] ?? $dashboard['package_name'] ?? 'لم تُحدد')) ?></div>
            <div class="admin-stat-note">السرعة: <?= htmlspecialchars((string) ($dashboard['speed'] ?? $package['rate_limit'] ?? '-')) ?></div>
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
            <div class="admin-stat-note">الاستهلاك: <?= htmlspecialchars((string) ($dashboard['used'] ?? '-')) ?> · المتبقي: <?= htmlspecialchars((string) ($dashboard['remaining'] ?? '-')) ?></div>
        </div>
    </div>

    <section class="admin-section-card">
        <h2 class="admin-section-title">العمليات الأساسية</h2>
        <p class="admin-section-subtitle">يختار GreenNet الراوتر ونظام الخدمة تلقائياً من ملف المشترك.</p>
        <div class="admin-action-grid">
            <a class="admin-action-card" href="/admin/customers/renew?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">↻</div><div class="admin-action-title">تجديد الاشتراك</div>
                <div class="admin-action-desc">تسجيل الدفعة وبدء دورة الاشتراك الجديدة.</div>
            </a>
            <a class="admin-action-card" href="/admin/customers/package?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">▣</div><div class="admin-action-title">تغيير الباقة</div>
                <div class="admin-action-desc">اختيار باقة GreenNet المناسبة لهذا المشترك.</div>
            </a>
            <?php if ($packageFound): ?>
                <?php if ($isNativeBackend): ?>
                    <form method="post" action="/admin/customers/native-operation" onsubmit="return confirm('سيتم تطبيق باقة <?= htmlspecialchars((string) ($package['name'] ?? '')) ?> على حساب <?= htmlspecialchars($username) ?> في <?= htmlspecialchars((string) ($assignedRouter['name'] ?? 'الراوتر')) ?>. هل تريد المتابعة؟')">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>"><input type="hidden" name="action" value="package">
                        <button class="admin-action-card" type="submit"><span class="admin-action-icon">⇄</span><span class="admin-action-title">مزامنة مع الراوتر</span><span class="admin-action-desc">تطبيق الباقة الحالية عبر نظام الخدمة المحدد.</span></button>
                    </form>
                <?php else: ?>
                    <a class="admin-action-card" href="/admin/package-assign?username=<?= htmlspecialchars($u) ?>&amp;package_id=<?= $packageId ?>&amp;assign_mode=replace">
                        <div class="admin-action-icon">⇄</div><div class="admin-action-title">مزامنة مع الراوتر</div><div class="admin-action-desc">تطبيق الباقة الحالية في User Manager.</div>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">الحساب والراوتر</h2>
        <div class="admin-action-grid">
            <?php if ($packageFound && !$routerFound): ?>
                <a class="admin-action-card" href="<?= $isNativeBackend ? '/admin/native-subscriber?username=' . htmlspecialchars($u) . '&amp;action=create' : '/admin/user-manager-user-create?username=' . htmlspecialchars($u) . '&amp;package_id=' . $packageId ?>">
                    <div class="admin-action-icon">＋</div><div class="admin-action-title">إنشاء حساب الشبكة</div>
                    <div class="admin-action-desc">إنشاء الحساب على الراوتر وربطه بالباقة.</div>
                </a>
            <?php endif; ?>
            <?php if ($routerFound && $routerDisabled): ?>
                <form method="post" action="<?= $isNativeBackend ? '/admin/customers/native-operation' : '/admin/customers/router-account' ?>">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>"><input type="hidden" name="action" value="<?= $isNativeBackend ? 'enable' : 'um_enable_user' ?>">
                    <button class="admin-action-card" type="submit"><span class="admin-action-icon">▶</span><span class="admin-action-title">تفعيل الحساب</span><span class="admin-action-desc">إعادة تمكين الحساب على الراوتر.</span></button>
                </form>
            <?php elseif ($routerFound): ?>
                <form method="post" action="<?= $isNativeBackend ? '/admin/customers/native-operation' : '/admin/customers/router-account' ?>">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>"><input type="hidden" name="action" value="<?= $isNativeBackend ? 'disable' : 'um_disable_user' ?>">
                    <button class="admin-action-card" type="submit"><span class="admin-action-icon">Ⅱ</span><span class="admin-action-title">إيقاف الحساب</span><span class="admin-action-desc">إيقاف خدمة المشترك على الراوتر.</span></button>
                </form>
            <?php endif; ?>
            <?php if ($activeSessions > 0 || (string) ($dashboard['connection_status'] ?? '') === 'online'): ?>
                <a class="admin-action-card" href="/admin/user-disconnect?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">⏏</div><div class="admin-action-title">فصل الجلسات</div>
                <div class="admin-action-desc">فصل الجلسات النشطة فقط باستخدام معرّفاتها الحالية.</div>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">بيانات المشترك</h2>
        <div class="admin-action-grid">
            <a class="admin-action-card" href="/admin/customers/edit?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">✎</div><div class="admin-action-title">تعديل البيانات</div>
                <div class="admin-action-desc">الاسم والهاتف والراوتر ونظام الخدمة.</div>
            </a>
            <a class="admin-action-card" href="<?= $isNativeBackend ? '/admin/native-subscriber?username=' . htmlspecialchars($u) . '&amp;action=password' : '/admin/user-manager-password?username=' . htmlspecialchars($u) ?>">
                <div class="admin-action-icon">●</div><div class="admin-action-title">تغيير كلمة المرور</div>
                <div class="admin-action-desc">تعيين كلمة مرور جديدة بواسطة المدير.</div>
            </a>
            <a class="admin-action-card" href="/admin/customers/timeline?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">◷</div><div class="admin-action-title">السجل</div>
                <div class="admin-action-desc">الدفعات والتجديدات والملاحظات التشغيلية.</div>
            </a>
            <?php if (count($migrationTargets) > 0): ?>
                <a class="admin-action-card" href="/admin/customers/migrate?username=<?= htmlspecialchars($u) ?>">
                    <div class="admin-action-icon">⇢</div><div class="admin-action-title">نقل إلى راوتر آخر</div>
                    <div class="admin-action-desc">إنشاء الحساب على الهدف أولاً مع الحفاظ على الاشتراك والتاريخ.</div>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($latestMigration !== []): ?>
        <section class="admin-section-card">
            <h2 class="admin-section-title">سجل النقل الأخير</h2>
            <p class="admin-section-subtitle">
                <?= htmlspecialchars((string) ($latestMigration['source_router_name'] ?? '-')) ?>
                ← <?= htmlspecialchars((string) ($latestMigration['target_router_name'] ?? '-')) ?>
                · <?= htmlspecialchars((string) ($latestMigration['status'] ?? '-')) ?>
                · بدأ <?= htmlspecialchars((string) ($latestMigration['started_at'] ?? '-')) ?>
            </p>
            <?php if ((string) ($latestMigration['status'] ?? '') === 'cleanup_pending'): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php foreach (['disable' => 'تعطيل الحساب القديم', 'disconnect' => 'فصل الاتصال القديم', 'delete' => 'حذف الحساب القديم'] as $action => $label): ?>
                        <form method="post" action="/admin/customers/migrate/cleanup" <?= $action === 'delete' ? 'onsubmit="return confirm(\'سيتم حذف الحساب القديم من الراوتر المصدر. هل تريد المتابعة؟\')"' : '' ?>>
                            <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                            <input type="hidden" name="migration_id" value="<?= (int) ($latestMigration['id'] ?? 0) ?>">
                            <input type="hidden" name="cleanup_action" value="<?= htmlspecialchars($action) ?>">
                            <button class="admin-mini-btn" type="submit"><?= htmlspecialchars($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="admin-section-card">
        <h2 class="admin-section-title">إجراءات متقدمة</h2>
        <p class="admin-section-subtitle">تتطلب إجراءات الحذف تأكيداً واضحاً قبل التنفيذ.</p>
        <div class="admin-action-grid">
            <?php if ($routerFound): ?>
                <?php if ($isNativeBackend): ?>
                    <form method="post" action="/admin/customers/native-operation" onsubmit="return confirm('سيتم حذف حساب <?= htmlspecialchars($username) ?> من <?= htmlspecialchars((string) ($assignedRouter['name'] ?? 'الراوتر')) ?>. هل تريد المتابعة؟')">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>"><input type="hidden" name="action" value="delete">
                        <button class="admin-action-card" type="submit"><span class="admin-action-icon">×</span><span class="admin-action-title">حذف الحساب من الراوتر</span><span class="admin-action-desc">حذف الحساب الحالي بعد هذا التأكيد.</span></button>
                    </form>
                <?php else: ?>
                    <a class="admin-action-card" href="/admin/user-manager-user-delete?username=<?= htmlspecialchars($u) ?>">
                        <div class="admin-action-icon">×</div><div class="admin-action-title">حذف الحساب من الراوتر</div><div class="admin-action-desc">تأكيد حذف حساب User Manager وارتباطاته.</div>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <a class="admin-action-card" href="/admin/customers/delete?username=<?= htmlspecialchars($u) ?>">
                <div class="admin-action-icon">⌫</div><div class="admin-action-title">حذف المشترك محلياً</div>
                <div class="admin-action-desc">يحذف سجل GreenNet فقط بعد صفحة تأكيد.</div>
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
