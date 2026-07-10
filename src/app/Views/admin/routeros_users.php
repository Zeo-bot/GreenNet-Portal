<?php
    $allUsers = $all_users ?? [];
    $missingUsers = $missing_users ?? [];
    $existingUsers = $existing_users ?? [];
    $disabledUsers = $disabled_users ?? [];
    $enabledUsers = $enabled_users ?? [];

    $filter = $_GET['filter'] ?? 'all';

    $visibleUsers = match ($filter) {
        'missing' => $missingUsers,
        'existing' => $existingUsers,
        'disabled' => $disabledUsers,
        'enabled' => $enabledUsers,
        default => $allUsers,
    };

    $filterLabel = match ($filter) {
        'missing' => 'غير الموجودين في CRM',
        'existing' => 'الموجودين في CRM',
        'disabled' => 'المعطلين',
        'enabled' => 'الفعالين',
        default => 'كل المستخدمين',
    };
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">📚</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>مستخدمو MikroTik</p>

            <div class="status-pill">
                <span class="dot"></span>
                Directory Read Only
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تقرأ كل المستخدمين الموجودين داخل MikroTik و User Manager وتصنفهم مع CRM المحلي.
            لا يتم تعديل أي شيء على MikroTik.
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">إجمالي المستخدمين</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['total_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">فعالين</div>
                <div class="value"><?= htmlspecialchars((string) ($enabled_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">معطلين</div>
                <div class="value"><?= htmlspecialchars((string) ($disabled_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">موجودين في CRM</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['existing_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">غير موجودين في CRM</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['missing_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">Hotspot Users</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['hotspot_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">PPP Secrets</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['ppp_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">User Manager</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['user_manager_count'] ?? 0)) ?></div>
            </div>

        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">الفلاتر</div>

            <a class="btn btn-outline" href="/admin/routeros/users?filter=all">
                كل المستخدمين
            </a>

            <a class="btn btn-outline" href="/admin/routeros/users?filter=missing">
                غير الموجودين في CRM
            </a>

            <a class="btn btn-outline" href="/admin/routeros/users?filter=existing">
                الموجودين في CRM
            </a>

            <a class="btn btn-outline" href="/admin/routeros/users?filter=enabled">
                الفعالين
            </a>

            <a class="btn btn-outline" href="/admin/routeros/users?filter=disabled">
                المعطلين
            </a>
        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">
                <?= htmlspecialchars($filterLabel) ?>
                —
                <?= htmlspecialchars((string) count($visibleUsers)) ?>
            </div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">

                <?php if (count($visibleUsers) === 0): ?>

                    لا توجد بيانات ضمن هذا التصنيف.

                <?php else: ?>

                    <?php foreach ($visibleUsers as $user): ?>
                        <?php
                            $crmFound = (bool) ($user['crm_found'] ?? false);
                            $disabled = strtolower((string) ($user['disabled'] ?? ''));
                            $isDisabled = str_contains($disabled, 'true') || $disabled === 'yes';

                            $boxStyle = 'border-bottom:1px solid #e5e7eb; padding:12px 0;';

                            if (!$crmFound) {
                                $boxStyle .= ' background:#fef2f2; border-radius:14px; padding:12px; margin-bottom:10px;';
                            }

                            if ($crmFound) {
                                $boxStyle .= ' background:#f0fdf4; border-radius:14px; padding:12px; margin-bottom:10px;';
                            }

                            if ($isDisabled) {
                                $boxStyle .= ' background:#f3f4f6; border-radius:14px; padding:12px; margin-bottom:10px;';
                            }
                        ?>

                        <div style="<?= htmlspecialchars($boxStyle) ?>">

                            <strong><?= htmlspecialchars($user['username'] ?? '-') ?></strong>
                            —
                            <?= htmlspecialchars($user['types'] ?? '-') ?>
                            <br>

                            المصدر:
                            <?= htmlspecialchars($user['sources'] ?? '-') ?>
                            <br>

                            حالة CRM:
                            <?= $crmFound ? 'موجود في CRM' : 'غير موجود في CRM' ?>
                            <br>

                            حالة MikroTik:
                            <?= $isDisabled ? 'معطل' : 'فعال' ?>
                            <br>

                            نوع الوصول المقترح:
                            <?= htmlspecialchars($user['access_type'] ?? '-') ?>
                            <br>

                            Profile:
                            <?= htmlspecialchars($user['profile'] ?? '-') ?>
                            <br>

                            Disabled:
                            <?= htmlspecialchars($user['disabled'] ?? '-') ?>
                            <br>

                            Service:
                            <?= htmlspecialchars($user['service'] ?? '-') ?>
                            <br>

                            MAC / Caller ID:
                            <?= htmlspecialchars($user['mac_or_caller'] ?? '-') ?>
                            <br>

                            Comment:
                            <?= htmlspecialchars($user['comment'] ?? '-') ?>
                            <br>

                            <?php if ($crmFound): ?>
                                الاسم:
                                <?= htmlspecialchars(($user['display_name'] ?? '') !== '' ? $user['display_name'] : '-') ?>
                                <br>

                                الهاتف:
                                <?= htmlspecialchars(($user['phone'] ?? '') !== '' ? $user['phone'] : '-') ?>
                                <br>

                                حالة الدفع:
                                <?= htmlspecialchars($user['payment_label'] ?? '-') ?>
                                <br>
                            <?php endif; ?>

                            <a class="btn btn-primary" href="/admin/search?q=<?= urlencode($user['username']) ?>">
                                فتح في البحث
                            </a>

                            <a class="btn btn-outline" href="/admin/customers/profile?username=<?= urlencode($user['username']) ?>">
                                ملف المشترك الكامل
                            </a>

                            <?php if (!$crmFound): ?>
                                <a class="btn btn-outline" href="/admin/customers/sync">
                                    إضافة عبر المزامنة
                                </a>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </div>

        <a class="btn btn-primary" href="/admin/customers/sync">
            🔄 مزامنة MikroTik مع CRM
        </a>

        <a class="btn btn-primary" href="/admin/routeros/users">
            تحديث القائمة
        </a>

        <a class="btn btn-outline" href="/admin/routeros/active-users">
            👥 المتصلون الآن
        </a>

        <a class="btn btn-outline" href="/admin/routeros/discovery">
            RouterOS Discovery
        </a>

        <a class="btn btn-outline" href="/admin/routeros">
            MikroTik API
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet RouterOS Users Directory
        </div>

    </div>
</div>