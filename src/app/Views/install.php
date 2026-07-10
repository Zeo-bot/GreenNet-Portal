<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">G</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>تثبيت قاعدة البيانات</p>

            <div class="status-pill">
                <span class="dot"></span>
                تم التثبيت بنجاح
            </div>
        </div>

        <div class="notice">
            تم إنشاء الجداول الأساسية بنجاح داخل قاعدة بيانات SQLite.
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">الإصدار</div>
                <div class="value"><?= htmlspecialchars($version ?? '-') ?></div>
            </div>

            <div class="stat">
                <div class="label">قاعدة البيانات</div>
                <div class="value">SQLite</div>
            </div>

        </div>

        <div class="stat" style="margin-top:12px;">
            <div class="label">مسار قاعدة البيانات</div>
            <div class="value" style="font-size:13px;">
                <?= htmlspecialchars($database ?? '-') ?>
            </div>
        </div>

        <a class="btn btn-primary" href="/">
            العودة للرئيسية
        </a>

        <a class="btn btn-outline" href="/dashboard">
            معاينة لوحة المشترك
        </a>

        <div class="footer">
            GreenNet Portal Database Ready
        </div>

    </div>
</div>