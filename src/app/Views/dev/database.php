<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">G</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>فحص قاعدة البيانات</p>

            <div class="status-pill">
                <span class="dot"></span>
                Models Ready
            </div>
        </div>

        <div class="notice">
            إذا ظهرت هذه الصفحة بدون أخطاء، فهذا يعني أن طبقة Models تعمل بنجاح.
        </div>

        <div class="stat">
            <div class="label">عدد حسابات المدير</div>
            <div class="value"><?= htmlspecialchars((string) $admin_count) ?></div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">الإعدادات</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                <?php foreach ($settings as $key => $value): ?>
                    <?= htmlspecialchars($key) ?> = <?= htmlspecialchars((string) $value) ?><br>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">QoS Profiles</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                <?php foreach ($qos_profiles as $profile): ?>
                    <?= htmlspecialchars($profile['name']) ?>
                    —
                    أولوية <?= htmlspecialchars((string) $profile['priority']) ?><br>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">الإعلانات</div>
            <div class="value" style="font-size:13px;">
                <?= count($announcements) ?> إعلان
            </div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">آخر الدفعات</div>
            <div class="value" style="font-size:13px;">
                <?= count($payments) ?> دفعة
            </div>
        </div>

        <a class="btn btn-primary" href="/">
            العودة للرئيسية
        </a>

        <div class="footer">
            GreenNet Database Layer
        </div>

    </div>
</div>