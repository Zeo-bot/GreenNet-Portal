<?php
    $isConnected = (bool) ($status['ok'] ?? false);
    $identity = $status['identity'] ?? [];
    $resource = $status['resource'] ?? [];
    $clock = $status['clock'] ?? [];
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">M</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>اختبار MikroTik API</p>

            <div class="status-pill">
                <span class="dot"></span>
                <?= $isConnected ? 'متصل' : 'غير متصل' ?>
            </div>
        </div>

        <?php if (!$isConnected): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                فشل الاتصال مع MikroTik API.
                <br>
                <?= htmlspecialchars($status['error'] ?? 'خطأ غير معروف') ?>
            </div>
        <?php else: ?>
            <div class="notice">
                تم الاتصال مع MikroTik API بنجاح. القراءة فقط، لم يتم تعديل أي إعداد.
            </div>
        <?php endif; ?>

        <div class="stat">
            <div class="label">إعدادات الاتصال</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                Host: <?= htmlspecialchars($host ?? '-') ?><br>
                Port: <?= htmlspecialchars((string) ($port ?? '-')) ?><br>
                Username: <?= htmlspecialchars($username ?? '-') ?>
            </div>
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">اسم الراوتر</div>
                <div class="value" style="font-size:15px;">
                    <?= htmlspecialchars($identity['name'] ?? '-') ?>
                </div>
            </div>

            <div class="stat">
                <div class="label">RouterOS</div>
                <div class="value" style="font-size:15px;">
                    <?= htmlspecialchars($resource['version'] ?? '-') ?>
                </div>
            </div>

            <div class="stat">
                <div class="label">Uptime</div>
                <div class="value" style="font-size:15px;">
                    <?= htmlspecialchars($resource['uptime'] ?? '-') ?>
                </div>
            </div>

            <div class="stat">
                <div class="label">CPU Load</div>
                <div class="value" style="font-size:15px;">
                    <?= htmlspecialchars($resource['cpu-load'] ?? '-') ?>%
                </div>
            </div>

            <div class="stat">
                <div class="label">Hotspot Active</div>
                <div class="value">
                    <?= htmlspecialchars((string) ($status['hotspot_active_count'] ?? 0)) ?>
                </div>
            </div>

            <div class="stat">
                <div class="label">PPP Active</div>
                <div class="value">
                    <?= htmlspecialchars((string) ($status['ppp_active_count'] ?? 0)) ?>
                </div>
            </div>

        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">Clock</div>
            <div class="value" style="font-size:13px; line-height:1.8;">
                Date: <?= htmlspecialchars($clock['date'] ?? '-') ?><br>
                Time: <?= htmlspecialchars($clock['time'] ?? '-') ?><br>
                Time Zone: <?= htmlspecialchars($clock['time-zone-name'] ?? '-') ?>
            </div>
        </div>

        <a class="btn btn-primary" href="/admin/routeros/profiles">
            📦 بروفايلات MikroTik والباقات
        </a>

        <a class="btn btn-primary" href="/admin/routeros/users">
            📚 كل مستخدمي MikroTik
        </a>

        <a class="btn btn-primary" href="/admin/routeros/discovery">
            🔍 RouterOS Data Discovery
        </a>

        <a class="btn btn-primary" href="/admin/routeros/active-users">
            👥 عرض المتصلين الآن
        </a>

        <a class="btn btn-outline" href="/admin/routeros">
            إعادة الاختبار
        </a>

        <a class="btn btn-outline" href="/admin/system">
            حالة النظام
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet RouterOS API Read-Only Test
        </div>

    </div>
</div>