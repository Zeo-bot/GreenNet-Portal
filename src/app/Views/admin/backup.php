<?php
declare(strict_types=1);
$backups = is_array($backups ?? null) ? $backups : [];
$success = (string) ($success ?? '');
$error = (string) ($error ?? '');
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$size = static function (int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
};
$messages = [
    'created' => 'تم إنشاء حزمة GreenNet الاحتياطية.',
    'uploaded' => 'تم رفع الحزمة والتحقق منها وحفظها.',
    'restored' => 'تمت الاستعادة والتحقق من قاعدة البيانات بنجاح.',
    'deleted' => 'تم حذف النسخة المحددة.',
];
?>
<style>
.gn-backup-grid{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.6fr);gap:18px}
.gn-backup-stack{display:grid;gap:16px;align-content:start}
.gn-backup-actions{display:flex;gap:8px;flex-wrap:wrap}
.gn-backup-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:14px;border:1px solid var(--gn-border);border-radius:14px;background:var(--gn-surface-2)}
.gn-backup-meta{display:flex;gap:8px;flex-wrap:wrap;color:var(--gn-muted);font-size:12px;margin-top:6px}
.gn-backup-warning{padding:13px;border-radius:12px;background:#fff7ed;color:#9a3412;line-height:1.7}
@media(max-width:900px){.gn-backup-grid,.gn-backup-item{grid-template-columns:1fr}}
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">نسخ GreenNet الاحتياطي والاستعادة</h1>
        <p class="admin-page-description">حزمة واحدة قابلة للنقل تحفظ قاعدة GreenNet وملفات الرفع المطلوبة. نسخة RouterOS/Export منفصلة وليست جزءاً منها.</p>
    </div>
</div>

<?php if (isset($messages[$success])): ?><div class="notice"><?= $h($messages[$success]) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice" style="background:#fee2e2;color:#991b1b"><?= $h($error) ?></div><?php endif; ?>

<div class="gn-backup-grid">
    <aside class="gn-backup-stack">
        <section class="admin-section-card">
            <h2 class="admin-section-title">إنشاء نسخة</h2>
            <p class="admin-section-subtitle">لقطة SQLite متسقة، Manifest، وملفات uploads داخل حزمة محمولة.</p>
            <form method="post" action="/admin/backup/create"><button class="gn-btn gn-btn-primary" type="submit">إنشاء Backup</button></form>
        </section>

        <section class="admin-section-card">
            <h2 class="admin-section-title">رفع واستعادة</h2>
            <form method="post" action="/admin/backup/restore-full" enctype="multipart/form-data">
                <div class="form-group"><label>حزمة GreenNet</label><input type="file" name="full_backup_file" accept=".zip,.gnbackup.zip" required></div>
                <div class="form-group"><label>اكتب RESTORE للتأكيد</label><input name="confirm_restore" autocomplete="off" required></div>
                <button class="gn-btn gn-btn-danger" type="submit" onclick="return confirm('سيتم التحقق، أخذ نسخة أمان تلقائية، ثم استبدال حالة GreenNet الحالية. متابعة؟')">استعادة</button>
            </form>
        </section>

        <section class="admin-section-card">
            <h2 class="admin-section-title">رفع فقط</h2>
            <form method="post" action="/admin/backup/upload" enctype="multipart/form-data">
                <div class="form-group"><input type="file" name="backup_package" accept=".zip,.gnbackup.zip" required></div>
                <button class="gn-btn gn-btn-secondary" type="submit">رفع والتحقق دون استعادة</button>
            </form>
        </section>

        <div class="gn-backup-warning">
            الحزمة قد تحتوي بيانات تشغيل حساسة داخل قاعدة GreenNet، بما فيها إعدادات الراوتر المخزنة للتوافق. احفظها كسر تشغيلي. ملف <span dir="ltr">.env</span> وأسرار النشر لا تُضاف إلى Manifest أو الحزمة.
        </div>
    </aside>

    <main class="admin-section-card">
        <div class="admin-page-header">
            <div><h2 class="admin-section-title">النسخ المتاحة</h2><p class="admin-section-subtitle"><?= count($backups) ?> حزمة · التخزين قابل للضبط عبر BACKUP_STORAGE_PATH.</p></div>
        </div>
        <div class="admin-payment-list">
            <?php if ($backups === []): ?><div class="notice">لا توجد نسخ بعد.</div><?php endif; ?>
            <?php foreach ($backups as $backup): ?>
                <article class="gn-backup-item">
                    <div>
                        <strong dir="ltr"><?= $h($backup['name'] ?? '') ?></strong>
                        <div class="gn-backup-meta">
                            <span>Format v<?= (int) ($backup['format_version'] ?? 0) ?></span>
                            <span><?= $h($size((int) ($backup['size'] ?? 0))) ?></span>
                            <span dir="ltr"><?= $h($backup['created_at'] ?: date('Y-m-d H:i:s', (int) ($backup['mtime'] ?? 0))) ?></span>
                            <span><?= !empty($backup['valid']) ? 'صالحة' : 'غير معروفة' ?></span>
                        </div>
                    </div>
                    <div class="gn-backup-actions">
                        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/backup/file?file=<?= rawurlencode((string) ($backup['name'] ?? '')) ?>">تحميل</a>
                        <form method="post" action="/admin/backup/restore-stored" onsubmit="return confirm('سيتم استعادة هذه النسخة بعد إنشاء نسخة أمان تلقائية. متابعة؟')">
                            <input type="hidden" name="file" value="<?= $h($backup['name'] ?? '') ?>"><input type="hidden" name="confirm_restore" value="RESTORE">
                            <button class="gn-btn gn-btn-primary gn-btn-sm" type="submit">استعادة</button>
                        </form>
                        <form method="post" action="/admin/backup/delete" onsubmit="return confirm('حذف هذه النسخة نهائياً؟')">
                            <input type="hidden" name="file" value="<?= $h($backup['name'] ?? '') ?>"><input type="hidden" name="confirm_delete" value="DELETE">
                            <button class="gn-btn gn-btn-danger gn-btn-sm" type="submit">حذف</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </main>
</div>
