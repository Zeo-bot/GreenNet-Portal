<?php
    $settings = is_array($settings ?? null) ? $settings : [];
    $counts = is_array($counts ?? null) ? $counts : [];
    $backup = is_array($backup ?? null) ? $backup : [];
    $readiness = is_array($readiness ?? null) ? $readiness : [];
    $safety = is_array($safety ?? null) ? $safety : [];
    $checklist = is_array($checklist ?? null) ? $checklist : [];
    $testResult = is_array($test_result ?? null) ? $test_result : null;
    $detectResult = is_array($detect_result ?? null) ? $detect_result : null;

    $badge = function (string $status): string {
        return match ($status) {
            'done', 'ok' => 'admin-badge admin-badge-success',
            'warning' => 'admin-badge admin-badge-warning',
            'missing', 'danger', 'failed' => 'admin-badge admin-badge-danger',
            'future' => 'admin-badge',
            default => 'admin-badge',
        };
    };

    $label = function (string $status): string {
        return match ($status) {
            'done' => 'تم',
            'ok' => 'جيد',
            'warning' => 'تحذير',
            'missing' => 'ناقص',
            'danger' => 'خطر',
            'failed' => 'فشل',
            'future' => 'لاحقاً',
            default => $status,
        };
    };

    $routerConfigured = trim((string) ($settings['host'] ?? '')) !== ''
        && trim((string) ($settings['username'] ?? '')) !== '';

    $lastSeen = trim((string) ($settings['last_seen'] ?? ''));

    $writeEnabled = strtolower((string) ($safety['write_enabled'] ?? 'false')) === 'true';
    $safeMode = strtolower((string) ($safety['safe_mode'] ?? 'true')) === 'true';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Health Dashboard</h1>

        <p class="admin-page-description">
            نظرة عامة على حالة GreenNet والراوتر والنسخ الاحتياطي وخطوات التجهيز قبل أي تنفيذ فعلي على MikroTik.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/health?refresh=1">تحديث الفحص</a>
        <a class="admin-mini-btn" href="/admin/router-setup">Router Setup</a>
        <a class="admin-mini-btn" href="/admin/readiness">Readiness</a>
        <a class="admin-mini-btn" href="/admin/backup">Backup</a>
    </div>
</div>

<?php if ($testResult !== null): ?>
    <div class="notice" style="<?= !empty($testResult['ok']) ? 'background:#f0fdf4;color:#166534;' : 'background:#fee2e2;color:#991b1b;' ?>">
        <strong>Connection Test:</strong>
        <?= htmlspecialchars((string) ($testResult['message'] ?? '-')) ?>
        —
        <?= htmlspecialchars((string) ($testResult['duration_ms'] ?? 0)) ?> ms
    </div>
<?php endif; ?>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">Router</div>
        <div class="admin-stat-value" style="font-size:18px;">
            <?= htmlspecialchars((string) (($settings['identity'] ?? '') !== '' ? $settings['identity'] : ($settings['router_name'] ?? 'Main MikroTik'))) ?>
        </div>
        <div class="admin-stat-note">
            <?= $routerConfigured ? 'Configured' : 'Not configured' ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Runtime Mode</div>
        <div class="admin-stat-value" style="font-size:18px;">
            <?= htmlspecialchars((string) ($settings['runtime_mode'] ?? 'external')) ?>
        </div>
        <div class="admin-stat-note">
            Access:
            <?= htmlspecialchars((string) ($settings['access_mode'] ?? 'hybrid')) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">API Last Seen</div>
        <div class="admin-stat-value" style="font-size:16px;">
            <?= htmlspecialchars($lastSeen !== '' ? $lastSeen : '-') ?>
        </div>
        <div class="admin-stat-note">
            Host:
            <?= htmlspecialchars((string) ($settings['host'] ?? '-')) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Write Mode</div>
        <div class="admin-stat-value" style="font-size:17px;">
            <?php if ($writeEnabled): ?>
                <span class="admin-badge admin-badge-danger">Write Enabled</span>
            <?php else: ?>
                <span class="admin-badge admin-badge-success">Read-only / Dry Run</span>
            <?php endif; ?>
        </div>
        <div class="admin-stat-note">
            Safe Mode:
            <?= $safeMode ? 'ON' : 'OFF' ?>
        </div>
    </div>

</div>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">Customers</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['customers'] ?? 0)) ?></div>
        <div class="admin-stat-note">CRM</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Packages</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['packages'] ?? 0)) ?></div>
        <div class="admin-stat-note">GreenNet Packages</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Payments</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['payments'] ?? 0)) ?></div>
        <div class="admin-stat-note">Renewals / Payments</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Logs</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['logs'] ?? 0)) ?></div>
        <div class="admin-stat-note">App Logs</div>
    </div>

</div>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">Hotspot Users</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($settings['detected_hotspot_users'] ?? 0)) ?></div>
        <div class="admin-stat-note">Detected</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">PPP Secrets</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($settings['detected_ppp_secrets'] ?? 0)) ?></div>
        <div class="admin-stat-note">Detected</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">User Manager</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($settings['detected_user_manager_users'] ?? 0)) ?></div>
        <div class="admin-stat-note">Detected Users</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Profiles</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($settings['detected_profiles'] ?? 0)) ?></div>
        <div class="admin-stat-note">Detected Profiles</div>
    </div>

</div>

