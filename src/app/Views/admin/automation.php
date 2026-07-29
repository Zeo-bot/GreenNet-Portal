<?php
$registry = is_array($registry ?? null) ? $registry : [];
$historyRows = is_array($history ?? null) ? $history : [];
$historyByJob = [];
foreach ($historyRows as $row) {
    $historyByJob[(string) ($row['job_name'] ?? '')] = $row;
}
$h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">العمليات المجدولة</h1>
        <p class="admin-page-description">تشغيل ومتابعة المهام الدورية الصغيرة. كل كتابة على RouterOS تمر عبر بوابة الكتابة المحمية.</p>
    </div>
    <span class="admin-badge <?= !empty($enabled) ? 'admin-badge-success' : 'admin-badge-danger' ?>"><?= !empty($enabled) ? 'مفعّلة' : 'معطّلة' ?></span>
</div>
<?php if (($message ?? '') !== ''): ?><div class="notice"><?= $h($message) ?></div><?php endif; ?>
<section class="admin-section-card">
    <div style="overflow:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead><tr><th>المهمة</th><th>الجدول المقترح</th><th>آخر تشغيل</th><th>الحالة</th><th>المعالجة</th><th>نجاح</th><th>فشل</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($registry as $name => $job): ?>
                <?php $last = $historyByJob[$name] ?? []; ?>
                <tr>
                    <td><strong><?= $h($job['label'] ?? $name) ?></strong><div dir="ltr" style="font-size:12px"><?= $h($name) ?></div></td>
                    <td><?= $h($job['schedule'] ?? '-') ?></td>
                    <td dir="ltr"><?= $h($last['finished_at'] ?? 'لم تُشغّل') ?></td>
                    <td><?= $h($last['status'] ?? '-') ?></td>
                    <td><?= (int) ($last['processed_count'] ?? 0) ?></td>
                    <td><?= (int) ($last['success_count'] ?? 0) ?></td>
                    <td><?= (int) ($last['failure_count'] ?? 0) ?></td>
                    <td><form method="post" action="/admin/automation/run"><input type="hidden" name="job" value="<?= $h($name) ?>"><button class="admin-mini-btn" type="submit">تشغيل الآن</button></form></td>
                </tr>
                <?php if (($last['error_summary'] ?? '') !== ''): ?><tr><td colspan="8" style="color:#991b1b"><?= $h($last['error_summary']) ?></td></tr><?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
