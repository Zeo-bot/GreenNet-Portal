<?php
    $mode = (string) ($mode ?? 'search');
    $q = (string) ($q ?? '');
    $customers = is_array($customers ?? null) ? $customers : [];

    $username = (string) ($username ?? '');
    $customer = is_array($customer ?? null) ? $customer : null;
    $events = is_array($events ?? null) ? $events : [];
    $stats = is_array($stats ?? null) ? $stats : [];

    $message = (string) ($message ?? '');
    $messageType = (string) ($message_type ?? 'success');

    $customerName = $customer
        ? (string) (($customer['full_name'] ?? '') !== '' ? $customer['full_name'] : $username)
        : $username;
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Timeline المشترك</h1>

        <p class="admin-page-description">
            سجل زمني لأحداث المشترك داخل GreenNet: تجديدات، دفعات، إشعارات، تسجيل دخول، وملاحظات إدارية.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/customers/table">جدول الزبائن</a>
        <a class="admin-mini-btn" href="/admin/renewal-requests">طلبات التجديد</a>
        <a class="admin-mini-btn" href="/admin/notifications">الإشعارات</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="notice" style="<?= $messageType === 'warning' ? 'background:#fff7ed;color:#92400e;' : 'background:#f0fdf4;color:#166534;' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($mode === 'search'): ?>

    <section class="admin-section-card">
        <h2 class="admin-section-title">اختيار مشترك</h2>

        <form method="get" action="/admin/customers/timeline" class="admin-filter-bar">
            <div class="form-group">
                <label>بحث عن مشترك</label>
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($q) ?>"
                    placeholder="username / phone / name"
                    dir="ltr"
                >
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-primary" type="submit">بحث</button>
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <a class="btn btn-outline" href="/admin/customers/timeline">إلغاء</a>
            </div>
        </form>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">الزبائن</h2>

        <?php if (count($customers) === 0): ?>
            <div class="admin-empty-state">
                لا يوجد زبائن للعرض.
            </div>
        <?php else: ?>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>المستخدم</th>
                            <th>الاسم</th>
                            <th>الهاتف</th>
                            <th>الحالة</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($customers as $row): ?>
                            <?php $rowUsername = (string) ($row['username'] ?? ''); ?>

                            <tr>
                                <td>
                                    <span class="admin-code"><?= htmlspecialchars($rowUsername) ?></span>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($row['full_name'] ?? '') !== '' ? $row['full_name'] : '-')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($row['phone'] ?? '') !== '' ? $row['phone'] : '-')) ?>
                                </td>

                                <td>
                                    <span class="admin-badge">
                                        <?= htmlspecialchars((string) (($row['payment_status'] ?? '') !== '' ? $row['payment_status'] : '-')) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($rowUsername !== ''): ?>
                                        <a class="admin-row-action primary" href="/admin/customers/timeline?username=<?= urlencode($rowUsername) ?>">
                                            فتح Timeline
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>
        <?php endif; ?>
    </section>

