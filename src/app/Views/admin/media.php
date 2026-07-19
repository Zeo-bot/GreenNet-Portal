<?php

declare(strict_types=1);

if (!function_exists('gn_media_h')) {
    function gn_media_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_media_size')) {
    function gn_media_size(mixed $bytes): string
    {
        $bytes = (int) $bytes;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}

if (!function_exists('gn_media_date')) {
    function gn_media_date(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

$assets = is_array($assets ?? null) ? $assets : [];
$assigned = is_array($assigned ?? null) ? $assigned : [];
$slots = is_array($slots ?? null) ? $slots : [];
$success = (string) ($success ?? '');
$error = (string) ($error ?? '');
$maxUploadMb = (int) ($maxUploadMb ?? 5);

$successMap = [
    'uploaded' => 'تم رفع الملف بنجاح.',
    'assigned' => 'تم تعيين الصورة بنجاح.',
    'cleared' => 'تم إلغاء التعيين.',
    'deleted' => 'تم حذف الملف.',
];

?>

<style>
    .gn-media-page {
        display: grid;
        gap: 18px;
    }

    .gn-media-hero {
        position: relative;
        overflow: hidden;
        padding: 26px;
        border-radius: var(--gn-radius-xl);
        background:
            radial-gradient(circle at 0% 0%, rgba(32, 201, 120, 0.16), transparent 30%),
            linear-gradient(135deg, var(--gn-surface), var(--gn-surface-2));
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-media-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 32px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-media-hero p {
        margin: 10px 0 0;
        max-width: 940px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-media-hero-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
    }

    .gn-media-alert {
        padding: 14px 16px;
        border-radius: var(--gn-radius-lg);
        font-weight: 900;
        line-height: 1.7;
    }

    .gn-media-alert.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.22);
    }

    .gn-media-alert.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.22);
    }

    .gn-media-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-media-kpi {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-media-kpi::before {
        content: "";
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gn-primary);
    }

    .gn-media-kpi.is-info::before {
        background: var(--gn-info);
    }

    .gn-media-kpi.is-warning::before {
        background: var(--gn-warning);
    }

    .gn-media-kpi.is-success::before {
        background: var(--gn-success);
    }

    .gn-media-kpi-label {
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 900;
    }

    .gn-media-kpi-value {
        margin-top: 7px;
        color: var(--gn-text);
        font-size: 28px;
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .gn-media-layout {
        display: grid;
        grid-template-columns: minmax(320px, 0.48fr) minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }

    .gn-media-card {
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        border-radius: var(--gn-radius-xl);
        box-shadow: var(--gn-shadow-sm);
        padding: 20px;
    }

    .gn-media-card h2 {
        margin: 0 0 14px;
        color: var(--gn-text);
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-media-form {
        display: grid;
        gap: 14px;
    }

    .gn-media-field {
        display: grid;
        gap: 7px;
        min-width: 0;
    }

    .gn-media-field label {
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 950;
    }

    .gn-media-field input,
    .gn-media-field select {
        width: 100%;
        min-height: 46px;
        padding: 10px 14px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
        outline: none;
        box-sizing: border-box;
    }

    .gn-media-note {
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        line-height: 1.75;
        font-weight: 850;
    }

    .gn-media-slots {
        display: grid;
        gap: 10px;
        margin-top: 16px;
    }

    .gn-media-slot {
        display: grid;
        grid-template-columns: 74px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        padding: 12px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-media-slot-preview {
        width: 74px;
        height: 56px;
        border-radius: 14px;
        overflow: hidden;
        background: var(--gn-surface-3);
        border: 1px solid var(--gn-border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gn-muted);
        font-weight: 950;
        font-size: 12px;
    }

    .gn-media-slot-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .gn-media-slot-title {
        color: var(--gn-text);
        font-weight: 950;
        margin-bottom: 4px;
    }

    .gn-media-slot-path {
        color: var(--gn-muted);
        font-size: 12px;
        direction: ltr;
        text-align: left;
        unicode-bidi: plaintext;
        word-break: break-all;
    }

    html[dir="rtl"] .gn-media-slot-path {
        text-align: right;
    }

    .gn-media-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-media-item {
        position: relative;
        overflow: hidden;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-media-thumb {
        position: relative;
        height: 180px;
        background: var(--gn-surface-2);
        overflow: hidden;
        border-bottom: 1px solid var(--gn-border);
    }

    .gn-media-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .gn-media-item-body {
        display: grid;
        gap: 10px;
        padding: 14px;
    }

    .gn-media-name {
        color: var(--gn-text);
        font-size: 14px;
        font-weight: 950;
        line-height: 1.5;
        word-break: break-word;
    }

    .gn-media-meta {
        display: grid;
        gap: 4px;
        color: var(--gn-muted);
        font-size: 12px;
        font-weight: 850;
    }

    .gn-media-path {
        direction: ltr;
        text-align: left;
        unicode-bidi: plaintext;
        word-break: break-all;
        color: var(--gn-text-soft);
        font-family: Consolas, "Cascadia Code", monospace;
        font-size: 11px;
        line-height: 1.5;
    }

    html[dir="rtl"] .gn-media-path {
        text-align: right;
    }

    .gn-media-actions {
        display: grid;
        gap: 8px;
    }

    .gn-media-action-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px;
        align-items: center;
    }

    .gn-media-action-row select {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border-radius: var(--gn-radius-md);
        border: 1px solid var(--gn-border);
        background: var(--gn-surface-2);
        color: var(--gn-text);
    }

    .gn-media-inline-form {
        margin: 0;
    }

    .gn-media-inline-form button,
    .gn-media-inline-form .gn-btn {
        width: 100%;
        justify-content: center;
    }

    .gn-media-empty {
        padding: 28px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
        text-align: center;
        color: var(--gn-muted);
        font-weight: 900;
    }

    html[data-theme="greennet-dark"] .gn-media-hero,
    html[data-theme="greennet-dark"] .gn-media-kpi,
    html[data-theme="greennet-dark"] .gn-media-card,
    html[data-theme="greennet-dark"] .gn-media-item,
    html[data-theme="greennet-dark"] .gn-media-empty {
        background:
            radial-gradient(circle at 0% 0%, rgba(46, 230, 139, 0.07), transparent 28%),
            linear-gradient(180deg, var(--gn-surface), var(--gn-surface-2)) !important;
    }

    @media (max-width: 1300px) {
        .gn-media-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .gn-media-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 980px) {
        .gn-media-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 720px) {
        .gn-media-grid,
        .gn-media-kpis {
            grid-template-columns: 1fr;
        }

        .gn-media-hero h1 {
            font-size: 26px;
        }

        .gn-media-action-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="gn-media-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">Media Library</h1>
            <p class="admin-page-description">
                رفع وإدارة صور الهوية والشعار والخلفيات المستخدمة في GreenNet.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/settings">الإعدادات</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/security">الأمان</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/media">Refresh</a>
        </div>
    </div>

    <?php if ($success !== '' && isset($successMap[$success])): ?>
        <div class="gn-media-alert is-success">
            <?= gn_media_h($successMap[$success]) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="gn-media-alert is-danger">
            <?= gn_media_h($error) ?>
        </div>
    <?php endif; ?>

    <section class="gn-media-hero">
        <h1>مكتبة الوسائط</h1>
        <p>
            هذه الصفحة مخصصة لرفع الصور وربطها بالهوية. الملفات تحفظ داخل
            <strong>/public/uploads/media</strong>
            ولا يوجد أي اتصال مع MikroTik.
        </p>

        <div class="gn-media-hero-actions">
            <a class="gn-btn gn-btn-primary" href="#upload-form">رفع صورة جديدة</a>
            <a class="gn-btn gn-btn-secondary" href="/admin/settings">إعدادات الهوية</a>
        </div>
    </section>

    <section class="gn-media-kpis">
        <div class="gn-media-kpi">
            <div class="gn-media-kpi-label">عدد الملفات</div>
            <div class="gn-media-kpi-value"><?= gn_media_h(count($assets)) ?></div>
        </div>

        <div class="gn-media-kpi is-info">
            <div class="gn-media-kpi-label">أماكن التعيين</div>
            <div class="gn-media-kpi-value"><?= gn_media_h(count($slots)) ?></div>
        </div>

        <div class="gn-media-kpi is-success">
            <div class="gn-media-kpi-label">الحد الأعلى</div>
            <div class="gn-media-kpi-value"><?= gn_media_h($maxUploadMb) ?> MB</div>
        </div>

        <div class="gn-media-kpi is-warning">
            <div class="gn-media-kpi-label">الأنواع</div>
            <div class="gn-media-kpi-value">Images</div>
        </div>
    </section>

    <section class="gn-media-layout">

        <aside class="gn-media-card">
            <h2>رفع صورة</h2>

            <form
                id="upload-form"
                class="gn-media-form"
                method="post"
                action="/admin/media/upload"
                enctype="multipart/form-data"
                data-gn-form-wrapped="1"
                data-gn-form-enhanced="1"
                data-gn-fields-grouped="1"
            >
                <div class="gn-media-field">
                    <label>اختر الصورة</label>
                    <input type="file" name="media_file" accept="image/jpeg,image/png,image/webp,image/gif" required>
                </div>

                <div class="gn-media-field">
                    <label>وسم اختياري</label>
                    <input type="text" name="tag" placeholder="logo / login / background">
                </div>

                <button class="gn-btn gn-btn-primary" type="submit">
                    رفع الصورة
                </button>
            </form>

            <div class="gn-media-note" style="margin-top: 14px;">
                الأنواع المسموحة: JPG, PNG, WEBP, GIF. الحد الأعلى الحالي <?= gn_media_h($maxUploadMb) ?>MB.
            </div>

            <h2 style="margin-top: 22px;">التعيينات الحالية</h2>

            <div class="gn-media-slots">
                <?php foreach ($slots as $slotKey => $slotLabel): ?>
                    <?php $path = (string) ($assigned[$slotKey] ?? ''); ?>

                    <article class="gn-media-slot">
                        <div class="gn-media-slot-preview">
                            <?php if ($path !== ''): ?>
                                <img src="<?= gn_media_h($path) ?>" alt="">
                            <?php else: ?>
                                Empty
                            <?php endif; ?>
                        </div>

                        <div>
                            <div class="gn-media-slot-title"><?= gn_media_h($slotLabel) ?></div>
                            <div class="gn-media-slot-path"><?= gn_media_h($path !== '' ? $path : 'غير معين') ?></div>

                            <?php if ($path !== ''): ?>
                                <form
                                    class="gn-media-inline-form"
                                    method="post"
                                    action="/admin/media/clear"
                                    data-gn-form-wrapped="1"
                                    data-gn-form-enhanced="1"
                                    data-gn-fields-grouped="1"
                                    style="margin-top: 8px;"
                                >
                                    <input type="hidden" name="slot" value="<?= gn_media_h($slotKey) ?>">
                                    <button class="gn-btn gn-btn-secondary gn-btn-sm" type="submit">
                                        إلغاء التعيين
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </aside>

        <main>
            <?php if (count($assets) === 0): ?>
                <div class="gn-media-empty">
                    لا توجد صور مرفوعة بعد.
                </div>
            <?php else: ?>
                <section class="gn-media-grid">
                    <?php foreach ($assets as $asset): ?>
                        <?php
                            $id = (int) ($asset['id'] ?? 0);
                            $publicPath = (string) ($asset['public_path'] ?? '');
                            $originalName = (string) ($asset['original_name'] ?? $asset['filename'] ?? '');
                            $mime = (string) ($asset['mime_type'] ?? '');
                            $size = (int) ($asset['size_bytes'] ?? 0);
                            $tag = (string) ($asset['tag'] ?? '');
                            $createdAt = (string) ($asset['created_at'] ?? '');
                        ?>

                        <article class="gn-media-item">
                            <div class="gn-media-thumb">
                                <img src="<?= gn_media_h($publicPath) ?>" alt="<?= gn_media_h($originalName) ?>">
                            </div>

                            <div class="gn-media-item-body">
                                <div class="gn-media-name"><?= gn_media_h($originalName) ?></div>

                                <div class="gn-media-meta">
                                    <span><?= gn_media_h($mime !== '' ? $mime : 'image') ?></span>
                                    <span><?= gn_media_h(gn_media_size($size)) ?></span>
                                    <span dir="ltr"><?= gn_media_h(gn_media_date($createdAt)) ?></span>
                                    <?php if ($tag !== ''): ?>
                                        <span>Tag: <?= gn_media_h($tag) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="gn-media-path"><?= gn_media_h($publicPath) ?></div>

                                <div class="gn-media-actions">
                                    <form
                                        class="gn-media-inline-form"
                                        method="post"
                                        action="/admin/media/assign"
                                        data-gn-form-wrapped="1"
                                        data-gn-form-enhanced="1"
                                        data-gn-fields-grouped="1"
                                    >
                                        <input type="hidden" name="public_path" value="<?= gn_media_h($publicPath) ?>">

                                        <div class="gn-media-action-row">
                                            <select name="slot">
                                                <?php foreach ($slots as $slotKey => $slotLabel): ?>
                                                    <option value="<?= gn_media_h($slotKey) ?>"><?= gn_media_h($slotLabel) ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button class="gn-btn gn-btn-primary gn-btn-sm" type="submit">
                                                تعيين
                                            </button>
                                        </div>
                                    </form>

                                    <a class="gn-btn gn-btn-secondary gn-btn-sm" href="<?= gn_media_h($publicPath) ?>" target="_blank">
                                        فتح الصورة
                                    </a>

                                    <form
                                        class="gn-media-inline-form"
                                        method="post"
                                        action="/admin/media/delete"
                                        data-gn-form-wrapped="1"
                                        data-gn-form-enhanced="1"
                                        data-gn-fields-grouped="1"
                                        onsubmit="return confirm('هل تريد حذف هذه الصورة نهائياً؟');"
                                    >
                                        <input type="hidden" name="id" value="<?= gn_media_h($id) ?>">
                                        <button class="gn-btn gn-btn-danger gn-btn-sm" type="submit">
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </main>

    </section>

</div>