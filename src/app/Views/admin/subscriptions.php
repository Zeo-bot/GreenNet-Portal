<?php
    $rows = is_array($rows ?? null) ? $rows : [];
    $stats = is_array($stats ?? null) ? $stats : [];
    $filters = is_array($filters ?? null) ? $filters : [];

    $badgeClass = function (string $status): string {
        return match ($status) {
            'active' => 'admin-badge admin-badge-success',
            'soon_3', 'soon_7' => 'admin-badge admin-badge-warning',
            'expired' => 'admin-badge admin-badge-danger',
            'no_package', 'no_renewal' => 'admin-badge',
            default => 'admin-badge',
        };
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">إدارة الاشتراكات</h1>
        <p class="admin-page-description">
            متابعة الاشتراكات الفعالة، المنتهية، والقريبة من الانتهاء قبل ربط أوامر MikroTik.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/customers/table">جدول الزبائن</a>
        <a class="admin-mini-btn" href="/admin/export/subscriptions.csv">CSV</a>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-label">إجمالي النتائج</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['total'] ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">فعال</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['active'] ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">قريب الانتهاء</div>
        <div class="admin-stat-value">
            <?= htmlspecialchars((string) (($stats['soon_3'] ?? 0) + ($stats['soon_7'] ?? 0))) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">منتهي / ناقص</div>
        <div class="admin-stat-value">
            <?= htmlspecialchars((string) (($stats['expired'] ?? 0) + ($stats['no_renewal'] ?? 0) + ($stats['no_package'] ?? 0))) ?>
        </div>
    </div>
</div>

<form class="admin-filter-bar" method="get" action="/admin/subscriptions" style="grid-template-columns:1.5fr 1fr auto;">
    <div class="form-group">
        <label>بحث</label>
        <input
            type="text"
            name="q"
            value="<?= htmlspecialchars((string) ($filters['q'] ?? '')) ?>"
            placeholder="اسم المستخدم / الاسم / الهاتف / الباقة"
        >
    </div>

    <div class="form-group">
        <label>الحالة</label>
        <select name="status">
            <?php
                $options = [
                    'all' => 'الكل',
                    'active' => 'فعال',
                    'soon_3' => 'ينتهي خلال 3 أيام',
                    'soon_7' => 'ينتهي خلال 7 أيام',
                    'expired' => 'منتهي',
                    'no_renewal' => 'بلا تجديد',
                    'no_package' => 'بلا باقة',
                ];
            ?>

            <?php foreach ($options as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>" <?= (($filters['status'] ?? 'all') === $value) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn-primary" type="submit">تطبيق</button>
</form>

<section class="admin-section-card">
    <h2 class="admin-section-title">الاشتراكات</h2>

    <?php if (count($rows) === 0): ?>
        <div class="admin-empty-state">
            لا توجد اشتراكات مطابقة للفلاتر الحالية.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>المشترك</th>
                        <th>الباقة</th>
                        <th>الحالة</th>
                        <th>بداية الاشتراك</th>
                        <th>انتهاء الاشتراك</th>
                        <th>آخر دفعة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $encodedUsername = urlencode((string) $row['username']); ?>

                        <tr>
                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) $row['display_name']) ?>
                                </div>
                                <div class="admin-table-subtitle">
                                    <span class="admin-code"><?= htmlspecialchars((string) $row['username']) ?></span>
                                    —
                                    <?= htmlspecialchars((string) $row['phone']) ?>
                                </div>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) $row['package_name']) ?>
                                </div>
                                <div class="admin-table-subtitle">
                                    <?= htmlspecialchars((string) $row['package_profile']) ?>
                                </div>
                            </td>

                            <td>
                                <span class="<?= htmlspecialchars($badgeClass((string) $row['status'])) ?>">
                                    <?= htmlspecialchars((string) $row['status_label']) ?>
                                </span>
                                <div class="admin-table-subtitle">
                                    <?= htmlspecialchars((string) $row['days_left_label']) ?>
                                </div>
                            </td>

                            <td><?= htmlspecialchars((string) ($row['starts_at'] !== '' ? $row['starts_at'] : '-')) ?></td>

                            <td><?= htmlspecialchars((string) ($row['expires_at'] !== '' ? $row['expires_at'] : '-')) ?></td>

                            <td>
                                <span style="direction:ltr;display:inline-block;">
                                    <?= htmlspecialchars((string) $row['last_payment_amount']) ?>
                                    <?= htmlspecialchars((string) $row['last_payment_currency']) ?>
                                </span>
                                <div class="admin-table-subtitle">
                                    <?= htmlspecialchars((string) $row['last_payment_date']) ?>
                                </div>
                            </td>

                            <td>
                                <div class="admin-row-actions">
                                    <a class="admin-row-action primary" href="/admin/customers/profile?username=<?= htmlspecialchars($encodedUsername) ?>">ملف</a>
                                    <a class="admin-row-action" href="/admin/customers/renew?username=<?= htmlspecialchars($encodedUsername) ?>">تجديد</a>
                                    <a class="admin-row-action" href="/dashboard?username=<?= htmlspecialchars($encodedUsername) ?>">معاينة</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</section>