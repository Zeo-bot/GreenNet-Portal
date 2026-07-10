<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">📢</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>إدارة الإعلانات</p>

            <div class="status-pill">
                <span class="dot"></span>
                تظهر في لوحة المشترك
            </div>
        </div>

        <div class="notice">
            أي إعلان فعال سيظهر للمشتركين داخل لوحة المشترك.
        </div>

        <form method="post" action="/admin/announcements/store">

            <div class="form-group">
                <label>عنوان الإعلان</label>
                <input type="text" name="title" placeholder="مثال: صيانة مؤقتة" required>
            </div>

            <div class="form-group">
                <label>نص الإعلان</label>
                <textarea name="body" class="input-textarea" placeholder="اكتب نص الإعلان هنا..." required></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" checked style="width:auto;">
                    إعلان فعال
                </label>
            </div>

            <button class="btn btn-primary" type="submit">
                إضافة الإعلان
            </button>

        </form>

        <div class="stat" style="margin-top:18px;">
            <div class="label">قائمة الإعلانات</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                <?php if (count($announcements) === 0): ?>
                    لا توجد إعلانات حالياً.
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div style="border-bottom:1px solid #e5e7eb; padding:12px 0;">

                            <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                            <br>

                            الحالة:
                            <?= ((int) $announcement['is_active'] === 1) ? 'فعال' : 'غير فعال' ?>
                            <br>

                            النص:
                            <br>
                            <?= nl2br(htmlspecialchars($announcement['body'])) ?>
                            <br>

                            التاريخ:
                            <?= htmlspecialchars($announcement['created_at']) ?>

                            <a class="btn btn-primary" href="/admin/announcements/edit?id=<?= urlencode((string) $announcement['id']) ?>">
                                تعديل الإعلان
                            </a>

                            <form method="post" action="/admin/announcements/delete" style="margin-top:10px;">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) $announcement['id']) ?>">

                                <div class="form-group">
                                    <label>للحذف اكتب DELETE</label>
                                    <input type="text" name="confirm" placeholder="DELETE">
                                </div>

                                <button class="btn btn-danger" type="submit">
                                    حذف الإعلان
                                </button>
                            </form>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <a class="btn btn-outline" href="/dashboard">
            معاينة لوحة المشترك
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Announcements
        </div>

    </div>
</div>