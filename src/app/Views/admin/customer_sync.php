<?php
    $allUsers = $summary['all_users'] ?? [];
    $missingUsers = $summary['missing_users'] ?? [];
    $existingUsers = $summary['existing_users'] ?? [];

    $importResult = $import_result ?? null;

    if (isset($_SESSION['sync_import_result'])) {
        unset($_SESSION['sync_import_result']);
    }
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">🔄</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>مزامنة MikroTik مع CRM</p>

            <div class="status-pill">
                <span class="dot"></span>
                Read Only + Local Import
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تقرأ مستخدمي MikroTik و User Manager، ثم تسمح بإضافتهم إلى CRM المحلي فقط.
            لا يتم تعديل أي شيء على MikroTik.
        </div>

        <?php if (is_array($importResult)): ?>
            <div class="notice" style="background:#dcfce7;color:#166534;">
                نتيجة الاستيراد:
                <br>
                تمت الإضافة: <?= htmlspecialchars((string) ($importResult['imported'] ?? 0)) ?>
                <br>
                تم التجاهل لأنه موجود مسبقاً: <?= htmlspecialchars((string) ($importResult['skipped'] ?? 0)) ?>
                <br>
                أخطاء: <?= htmlspecialchars((string) ($importResult['errors'] ?? 0)) ?>
            </div>
        <?php endif; ?>

        <div class="grid">

            <div class="stat">
                <div class="label">إجمالي مستخدمي MikroTik</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['total_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">غير موجودين في CRM</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['missing_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">موجودين في CRM</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['existing_count'] ?? 0)) ?></div>
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
            <div class="label">المستخدمون غير الموجودين في CRM</div>

            <?php if (count($missingUsers) === 0): ?>

                <div class="notice" style="margin-top:12px;">
                    ممتاز، كل مستخدمي MikroTik الموجودين حالياً مضافون إلى CRM.
                </div>

            <?php else: ?>

                <form method="post" action="/admin/customers/sync/import-selected">

                    <div style="font-size:13px; line-height:1.9; margin-top:10px;">

                        <?php foreach ($missingUsers as $user): ?>
                            <div style="background:#fef2f2; border-radius:14px; padding:12px; margin-bottom:10px;">

                                <label style="display:flex; gap:8px; align-items:center;">
                                    <input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($user['username']) ?>" checked>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                </label>

                                النوع:
                                <?= htmlspecialchars($user['types'] ?? '-') ?>
                                <br>

                                المصدر:
                                <?= htmlspecialchars($user['sources'] ?? '-') ?>
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

                                <a class="btn btn-outline" href="/admin/search?q=<?= urlencode($user['username']) ?>">
                                    فتح في البحث
                                </a>

                            </div>
                        <?php endforeach; ?>

                    </div>

                    <button class="btn btn-primary" type="submit">
                        إضافة المحددين إلى CRM
                    </button>

                </form>

            <?php endif; ?>
        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">المستخدمون الموجودون مسبقاً في CRM</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                <?php if (count($existingUsers) === 0): ?>
                    لا يوجد مستخدمون متطابقون حالياً.
                <?php else: ?>
                    <?php foreach ($existingUsers as $user): ?>
                        <div style="background:#f0fdf4; border-radius:14px; padding:12px; margin-bottom:10px;">

                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                            —
                            <?= htmlspecialchars($user['payment_label'] ?? '-') ?>
                            <br>

                            الاسم:
                            <?= htmlspecialchars(($user['display_name'] ?? '') !== '' ? $user['display_name'] : '-') ?>
                            <br>

                            الهاتف:
                            <?= htmlspecialchars(($user['phone'] ?? '') !== '' ? $user['phone'] : '-') ?>
                            <br>

                            المصدر:
                            <?= htmlspecialchars($user['sources'] ?? '-') ?>
                            <br>

                            Profile:
                            <?= htmlspecialchars($user['profile'] ?? '-') ?>

                            <a class="btn btn-primary" href="/admin/customers/profile?username=<?= urlencode($user['username']) ?>">
                                ملف المشترك الكامل
                            </a>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <a class="btn btn-primary" href="/admin/customers/sync">
            تحديث المزامنة
        </a>

        <a class="btn btn-outline" href="/admin/search">
            🔎 البحث عن مشترك
        </a>

        <a class="btn btn-outline" href="/admin/customers">
            إدارة الزبائن
        </a>

        <a class="btn btn-outline" href="/admin/routeros/discovery">
            RouterOS Discovery
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet MikroTik CRM Sync
        </div>

    </div>
</div>