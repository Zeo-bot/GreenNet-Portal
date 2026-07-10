<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">🧩</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>حالة النظام</p>

            <div class="status-pill">
                <span class="dot"></span>
                System Ready
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تساعدنا نتأكد أن إعدادات المشروع مرتبة قبل ربط MikroTik API.
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">بيئة التشغيل</div>
                <div class="value"><?= htmlspecialchars($app_env ?? '-') ?></div>
            </div>

            <div class="stat">
                <div class="label">الإصدار</div>
                <div class="value"><?= htmlspecialchars($app_version ?? '-') ?></div>
            </div>

            <div class="stat">
                <div class="label">Access Mode</div>
                <div class="value"><?= htmlspecialchars($access_mode ?? '-') ?></div>
            </div>

            <div class="stat">
                <div class="label">Auth Backend</div>
                <div class="value"><?= htmlspecialchars($auth_backend ?? '-') ?></div>
            </div>

        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">MikroTik API</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                Host: <?= htmlspecialchars($mikrotik_host ?? '-') ?><br>
                Port: <?= htmlspecialchars((string) ($mikrotik_api_port ?? '-')) ?><br>
                Username: <?= htmlspecialchars($mikrotik_username ?? '-') ?><br>
                الحالة: لم يتم الربط بعد
            </div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">Smart QoS</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                Enabled: <?= htmlspecialchars($qos_enabled ?? 'false') ?><br>
                Mode: <?= htmlspecialchars($qos_mode ?? '-') ?><br>
                Backend: <?= htmlspecialchars($qos_backend ?? '-') ?><br>
                Profiles: <?= htmlspecialchars((string) ($qos_profiles_count ?? 0)) ?>
            </div>
        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">SQLite Database</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                Path:<br>
                <?= htmlspecialchars($database_path ?? '-') ?>
            </div>
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">الزبائن</div>
                <div class="value"><?= htmlspecialchars((string) ($customers_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">الدفعات</div>
                <div class="value" style="font-size:15px;">
                    <?= htmlspecialchars((string) ($payments_total ?? 0)) ?> SYP
                </div>
            </div>

            <div class="stat">
                <div class="label">الإعلانات</div>
                <div class="value"><?= htmlspecialchars((string) ($announcements_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">QoS</div>
                <div class="value"><?= htmlspecialchars((string) ($qos_profiles_count ?? 0)) ?></div>
            </div>

        </div>

        <a class="btn btn-primary" href="/dashboard">
            معاينة لوحة المشترك
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet System Status
        </div>

    </div>
</div>