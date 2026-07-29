<?php
    $error = (string) ($error ?? '');
    $username = (string) ($username ?? '');
?>

<div class="auth-page">

    <div class="auth-card">

        <div class="brand-block">
            <div class="brand-mark <?= !empty($site_logo_path) ? 'has-logo' : '' ?>">
                <?php if (!empty($site_logo_path)): ?>
                    <img class="brand-logo" src="<?= htmlspecialchars($site_logo_path) ?>" alt="Logo">
                <?php else: ?>
                    G
                <?php endif; ?>
            </div>

            <div>
                <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
                <p>تسجيل دخول المشترك</p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/login" class="form-card">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">

            <div class="form-group">
                <label for="subscriber-username">اسم المستخدم</label>
                <input
                    id="subscriber-username"
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars($username) ?>"
                    placeholder="أدخل اسم المستخدم"
                    required
                    autocomplete="username"
                    dir="ltr"
                >
            </div>

            <div class="form-group">
                <label for="subscriber-password">كلمة المرور</label>
                <input
                    id="subscriber-password"
                    type="password"
                    name="password"
                    placeholder="أدخل كلمة المرور"
                    required
                    autocomplete="current-password"
                    dir="ltr"
                >
            </div>

            <button class="btn btn-primary full-width" type="submit">
                دخول
            </button>

        </form>

        <div class="auth-help">
            لا تعرف كلمة المرور؟ تواصل مع الإدارة لتفعيل أو إعادة تعيين كلمة مرور حسابك.
        </div>

        <?php if (!empty($support_phone)): ?>
            <div class="auth-help">
                الدعم:
                <strong dir="ltr"><?= htmlspecialchars((string) $support_phone) ?></strong>
            </div>
        <?php endif; ?>

    </div>

</div>
