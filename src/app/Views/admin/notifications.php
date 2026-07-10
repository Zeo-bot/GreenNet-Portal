<?php
    $notifications = is_array($notifications ?? null) ? $notifications : [];
    $counts = is_array($counts ?? null) ? $counts : [];

    $status = (string) ($status ?? 'all');
    $category = (string) ($category ?? 'all');
    $q = (string) ($q ?? '');

    $message = (string) ($message ?? '');
    $messageType = (string) ($message_type ?? 'success');

    $statusTabClass = function (string $value) use ($status): string {
        return $status === $value ? 'admin-tab active' : 'admin-tab';
    };

    $categoryTabClass = function (string $value) use ($category): string {
        return $category === $value ? 'admin-tab active' : 'admin-tab';
    };

    $statusUrl = function (string $value) use ($category, $q): string {
        $params = [
            'status' => $value,
            'category' => $category,
        ];

        if ($q !== '') {
            $params['q'] = $q;
        }

        return '/admin/notifications?' . http_build_query($params);
    };

    $categoryUrl = function (string $value) use ($status, $q): string {
        $params = [
            'status' => $status,
            'category' => $value,
        ];

        if ($q !== '') {
            $params['q'] = $q;
        }

        return '/admin/notifications?' . http_build_query($params);
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">مركز الإشعارات</h1>

        <p class="admin-page-description">
            متابعة الأحداث المهمة في GreenNet مثل طلبات التجديد، محاولات الدخول، أخطاء API، والنسخ الاحتياطي.
        </p>
    </div>

    <div class="admin-header-actions">
        <form method="post" action="/admin/notifications/mark-all-read" style="display:inline;">
            <input type="hidden" name="current_status" value="<?= htmlspecialchars($status) ?>">
            <input type="hidden" name="current_category" value="<?= htmlspecialchars($category) ?>">
            <input type="hidden" name="current_q" value="<?= htmlspecialchars($q) ?>">

            <button class="admin-mini-btn" type="submit">
                تعليم الكل كمقروء
            </button>
        </form>

        <a class="admin-mini-btn" href="/admin/renewal-requests">طلبات التجديد</a>
        <a class="admin-mini-btn" href="/admin/logs">Logs</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="notice" style="<?= $messageType === 'warning' ? 'background:#fff7ed;color:#92400e;' : 'background:#f0fdf4;color:#166534;' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">كل الإشعارات</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['all'] ?? 0)) ?></div>
        <div class="admin-stat-note">غير مؤرشفة</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">غير مقروءة</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['unread'] ?? 0)) ?></div>
        <div class="admin-stat-note">بحاجة متابعة</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">طلبات تجديد</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['renewal'] ?? 0)) ?></div>
        <div class="admin-stat-note">Renewal</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">أمان</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['security'] ?? 0)) ?></div>
        <div class="admin-stat-note">Login / PIN</div>
    </div>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">فلترة حسب الحالة</h2>

    <div class="admin-tabs">
        <a class="<?= htmlspecialchars($statusTabClass('all')) ?>" href="<?= htmlspecialchars($statusUrl('all')) ?>">
            الكل
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['all'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($statusTabClass('unread')) ?>" href="<?= htmlspecialchars($statusUrl('unread')) ?>">
            غير مقروء
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['unread'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($statusTabClass('read')) ?>" href="<?= htmlspecialchars($statusUrl('read')) ?>">
            مقروء
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['read'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($statusTabClass('archived')) ?>" href="<?= htmlspecialchars($statusUrl('archived')) ?>">
            مؤرشف
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['archived'] ?? 0)) ?></span>
        </a>
    </div>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">فلترة حسب النوع</h2>

    <div class="admin-tabs">
        <a class="<?= htmlspecialchars($categoryTabClass('all')) ?>" href="<?= htmlspecialchars($categoryUrl('all')) ?>">
            الكل
        </a>

        <a class="<?= htmlspecialchars($categoryTabClass('renewal')) ?>" href="<?= htmlspecialchars($categoryUrl('renewal')) ?>">
            تجديد
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['renewal'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($categoryTabClass('security')) ?>" href="<?= htmlspecialchars($categoryUrl('security')) ?>">
            أمان
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['security'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($categoryTabClass('api')) ?>" href="<?= htmlspecialchars($categoryUrl('api')) ?>">
            API
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['api'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($categoryTabClass('backup')) ?>" href="<?= htmlspecialchars($categoryUrl('backup')) ?>">
            Backup
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['backup'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($categoryTabClass('router')) ?>" href="<?= htmlspecialchars($categoryUrl('router')) ?>">
            Router
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['router'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($categoryTabClass('customer')) ?>" href="<?= htmlspecialchars($categoryUrl('customer')) ?>">
            مشتركين
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['customer'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($categoryTabClass('system')) ?>" href="<?= htmlspecialchars($categoryUrl('system')) ?>">
            نظام
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['system'] ?? 0)) ?></span>
        </a>
    </div>

    <form method="get" action="/admin/notifications" class="admin-filter-bar" style="margin-top:14px;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">

        <div class="form-group">
            <label>بحث</label>
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="username / message / category"
                dir="ltr"
            >
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">بحث</button>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <a class="btn btn-outline" href="/admin/notifications">إلغاء الفلتر</a>
        </div>
    </form>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">الإشعارات</h2>

    <?php if (count($notifications) === 0): ?>
        <div class="admin-empty-state">
            لا توجد إشعارات ضمن الفلتر الحالي.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:1100px;">
                <thead>
                    <tr>
                        <th>الحالة</th>
                        <th>الإشعار</th>
                        <th>التصنيف</th>
                        <th>المشترك</th>
                        <th>الوقت</th>
                        <th>المصدر</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                            $id = (int) ($notification['id'] ?? 0);
                            $isRead = (int) ($notification['is_read'] ?? 0) === 1;
                            $isArchived = (int) ($notification['is_archived'] ?? 0) === 1;
                            $linkUrl = (string) ($notification['link_url'] ?? '');
                            $username = (string) ($notification['related_username'] ?? '');
                        ?>

                        <tr style="<?= !$isRead && !$isArchived ? 'background:#f8fafc;' : '' ?>">
                            <td>
                                <?php if ($isArchived): ?>
                                    <span class="admin-badge">مؤرشف</span>
                                <?php elseif ($isRead): ?>
                                    <span class="admin-badge admin-badge-success">مقروء</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-warning">جديد</span>
                                <?php endif; ?>

                                <div style="margin-top:6px;">
                                    <span class="<?= htmlspecialchars((string) ($notification['severity_badge'] ?? 'admin-badge')) ?>">
                                        <?= htmlspecialchars((string) ($notification['severity_label'] ?? 'معلومة')) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) (($notification['title'] ?? '') !== '' ? $notification['title'] : 'إشعار')) ?>
                                </div>

                                <div style="line-height:1.8;max-width:420px;margin-top:6px;">
                                    <?= nl2br(htmlspecialchars((string) ($notification['message'] ?? ''))) ?>
                                </div>

                                <?php if ($linkUrl !== ''): ?>
                                    <div class="admin-table-subtitle" style="margin-top:6px;direction:ltr;text-align:left;">
                                        <?= htmlspecialchars($linkUrl) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="admin-badge">
                                    <?= htmlspecialchars((string) ($notification['category_label'] ?? $notification['category'] ?? 'system')) ?>
                                </span>

                                <div class="admin-table-subtitle" style="margin-top:6px;">
                                    <?= htmlspecialchars((string) ($notification['category'] ?? 'system')) ?>
                                </div>
                            </td>

                            <td>
                                <?php if ($username !== ''): ?>
                                    <a class="admin-code" href="/admin/customers/profile?username=<?= urlencode($username) ?>">
                                        <?= htmlspecialchars($username) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="admin-code">
                                    <?= htmlspecialchars((string) ($notification['created_at'] ?? '-')) ?>
                                </div>

                                <?php if (($notification['read_at'] ?? '') !== ''): ?>
                                    <div class="admin-table-subtitle">
                                        قراءة:
                                        <?= htmlspecialchars((string) ($notification['read_at'] ?? '')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) (($notification['source_table'] ?? '') !== '' ? $notification['source_table'] : '-')) ?>
                                </div>

                                <?php if ((int) ($notification['source_id'] ?? 0) > 0): ?>
                                    <div class="admin-table-subtitle">
                                        ID:
                                        <?= htmlspecialchars((string) ($notification['source_id'] ?? 0)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="admin-row-actions" style="display:grid;gap:7px;">

                                    <?php if ($linkUrl !== ''): ?>
                                        <a class="admin-row-action primary" href="<?= htmlspecialchars($linkUrl) ?>">
                                            فتح
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!$isRead): ?>
                                        <form method="post" action="/admin/notifications/mark-read">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars((string) $id) ?>">
                                            <input type="hidden" name="current_status" value="<?= htmlspecialchars($status) ?>">
                                            <input type="hidden" name="current_category" value="<?= htmlspecialchars($category) ?>">
                                            <input type="hidden" name="current_q" value="<?= htmlspecialchars($q) ?>">

                                            <button class="admin-row-action" type="submit" style="width:100%;">
                                                تعليم كمقروء
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!$isArchived): ?>
                                        <form method="post" action="/admin/notifications/archive">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars((string) $id) ?>">
                                            <input type="hidden" name="current_status" value="<?= htmlspecialchars($status) ?>">
                                            <input type="hidden" name="current_category" value="<?= htmlspecialchars($category) ?>">
                                            <input type="hidden" name="current_q" value="<?= htmlspecialchars($q) ?>">

                                            <button class="admin-row-action danger" type="submit" style="width:100%;">
                                                أرشفة
                                            </button>
                                        </form>
                                    <?php endif; ?>

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
    <h2 class="admin-section-title">ملاحظات</h2>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">🔔</div>
            <div>
                <strong>الإشعارات تُولّد من الأحداث الموجودة</strong>
                <br>
                مثل طلبات التجديد وLogs المهمة، بدون تنفيذ أي أمر على MikroTik.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">👁️</div>
            <div>
                <strong>مقروء / غير مقروء</strong>
                <br>
                يمكن متابعة الأحداث الجديدة وتمييز ما تمت مراجعته.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">→</div>
            <div>
                <strong>لاحقاً</strong>
                <br>
                يمكن ربط هذا المركز بتنبيهات حية داخل Dashboard أو Web Push بعد PWA.
            </div>
        </div>

    </div>
</section>