<?php
$rows = is_array($rows ?? null) ? $rows : [];
$h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$backendLabel = static fn (string $backend): string => match ($backend) {
    'native-hotspot' => 'Native Hotspot',
    'native-pppoe' => 'Native PPPoE',
    default => 'User Manager',
};
?>
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">دورة الاشتراكات</h1>
        <p class="admin-page-description">معاينة محلية للانتهاء والحصة، مع تنفيذ يدوي عبر مسارات RouterOS المحمية الحالية.</p>
    </div>
    <a class="admin-mini-btn" href="/admin">لوحة العمليات</a>
</div>

<?php if (($message ?? '') !== ''): ?><div class="notice"><?= $h($message) ?></div><?php endif; ?>

<section class="admin-section-card">
    <p class="admin-section-subtitle">المعاينة الجماعية لا تتصل بالموجّهات. استخدم «تقييم الآن» لمشترك واحد لقراءة الاستهلاك المتاح وتثبيت لقطة الحالة.</p>
    <div style="overflow:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead><tr><th>المشترك</th><th>الحالة المحلية</th><th>الاستهلاك</th><th>الموجّه</th><th>النظام</th><th>التنفيذ</th><th>الإجراء</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $username = (string) ($row['username'] ?? '');
                $backend = (string) ($row['backend'] ?? 'user-manager');
                $requires = !empty($row['requires_enforcement']);
                $enforceUrl = in_array($backend, ['native-hotspot', 'native-pppoe'], true)
                    ? '/admin/native-subscriber?username=' . urlencode($username) . '&action=disable'
                    : '/admin/mikrotik-dry-run?username=' . urlencode($username) . '&action=um_disable_user';
                ?>
                <tr>
                    <td><a href="/admin/customers/profile?username=<?= urlencode($username) ?>"><?= $h($username) ?></a></td>
                    <td><strong><?= $h($row['label'] ?? '-') ?></strong><div style="font-size:12px;color:#6b7280"><?= $h($row['reason'] ?? '') ?></div></td>
                    <td><?= ($row['usage_state'] ?? '') === 'unavailable' ? 'غير متاح' : (($row['usage_state'] ?? '') === 'not_applicable' ? 'بلا حصة' : $h($row['used_bytes'] ?? 0)) ?></td>
                    <td><?= (int) ($row['router_id'] ?? 0) > 0 ? '#' . (int) $row['router_id'] : 'غير معيّن' ?></td>
                    <td dir="ltr"><?= $h($backendLabel($backend)) ?></td>
                    <td><?= $h($row['enforcement_state'] ?? 'not_required') ?></td>
                    <td style="white-space:nowrap">
                        <form method="post" action="/admin/lifecycle/evaluate" style="display:inline"><input type="hidden" name="username" value="<?= $h($username) ?>"><button class="admin-mini-btn" type="submit">تقييم الآن</button></form>
                        <?php if ($requires): ?><a class="admin-mini-btn danger" href="<?= $h($enforceUrl) ?>">معاينة الإيقاف</a><?php endif; ?>
                        <?php if (($row['enforcement_state'] ?? '') === 'renewal_sync_pending'): ?>
                            <a class="admin-mini-btn" href="<?= $h(in_array($backend, ['native-hotspot', 'native-pppoe'], true) ? '/admin/native-subscriber?username=' . urlencode($username) . '&action=package' : '/admin/package-assign?username=' . urlencode($username) . '&package_id=' . (int) ($row['package_id'] ?? 0) . '&assign_mode=replace') ?>">إعادة المزامنة</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="7">لا يوجد مشتركون.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
