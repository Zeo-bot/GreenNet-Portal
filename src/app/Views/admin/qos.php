<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">⚙️</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>إدارة Smart QoS</p>

            <div class="status-pill">
                <span class="dot"></span>
                Profiles: <?= htmlspecialchars((string) count($qos_profiles)) ?>
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تحضيرية لإدارة أولويات الشبكة.
            حالياً يتم حفظ الإعدادات داخل GreenNet فقط، ولا يتم تطبيقها على MikroTik بعد.
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">عدد البروفايلات</div>
                <div class="value"><?= htmlspecialchars((string) count($qos_profiles)) ?></div>
            </div>

            <div class="stat">
                <div class="label">البروفايلات الفعالة</div>
                <div class="value"><?= htmlspecialchars((string) ($active_count ?? 0)) ?></div>
            </div>

        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">شرح الأولويات</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                الرقم الأصغر يعني أولوية أعلى.<br>
                1 = أعلى أولوية<br>
                8 = أقل أولوية<br>
                لاحقاً سيتم تحويل هذه القيم إلى Queue / Mangle على MikroTik.
            </div>
        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">قائمة QoS Profiles</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                <?php if (count($qos_profiles) === 0): ?>
                    لا توجد بروفايلات QoS حالياً.
                <?php else: ?>
                    <?php foreach ($qos_profiles as $profile): ?>
                        <div style="border-bottom:1px solid #e5e7eb; padding:12px 0;">

                            <strong><?= htmlspecialchars($profile['name']) ?></strong>
                            <br>

                            الوضع:
                            <?= htmlspecialchars($profile['mode']) ?>
                            <br>

                            الأولوية:
                            <?= htmlspecialchars((string) $profile['priority']) ?>
                            <br>

                            الحالة:
                            <?= ((int) $profile['is_active'] === 1) ? 'فعال' : 'غير فعال' ?>
                            <br>

                            الوصف:
                            <?= htmlspecialchars($profile['description'] ?: '-') ?>

                            <a class="btn btn-primary" href="/admin/qos/edit?id=<?= urlencode((string) $profile['id']) ?>">
                                تعديل البروفايل
                            </a>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Smart QoS
        </div>

    </div>
</div>