<?php else: ?>

    <div class="admin-stats-grid">

        <div class="admin-stat-card">
            <div class="admin-stat-label">كل الأحداث</div>
            <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['total'] ?? 0)) ?></div>
            <div class="admin-stat-note" dir="ltr"><?= htmlspecialchars($username) ?></div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">دفعات</div>
            <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['payment'] ?? 0)) ?></div>
            <div class="admin-stat-note">Payments</div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">طلبات تجديد</div>
            <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['renewal'] ?? 0)) ?></div>
            <div class="admin-stat-note">Renewal</div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">تنبيهات</div>
            <div class="admin-stat-value">
                <?= htmlspecialchars((string) ((int) ($stats['warning'] ?? 0) + (int) ($stats['danger'] ?? 0))) ?>
            </div>
            <div class="admin-stat-note">Warning / Danger</div>
        </div>

    </div>

    <section class="admin-section-card">
        <div class="admin-page-header" style="margin-bottom:0;">
            <div>
                <h2 class="admin-section-title" style="margin-bottom:8px;">
                    <?= htmlspecialchars($customerName) ?>
                </h2>

                <div class="admin-table-subtitle" dir="ltr">
                    <?= htmlspecialchars($username) ?>
                </div>

                <?php if ($customer): ?>
                    <div style="margin-top:10px;line-height:1.8;">
                        <?php if (($customer['phone'] ?? '') !== ''): ?>
                            الهاتف:
                            <span class="admin-code"><?= htmlspecialchars((string) $customer['phone']) ?></span>
                            <br>
                        <?php endif; ?>

                        <?php if (($customer['payment_status'] ?? '') !== ''): ?>
                            حالة الدفع:
                            <span class="admin-badge"><?= htmlspecialchars((string) $customer['payment_status']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="admin-header-actions">
                <a class="admin-mini-btn" href="/admin/customers/profile?username=<?= urlencode($username) ?>">صفحة الزبون</a>
                <a class="admin-mini-btn" href="/admin/customers/password?username=<?= urlencode($username) ?>">PIN</a>
                <a class="admin-mini-btn" href="/admin/customers/renew?username=<?= urlencode($username) ?>">تجديد</a>
                <a class="admin-mini-btn" href="/dashboard?username=<?= urlencode($username) ?>">معاينة التطبيق</a>
            </div>
        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">إضافة ملاحظة إدارية</h2>

        <form method="post" action="/admin/customers/timeline/note" class="admin-filter-bar">
            <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">

            <div class="form-group">
                <label>العنوان</label>
                <input
                    type="text"
                    name="title"
                    value=""
                    placeholder="مثال: اتصال من المشترك"
                >
            </div>

            <div class="form-group">
                <label>الأهمية</label>
                <select name="severity">
                    <option value="info">معلومة</option>
                    <option value="success">نجاح</option>
                    <option value="warning">تنبيه</option>
                    <option value="danger">خطير</option>
                </select>
            </div>

            <div class="form-group" style="grid-column:1 / -1;">
                <label>الملاحظة</label>
                <textarea name="note" placeholder="اكتب ملاحظة تظهر ضمن Timeline هذا المشترك" style="min-height:90px;"></textarea>
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-primary" type="submit">إضافة إلى Timeline</button>
            </div>
        </form>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">السجل الزمني</h2>

        <?php if (count($events) === 0): ?>
            <div class="admin-empty-state">
                لا توجد أحداث لهذا المشترك بعد.
            </div>
        <?php else: ?>

            <div style="display:grid;gap:14px;">
                <?php foreach ($events as $event): ?>
                    <div style="display:grid;grid-template-columns:52px 1fr;gap:12px;align-items:start;">
                        <div style="
                            width:44px;
                            height:44px;
                            border-radius:16px;
                            background:#f1f5f9;
                            display:grid;
                            place-items:center;
                            font-size:22px;
                            border:1px solid #e5e7eb;
                        ">
                            <?= htmlspecialchars((string) ($event['icon'] ?? '•')) ?>
                        </div>

                        <div style="
                            border:1px solid #e5e7eb;
                            border-radius:20px;
                            background:#ffffff;
                            padding:14px;
                            box-shadow:0 10px 30px rgba(15,23,42,0.04);
                        ">
                            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                                <div>
                                    <div class="admin-table-title">
                                        <?= htmlspecialchars((string) ($event['title'] ?? 'حدث')) ?>
                                    </div>

                                    <div class="admin-table-subtitle" style="margin-top:4px;">
                                        <?= htmlspecialchars((string) ($event['time'] ?? '-')) ?>
                                    </div>
                                </div>

                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <span class="<?= htmlspecialchars((string) ($event['severity_badge'] ?? 'admin-badge')) ?>">
                                        <?= htmlspecialchars((string) ($event['severity_label'] ?? 'معلومة')) ?>
                                    </span>

                                    <?php if (($event['type'] ?? '') !== ''): ?>
                                        <span class="admin-badge">
                                            <?= htmlspecialchars((string) $event['type']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (($event['message'] ?? '') !== ''): ?>
                                <div style="margin-top:10px;line-height:1.9;">
                                    <?= nl2br(htmlspecialchars((string) $event['message'])) ?>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                <?php if (($event['source'] ?? '') !== ''): ?>
                                    <span class="admin-table-subtitle">
                                        المصدر:
                                        <span class="admin-code"><?= htmlspecialchars((string) $event['source']) ?></span>
                                    </span>
                                <?php endif; ?>

                                <?php if ((int) ($event['source_id'] ?? 0) > 0): ?>
                                    <span class="admin-table-subtitle">
                                        ID:
                                        <span class="admin-code"><?= htmlspecialchars((string) $event['source_id']) ?></span>
                                    </span>
                                <?php endif; ?>

                                <?php if (($event['link_url'] ?? '') !== ''): ?>
                                    <a class="admin-row-action" href="<?= htmlspecialchars((string) $event['link_url']) ?>">
                                        فتح المصدر
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </section>

<?php endif; ?>