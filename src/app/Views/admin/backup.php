<?php
    $flashType = $flash['type'] ?? '';
    $flashMessage = $flash['message'] ?? '';

    $storedBackups = is_array($stored_backups ?? null) ? $stored_backups : [];
    $mediaStats = is_array($media_stats ?? null) ? $media_stats : [];

    $formatBytes = function (int $bytes): string {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            النسخ الاحتياطي والاستعادة
        </h1>

        <p class="admin-page-description">
            إدارة النسخ الاحتياطية لقاعدة البيانات، والصور، والشعار، وخلفيات الدخول.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin">لوحة المدير</a>
        <a class="admin-mini-btn" href="/admin/media">الصور والهوية</a>
        <a class="admin-mini-btn" href="/admin/logs">Logs</a>
    </div>
</div>

<?php if ($flashMessage !== ''): ?>
    <div class="notice" style="<?= $flashType === 'success' ? 'background:#f0fdf4;color:#166534;' : 'background:#fee2e2;color:#991b1b;' ?>">
        <?= htmlspecialchars($flashMessage) ?>
    </div>
<?php endif; ?>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">قاعدة البيانات</div>
        <div class="admin-stat-value" style="font-size:16px;">
            <?= !empty($database_exists) ? 'موجودة' : 'غير موجودة' ?>
        </div>
        <div class="admin-stat-note">
            الحجم:
            <?= htmlspecialchars($formatBytes((int) ($database_size ?? 0))) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">ملفات الصور</div>
        <div class="admin-stat-value">
            <?= htmlspecialchars((string) ($mediaStats['files_count'] ?? 0)) ?>
        </div>
        <div class="admin-stat-note">
            الحجم:
            <?= htmlspecialchars($formatBytes((int) ($mediaStats['total_size_bytes'] ?? 0))) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Backups محفوظة</div>
        <div class="admin-stat-value">
            <?= htmlspecialchars((string) count($storedBackups)) ?>
        </div>
        <div class="admin-stat-note">
            داخل storage/backups
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">ZIP Support</div>
        <div class="admin-stat-value" style="font-size:16px;">
            <?= !empty($zip_available) ? 'متاح' : 'غير متاح' ?>
        </div>
        <div class="admin-stat-note">
            مطلوب للـ Full Backup
        </div>
    </div>

</div>

<div class="admin-two-columns">

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            إنشاء Backup
        </h2>

        <p class="admin-section-subtitle">
            SQLite Backup يحفظ قاعدة البيانات فقط. Full Backup يحفظ قاعدة البيانات مع الصور والشعار والخلفيات.
        </p>

        <div class="admin-action-grid" style="grid-template-columns:1fr 1fr;">

            <a class="admin-action-card" href="/admin/backup/download">
                <div class="admin-action-icon">🗄️</div>
                <div class="admin-action-title">SQLite Backup</div>
                <div class="admin-action-desc">
                    تنزيل نسخة من:
                    <br>
                    <span class="admin-code">database/database.sqlite</span>
                </div>
            </a>

            <a class="admin-action-card" href="/admin/backup/full">
                <div class="admin-action-icon">📦</div>
                <div class="admin-action-title">Full Backup ZIP</div>
                <div class="admin-action-desc">
                    قاعدة البيانات + الصور:
                    <br>
                    <span class="admin-code">public/media</span>
                </div>
            </a>

        </div>

        <?php if (empty($zip_available)): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;margin-top:14px;">
                ZipArchive غير متاح. إذا ظهر هذا التنبيه، أعد بناء الحاويات:
                <br>
                <span class="admin-code">docker compose up -d --build</span>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            محتوى Full Backup
        </h2>

        <p class="admin-section-subtitle">
            النسخة الكاملة تحتوي على:
        </p>

        <div class="admin-checklist" style="grid-template-columns:1fr;">

            <div class="admin-check-item">
                <div class="admin-check-icon">✓</div>
                <div>
                    <strong>قاعدة البيانات</strong>
                    <br>
                    <span class="admin-code">database/database.sqlite</span>
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon">✓</div>
                <div>
                    <strong>صور النظام</strong>
                    <br>
                    <span class="admin-code">public/media</span>
                </div>
            </div>

            <div class="admin-check-item">
                <div class="admin-check-icon">✓</div>
                <div>
                    <strong>معلومات النسخة</strong>
                    <br>
                    <span class="admin-code">backup-info.json</span>
                </div>
            </div>

        </div>
    </section>

</div>

<div class="admin-two-columns">

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            Restore SQLite
        </h2>

        <p class="admin-section-subtitle">
            يستبدل قاعدة البيانات فقط. لا يغير الصور الموجودة.
        </p>

        <form method="post" action="/admin/backup/restore" enctype="multipart/form-data">

            <div class="form-group">
                <label>ملف SQLite</label>
                <input type="file" name="backup_file" accept=".sqlite,.db" required>
            </div>

            <div class="form-group">
                <label>اكتب RESTORE للتأكيد</label>
                <input type="text" name="confirmation" placeholder="RESTORE" required>
            </div>

            <button class="btn btn-danger" type="submit">
                استعادة SQLite
            </button>

        </form>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">
            Restore Full ZIP
        </h2>

        <p class="admin-section-subtitle">
            يستعيد قاعدة البيانات ويضيف الصور من النسخة الكاملة. يتم إنشاء Full Backup تلقائياً قبل الاستعادة.
        </p>

        <form method="post" action="/admin/backup/restore-full" enctype="multipart/form-data">

            <div class="form-group">
                <label>ملف Full Backup ZIP</label>
                <input type="file" name="full_backup_file" accept=".zip" required>
            </div>

            <div class="form-group">
                <label>اكتب RESTORE_FULL للتأكيد</label>
                <input type="text" name="confirmation" placeholder="RESTORE_FULL" required>
            </div>

            <button class="btn btn-danger" type="submit">
                استعادة Full Backup
            </button>

        </form>
    </section>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">
        النسخ المحفوظة
    </h2>

    <p class="admin-section-subtitle">
        هذه الملفات محفوظة داخل:
        <span class="admin-code">storage/backups</span>
    </p>

    <?php if (count($storedBackups) === 0): ?>
        <div class="admin-empty-state">
            لا توجد نسخ محفوظة بعد.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:760px;">
                <thead>
                    <tr>
                        <th>الملف</th>
                        <th>النوع</th>
                        <th>الحجم</th>
                        <th>آخر تعديل</th>
                        <th>تنزيل</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($storedBackups as $backup): ?>
                        <tr>
                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($backup['filename'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <span class="admin-badge <?= (($backup['type'] ?? '') === 'Full ZIP') ? 'admin-badge-primary' : '' ?>">
                                    <?= htmlspecialchars((string) ($backup['type'] ?? '-')) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($formatBytes((int) ($backup['size_bytes'] ?? 0))) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars((string) ($backup['modified_at'] ?? '-')) ?>
                            </td>

                            <td>
                                <a class="admin-row-action primary" href="/admin/backup/file?file=<?= urlencode((string) ($backup['filename'] ?? '')) ?>">
                                    تنزيل
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">
        Clean Development Data
    </h2>

    <p class="admin-section-subtitle">
        استخدم هذه العملية فقط بعد أخذ نسخة احتياطية، وقبل الانتقال إلى بيانات حقيقية.
    </p>

    <form method="post" action="/admin/cleanup/development">

        <div class="admin-checklist">

            <label class="admin-check-item">
                <input type="checkbox" name="clean_customers" value="1">
                <div>
                    <strong>حذف الزبائن والدفعات والتنبيهات</strong>
                    <br>
                    ينظف customers_local + payments + notifications.
                </div>
            </label>

            <label class="admin-check-item">
                <input type="checkbox" name="clean_payments" value="1">
                <div>
                    <strong>حذف الدفعات فقط</strong>
                    <br>
                    يحذف payments فقط إذا لم تختر حذف الزبائن.
                </div>
            </label>

            <label class="admin-check-item">
                <input type="checkbox" name="clean_announcements" value="1">
                <div>
                    <strong>حذف الإعلانات</strong>
                    <br>
                    ينظف announcements.
                </div>
            </label>

            <label class="admin-check-item">
                <input type="checkbox" name="clean_packages" value="1">
                <div>
                    <strong>حذف الباقات</strong>
                    <br>
                    ينظف service_packages.
                </div>
            </label>

            <label class="admin-check-item">
                <input type="checkbox" name="clean_logs" value="1">
                <div>
                    <strong>حذف Logs</strong>
                    <br>
                    ينظف app_logs.
                </div>
            </label>

        </div>

        <div class="form-group" style="margin-top:16px;">
            <label>اكتب CLEAN للتأكيد</label>
            <input type="text" name="confirmation" placeholder="CLEAN" required>
        </div>

        <button class="btn btn-danger" type="submit">
            تنظيف البيانات المحددة
        </button>

    </form>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">
        CSV Export
    </h2>

    <div class="admin-action-grid">

        <a class="admin-action-card" href="/admin/export/customers.csv">
            <div class="admin-action-icon">👥</div>
            <div class="admin-action-title">Customers CSV</div>
            <div class="admin-action-desc">تصدير الزبائن.</div>
        </a>

        <a class="admin-action-card" href="/admin/export/payments.csv">
            <div class="admin-action-icon">💳</div>
            <div class="admin-action-title">Payments CSV</div>
            <div class="admin-action-desc">تصدير الدفعات.</div>
        </a>

        <a class="admin-action-card" href="/admin/export/subscriptions.csv">
            <div class="admin-action-icon">📅</div>
            <div class="admin-action-title">Subscriptions CSV</div>
            <div class="admin-action-desc">تصدير الاشتراكات.</div>
        </a>

        <a class="admin-action-card" href="/admin/export/logs.csv">
            <div class="admin-action-icon">🧾</div>
            <div class="admin-action-title">Logs CSV</div>
            <div class="admin-action-desc">تصدير السجلات.</div>
        </a>

    </div>
</section>