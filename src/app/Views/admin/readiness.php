<?php
    $report = is_array($report ?? null) ? $report : [];
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $issues = is_array($report['issues'] ?? null) ? $report['issues'] : [];
    $routerErrors = is_array($report['router_errors'] ?? null) ? $report['router_errors'] : [];

    $severityBadge = function (string $severity): string {
        return match ($severity) {
            'error' => 'admin-badge admin-badge-danger',
            'warning' => 'admin-badge admin-badge-warning',
            'info' => 'admin-badge',
            default => 'admin-badge',
        };
    };

    $severityLabel = function (string $severity): string {
        return match ($severity) {
            'error' => 'خطأ',
            'warning' => 'تحذير',
            'info' => 'معلومة',
            default => $severity,
        };
    };

    $filter = (string) ($_GET['severity'] ?? 'all');

    $visibleIssues = [];

    foreach ($issues as $issue) {
        if ($filter === 'all' || (($issue['severity'] ?? '') === $filter)) {
            $visibleIssues[] = $issue;
        }
    }
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Readiness Check</h1>

        <p class="admin-page-description">
            فحص جاهزية بيانات GreenNet وMikroTik قبل أي أوامر كتابة فعلية مثل تفعيل، تعطيل، تغيير Profile، أو تصفير عدادات.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/api/browser">API Browser</a>
        <a class="admin-mini-btn" href="/admin/api/diagnostics">Diagnostics</a>
        <a class="admin-mini-btn" href="/admin/backup">Backup</a>
    </div>
</div>

<section class="admin-section-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div>
            <h2 class="admin-section-title">
                حالة الجاهزية
            </h2>

            <p class="admin-section-subtitle">
                <?= htmlspecialchars((string) ($summary['status_description'] ?? '-')) ?>
            </p>
        </div>

        <?php if (!empty($summary['ready_for_write'])): ?>
            <span class="admin-badge admin-badge-success">
                <?= htmlspecialchars((string) ($summary['status_label'] ?? 'جاهز')) ?>
            </span>
        <?php else: ?>
            <span class="admin-badge admin-badge-danger">
                <?= htmlspecialchars((string) ($summary['status_label'] ?? 'غير جاهز')) ?>
            </span>
        <?php endif; ?>
    </div>
</section>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">Customers</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['customers_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">CRM Local</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Packages</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['packages_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">GreenNet Packages</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Router Users</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['router_users_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Hotspot / PPP / UM</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Router Profiles</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['router_profiles_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Profiles read-only</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Errors</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['errors_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">تمنع الكتابة</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Warnings</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['warnings_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">تحتاج مراجعة</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Info</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['info_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">ملاحظات</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Router Errors</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['router_errors_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">API read errors</div>
    </div>

</div>

<?php if (count($routerErrors) > 0): ?>
    <section class="admin-section-card">
        <h2 class="admin-section-title">RouterOS Errors</h2>

        <?php foreach ($routerErrors as $routerError): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                <strong><?= htmlspecialchars((string) ($routerError['source'] ?? 'routeros')) ?></strong>
                <br>
                <?= htmlspecialchars((string) ($routerError['message'] ?? '-')) ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">فلترة النتائج</h2>

    <div class="admin-tabs">
        <a class="admin-tab <?= $filter === 'all' ? 'active' : '' ?>" href="/admin/readiness?severity=all">
            الكل
            <span class="admin-tab-count"><?= htmlspecialchars((string) count($issues)) ?></span>
        </a>

        <a class="admin-tab <?= $filter === 'error' ? 'active' : '' ?>" href="/admin/readiness?severity=error">
            أخطاء
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($summary['errors_count'] ?? 0)) ?></span>
        </a>

        <a class="admin-tab <?= $filter === 'warning' ? 'active' : '' ?>" href="/admin/readiness?severity=warning">
            تحذيرات
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($summary['warnings_count'] ?? 0)) ?></span>
        </a>

        <a class="admin-tab <?= $filter === 'info' ? 'active' : '' ?>" href="/admin/readiness?severity=info">
            معلومات
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($summary['info_count'] ?? 0)) ?></span>
        </a>
    </div>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">نتائج الفحص</h2>

    <p class="admin-section-subtitle">
        أصلح الأخطاء أولاً قبل الانتقال إلى Write Safety. التحذيرات لا تمنع دائماً، لكنها تحتاج مراجعة.
    </p>

    <?php if (count($visibleIssues) === 0): ?>
        <div class="admin-empty-state">
            لا توجد نتائج ضمن الفلتر الحالي.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:980px;">
                <thead>
                    <tr>
                        <th>Severity</th>
                        <th>المشكلة</th>
                        <th>Subject</th>
                        <th>Source</th>
                        <th>الوصف</th>
                        <th>إجراء</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($visibleIssues as $issue): ?>
                        <?php
                            $severity = (string) ($issue['severity'] ?? 'info');
                            $actionUrl = (string) ($issue['action_url'] ?? '#');
                        ?>

                        <tr>
                            <td>
                                <span class="<?= htmlspecialchars($severityBadge($severity)) ?>">
                                    <?= htmlspecialchars($severityLabel($severity)) ?>
                                </span>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($issue['title'] ?? '-')) ?>
                                </div>

                                <div class="admin-table-subtitle">
                                    <?= htmlspecialchars((string) ($issue['type'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <span class="admin-code">
                                    <?= htmlspecialchars((string) ($issue['subject'] ?? '-')) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars((string) ($issue['source'] ?? '-')) ?>
                            </td>

                            <td>
                                <div style="line-height:1.7;">
                                    <?= htmlspecialchars((string) ($issue['description'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <a class="admin-row-action primary" href="<?= htmlspecialchars($actionUrl) ?>">
                                    فتح / إصلاح
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">ماذا بعد؟</h2>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">1</div>
            <div>
                <strong>عالج الأخطاء الحمراء</strong>
                <br>
                مثل مشترك بلا باقة، Profile غير موجود، أو اسم مستخدم مكرر.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">2</div>
            <div>
                <strong>راجع التحذيرات</strong>
                <br>
                مثل مستخدم Disabled أو مستخدم على MikroTik غير موجود في CRM.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">3</div>
            <div>
                <strong>خذ Full Backup</strong>
                <br>
                قبل أي مرحلة Write Safety أو تنفيذ أوامر على MikroTik.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">🔒</div>
            <div>
                <strong>المرحلة التالية</strong>
                <br>
                Auto Match / Auto Link ثم Security Basics وWrite Safety.
            </div>
        </div>

    </div>
</section>