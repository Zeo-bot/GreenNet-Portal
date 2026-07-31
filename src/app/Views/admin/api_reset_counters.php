<?php
    $dataset = is_array($dataset ?? null) ? $dataset : [];
    $record = is_array($record ?? null) ? $record : [];
    $crmCustomer = is_array($crm_customer ?? null) ? $crm_customer : [];
    $usage = is_array($usage ?? null) ? $usage : [];
    $resetPreview = is_array($reset_preview ?? null) ? $reset_preview : [];
    $result = is_array($result ?? null) ? $result : null;

    $datasetKey = (string) ($dataset_key ?? '');
    $username = (string) ($username ?? '');
    $recordId = (string) ($record_id ?? '');
    $error = (string) ($error ?? '');

    $crmFound = count($crmCustomer) > 0;

    $queryParams = http_build_query([
        'dataset' => $datasetKey,
        'id' => $recordId,
        'username' => $username,
    ]);

    $recordUrl = '/admin/api/record?' . $queryParams;
    $browserUrl = '/admin/api/browser?dataset=' . urlencode($datasetKey);

    $command = (string) ($resetPreview['command'] ?? '');
    $parameters = is_array($resetPreview['parameters'] ?? null) ? $resetPreview['parameters'] : [];
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">تصفير عدادات RouterOS</h1>

        <p class="admin-page-description">
            تنفيذ حقيقي ومحمي لتصفير عدادات السجل باستخدام RouterOS exact ID.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="<?= htmlspecialchars($recordUrl) ?>">رجوع للتفاصيل</a>
        <a class="admin-mini-btn" href="<?= htmlspecialchars($browserUrl) ?>">API Browser</a>
        <a class="admin-mini-btn" href="/admin/logs">Logs</a>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="notice" style="background:#fee2e2;color:#991b1b;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($result !== null): ?>
    <div class="notice" style="<?= !empty($result['ok']) ? 'background:#f0fdf4;color:#166534;' : 'background:#fee2e2;color:#991b1b;' ?>">
        <?= htmlspecialchars((string) ($result['message'] ?? '-')) ?>
    </div>
<?php endif; ?>

<?php if ($error === '' && count($record) > 0): ?>

    <div class="admin-stats-grid">

        <div class="admin-stat-card">
            <div class="admin-stat-label">Username</div>
            <div class="admin-stat-value" style="font-size:19px;">
                <span class="admin-code"><?= htmlspecialchars($username !== '' ? $username : '-') ?></span>
            </div>
            <div class="admin-stat-note">
                Dataset: <?= htmlspecialchars($datasetKey) ?>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Record ID</div>
            <div class="admin-stat-value" style="font-size:19px;">
                <span class="admin-code"><?= htmlspecialchars($recordId !== '' ? $recordId : '-') ?></span>
            </div>
            <div class="admin-stat-note">
                Source: <?= htmlspecialchars((string) ($dataset['source'] ?? '-')) ?>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Total Usage</div>
            <div class="admin-stat-value" style="font-size:20px;">
                <?= htmlspecialchars((string) ($usage['total_bytes_human'] ?? '0 B')) ?>
            </div>
            <div class="admin-stat-note">
                Raw: <?= htmlspecialchars((string) ($usage['total_bytes'] ?? 0)) ?> bytes
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Mode</div>
            <div class="admin-stat-value" style="font-size:18px;">
                تنفيذ محمي
            </div>
            <div class="admin-stat-note">
                يتطلب تأكيدًا صريحًا
            </div>
        </div>

    </div>

    <div class="admin-two-columns">

        <section class="admin-section-card">
            <h2 class="admin-section-title">عدادات الاستخدام الحالية</h2>

            <div class="admin-checklist" style="grid-template-columns:1fr;">

                <div class="admin-check-item">
                    <div class="admin-check-icon">⬇</div>
                    <div>
                        <strong>Download / bytes-out</strong>
                        <br>
                        <?= htmlspecialchars((string) ($usage['bytes_out_human'] ?? '0 B')) ?>
                        —
                        <span class="admin-code"><?= htmlspecialchars((string) ($usage['bytes_out'] ?? 0)) ?></span>
                        bytes
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">⬆</div>
                    <div>
                        <strong>Upload / bytes-in</strong>
                        <br>
                        <?= htmlspecialchars((string) ($usage['bytes_in_human'] ?? '0 B')) ?>
                        —
                        <span class="admin-code"><?= htmlspecialchars((string) ($usage['bytes_in'] ?? 0)) ?></span>
                        bytes
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">Σ</div>
                    <div>
                        <strong>Total</strong>
                        <br>
                        <?= htmlspecialchars((string) ($usage['total_bytes_human'] ?? '0 B')) ?>
                        —
                        <span class="admin-code"><?= htmlspecialchars((string) ($usage['total_bytes'] ?? 0)) ?></span>
                        bytes
                    </div>
                </div>

                <div class="admin-check-item">
                    <div class="admin-check-icon">📦</div>
                    <div>
                        <strong>Packets</strong>
                        <br>
                        In:
                        <span class="admin-code"><?= htmlspecialchars((string) ($usage['packets_in'] ?? 0)) ?></span>
                        —
                        Out:
                        <span class="admin-code"><?= htmlspecialchars((string) ($usage['packets_out'] ?? 0)) ?></span>
                        —
                        Total:
                        <span class="admin-code"><?= htmlspecialchars((string) ($usage['total_packets'] ?? 0)) ?></span>
                    </div>
                </div>

            </div>
        </section>

        <section class="admin-section-card">
            <h2 class="admin-section-title">تفاصيل الأمر</h2>

            <?php if (!empty($resetPreview['supported'])): ?>

                <div class="notice" style="background:#eff6ff;color:#1d4ed8;">
                    سيُنفذ الأمر التالي فعليًا بعد التأكيد، ثم يعاد قراءة السجل بالمعرّف الدقيق.
                </div>

                <div class="admin-json-box">
