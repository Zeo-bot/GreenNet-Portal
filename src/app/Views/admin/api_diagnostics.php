<?php
    $diagnostics = is_array($diagnostics ?? null) ? $diagnostics : [];
    $config = is_array($diagnostics['config'] ?? null) ? $diagnostics['config'] : [];
    $checks = is_array($diagnostics['checks'] ?? null) ? $diagnostics['checks'] : [];
    $summary = is_array($diagnostics['summary'] ?? null) ? $diagnostics['summary'] : [];

    $badgeClass = function (string $status): string {
        return match ($status) {
            'ok' => 'admin-badge admin-badge-success',
            'warning' => 'admin-badge admin-badge-warning',
            'failed' => 'admin-badge admin-badge-danger',
            default => 'admin-badge',
        };
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">API Diagnostics</h1>

        <p class="admin-page-description">
            فحص قراءة RouterOS API قبل أي أوامر كتابة على MikroTik. هذه الصفحة لا تعدّل أي شيء في الراوتر.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/routeros">RouterOS</a>
        <a class="admin-mini-btn" href="/admin/routeros/discovery">Discovery</a>
        <a class="admin-mini-btn" href="/admin/logs">Logs</a>
    </div>
</div>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">إجمالي الفحوصات</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['total'] ?? 0)) ?></div>
        <div class="admin-stat-note">Read-only checks</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">ناجح</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['ok'] ?? 0)) ?></div>
        <div class="admin-stat-note">OK</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">فشل</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['failed'] ?? 0)) ?></div>
        <div class="admin-stat-note">Failed</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">زمن الفحص</div>
        <div class="admin-stat-value" style="font-size:20px;">
            <?= htmlspecialchars((string) ($diagnostics['total_duration_ms'] ?? 0)) ?> ms
        </div>
        <div class="admin-stat-note">Total time</div>
    </div>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">إعدادات الاتصال الحالية</h2>

    <p class="admin-section-subtitle">
        هذه القيم مقروءة من إعدادات المشروع. كلمة المرور لا تظهر هنا.
    </p>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">🌐</div>
            <div>
                <strong>Host</strong>
                <br>
                <span class="admin-code"><?= htmlspecialchars((string) ($config['host'] ?? '-')) ?></span>
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">🔌</div>
            <div>
                <strong>API Port</strong>
                <br>
                <span class="admin-code"><?= htmlspecialchars((string) ($config['port'] ?? '-')) ?></span>
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">👤</div>
            <div>
                <strong>Username</strong>
                <br>
                <span class="admin-code"><?= htmlspecialchars((string) ($config['username'] ?? '-')) ?></span>
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon <?= !empty($config['password_set']) ? '' : 'warning' ?>">
                <?= !empty($config['password_set']) ? '✓' : '!' ?>
            </div>
            <div>
                <strong>Password</strong>
                <br>
                <?= !empty($config['password_set']) ? 'موجودة' : 'غير مضبوطة' ?>
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">🧩</div>
            <div>
                <strong>Access Mode</strong>
                <br>
                <span class="admin-code"><?= htmlspecialchars((string) ($config['access_mode'] ?? '-')) ?></span>
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">🔐</div>
            <div>
                <strong>Auth Backend</strong>
                <br>
                <span class="admin-code"><?= htmlspecialchars((string) ($config['auth_backend'] ?? '-')) ?></span>
            </div>
        </div>

    </div>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">نتائج الفحص</h2>

    <?php if (count($checks) === 0): ?>
        <div class="admin-empty-state">
            لا توجد نتائج فحص.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:920px;">
                <thead>
                    <tr>
                        <th>الفحص</th>
                        <th>الحالة</th>
                        <th>Rows</th>
                        <th>الزمن</th>
                        <th>الرسالة</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($checks as $check): ?>
                        <tr>
                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($check['name'] ?? '-')) ?>
                                </div>

                                <div class="admin-table-subtitle">
                                    <?= htmlspecialchars((string) ($check['description'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <span class="<?= htmlspecialchars($badgeClass((string) ($check['status'] ?? 'failed'))) ?>">
                                    <?= htmlspecialchars((string) ($check['status_label'] ?? '-')) ?>
                                </span>
                            </td>

                            <td>
                                <span class="admin-badge">
                                    <?= htmlspecialchars((string) ($check['rows_count'] ?? 0)) ?>
                                </span>
                            </td>

                            <td>
                                <span class="admin-code">
                                    <?= htmlspecialchars((string) ($check['duration_ms'] ?? 0)) ?> ms
                                </span>
                            </td>

                            <td>
                                <div class="admin-table-subtitle">
                                    <?= htmlspecialchars((string) ($check['message'] ?? '-')) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">Samples</h2>

    <p class="admin-section-subtitle">
        عينات صغيرة من النتائج للتأكد من أسماء الحقول بدون عرض كل البيانات.
    </p>

    <?php foreach ($checks as $check): ?>
        <?php
            $sample = is_array($check['sample'] ?? null) ? $check['sample'] : [];
        ?>

        <div class="admin-section-card" style="background:#f9fafb;">
            <h3 class="admin-section-title" style="font-size:15px;">
                <?= htmlspecialchars((string) ($check['name'] ?? '-')) ?>
            </h3>

            <?php if (count($sample) === 0): ?>
                <div class="admin-empty-state" style="padding:14px;">
                    لا توجد عينة.
                </div>
            <?php else: ?>
                <div class="admin-json-box">
<?= htmlspecialchars(json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">ماذا تعني النتائج؟</h2>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">✓</div>
            <div>
                <strong>إذا نجح RouterOS API Connection</strong>
                <br>
                هذا يعني أن الاتصال الأساسي واليوزر والبورت يعملون.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon warning">!</div>
            <div>
                <strong>إذا فشل User Manager فقط</strong>
                <br>
                غالباً المسارات أو الصلاحيات مختلفة، وهذا طبيعي حسب إصدار RouterOS.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon warning">!</div>
            <div>
                <strong>إذا فشل كل شيء</strong>
                <br>
                راجع IP، Port 8728، اسم المستخدم، كلمة المرور، وصلاحية api على MikroTik.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">↻</div>
            <div>
                <strong>الخطوة التالية</strong>
                <br>
                سنبني API Data Browser لعرض كل الجداول المقروءة من MikroTik بشكل مرتب.
            </div>
        </div>

    </div>
</section>