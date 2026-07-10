<?php
    $datasets = is_array($datasets ?? null) ? $datasets : [];
    $results = is_array($results ?? null) ? $results : [];
    $selected = is_array($selected ?? null) ? $selected : [];
    $selectedKey = (string) ($selected_key ?? 'hotspot_users');
    $query = (string) ($query ?? '');

    $rows = is_array($selected['rows'] ?? null) ? $selected['rows'] : [];

    $statusBadge = function (string $status): string {
        return match ($status) {
            'ok' => 'admin-badge admin-badge-success',
            'failed' => 'admin-badge admin-badge-danger',
            default => 'admin-badge',
        };
    };

    $value = function (array $row, array $keys, string $default = '-'): string {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return (string) $row[$key];
            }
        }

        return $default;
    };

    $isTruthy = function (string $value): bool {
        $value = strtolower(trim($value));
        return in_array($value, ['true', 'yes', '1'], true);
    };

    $recordUrl = function (string $dataset, array $row) use ($value): string {
        $id = $value($row, ['.id', 'id'], '');
        $username = $value($row, ['_greennet_username', 'name', 'user', 'username'], '');

        $url = '/admin/api/record?dataset=' . urlencode($dataset);

        if ($id !== '') {
            $url .= '&id=' . urlencode($id);
        } elseif ($username !== '') {
            $url .= '&username=' . urlencode($username);
        }

        return $url;
    };

    $isDiscovery = (($selected['type'] ?? '') === 'discovery_rows');
    $isProfile = (($selected['type'] ?? '') === 'profile_rows');
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">API Data Browser</h1>

        <p class="admin-page-description">
            جدول مختصر ونظيف لبيانات MikroTik. التفاصيل الكاملة تفتح في صفحة منفصلة لكل سجل.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/api/diagnostics">API Diagnostics</a>
        <a class="admin-mini-btn" href="/admin/routeros/discovery">Discovery</a>
        <a class="admin-mini-btn" href="/admin/logs">Logs</a>
    </div>
</div>

<div class="api-browser-note">
    هذه الصفحة قراءة فقط. لا يوجد أي أمر تعديل على MikroTik. كلمات المرور والحقول الحساسة مخفية.
</div>

<div class="admin-tabs">
    <?php foreach ($datasets as $key => $dataset): ?>
        <?php
            $result = $results[$key] ?? [];
            $count = (int) ($result['total_count'] ?? 0);
            $status = (string) ($result['status'] ?? 'failed');

            $url = '/admin/api/browser?dataset=' . urlencode((string) $key);

            if ($query !== '') {
                $url .= '&q=' . urlencode($query);
            }
        ?>

        <a class="admin-tab <?= $selectedKey === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($url) ?>">
            <span><?= htmlspecialchars((string) ($dataset['title'] ?? $key)) ?></span>
            <span class="admin-tab-count"><?= htmlspecialchars((string) $count) ?></span>
            <?php if ($status !== 'ok'): ?>
                <span>⚠️</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">Dataset</div>
        <div class="admin-stat-value" style="font-size:18px;">
            <?= htmlspecialchars((string) ($selected['title'] ?? $selectedKey)) ?>
        </div>
        <div class="admin-stat-note">
            <?= htmlspecialchars((string) ($selected['description'] ?? '-')) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Status</div>
        <div class="admin-stat-value" style="font-size:16px;">
            <span class="<?= htmlspecialchars($statusBadge((string) ($selected['status'] ?? 'failed'))) ?>">
                <?= htmlspecialchars((string) ($selected['status_label'] ?? '-')) ?>
            </span>
        </div>
        <div class="admin-stat-note">
            <?= htmlspecialchars((string) ($selected['message'] ?? '-')) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Rows</div>
        <div class="admin-stat-value">
            <?= htmlspecialchars((string) ($selected['filtered_count'] ?? 0)) ?>
        </div>
        <div class="admin-stat-note">
            Total:
            <?= htmlspecialchars((string) ($selected['total_count'] ?? 0)) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Time</div>
        <div class="admin-stat-value" style="font-size:20px;">
            <?= htmlspecialchars((string) ($selected['duration_ms'] ?? 0)) ?> ms
        </div>
        <div class="admin-stat-note">
            API read duration
        </div>
    </div>

</div>

<form class="admin-filter-bar" method="get" action="/admin/api/browser" style="grid-template-columns:1fr 1.6fr auto;">
    <div class="form-group">
        <label>Dataset</label>
        <select name="dataset">
            <?php foreach ($datasets as $key => $dataset): ?>
                <option value="<?= htmlspecialchars((string) $key) ?>" <?= $selectedKey === $key ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($dataset['title'] ?? $key)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>بحث داخل النتائج</label>
        <input
            type="text"
            name="q"
            value="<?= htmlspecialchars($query) ?>"
            placeholder="username / profile / IP / comment / disabled"
        >
    </div>

    <button class="btn btn-primary" type="submit">
        تطبيق
    </button>
</form>

