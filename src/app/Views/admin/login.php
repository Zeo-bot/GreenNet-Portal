<?php

declare(strict_types=1);

if (!function_exists('gn_admin_login_h')) {
    function gn_admin_login_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$errorMessage = (string) ($error ?? $message ?? $_SESSION['admin_login_error'] ?? '');
$usernameValue = (string) ($_POST['username'] ?? $_GET['username'] ?? 'admin');

if (isset($_SESSION['admin_login_error'])) {
    unset($_SESSION['admin_login_error']);
}

?>

<section class="gn-admin-login-shell">

    <aside class="gn-admin-login-info">
        <div>
            <span class="gn-admin-login-kicker">GreenNet Admin</span>

            <h1>إدارة الشبكة من مكان واحد</h1>

            <p>
                لوحة تحكم GreenNet لإدارة المشتركين، الباقات، الدفعات، التقارير،
                وعمليات MikroTik الآمنة قبل التنفيذ الحقيقي.
            </p>

            <ul class="gn-admin-login-points">
                <li>
                    <span>1</span>
                    <strong>إدارة المشتركين والباقات</strong>
                </li>
                <li>
                    <span>2</span>
                    <strong>سجل عمليات وتنبيهات واضح</strong>
                </li>
                <li>
                    <span>3</span>
                    <strong>طبقة أمان قبل أوامر MikroTik</strong>
                </li>
            </ul>
        </div>

        <div class="gn-admin-login-copy">
            GreenNet Portal • Admin Console
        </div>
    </aside>

    <section class="gn-admin-login-card">
        <div class="gn-admin-login-card-header">
            <div class="gn-admin-login-card-logo">G</div>

            <h2>دخول المدير</h2>

            <p>
                أدخل بيانات المدير للمتابعة إلى لوحة التحكم.
            </p>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="gn-admin-login-error">
                <?= gn_admin_login_h($errorMessage) ?>
            </div>
        <?php endif; ?>

        <form class="gn-admin-login-form" method="post" action="/admin/login" autocomplete="on">
            <div class="gn-admin-login-field">
                <label for="admin-username">اسم المستخدم</label>
                <input
                    id="admin-username"
                    type="text"
                    name="username"
                    value="<?= gn_admin_login_h($usernameValue) ?>"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="gn-admin-login-field">
                <label for="admin-password">كلمة المرور</label>
                <input
                    id="admin-password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    autofocus
                >
            </div>

            <div class="gn-admin-login-actions">
                <button class="gn-admin-login-button" type="submit">
                    دخول المدير
                </button>

                <a class="gn-admin-login-secondary" href="/login?switch=1">
                    العودة لبوابة المشترك
                </a>
            </div>
        </form>

        <div class="gn-admin-login-footer">
            GreenNet Admin Panel
        </div>
    </section>

</section>