<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">📢</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>تعديل الإعلان</p>
        </div>

        <form method="post" action="/admin/announcements/update">

            <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($announcement['id'] ?? 0)) ?>">

            <div class="form-group">
                <label>عنوان الإعلان</label>
                <input
                    type="text"
                    name="title"
                    value="<?= htmlspecialchars($announcement['title'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>نص الإعلان</label>
                <textarea name="body" class="input-textarea" required><?= htmlspecialchars($announcement['body'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input
                        type="checkbox"
                        name="is_active"
                        style="width:auto;"
                        <?= ((int) ($announcement['is_active'] ?? 0) === 1) ? 'checked' : '' ?>
                    >
                    إعلان فعال
                </label>
            </div>

            <button class="btn btn-primary" type="submit">
                حفظ التعديلات
            </button>

        </form>

        <a class="btn btn-outline" href="/admin/announcements">
            العودة للإعلانات
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Announcement Edit
        </div>

    </div>
</div>