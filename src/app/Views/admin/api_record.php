<?php
    $dataset = is_array($dataset ?? null) ? $dataset : [];
    $record = is_array($record ?? null) ? $record : [];
    $crmCustomer = is_array($crm_customer ?? null) ? $crm_customer : [];
    $usage = is_array($usage ?? null) ? $usage : [];
    $resetPreview = is_array($reset_preview ?? null) ? $reset_preview : [];

    $datasetKey = (string) ($dataset_key ?? '');
    $username = (string) ($username ?? '');
    $error = (string) ($error ?? '');

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

    $recordId = $value($record, ['.id', 'id'], '');
    $profile = $value($record, ['profile', 'actual-profile'], '-');
    $disabled = $value($record, ['disabled'], '-');
    $address = $value($record, ['address', 'ip'], '-');
    $mac = $value($record, ['mac-address', 'caller-id'], '-');
    $comment = $value($record, ['comment'], '-');

    $crmFound = count($crmCustomer) > 0;

    $crmName = '-';

    if ($crmFound) {
        $crmName = (string) (
            $crmCustomer['display_name']
            ?? $crmCustomer['full_name']
            ?? $crmCustomer['name']
            ?? $username
        );
    }

    $encodedUsername = urlencode($username);

    $resetUrl = '/admin/api/reset-counters?' . http_build_query([
        'dataset' => $datasetKey,
        'id' => $recordId,
        'username' => $username,
    ]);
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">API Record Details</h1>

        <p class="admin-page-description">
            تفاصيل كاملة ومنظمة لسجل واحد من MikroTik API. هذه الصفحة قراءة فقط، ولا تنفذ أي تعديل.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/api/browser?dataset=<?= urlencode($datasetKey) ?>">رجوع للـ Browser</a>
        <a class="admin-mini-btn" href="/admin/api/diagnostics">Diagnostics</a>
        <?php if ($username !== ''): ?>
            <a class="admin-mini-btn" href="/admin/search?q=<?= htmlspecialchars($encodedUsername) ?>">بحث</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="notice" style="background:#fee2e2;color:#991b1b;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($error === '' && count($record) > 0): ?>

    <section class="admin-section-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">

            <div>
                <h2 class="admin-section-title" style="margin-bottom:6px;">
                    <?= htmlspecialchars($username !== '' ? $username : ($value($record, ['name', 'user', 'username'], 'Record'))) ?>
                </h2>

                <p class="admin-section-subtitle" style="margin:0;">
                    Dataset:
                    <span class="api-source-pill"><?= htmlspecialchars($datasetKey) ?></span>
                    —
                    Source:
                    <span class="api-source-pill"><?= htmlspecialchars((string) ($dataset['source'] ?? '-')) ?></span>
                </p>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($crmFound): ?>
                    <span class="admin-badge admin-badge-success">CRM موجود</span>
                <?php else: ?>
                    <span class="admin-badge admin-badge-warning">CRM غير موجود</span>
                <?php endif; ?>

                <span class="<?= $isTruthy($disabled) ? 'admin-badge admin-badge-danger' : 'admin-badge admin-badge-success' ?>">
                    disabled: <?= htmlspecialchars($disabled) ?>
                </span>
            </div>

        </div>
    </section>

    <div class="admin-stats-grid">

        <div class="admin-stat-card">
            <div class="admin-stat-label">Download / bytes-out</div>
            <div class="admin-stat-value" style="font-size:21px;">
                <?= htmlspecialchars((string) ($usage['bytes_out_human'] ?? '0 B')) ?>
            </div>
            <div class="admin-stat-note">
                Raw:
                <?= htmlspecialchars((string) ($usage['bytes_out'] ?? 0)) ?>
                bytes
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Upload / bytes-in</div>
            <div class="admin-stat-value" style="font-size:21px;">
                <?= htmlspecialchars((string) ($usage['bytes_in_human'] ?? '0 B')) ?>
            </div>
            <div class="admin-stat-note">
                Raw:
                <?= htmlspecialchars((string) ($usage['bytes_in'] ?? 0)) ?>
                bytes
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Total Usage</div>
            <div class="admin-stat-value" style="font-size:21px;">
                <?= htmlspecialchars((string) ($usage['total_bytes_human'] ?? '0 B')) ?>
            </div>
            <div class="admin-stat-note">
                Raw:
                <?= htmlspecialchars((string) ($usage['total_bytes'] ?? 0)) ?>
                bytes
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Uptime</div>
            <div class="admin-stat-value" style="font-size:21px;">
                <?= htmlspecialchars((string) ($usage['uptime'] ?? '-')) ?>
            </div>
            <div class="admin-stat-note">
                Limit:
                <?= htmlspecialchars((string) ($usage['limit_uptime'] ?? '-')) ?>
            </div>
        </div>

    </div>

    <div class="admin-two-columns">

        <section class="admin-section-card">
            <h2 class="admin-section-title">ملخص الحساب</h2>

            <div class="admin-checklist" style="grid-template-columns:1fr;">

                <div class="admin-check-item">
                    <div class="admin-check-icon">👤</div>
                    <div>
                        <strong>Username</strong>
                        <br>
                        <span class="admin-code"><?= htmlspecialchars($username !== '' ? $username : '-') ?></span>
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">🆔</div>
                    <div>
                        <strong>ID</strong>
                        <br>
                        <span class="admin-code"><?= htmlspecialchars($recordId !== '' ? $recordId : '-') ?></span>
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">📦</div>
                    <div>
                        <strong>Profile</strong>
                        <br>
                        <span class="admin-code"><?= htmlspecialchars($profile) ?></span>
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">📝</div>
                    <div>
                        <strong>Comment</strong>
                        <br>
                        <?= nl2br(htmlspecialchars($comment)) ?>
                    </div>
                </div>

            </div>
        </section>

        <section class="admin-section-card">
            <h2 class="admin-section-title">حالة CRM</h2>

            <?php if ($crmFound): ?>
                <div class="notice" style="background:#f0fdf4;color:#166534;">
                    هذا المستخدم موجود داخل CRM باسم:
                    <strong><?= htmlspecialchars($crmName) ?></strong>
                </div>

                <a class="btn btn-primary" href="/admin/customers/profile?username=<?= htmlspecialchars($encodedUsername) ?>">
                    فتح ملف CRM
                </a>

                <a class="btn btn-outline" href="/dashboard?username=<?= htmlspecialchars($encodedUsername) ?>">
                    معاينة لوحة المشترك
                </a>
            <?php else: ?>
                <div class="notice" style="background:#fff7ed;color:#92400e;">
                    هذا المستخدم غير موجود في CRM المحلي. يفضل استيراده أو ربطه قبل أي أوامر لاحقة.
                </div>

                <?php if ($username !== ''): ?>
                    <a class="btn btn-primary" href="/admin/search?q=<?= htmlspecialchars($encodedUsername) ?>">
                        بحث / استيراد
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </section>

    </div>

    <div class="admin-two-columns">

        <section class="admin-section-card">
            <h2 class="admin-section-title">Network Info</h2>

            <div class="admin-stats-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">

                <div class="admin-stat-card">
                    <div class="admin-stat-label">IP / Address</div>
                    <div class="admin-stat-value" style="font-size:17px;direction:ltr;">
                        <?= htmlspecialchars($address) ?>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-label">MAC / Caller ID</div>
                    <div class="admin-stat-value" style="font-size:15px;direction:ltr;">
                        <?= htmlspecialchars($mac) ?>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-label">Packets In</div>
                    <div class="admin-stat-value" style="font-size:18px;">
                        <?= htmlspecialchars((string) ($usage['packets_in'] ?? 0)) ?>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-label">Packets Out</div>
                    <div class="admin-stat-value" style="font-size:18px;">
                        <?= htmlspecialchars((string) ($usage['packets_out'] ?? 0)) ?>
                    </div>
                </div>

            </div>
        </section>

        <section class="admin-section-card">
            <h2 class="admin-section-title">Reset Counters</h2>

            <?php if (!empty($resetPreview['supported'])): ?>
                <div class="notice" style="background:#eff6ff;color:#1d4ed8;">
                    تصفير العدادات مدعوم لهذا النوع، لكن التنفيذ الحالي Dry Run فقط.
                </div>

                <a class="btn btn-danger" href="<?= htmlspecialchars($resetUrl) ?>">
                    فتح صفحة تصفير العدادات Dry Run
                </a>
            <?php else: ?>
                <div class="notice" style="background:#fff7ed;color:#92400e;">
                    تصفير العدادات غير مفعّل لهذا النوع حالياً.
                    <br>
                    <?= htmlspecialchars((string) ($resetPreview['message'] ?? '')) ?>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <section class="admin-section-card">
        <h2 class="admin-section-title">كل الحقول الخام Raw Fields</h2>

        <p class="admin-section-subtitle">
            كل الحقول القادمة من MikroTik API بعد إخفاء الحقول الحساسة.
        </p>

        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:720px;">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($record as $field => $fieldValue): ?>
                        <tr>
                            <td>
                                <span class="admin-code">
                                    <?= htmlspecialchars((string) $field) ?>
                                </span>
                            </td>

                            <td>
                                <div style="direction:ltr;text-align:left;white-space:pre-wrap;word-break:break-word;">
                                    <?= htmlspecialchars((string) $fieldValue !== '' ? (string) $fieldValue : '-') ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    </section>

<?php endif; ?>