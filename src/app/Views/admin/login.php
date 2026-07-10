<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">G</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>دخول لوحة المدير</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/admin/login">

            <div class="form-group">
                <label>اسم المستخدم</label>
                <input type="text" name="username" placeholder="admin" required>
            </div>

            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <button class="btn btn-primary" type="submit">
                دخول المدير
            </button>

        </form>

        <a class="btn btn-outline" href="/">
            العودة للرئيسية
        </a>

        <div class="footer">
            GreenNet Admin Panel
        </div>

    </div>
</div>