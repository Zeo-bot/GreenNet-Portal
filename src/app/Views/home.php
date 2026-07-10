<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">G</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>بوابة المشترك الذكية</p>
        </div>

        <div class="notice">
            أهلاً بك في <?= htmlspecialchars($app_name ?? 'GreenNet') ?> Portal.
            من هنا يستطيع المشترك متابعة الباقة، الاستهلاك، الصلاحية، والتجديد بسهولة.
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">نمط التشغيل</div>
                <div class="value"><?= htmlspecialchars($access_mode ?? '-') ?></div>
            </div>

            <div class="stat">
                <div class="label">نظام الحسابات</div>
                <div class="value"><?= htmlspecialchars($auth_backend ?? '-') ?></div>
            </div>

        </div>

        <a class="btn btn-primary" href="/login">
            تسجيل الدخول
        </a>

        <a class="btn btn-outline" href="/dashboard">
            معاينة لوحة المشترك
        </a>

        <a class="btn btn-outline" href="https://wa.me/<?= htmlspecialchars($support_whatsapp ?? '963966393915') ?>">
            💬 الدعم الفني
        </a>

        <div class="footer">
            <?= htmlspecialchars($app_name ?? 'GreenNet') ?> Portal v<?= htmlspecialchars($version ?? '1.0.0') ?>
        </div>

    </div>
</div>