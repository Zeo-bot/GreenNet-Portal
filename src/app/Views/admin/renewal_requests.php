<?php
    $requests = is_array($requests ?? null) ? $requests : [];
    $counts = is_array($counts ?? null) ? $counts : [];

    $status = (string) ($status ?? 'all');
    $q = (string) ($q ?? '');
    $message = (string) ($message ?? '');
    $messageType = (string) ($message_type ?? 'success');

    $tabClass = function (string $value) use ($status): string {
        return $status === $value ? 'admin-tab active' : 'admin-tab';
    };

    $statusOptions = [
        'pending' => 'جديد',
        'in_review' => 'قيد المراجعة',
        'completed' => 'مكتمل',
        'rejected' => 'مرفوض',
    ];
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">طلبات التجديد</h1>

        <p class="admin-page-description">
            متابعة طلبات التجديد المرسلة من تطبيق المشترك. هذه الصفحة لا تنفذ أي تعديل على MikroTik.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/customers/table">الزبائن</a>
        <a class="admin-mini-btn" href="/admin/subscriptions">الاشتراكات</a>
        <a class="admin-mini-btn" href="/admin/reports">التقارير</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="notice" style="<?= $messageType === 'warning' ? 'background:#fff7ed;color:#92400e;' : 'background:#f0fdf4;color:#166534;' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">كل الطلبات</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['all'] ?? 0)) ?></div>
        <div class="admin-stat-note">Renewal Requests</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">جديدة</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['pending'] ?? 0)) ?></div>
        <div class="admin-stat-note">Pending</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">قيد المراجعة</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['in_review'] ?? 0)) ?></div>
        <div class="admin-stat-note">In Review</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">مكتملة</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['completed'] ?? 0)) ?></div>
        <div class="admin-stat-note">Completed</div>
    </div>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">فلترة الطلبات</h2>

    <div class="admin-tabs">
        <a class="<?= htmlspecialchars($tabClass('all')) ?>" href="/admin/renewal-requests?status=all">
            الكل
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['all'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($tabClass('pending')) ?>" href="/admin/renewal-requests?status=pending">
            جديد
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['pending'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($tabClass('in_review')) ?>" href="/admin/renewal-requests?status=in_review">
            قيد المراجعة
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['in_review'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($tabClass('completed')) ?>" href="/admin/renewal-requests?status=completed">
            مكتمل
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['completed'] ?? 0)) ?></span>
        </a>

        <a class="<?= htmlspecialchars($tabClass('rejected')) ?>" href="/admin/renewal-requests?status=rejected">
            مرفوض
            <span class="admin-tab-count"><?= htmlspecialchars((string) ($counts['rejected'] ?? 0)) ?></span>
        </a>
    </div>

    <form method="get" action="/admin/renewal-requests" class="admin-filter-bar" style="margin-top:14px;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">

        <div class="form-group">
            <label>بحث</label>
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="username / phone / package"
                dir="ltr"
            >
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">بحث</button>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <a class="btn btn-outline" href="/admin/renewal-requests">إلغاء الفلتر</a>
        </div>
    </form>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">قائمة طلبات التجديد</h2>

    <?php if (count($requests) === 0): ?>
        <div class="admin-empty-state">
            لا توجد طلبات تجديد ضمن الفلتر الحالي.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:1120px;">
                <thead>
                    <tr>
                        <th>الحالة</th>
                        <th>المشترك</th>
                        <th>الهاتف</th>
                        <th>الباقة</th>
                        <th>الرسالة</th>
                        <th>الوقت</th>
                        <th>تحديث</th>
                        <th>روابط</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <?php
                            $requestId = (int) ($request['id'] ?? 0);
                            $username = (string) ($request['username'] ?? '');
                            $phone = (string) ($request['phone'] ?? '');
                            $packageName = (string) (($request['package_name'] ?? '') !== '' ? $request['package_name'] : ($request['current_package_name'] ?? ''));
                            $requestStatus = (string) ($request['status'] ?? 'pending');
                        ?>

                        <tr>
                            <td>
                                <span class="<?= htmlspecialchars((string) ($request['status_badge'] ?? 'admin-badge')) ?>">
                                    <?= htmlspecialchars((string) ($request['status_label'] ?? $requestStatus)) ?>
                                </span>
                            </td>

                            <td>
                                <div class="admin-table-title" dir="ltr">
                                    <?= htmlspecialchars($username) ?>
                                </div>

                                <div class="admin-table-subtitle">
                                    <?= htmlspecialchars((string) (($request['full_name'] ?? '') !== '' ? $request['full_name'] : '-')) ?>
                                </div>
                            </td>

                            <td>
                                <?php if ($phone !== ''): ?>
                                    <span class="admin-code"><?= htmlspecialchars($phone) ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars($packageName !== '' ? $packageName : '-') ?>
                                </div>

                                <?php if (($request['current_package_price'] ?? '') !== ''): ?>
                                    <div class="admin-table-subtitle">
                                        <?= htmlspecialchars((string) ($request['current_package_price'] ?? '0')) ?>
                                        <?= htmlspecialchars((string) ($request['current_package_currency'] ?? '')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div style="line-height:1.8;max-width:260px;">
                                    <?= nl2br(htmlspecialchars((string) (($request['message'] ?? '') !== '' ? $request['message'] : '-'))) ?>
                                </div>

                                <?php if (($request['admin_note'] ?? '') !== ''): ?>
                                    <div class="admin-table-subtitle" style="margin-top:6px;">
                                        ملاحظة:
                                        <?= htmlspecialchars((string) ($request['admin_note'] ?? '')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="admin-code">
                                    <?= htmlspecialchars((string) ($request['created_at'] ?? '-')) ?>
                                </div>

                                <?php if (($request['updated_at'] ?? '') !== ''): ?>
                                    <div class="admin-table-subtitle">
                                        تحديث:
                                        <?= htmlspecialchars((string) ($request['updated_at'] ?? '')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <form method="post" action="/admin/renewal-requests/update" style="display:grid;gap:8px;min-width:210px;">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $requestId) ?>">
                                    <input type="hidden" name="current_status" value="<?= htmlspecialchars($status) ?>">
                                    <input type="hidden" name="current_q" value="<?= htmlspecialchars($q) ?>">

                                    <select name="status">
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= $requestStatus === $value ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <textarea
                                        name="admin_note"
                                        placeholder="ملاحظة إدارية"
                                        style="min-height:60px;"
                                    ><?= htmlspecialchars((string) ($request['admin_note'] ?? '')) ?></textarea>

                                    <button class="admin-row-action primary" type="submit">
                                        حفظ
                                    </button>
                                </form>
                            </td>

                            <td>
                                <div class="admin-row-actions" style="display:grid;gap:7px;">

                                    <?php if (($request['whatsapp_url'] ?? '') !== ''): ?>
                                        <a
                                            class="admin-row-action primary"
                                            href="<?= htmlspecialchars((string) ($request['whatsapp_url'] ?? '')) ?>"
                                            target="_blank"
                                        >
                                            واتساب
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        class="admin-row-action"
                                        href="/admin/customers/profile?username=<?= urlencode($username) ?>"
                                    >
                                        صفحة الزبون
                                    </a>

                                    <a
                                        class="admin-row-action"
                                        href="/admin/customers/renew?username=<?= urlencode($username) ?>"
                                    >
                                        تجديد من المدير
                                    </a>

                                    <a
                                        class="admin-row-action"
                                        href="/dashboard?username=<?= urlencode($username) ?>"
                                    >
                                        معاينة التطبيق
                                    </a>

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
            <div class="admin-check-icon">1</div>
            <div>
                <strong>هذه الصفحة لا تعدل MikroTik</strong>
                <br>
                تغيير حالة الطلب محلي فقط داخل GreenNet.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">2</div>
            <div>
                <strong>التجديد الحقيقي</strong>
                <br>
                استخدم زر "تجديد من المدير" لتسجيل دفعة وتجديد داخل GreenNet.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">→</div>
            <div>
                <strong>لاحقاً</strong>
                <br>
                بعد Write Safety يمكن ربط التجديد بتعديل فعلي على MikroTik.
            </div>
        </div>

    </div>
</section>