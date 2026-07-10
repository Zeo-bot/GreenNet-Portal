<?php
    $filter = $_GET['filter'] ?? 'all';

    $hotspotProfiles = $summary['hotspot']['profiles'] ?? [];
    $pppProfiles = $summary['ppp']['profiles'] ?? [];
    $userManagerProfiles = $summary['user_manager']['profiles'] ?? [];
    $allProfiles = $summary['all_profiles'] ?? [];

    $visibleProfiles = match ($filter) {
        'hotspot' => $hotspotProfiles,
        'ppp' => $pppProfiles,
        'user-manager' => $userManagerProfiles,
        'rate-limited' => array_values(array_filter(
            $allProfiles,
            fn (array $profile) => ($profile['rate_limit'] ?? '-') !== '-'
        )),
        default => $allProfiles,
    };

    $filterLabel = match ($filter) {
        'hotspot' => 'Hotspot Profiles',
        'ppp' => 'PPP Profiles',
        'user-manager' => 'User Manager Profiles',
        'rate-limited' => 'البروفايلات التي تحتوي Rate Limit',
        default => 'كل البروفايلات',
    };
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">📦</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>بروفايلات MikroTik والباقات</p>

            <div class="status-pill">
                <span class="dot"></span>
                Profiles Read Only
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تقرأ بروفايلات Hotspot و PPP و User Manager من MikroTik فقط.
            يمكن بعدها استيراد هذه البروفايلات كباقات محلية داخل GreenNet.
        </div>

        <a class="btn btn-primary" href="/admin/packages">
            📦 إدارة باقات GreenNet
        </a>

        <div class="grid">

            <div class="stat">
                <div class="label">إجمالي البروفايلات</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['total_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">Hotspot</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['hotspot_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">PPP</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['ppp_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">User Manager</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['user_manager_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">فيها Rate Limit</div>
                <div class="value"><?= htmlspecialchars((string) ($summary['rate_limited_count'] ?? 0)) ?></div>
            </div>

        </div>

        <?php if (($summary['hotspot']['ok'] ?? false) !== true): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                فشل قراءة Hotspot Profiles:
                <?= htmlspecialchars($summary['hotspot']['error'] ?? '-') ?>
            </div>
        <?php endif; ?>

        <?php if (($summary['ppp']['ok'] ?? false) !== true): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                فشل قراءة PPP Profiles:
                <?= htmlspecialchars($summary['ppp']['error'] ?? '-') ?>
            </div>
        <?php endif; ?>

        <?php if (($summary['user_manager']['ok'] ?? false) !== true): ?>
            <div class="notice" style="background:#fff7ed;color:#92400e;">
                لم يتم قراءة User Manager Profiles:
                <?= htmlspecialchars($summary['user_manager']['error'] ?? '-') ?>
            </div>
        <?php endif; ?>

        <div class="stat" style="margin-top:18px;">
            <div class="label">الفلاتر</div>

            <a class="btn btn-outline" href="/admin/routeros/profiles?filter=all">
                كل البروفايلات
            </a>

            <a class="btn btn-outline" href="/admin/routeros/profiles?filter=hotspot">
                Hotspot
            </a>

            <a class="btn btn-outline" href="/admin/routeros/profiles?filter=ppp">
                PPP
            </a>

            <a class="btn btn-outline" href="/admin/routeros/profiles?filter=user-manager">
                User Manager
            </a>

            <a class="btn btn-outline" href="/admin/routeros/profiles?filter=rate-limited">
                Rate Limit
            </a>
        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">
                <?= htmlspecialchars($filterLabel) ?>
                —
                <?= htmlspecialchars((string) count($visibleProfiles)) ?>
            </div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">

                <?php if (count($visibleProfiles) === 0): ?>

                    لا توجد بروفايلات ضمن هذا التصنيف.

                <?php else: ?>

                    <?php foreach ($visibleProfiles as $profile): ?>
                        <?php
                            $hasRateLimit = (($profile['rate_limit'] ?? '-') !== '-');

                            $boxStyle = 'border-bottom:1px solid #e5e7eb; padding:12px 0;';

                            if ($hasRateLimit) {
                                $boxStyle .= ' background:#f0fdf4; border-radius:14px; padding:12px; margin-bottom:10px;';
                            } else {
                                $boxStyle .= ' background:#f9fafb; border-radius:14px; padding:12px; margin-bottom:10px;';
                            }
                        ?>

                        <div style="<?= htmlspecialchars($boxStyle) ?>">

                            <strong><?= htmlspecialchars($profile['name'] ?? '-') ?></strong>
                            —
                            <?= htmlspecialchars($profile['type'] ?? '-') ?>
                            <br>

                            Rate Limit:
                            <span style="direction:ltr; display:inline-block;">
                                <?= htmlspecialchars($profile['rate_limit'] ?? '-') ?>
                            </span>
                            <br>

                            Shared Users:
                            <?= htmlspecialchars($profile['shared_users'] ?? '-') ?>
                            <br>

                            Address Pool:
                            <?= htmlspecialchars($profile['address_pool'] ?? '-') ?>
                            <br>

                            Local Address:
                            <?= htmlspecialchars($profile['local_address'] ?? '-') ?>
                            <br>

                            Remote Address:
                            <?= htmlspecialchars($profile['remote_address'] ?? '-') ?>
                            <br>

                            Session Timeout:
                            <?= htmlspecialchars($profile['session_timeout'] ?? '-') ?>
                            <br>

                            Idle Timeout:
                            <?= htmlspecialchars($profile['idle_timeout'] ?? '-') ?>
                            <br>

                            Keepalive Timeout:
                            <?= htmlspecialchars($profile['keepalive_timeout'] ?? '-') ?>
                            <br>

                            MAC Cookie Timeout:
                            <?= htmlspecialchars($profile['mac_cookie_timeout'] ?? '-') ?>
                            <br>

                            Only One:
                            <?= htmlspecialchars($profile['only_one'] ?? '-') ?>
                            <br>

                            Comment:
                            <?= htmlspecialchars($profile['comment'] ?? '-') ?>
                            <br>

                            <details style="margin-top:10px;">
                                <summary>عرض كل الحقول الخام</summary>

                                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:10px; margin-top:8px;">
                                    <?php foreach (($profile['raw'] ?? []) as $key => $value): ?>
                                        <span style="direction:ltr; display:inline-block;">
                                            <?= htmlspecialchars((string) $key) ?>
                                        </span>
                                        =
                                        <span style="direction:ltr; display:inline-block;">
                                            <?= htmlspecialchars((string) $value) ?>
                                        </span>
                                        <br>
                                    <?php endforeach; ?>
                                </div>
                            </details>

                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </div>

        <a class="btn btn-primary" href="/admin/packages">
            📦 إدارة باقات GreenNet
        </a>

        <a class="btn btn-primary" href="/admin/routeros/profiles">
            تحديث البروفايلات
        </a>

        <a class="btn btn-outline" href="/admin/routeros/users">
            📚 كل مستخدمي MikroTik
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
            GreenNet RouterOS Profiles
        </div>

    </div>
</div>