<?php
    $events = is_array($events ?? null) ? $events : [];
    $counts = is_array($counts ?? null) ? $counts : [];
    $q = (string) ($q ?? '');
    $source = (string) ($source ?? 'all');
    $severity = (string) ($severity ?? 'all');
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Audit Center</h1>
        <p class="admin-page-description">
            مركز تدقيق موحد يعرض الأحداث المهمة من Logs والإشعارات وطلبات التجديد والدفعات وTimeline وطبقة أمان MikroTik القادمة.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/notifications">الإشعارات</a>
        <a class="admin-mini-btn" href="/admin/logs">Logs</a>
        <a class="admin-mini-btn" href="/admin/write-safety">Write Safety</a>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-label">Logs</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['app_logs'] ?? 0)) ?></div>
        <div class="admin-stat-note">app_logs</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">إشعارات</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['admin_notifications'] ?? 0)) ?></div>
        <div class="admin-stat-note">admin_notifications</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">طلبات تجديد</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['renewal_requests'] ?? 0)) ?></div>
        <div class="admin-stat-note">renewal_requests</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">API Audit</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['api_audit_logs'] ?? 0)) ?></div>
        <div class="admin-stat-note">جاهز لـ Sprint 10</div>
    </div>
</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">فلترة</h2>

    <form method="get" action="/admin/audit" class="admin-filter-bar">
        <div class="form-group">
            <label>بحث</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="username / event / message" dir="ltr">
        </div>

        <div class="form-group">
            <label>المصدر</label>
            <select name="source">
                <?php
                    $sources = [
                        'all' => 'الكل',
                        'app_logs' => 'Logs',
                        'admin_notifications' => 'Notifications',
                        'renewal_requests' => 'Renewal Requests',
                        'payments' => 'Payments',
                        'customer_timeline_notes' => 'Timeline Notes',
                        'api_audit_logs' => 'API Audit',
                        'mikrotik_transaction_queue' => 'Transaction Queue',
                    ];
                ?>

                <?php foreach ($sources as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $source === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>الأهمية</label>
            <select name="severity">
                <?php
                    $severities = [
                        'all' => 'الكل',
                        'info' => 'معلومة',
                        'success' => 'نجاح',
                        'warning' => 'تنبيه',
                        'danger' => 'خطير',
                    ];
                ?>

                <?php foreach ($severities as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $severity === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">تطبيق</button>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <a class="btn btn-outline" href="/admin/audit">إلغاء</a>
        </div>
    </form>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">الأحداث</h2>

    <?php if (count($events) === 0): ?>
        <div class="admin-empty-state">
            لا توجد أحداث ضمن الفلتر الحالي.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:1100px;">
                <thead>
                    <tr>
                        <th>الأهمية</th>
                        <th>الحدث</th>
                        <th>المشترك</th>
                        <th>المصدر</th>
                        <th>الوقت</th>
                        <th>رابط</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td>
                                <span class="<?= htmlspecialchars((string) ($event['severity_badge'] ?? 'admin-badge')) ?>">
                                    <?= htmlspecialchars((string) ($event['severity_label'] ?? 'معلومة')) ?>
                                </span>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($event['title'] ?? 'Event')) ?>
                                </div>

                                <?php if (($event['message'] ?? '') !== ''): ?>
                                    <div style="line-height:1.8;margin-top:6px;max-width:520px;">
                                        <?= nl2br(htmlspecialchars((string) $event['message'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (($event['username'] ?? '') !== ''): ?>
                                    <a class="admin-code" href="/admin/customers/timeline?username=<?= urlencode((string) $event['username']) ?>">
                                        <?= htmlspecialchars((string) $event['username']) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="admin-code"><?= htmlspecialchars((string) ($event['source'] ?? '-')) ?></div>
                                <?php if ((int) ($event['source_id'] ?? 0) > 0): ?>
                                    <div class="admin-table-subtitle">
                                        ID: <?= htmlspecialchars((string) ($event['source_id'] ?? 0)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="admin-code"><?= htmlspecialchars((string) ($event['time'] ?? '-')) ?></span>
                            </td>

                            <td>
                                <?php if (($event['link_url'] ?? '') !== ''): ?>
                                    <a class="admin-row-action primary" href="<?= htmlspecialchars((string) $event['link_url']) ?>">
                                        فتح
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</section>