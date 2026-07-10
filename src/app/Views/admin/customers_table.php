<?php
    $customers = is_array($customers ?? null) ? $customers : [];
    $packages = is_array($packages ?? null) ? $packages : [];
    $stats = is_array($stats ?? null) ? $stats : [];
    $filters = is_array($filters ?? null) ? $filters : [];

    $paymentBadge = function (string $status): string {
        return match ($status) {
            'paid' => 'admin-badge admin-badge-success',
            'due', 'unpaid' => 'admin-badge admin-badge-danger',
            'pending' => 'admin-badge admin-badge-warning',
            default => 'admin-badge',
        };
    };

    $paymentLabel = function (string $status): string {
        return match ($status) {
            'paid' => 'مدفوع',
            'due' => 'عليه دفع',
            'unpaid' => 'غير مدفوع',
            'pending' => 'مؤجل',
            default => 'غير معروف',
        };
    };

    $displayName = function (array $customer): string {
        foreach (['display_name', 'full_name', 'name'] as $field) {
            if (!empty($customer[$field])) {
                return (string) $customer[$field];
            }
        }

        return (string) ($customer['username'] ?? '-');
    };

    $phone = function (array $customer): string {
        foreach (['phone', 'mobile', 'phone_number'] as $field) {
            if (!empty($customer[$field])) {
                return (string) $customer[$field];
            }
        }

        return '-';
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">جدول الزبائن</h1>
        <p class="admin-page-description">
            جدول احترافي للبحث، الفلترة، والوصول السريع إلى ملف المشترك والإجراءات الأساسية.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/customers">إضافة / إدارة</a>
        <a class="admin-mini-btn" href="/admin/customers/sync">Sync</a>
        <a class="admin-mini-btn" href="/admin/export/customers.csv">CSV</a>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-label">إجمالي النتائج</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['total'] ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">مدفوع</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['paid'] ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">عليه دفع</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['due'] ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">بلا باقة</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['no_package'] ?? 0)) ?></div>
    </div>
</div>

<form class="admin-filter-bar" method="get" action="/admin/customers/table">
    <div class="form-group">
        <label>بحث</label>
        <input
            type="text"
            name="q"
            value="<?= htmlspecialchars((string) ($filters['q'] ?? '')) ?>"
            placeholder="اسم المستخدم / الاسم / الهاتف / ملاحظة"
        >
    </div>

    <div class="form-group">
        <label>حالة الدفع</label>
        <select name="payment_status">
            <?php
                $paymentOptions = [
                    'all' => 'الكل',
                    'paid' => 'مدفوع',
                    'due' => 'عليه دفع',
                    'unpaid' => 'غير مدفوع',
                    'pending' => 'مؤجل',
                    'unknown' => 'غير معروف',
                ];
            ?>

            <?php foreach ($paymentOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>" <?= (($filters['payment_status'] ?? 'all') === $value) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>نوع الوصول</label>
        <select name="access_type">
            <?php
                $accessOptions = [
                    'all' => 'الكل',
                    'hotspot' => 'Hotspot',
                    'ppp' => 'PPP',
                    'pppoe' => 'PPPoE',
                    'user-manager' => 'User Manager',
                    'hybrid' => 'Hybrid',
                ];
            ?>

            <?php foreach ($accessOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>" <?= (($filters['access_type'] ?? 'all') === $value) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>الباقة</label>
        <select name="package_id">
            <option value="0">كل الباقات</option>
            <?php foreach ($packages as $package): ?>
                <option value="<?= htmlspecialchars((string) $package['id']) ?>" <?= ((int) ($filters['package_id'] ?? 0) === (int) $package['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($package['name'] ?? '-') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn-primary" type="submit">
        تطبيق
    </button>
</form>

<section class="admin-section-card">
    <h2 class="admin-section-title">الزبائن</h2>

    <?php if (count($customers) === 0): ?>
        <div class="admin-empty-state">
            لا توجد نتائج مطابقة للفلاتر الحالية.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>المشترك</th>
                        <th>الهاتف</th>
                        <th>الوصول</th>
                        <th>الباقة</th>
                        <th>الدفع</th>
                        <th>آخر تحديث</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <?php
                            $username = (string) ($customer['username'] ?? '');
                            $encodedUsername = urlencode($username);
                            $status = (string) ($customer['payment_status'] ?? 'unknown');
                        ?>

                        <tr>
                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars($displayName($customer)) ?>
                                </div>

                                <div class="admin-table-subtitle">
                                    <span class="admin-code"><?= htmlspecialchars($username) ?></span>
                                </div>
                            </td>

                            <td><?= htmlspecialchars($phone($customer)) ?></td>

                            <td>
                                <span class="admin-badge">
                                    <?= htmlspecialchars((string) ($customer['access_type'] ?? '-')) ?>
                                </span>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($customer['package_name'] ?? 'بلا باقة')) ?>
                                </div>

                                <div class="admin-table-subtitle">
                                    <?= htmlspecialchars((string) ($customer['package_source_profile'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <span class="<?= htmlspecialchars($paymentBadge($status)) ?>">
                                    <?= htmlspecialchars($paymentLabel($status)) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars((string) ($customer['updated_at'] ?? $customer['created_at'] ?? '-')) ?>
                            </td>

                            <td>
                                <div class="admin-row-actions">
                                    <a class="admin-row-action primary" href="/admin/customers/profile?username=<?= htmlspecialchars($encodedUsername) ?>">
                                        ملف
                                    </a>

                                    <a class="admin-row-action" href="/dashboard?username=<?= htmlspecialchars($encodedUsername) ?>">
                                        معاينة
                                    </a>

                                    <a class="admin-row-action" href="/admin/customers/renew?username=<?= htmlspecialchars($encodedUsername) ?>">
                                        تجديد
                                    </a>

                                    <a class="admin-row-action" href="/admin/customers/package?username=<?= htmlspecialchars($encodedUsername) ?>">
                                        باقة
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