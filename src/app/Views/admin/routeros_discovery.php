<?php
    $results = $discovery['results'] ?? [];
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">🔍</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>RouterOS Data Discovery</p>

            <div class="status-pill">
                <span class="dot"></span>
                Read Only
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تختبر ما هي البيانات التي يمكن قراءتها من MikroTik.
            لا يتم تعديل أي إعداد، ولا يتم عرض كلمات المرور.
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">إجمالي الاختبارات</div>
                <div class="value"><?= htmlspecialchars((string) ($discovery['total_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">ناجحة</div>
                <div class="value"><?= htmlspecialchars((string) ($discovery['successful_count'] ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">فاشلة</div>
                <div class="value"><?= htmlspecialchars((string) ($discovery['failed_count'] ?? 0)) ?></div>
            </div>

        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">نتائج الاختبار</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">

                <?php foreach ($results as $result): ?>
                    <?php
                        $ok = (bool) ($result['ok'] ?? false);

                        $boxStyle = 'border-bottom:1px solid #e5e7eb; padding:12px 0;';

                        if ($ok) {
                            $boxStyle .= ' background:#f0fdf4; border-radius:14px; padding:12px; margin-bottom:10px;';
                        } else {
                            $boxStyle .= ' background:#fef2f2; border-radius:14px; padding:12px; margin-bottom:10px;';
                        }

                        $fields = $result['fields'] ?? [];
                        $sample = $result['sample'] ?? [];
                    ?>

                    <div style="<?= htmlspecialchars($boxStyle) ?>">

                        <strong><?= htmlspecialchars($result['title'] ?? '-') ?></strong>
                        <br>

                        Command:
                        <span style="direction:ltr; display:inline-block;">
                            <?= htmlspecialchars($result['command'] ?? '-') ?>
                        </span>
                        <br>

                        الوصف:
                        <?= htmlspecialchars($result['description'] ?? '-') ?>
                        <br>

                        الحالة:
                        <?= $ok ? 'نجح' : 'فشل' ?>
                        <br>

                        العدد:
                        <?= htmlspecialchars((string) ($result['count'] ?? 0)) ?>
                        <br>

                        <?php if (!$ok): ?>
                            الخطأ:
                            <?= htmlspecialchars($result['error'] ?? '-') ?>
                            <br>
                        <?php endif; ?>

                        <?php if ($ok && count($fields) > 0): ?>
                            الحقول المتاحة:
                            <br>
                            <span style="font-size:12px; direction:ltr; display:block;">
                                <?= htmlspecialchars(implode(', ', $fields)) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($ok && count($sample) > 0): ?>
                            <br>
                            عينة بيانات:
                            <br>

                            <?php foreach ($sample as $index => $row): ?>
                                <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:10px; margin-top:8px;">
                                    <strong>Row <?= htmlspecialchars((string) ($index + 1)) ?></strong>
                                    <br>

                                    <?php foreach ($row as $key => $value): ?>
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
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>

            </div>
        </div>

        <div class="notice">
            بعد هذه الصفحة سنقرر أي مصادر ندمجها في البحث:
            Hotspot Users، PPP Secrets، و User Manager حسب النتائج الناجحة.
        </div>

        <a class="btn btn-primary" href="/admin/routeros/discovery">
            إعادة الفحص
        </a>

        <a class="btn btn-outline" href="/admin/routeros">
            العودة إلى MikroTik API
        </a>

        <a class="btn btn-outline" href="/admin/search">
            البحث عن مشترك
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet RouterOS Discovery
        </div>

    </div>
</div>