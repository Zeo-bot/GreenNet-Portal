<?php
    $settings = is_array($settings ?? null) ? $settings : [];
    $mediaFiles = is_array($media_files ?? null) ? $media_files : [];

    $flashType = $flash['type'] ?? '';
    $flashMessage = $flash['message'] ?? '';

    $logoPath = (string) ($settings['site_logo_path'] ?? '');
    $iconPath = (string) ($settings['app_icon_path'] ?? '');
    $loginBackgroundPath = (string) ($settings['login_background_path'] ?? '');

    $isUsed = function (string $path) use ($logoPath, $iconPath, $loginBackgroundPath): bool {
        return in_array($path, [$logoPath, $iconPath, $loginBackgroundPath], true);
    };

    $usedLabel = function (string $path) use ($logoPath, $iconPath, $loginBackgroundPath): string {
        $labels = [];

        if ($path === $logoPath) {
            $labels[] = 'Logo';
        }

        if ($path === $iconPath) {
            $labels[] = 'Icon';
        }

        if ($path === $loginBackgroundPath) {
            $labels[] = 'Login Background';
        }

        return implode(' / ', $labels);
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">الصور والهوية</h1>

        <p class="admin-page-description">
            إدارة صور النظام: الشعار، أيقونة التطبيق، وصورة خلفية صفحة دخول المشترك.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/settings">الإعدادات</a>
        <a class="admin-mini-btn" href="/login">معاينة الدخول</a>
        <a class="admin-mini-btn" href="/admin/backup">Backup</a>
    </div>
</div>

<?php if ($flashMessage !== ''): ?>
    <div class="notice" style="<?= $flashType === 'success' ? 'background:#f0fdf4;color:#166534;' : 'background:#fee2e2;color:#991b1b;' ?>">
        <?= htmlspecialchars($flashMessage) ?>
    </div>
<?php endif; ?>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">الشعار الحالي</div>

        <div class="admin-stat-value" style="font-size:15px;">
            <?= $logoPath !== '' ? 'محدد' : 'غير محدد' ?>
        </div>

        <div class="admin-stat-note">
            <?= htmlspecialchars($logoPath !== '' ? $logoPath : '-') ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">أيقونة التطبيق</div>

        <div class="admin-stat-value" style="font-size:15px;">
            <?= $iconPath !== '' ? 'محددة' : 'غير محددة' ?>
        </div>

        <div class="admin-stat-note">
            <?= htmlspecialchars($iconPath !== '' ? $iconPath : '-') ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">خلفية الدخول</div>

        <div class="admin-stat-value" style="font-size:15px;">
            <?= $loginBackgroundPath !== '' ? 'محددة' : 'غير محددة' ?>
        </div>

        <div class="admin-stat-note">
            <?= htmlspecialchars($loginBackgroundPath !== '' ? $loginBackgroundPath : '-') ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">عدد الصور</div>

        <div class="admin-stat-value">
            <?= htmlspecialchars((string) count($mediaFiles)) ?>
        </div>

        <div class="admin-stat-note">
            داخل public/media
        </div>
    </div>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">رفع صورة جديدة</h2>

    <p class="admin-section-subtitle">
        يمكنك رفع صورة عامة، أو رفعها وتعيينها مباشرة كشعار أو أيقونة أو خلفية دخول.
    </p>

    <form method="post" action="/admin/media/upload" enctype="multipart/form-data">

        <div class="admin-filter-bar" style="grid-template-columns:1fr 1fr auto;">
            <div class="form-group">
                <label>الصورة</label>
                <input type="file" name="media_file" accept="image/png,image/jpeg,image/webp,image/gif" required>
            </div>

            <div class="form-group">
                <label>الاستخدام بعد الرفع</label>
                <select name="asset_type">
                    <option value="gallery">رفع فقط إلى المكتبة</option>
                    <option value="logo">تعيين كشعار الشبكة</option>
                    <option value="icon">تعيين كأيقونة التطبيق</option>
                    <option value="login_background">تعيين كخلفية صفحة الدخول</option>
                </select>
            </div>

            <button class="btn btn-primary" type="submit">
                رفع الصورة
            </button>
        </div>

    </form>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">الصور المستخدمة حالياً</h2>

    <div class="admin-action-grid">

        <div class="admin-action-card">
            <div class="admin-action-icon">🖼️</div>
            <div class="admin-action-title">شعار الشبكة</div>

            <div class="admin-action-desc">
                <?php if ($logoPath !== ''): ?>
                    <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" style="width:100%;height:120px;object-fit:contain;background:white;border:1px solid #e5e7eb;border-radius:16px;padding:8px;margin-bottom:10px;">
                    <span class="admin-code"><?= htmlspecialchars($logoPath) ?></span>

                    <form method="post" action="/admin/media/clear" style="margin-top:10px;">
                        <input type="hidden" name="setting_key" value="site_logo_path">
                        <button class="btn btn-danger" type="submit">إزالة الشعار</button>
                    </form>
                <?php else: ?>
                    لا يوجد شعار محدد حالياً.
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-action-card">
            <div class="admin-action-icon">📱</div>
            <div class="admin-action-title">أيقونة التطبيق</div>

            <div class="admin-action-desc">
                <?php if ($iconPath !== ''): ?>
                    <img src="<?= htmlspecialchars($iconPath) ?>" alt="Icon" style="width:100%;height:120px;object-fit:contain;background:white;border:1px solid #e5e7eb;border-radius:16px;padding:8px;margin-bottom:10px;">
                    <span class="admin-code"><?= htmlspecialchars($iconPath) ?></span>

                    <form method="post" action="/admin/media/clear" style="margin-top:10px;">
                        <input type="hidden" name="setting_key" value="app_icon_path">
                        <button class="btn btn-danger" type="submit">إزالة الأيقونة</button>
                    </form>
                <?php else: ?>
                    لا توجد أيقونة محددة حالياً.
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-action-card">
            <div class="admin-action-icon">🌄</div>
            <div class="admin-action-title">خلفية صفحة الدخول</div>

            <div class="admin-action-desc">
                <?php if ($loginBackgroundPath !== ''): ?>
                    <img src="<?= htmlspecialchars($loginBackgroundPath) ?>" alt="Login Background" style="width:100%;height:120px;object-fit:cover;background:white;border:1px solid #e5e7eb;border-radius:16px;margin-bottom:10px;">
                    <span class="admin-code"><?= htmlspecialchars($loginBackgroundPath) ?></span>

                    <form method="post" action="/admin/media/clear" style="margin-top:10px;">
                        <input type="hidden" name="setting_key" value="login_background_path">
                        <button class="btn btn-danger" type="submit">إزالة الخلفية</button>
                    </form>
                <?php else: ?>
                    لا توجد خلفية دخول محددة حالياً.
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">مكتبة الصور</h2>

    <p class="admin-section-subtitle">
        الصور الموجودة داخل مجلد public/media. لا يمكن حذف صورة مستخدمة حالياً إلا بعد إزالة تعيينها.
    </p>

    <?php if (count($mediaFiles) === 0): ?>
        <div class="admin-empty-state">
            لا توجد صور مرفوعة حالياً.
        </div>
    <?php else: ?>
        <div class="admin-action-grid">

            <?php foreach ($mediaFiles as $file): ?>
                <?php
                    $path = (string) ($file['path'] ?? '');
                    $used = $isUsed($path);
                ?>

                <div class="admin-action-card">
                    <div style="position:relative;">
                        <img
                            src="<?= htmlspecialchars($path) ?>"
                            alt="<?= htmlspecialchars((string) ($file['filename'] ?? 'media')) ?>"
                            style="width:100%;height:150px;object-fit:cover;background:#f9fafb;border:1px solid #e5e7eb;border-radius:16px;margin-bottom:10px;"
                        >

                        <?php if ($used): ?>
                            <span class="admin-badge admin-badge-success" style="position:absolute;top:8px;right:8px;">
                                <?= htmlspecialchars($usedLabel($path)) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="admin-action-title">
                        <?= htmlspecialchars((string) ($file['filename'] ?? '-')) ?>
                    </div>

                    <div class="admin-action-desc">
                        <span class="admin-code"><?= htmlspecialchars($path) ?></span>
                        <br>
                        الحجم:
                        <?= htmlspecialchars((string) round(((int) ($file['size_bytes'] ?? 0)) / 1024, 1)) ?>
                        KB
                        <br>
                        آخر تعديل:
                        <?= htmlspecialchars((string) ($file['modified_at'] ?? '-')) ?>
                    </div>

                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;">

                        <form method="post" action="/admin/media/assign">
                            <input type="hidden" name="path" value="<?= htmlspecialchars($path) ?>">
                            <input type="hidden" name="setting_key" value="site_logo_path">
                            <button class="admin-row-action primary" type="submit">Logo</button>
                        </form>

                        <form method="post" action="/admin/media/assign">
                            <input type="hidden" name="path" value="<?= htmlspecialchars($path) ?>">
                            <input type="hidden" name="setting_key" value="app_icon_path">
                            <button class="admin-row-action" type="submit">Icon</button>
                        </form>

                        <form method="post" action="/admin/media/assign">
                            <input type="hidden" name="path" value="<?= htmlspecialchars($path) ?>">
                            <input type="hidden" name="setting_key" value="login_background_path">
                            <button class="admin-row-action" type="submit">Background</button>
                        </form>

                        <form method="post" action="/admin/media/delete" onsubmit="return confirm('هل تريد حذف هذه الصورة؟');">
                            <input type="hidden" name="path" value="<?= htmlspecialchars($path) ?>">
                            <button class="admin-row-action" type="submit" <?= $used ? 'disabled' : '' ?>>
                                حذف
                            </button>
                        </form>

                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">ملاحظة Backup مهمة</h2>

    <div class="notice" style="background:#fff7ed;color:#92400e;">
        قاعدة البيانات تحفظ مسارات الصور فقط، أما الصور نفسها موجودة داخل:
        <br>
        <span class="admin-code">src/public/media</span>
        <br>
        في مرحلة Full Backup القادمة سنضيف نسخة ZIP تشمل قاعدة البيانات + مجلد media.
    </div>
</section>