<?= htmlspecialchars(json_encode([
    'mode' => 'guarded_write',
    'requires_confirmation' => true,
    'command' => $command,
    'parameters' => $parameters,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>
                </div>

                <form method="post" action="/admin/api/reset-counters/execute" style="margin-top:16px;" onsubmit="return confirm('سيتم تصفير العدادات فعليًا على الراوتر. متابعة؟');">

                    <input type="hidden" name="dataset" value="<?= htmlspecialchars($datasetKey) ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($recordId) ?>">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">

                    <div class="form-group">
                        <label>اكتب RESET_CONFIRM لتنفيذ التصفير</label>
                        <input type="text" name="confirmation" placeholder="RESET_CONFIRM" required>
                    </div>

                    <button class="btn btn-danger" type="submit">
                        تنفيذ تصفير العدادات
                    </button>

                </form>

            <?php else: ?>

                <div class="notice" style="background:#fff7ed;color:#92400e;">
                    تصفير العدادات غير مدعوم لهذا النوع حالياً.
                    <br>
                    <?= htmlspecialchars((string) ($resetPreview['message'] ?? '')) ?>
                </div>

            <?php endif; ?>

        </section>

    </div>

    <section class="admin-section-card">
        <h2 class="admin-section-title">ضمانات التنفيذ</h2>

        <div class="admin-checklist">

            <div class="admin-check-item">
                <div class="admin-check-icon">✓</div>
                <div>
                    <strong>Exact-ID فقط</strong>
                    <br>
                    لا يُسمح باسم مستخدم أو wildcard كهدف للحذف أو التصفير.
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon">🧾</div>
                <div>
                    <strong>Audit كامل ومنقّح</strong>
                    <br>
                    يُسجل الأمر والهدف والنتيجة دون كلمات مرور أو أسرار.
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon warning">!</div>
                <div>
                    <strong>تحقق بعد التنفيذ</strong>
                    <br>
                    يعاد قراءة السجل نفسه بعد نجاح أمر RouterOS.
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon future">🔒</div>
                <div>
                    <strong>Write Safety</strong>
                    <br>
                    يظل التنفيذ خاضعًا للتفعيل والنسخة الحديثة والتأكيد.
                </div>
            </div>

        </div>
    </section>

<?php endif; ?>
