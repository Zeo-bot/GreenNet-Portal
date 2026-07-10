<?php
    $settings = is_array($settings ?? null) ? $settings : [];
    $queue = is_array($queue ?? null) ? $queue : [];
    $apiAudits = is_array($api_audits ?? null) ? $api_audits : [];
    $message = (string) ($message ?? '');
    $messageType = (string) ($message_type ?? 'success');

    $checked = function (string $key) use ($settings): string {
        return (($settings[$key] ?? '') === 'true') ? 'checked' : '';
    };

    $backupDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/storage/backups';
    $backupFiles = [];

    if (is_dir($backupDir)) {
        foreach ((glob($backupDir . '/*') ?: []) as $file) {
            if (is_file($file)) {
                $backupFiles[] = [
                    'name' => basename($file),
                    'time' => filemtime($file) ?: 0,
                    'size' => filesize($file) ?: 0,
                ];
            }
        }
    }

    usort($backupFiles, static fn (array $a, array $b): int => (int) $b['time'] <=> (int) $a['time']);

    $latestBackup = $backupFiles[0] ?? null;
    $latestBackupAgeHours = null;

    if ($latestBackup) {
        $latestBackupAgeHours = (time() - (int) $latestBackup['time']) / 3600;
    }

    $backupFresh = $latestBackupAgeHours !== null && $latestBackupAgeHours <= 24;

    $queuePending = 0;
    $queueFailed = 0;

    foreach ($queue as $item) {
        $status = (string) ($item['status'] ?? 'pending');

        if ($status === 'pending') {
            $queuePending++;
        }

        if ($status === 'failed') {
            $queueFailed++;
        }
    }

    $lastApiAudit = $apiAudits[0] ?? null;

    $safeMode = ($settings['greennet_safe_mode'] ?? 'true') === 'true';
    $writeEnabled = ($settings['mikrotik_write_enabled'] ?? 'false') === 'true';
    $backupGuard = ($settings['backup_guard_enabled'] ?? 'true') === 'true';
    $dryRun = ($settings['dry_run_required'] ?? 'true') === 'true';
    $confirm = ($settings['confirm_required'] ?? 'true') === 'true';

    $readyForSprint10DryRun = $safeMode && !$writeEnabled && $backupGuard && $dryRun && $confirm;
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Write Safety</h1>
        <p class="admin-page-description">
            طبقة أمان وتجهيز قبل أي كتابة مستقبلية على MikroTik. هذه الصفحة لا تنفذ أي أمر على الراوتر.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/setup-wizard">Setup Wizard</a>
        <a class="admin-mini-btn" href="/admin/audit">Audit</a>
        <a class="admin-mini-btn" href="/admin/backup">Backup</a>
        <a class="admin-mini-btn" href="/admin/api/diagnostics">API Diagnostics</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="notice" style="<?= $messageType === 'warning' ? 'background:#fff7ed;color:#92400e;' : 'background:#f0fdf4;color:#166534;' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">Preflight قبل Sprint 10</h2>

    <div class="admin-stats-grid">

        <div class="admin-stat-card">
            <div class="admin-stat-label">Safe Mode</div>
            <div class="admin-stat-value"><?= $safeMode ? 'ON' : 'OFF' ?></div>
            <div class="admin-stat-note"><?= $safeMode ? 'ممتاز قبل Sprint 10' : 'يفضل تفعيله' ?></div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Write Enabled</div>
            <div class="admin-stat-value"><?= $writeEnabled ? 'ON' : 'OFF' ?></div>
            <div class="admin-stat-note"><?= !$writeEnabled ? 'صحيح حالياً' : 'انتبه: الكتابة مفعلة' ?></div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Backup آخر 24 ساعة</div>
            <div class="admin-stat-value"><?= $backupFresh ? 'OK' : 'NO' ?></div>
            <div class="admin-stat-note">
                <?php if ($latestBackup): ?>
                    <?= htmlspecialchars((string) $latestBackup['name']) ?>
                <?php else: ?>
                    لا توجد نسخة
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Queue</div>
            <div class="admin-stat-value"><?= htmlspecialchars((string) count($queue)) ?></div>
            <div class="admin-stat-note">
                Pending: <?= htmlspecialchars((string) $queuePending) ?> /
                Failed: <?= htmlspecialchars((string) $queueFailed) ?>
            </div>
        </div>

    </div>

    <div class="admin-checklist" style="margin-top:16px;">

        <div class="admin-check-item">
            <div class="admin-check-icon <?= $readyForSprint10DryRun ? '' : 'future' ?>">
                <?= $readyForSprint10DryRun ? '✓' : '!' ?>
            </div>
            <div>
                <strong>حالة الدخول إلى Sprint 10 Dry Run</strong>
                <br>
                <?php if ($readyForSprint10DryRun): ?>
                    الوضع مناسب للدخول إلى أول مرحلة Dry Run، مع بقاء الكتابة الحقيقية مقفلة.
                <?php else: ?>
                    راجع الإعدادات. الأفضل قبل Sprint 10: Safe Mode ON و Write Enabled OFF و Dry Run ON و Confirm ON.
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon <?= $backupFresh ? '' : 'future' ?>">
                <?= $backupFresh ? '✓' : '!' ?>
            </div>
            <div>
                <strong>Backup Guard</strong>
                <br>
                <?php if ($latestBackup): ?>
                    آخر Backup:
                    <span class="admin-code"><?= htmlspecialchars(date('Y-m-d H:i:s', (int) $latestBackup['time'])) ?></span>
                    <?php if (!$backupFresh): ?>
                        <br>
                        يفضل إنشاء Backup حديث قبل Sprint 10.
                    <?php endif; ?>
                <?php else: ?>
                    لا يوجد Backup معروف. أنشئ Backup كامل قبل أي Write.
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">🧪</div>
            <div>
                <strong>API Status</strong>
                <br>
                <?php if ($lastApiAudit): ?>
                    آخر API Audit:
                    <span class="admin-code"><?= htmlspecialchars((string) ($lastApiAudit['created_at'] ?? '-')) ?></span>
                <?php else: ?>
                    لا توجد API Audit Logs بعد. افحص الاتصال من API Diagnostics قبل Sprint 10.
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">إعدادات الأمان</h2>

    <form method="post" action="/admin/write-safety">
        <div class="admin-checklist">

            <label class="admin-check-item">
                <div class="admin-check-icon">🧯</div>
                <div>
                    <strong>Safe Mode</strong>
                    <br>
                    <span>يبقي النظام في وضع آمن قبل التنفيذ الحقيقي.</span>
                    <br>
                    <input type="checkbox" name="greennet_safe_mode" value="1" <?= $checked('greennet_safe_mode') ?>>
                    مفعّل
                </div>
            </label>

            <label class="admin-check-item">
                <div class="admin-check-icon">🔒</div>
                <div>
                    <strong>MikroTik Write Enabled</strong>
                    <br>
                    <span>اتركه غير مفعّل الآن. سنستخدمه لاحقاً في Sprint 10.</span>
                    <br>
                    <input type="checkbox" name="mikrotik_write_enabled" value="1" <?= $checked('mikrotik_write_enabled') ?>>
                    السماح بالكتابة مستقبلاً
                </div>
            </label>

            <label class="admin-check-item">
                <div class="admin-check-icon">💾</div>
                <div>
                    <strong>Backup Guard</strong>
                    <br>
                    <span>يتطلب Backup حديث قبل العمليات الخطيرة.</span>
                    <br>
                    <input type="checkbox" name="backup_guard_enabled" value="1" <?= $checked('backup_guard_enabled') ?>>
                    مفعّل
                </div>
            </label>

            <label class="admin-check-item">
                <div class="admin-check-icon">🧪</div>
                <div>
                    <strong>Dry Run Required</strong>
                    <br>
                    <span>أي أمر MikroTik يبدأ بمحاكاة قبل التنفيذ.</span>
                    <br>
                    <input type="checkbox" name="dry_run_required" value="1" <?= $checked('dry_run_required') ?>>
                    مفعّل
                </div>
            </label>

            <label class="admin-check-item">
                <div class="admin-check-icon">✅</div>
                <div>
                    <strong>Confirmation Required</strong>
                    <br>
                    <span>يتطلب تأكيد واضح قبل التنفيذ الحقيقي.</span>
                    <br>
                    <input type="checkbox" name="confirm_required" value="1" <?= $checked('confirm_required') ?>>
                    مفعّل
                </div>
            </label>

            <label class="admin-check-item">
                <div class="admin-check-icon">📥</div>
                <div>
                    <strong>Transaction Queue</strong>
                    <br>
                    <span>أي عملية مستقبلية تدخل Queue حتى لا تضيع عند انقطاع الاتصال.</span>
                    <br>
                    <input type="checkbox" name="transaction_queue_enabled" value="1" <?= $checked('transaction_queue_enabled') ?>>
                    مفعّل
                </div>
            </label>

        </div>

        <div style="margin-top:16px;">
            <button class="btn btn-primary" type="submit">حفظ إعدادات الأمان</button>
        </div>
    </form>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">Transaction Queue</h2>

    <?php if (count($queue) === 0): ?>
        <div class="admin-empty-state">
            لا توجد عمليات Queue حالياً. هذا طبيعي قبل Sprint 10.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Action</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['action'] ?? '')) ?></td>
                            <td><span class="admin-code"><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></span></td>
                            <td><span class="admin-badge"><?= htmlspecialchars((string) ($row['status'] ?? 'pending')) ?></span></td>
                            <td><?= htmlspecialchars((string) ($row['attempts'] ?? 0)) ?></td>
                            <td><span class="admin-code"><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">API Audit Logs</h2>

    <?php if (count($apiAudits) === 0): ?>
        <div class="admin-empty-state">
            لا توجد API Audit Logs حالياً. سيتم استخدامها عند بدء أوامر MikroTik الحقيقية.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:1000px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Username</th>
                        <th>Dry Run</th>
                        <th>Executed</th>
                        <th>Success</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apiAudits as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['admin_username'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['action'] ?? '')) ?></td>
                            <td><span class="admin-code"><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></span></td>
                            <td><?= ((int) ($row['dry_run'] ?? 1) === 1) ? 'نعم' : 'لا' ?></td>
                            <td><?= ((int) ($row['executed'] ?? 0) === 1) ? 'نعم' : 'لا' ?></td>
                            <td><?= ((int) ($row['success'] ?? 0) === 1) ? 'نعم' : 'لا' ?></td>
                            <td><span class="admin-code"><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>