<div class="admin-two-columns">

    <section class="admin-section-card">
        <h2 class="admin-section-title">Router Fingerprint</h2>

        <div class="admin-checklist" style="grid-template-columns:1fr;">

            <div class="admin-check-item">
                <div class="admin-check-icon">🌐</div>
                <div>
                    <strong>Host</strong>
                    <br>
                    <span class="admin-code"><?= htmlspecialchars((string) ($settings['host'] ?? '-')) ?></span>
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon">🧩</div>
                <div>
                    <strong>Board</strong>
                    <br>
                    <?= htmlspecialchars((string) (($settings['board_name'] ?? '') !== '' ? $settings['board_name'] : '-')) ?>
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon">⚙️</div>
                <div>
                    <strong>RouterOS</strong>
                    <br>
                    <?= htmlspecialchars((string) (($settings['routeros_version'] ?? '') !== '' ? $settings['routeros_version'] : '-')) ?>
                    —
                    <?= htmlspecialchars((string) (($settings['architecture'] ?? '') !== '' ? $settings['architecture'] : '-')) ?>
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon">⏱️</div>
                <div>
                    <strong>Uptime</strong>
                    <br>
                    <?= htmlspecialchars((string) (($settings['uptime'] ?? '') !== '' ? $settings['uptime'] : '-')) ?>
                </div>
            </div>

        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">Backup Status</h2>

        <div class="notice" style="<?php
            if (($backup['status'] ?? '') === 'ok') {
                echo 'background:#f0fdf4;color:#166534;';
            } elseif (($backup['status'] ?? '') === 'warning') {
                echo 'background:#fff7ed;color:#92400e;';
            } else {
                echo 'background:#fee2e2;color:#991b1b;';
            }
        ?>">
            <strong><?= htmlspecialchars((string) ($backup['status_label'] ?? '-')) ?></strong>
            <br>
            <?= htmlspecialchars((string) ($backup['message'] ?? '-')) ?>
        </div>

        <div class="admin-checklist" style="grid-template-columns:1fr;">

            <div class="admin-check-item">
                <div class="admin-check-icon">💾</div>
                <div>
                    <strong>Latest File</strong>
                    <br>
                    <span class="admin-code">
                        <?= htmlspecialchars((string) (($backup['filename'] ?? '') !== '' ? $backup['filename'] : '-')) ?>
                    </span>
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon">📦</div>
                <div>
                    <strong>Size</strong>
                    <br>
                    <?= htmlspecialchars((string) ($backup['size_human'] ?? '0 B')) ?>
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon">🕒</div>
                <div>
                    <strong>Age</strong>
                    <br>
                    <?= htmlspecialchars((string) ($backup['age_human'] ?? '-')) ?>
                    —
                    <?= htmlspecialchars((string) ($backup['modified_at'] ?? '-')) ?>
                </div>
            </div>

        </div>

        <a class="btn btn-primary" href="/admin/backup">
            فتح Backup
        </a>
    </section>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">Setup Checklist</h2>

    <p class="admin-section-subtitle">
        هذه القائمة تساعدنا نعرف ماذا تم وما الذي بقي قبل الانتقال إلى Auto Match ثم Write Safety.
    </p>

    <div class="admin-table-responsive">
        <table class="admin-table" style="min-width:920px;">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>الخطوة</th>
                    <th>الوصف</th>
                    <th>إجراء</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($checklist as $item): ?>
                    <?php
                        $status = (string) ($item['status'] ?? 'warning');
                        $actionUrl = (string) ($item['action_url'] ?? '#');
                    ?>

                    <tr>
                        <td>
                            <span class="<?= htmlspecialchars($badge($status)) ?>">
                                <?= htmlspecialchars($label($status)) ?>
                            </span>
                        </td>

                        <td>
                            <div class="admin-table-title">
                                <?= htmlspecialchars((string) ($item['title'] ?? '-')) ?>
                            </div>
                        </td>

                        <td>
                            <div style="line-height:1.8;">
                                <?= htmlspecialchars((string) ($item['description'] ?? '-')) ?>
                            </div>
                        </td>

                        <td>
                            <a class="admin-row-action primary" href="<?= htmlspecialchars($actionUrl) ?>">
                                <?= htmlspecialchars((string) ($item['action_label'] ?? 'فتح')) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</section>

<?php if ($detectResult !== null): ?>
    <section class="admin-section-card">
        <h2 class="admin-section-title">آخر Detect Services</h2>

        <div class="admin-json-box">
<?= htmlspecialchars(json_encode($detectResult['summary'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>
        </div>
    </section>
<?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">الخطة القادمة</h2>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">1</div>
            <div>
                <strong>Auto Match / Auto Link</strong>
                <br>
                استيراد المستخدمين والبروفايلات وربطها محلياً بدون أوامر Write على MikroTik.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">2</div>
            <div>
                <strong>Subscriber App / PWA</strong>
                <br>
                تطوير واجهة المشترك كتطبيق موبايل Web/PWA يعمل خارجياً أو داخل MikroTik.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">3</div>
            <div>
                <strong>API Audit Log</strong>
                <br>
                سجل مستقل لكل أمر MikroTik قبل تفعيل التنفيذ الحقيقي.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">Y</div>
            <div>
                <strong>RouterOS Apps YAML 7.22+</strong>
                <br>
                خيار التنصيب الأوتوماتيكي عبر YAML محفوظ ضمن خطة النشر المستقبلية.
            </div>
        </div>

    </div>
</section>