<section class="admin-section-card">
    <h2 class="admin-section-title">
        البيانات
    </h2>

    <p class="admin-section-subtitle">
        المصدر:
        <span class="api-source-pill"><?= htmlspecialchars((string) ($selected['source'] ?? $selectedKey)) ?></span>
    </p>

    <?php if (($selected['status'] ?? '') !== 'ok'): ?>
        <div class="notice" style="background:#fee2e2;color:#991b1b;">
            فشل قراءة هذا القسم:
            <?= htmlspecialchars((string) ($selected['message'] ?? '-')) ?>
        </div>
    <?php elseif (count($rows) === 0): ?>
        <div class="admin-empty-state">
            لا توجد بيانات مطابقة.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">

            <?php if ($isDiscovery): ?>

                <table class="admin-table" style="min-width:900px;">
                    <thead>
                        <tr>
                            <th>Command</th>
                            <th>Status</th>
                            <th>Rows</th>
                            <th>Error</th>
                            <th>First Row Keys</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $ok = (string) ($row['ok'] ?? 'no'); ?>

                            <tr>
                                <td>
                                    <span class="admin-code"><?= htmlspecialchars((string) ($row['command'] ?? '-')) ?></span>
                                </td>

                                <td>
                                    <span class="<?= $isTruthy($ok) ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-danger' ?>">
                                        <?= htmlspecialchars($ok) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="admin-badge"><?= htmlspecialchars((string) ($row['rows_count'] ?? 0)) ?></span>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($row['error'] ?? '') !== '' ? $row['error'] : '-')) ?>
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars((string) (($row['first_row_keys'] ?? '') !== '' ? $row['first_row_keys'] : '-')) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($isProfile): ?>

                <table class="admin-table" style="min-width:900px;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>ID</th>
                            <th>Rate Limit</th>
                            <th>Shared Users</th>
                            <th>Comment</th>
                            <th>تفاصيل</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <div class="admin-table-title">
                                        <?= htmlspecialchars($value($row, ['name', 'profile'])) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars($value($row, ['.id', 'id'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars($value($row, ['rate-limit', 'rate_limit'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($value($row, ['shared-users', 'shared_users'])) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($value($row, ['comment'])) ?>
                                </td>

                                <td>
                                    <a class="admin-row-action primary" href="<?= htmlspecialchars($recordUrl($selectedKey, $row)) ?>">
                                        عرض التفاصيل
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php else: ?>

                <table class="admin-table" style="min-width:980px;">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>CRM</th>
                            <th>Source</th>
                            <th>ID</th>
                            <th>Profile</th>
                            <th>Disabled</th>
                            <th>IP / Address</th>
                            <th>MAC / Caller ID</th>
                            <th>Uptime</th>
                            <th>Usage</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $username = $value($row, ['_greennet_username', 'name', 'user', 'username'], '');
                                $crmFound = (string) ($row['_crm_found'] ?? 'no');
                                $disabled = $value($row, ['disabled'], '-');

                                $bytesIn = (int) preg_replace('/\D+/', '', $value($row, ['bytes-in', 'bytes_in'], '0'));
                                $bytesOut = (int) preg_replace('/\D+/', '', $value($row, ['bytes-out', 'bytes_out'], '0'));
                                $totalBytes = $bytesIn + $bytesOut;

                                $encodedUsername = urlencode($username);
                            ?>

                            <tr>
                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars($username !== '' ? $username : '-') ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($crmFound === 'yes'): ?>
                                        <span class="admin-badge admin-badge-success">موجود</span>
                                        <div class="admin-table-subtitle">
                                            <?= htmlspecialchars((string) ($row['_crm_name'] ?? '')) ?>
                                        </div>
                                    <?php elseif ($crmFound === 'unknown'): ?>
                                        <span class="admin-badge">غير معروف</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-warning">غير موجود</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="api-source-pill">
                                        <?= htmlspecialchars($value($row, ['_greennet_source'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars($value($row, ['.id', 'id'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars($value($row, ['profile', 'actual-profile'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="<?= $isTruthy($disabled) ? 'admin-badge admin-badge-danger' : 'admin-badge admin-badge-success' ?>">
                                        <?= htmlspecialchars($disabled) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars($value($row, ['address', 'ip'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars($value($row, ['mac-address', 'caller-id'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($value($row, ['uptime'])) ?>
                                </td>

                                <td>
                                    <span class="admin-badge">
                                        <?= htmlspecialchars((string) $totalBytes) ?> B
                                    </span>
                                </td>

                                <td>
                                    <div class="admin-row-actions">
                                        <a class="admin-row-action primary" href="<?= htmlspecialchars($recordUrl($selectedKey, $row)) ?>">
                                            عرض التفاصيل
                                        </a>

                                        <?php if ($username !== ''): ?>
                                            <?php if ($crmFound === 'yes'): ?>
                                                <a class="admin-row-action" href="/admin/customers/profile?username=<?= htmlspecialchars($encodedUsername) ?>">
                                                    ملف CRM
                                                </a>
                                            <?php else: ?>
                                                <a class="admin-row-action" href="/admin/search?q=<?= htmlspecialchars($encodedUsername) ?>">
                                                    بحث / استيراد
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

        </div>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">
        ملاحظات مهمة
    </h2>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">✓</div>
            <div>
                <strong>العرض مختصر</strong>
                <br>
                الجدول يعرض الحقول الأساسية فقط. التفاصيل الكاملة داخل صفحة منفصلة.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">📊</div>
            <div>
                <strong>عدادات الاستخدام</strong>
                <br>
                صفحة التفاصيل تعرض Upload وDownload وTotal بشكل أوضح.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon warning">!</div>
            <div>
                <strong>زر تصفير العدادات</strong>
                <br>
                سيظهر داخل صفحة التفاصيل كـ Preview فقط، بدون تنفيذ فعلي حالياً.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">↻</div>
            <div>
                <strong>الخطوة التالية</strong>
                <br>
                بعد تثبيت التفاصيل، نضيف Reset Counters Dry Run بشكل أوسع.
            </div>
        </div>

    </div>
</section>