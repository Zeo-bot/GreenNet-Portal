<?php
    $syncResult = $sync_result ?? null;

    if (isset($_SESSION['packages_sync_result'])) {
        unset($_SESSION['packages_sync_result']);
    }
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">📦</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>إدارة الباقات</p>

            <div class="status-pill">
                <span class="dot"></span>
                Packages
            </div>
        </div>

        <div class="notice">
            هذه الباقات محلية داخل GreenNet. يمكن استيرادها من بروفايلات MikroTik، ثم تعديل السعر والحجم والصلاحية داخل GreenNet فقط.
        </div>

        <?php if (is_array($syncResult)): ?>
            <div class="notice" style="background:#dcfce7;color:#166534;">
                نتيجة الاستيراد من MikroTik:
                <br>
                بروفايلات مقروءة: <?= htmlspecialchars((string) ($syncResult['total_profiles'] ?? 0)) ?>
                <br>
                باقات جديدة: <?= htmlspecialchars((string) ($syncResult['created'] ?? 0)) ?>
                <br>
                باقات محدثة: <?= htmlspecialchars((string) ($syncResult['updated'] ?? 0)) ?>
                <br>
                تم تجاهلها: <?= htmlspecialchars((string) ($syncResult['skipped'] ?? 0)) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/admin/packages/sync-routeros">
            <button class="btn btn-primary" type="submit">
                استيراد البروفايلات من MikroTik
            </button>
        </form>

        <div class="grid">

            <div class="stat">
                <div class="label">إجمالي الباقات</div>
                <div class="value"><?= htmlspecialchars((string) ($packages_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">الباقات الفعالة</div>
                <div class="value"><?= htmlspecialchars((string) ($active_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">Hotspot</div>
                <div class="value"><?= htmlspecialchars((string) ($hotspot_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">PPP</div>
                <div class="value"><?= htmlspecialchars((string) ($ppp_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">Hybrid</div>
                <div class="value"><?= htmlspecialchars((string) ($hybrid_count ?? 0)) ?></div>
            </div>

        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">قائمة الباقات</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">

                <?php if (count($packages) === 0): ?>

                    لا توجد باقات حالياً. اضغط على زر استيراد البروفايلات من MikroTik.

                <?php else: ?>

                    <?php foreach ($packages as $package): ?>
                        <?php
                            $isActive = ((int) ($package['is_active'] ?? 0)) === 1;

                            $boxStyle = 'border-bottom:1px solid #e5e7eb; padding:12px 0;';

                            if ($isActive) {
                                $boxStyle .= ' background:#f0fdf4; border-radius:14px; padding:12px; margin-bottom:10px;';
                            } else {
                                $boxStyle .= ' background:#f3f4f6; border-radius:14px; padding:12px; margin-bottom:10px;';
                            }
                        ?>

                        <div style="<?= htmlspecialchars($boxStyle) ?>">

                            <strong><?= htmlspecialchars($package['name'] ?? '-') ?></strong>
                            —
                            <?= $isActive ? 'فعالة' : 'متوقفة' ?>
                            <br>

                            النوع:
                            <?= htmlspecialchars($package['access_type'] ?? '-') ?>
                            <br>

                            المصدر:
                            <?= htmlspecialchars($package['source_type'] ?? '-') ?>
                            <br>

                            بروفايل MikroTik:
                            <?= htmlspecialchars($package['source_profile'] ?? '-') ?>
                            <br>

                            Rate Limit:
                            <span style="direction:ltr; display:inline-block;">
                                <?= htmlspecialchars($package['rate_limit'] ?? '-') ?>
                            </span>
                            <br>

                            السعر:
                            <?= htmlspecialchars((string) ($package['price'] ?? 0)) ?>
                            <?= htmlspecialchars($package['currency'] ?? 'SYP') ?>
                            <br>

                            الصلاحية:
                            <?= htmlspecialchars((string) ($package['duration_days'] ?? 0)) ?>
                            يوم
                            <br>

                            الحجم:
                            <?= htmlspecialchars((string) ($package['quota_gb'] ?? 0)) ?>
                            GB
                            <br>

                            ملاحظات:
                            <?= htmlspecialchars(($package['notes'] ?? '') !== '' ? $package['notes'] : '-') ?>

                            <a class="btn btn-primary" href="/admin/packages/edit?id=<?= urlencode((string) $package['id']) ?>">
                                تعديل الباقة
                            </a>

                            <a class="btn btn-outline" href="/admin/search?q=<?= urlencode($package['source_profile'] ?? '') ?>">
                                بحث عن مستخدمي هذه الباقة
                            </a>

                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </div>

        <a class="btn btn-outline" href="/admin/routeros/profiles">
            بروفايلات MikroTik
        </a>

        <a class="btn btn-outline" href="/admin/customers">
            إدارة الزبائن
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Packages
        </div>

    </div>
</div>