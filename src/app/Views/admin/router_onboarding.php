<?php
$router = is_array($router ?? null) ? $router : null;
$roles = is_array($roles ?? null) ? $roles : [];
$capabilities = is_array($capabilities ?? null) ? $capabilities : [];
$readiness = is_array($readiness ?? null) ? $readiness : [];
$missingMappings = is_array($missing_mappings ?? null) ? $missing_mappings : [];
$h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$id = (int) ($router['id'] ?? 0);
?>
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">إعداد موجّه جديد</h1>
        <p class="admin-page-description">تسجيل، فحص بالقراءة فقط، مراجعة الجاهزية، ثم إنشاء ملفات إعداد يدوية.</p>
    </div>
    <a class="admin-mini-btn" href="/admin/routers">سجل الموجّهات</a>
</div>
<?php if (($message ?? '') !== ''): ?><div class="notice"><?= $h($message) ?></div><?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">1–2. الهوية والاتصال والأدوار</h2>
    <p class="admin-section-subtitle">وضع «موجّه قائم» لا يغيّر أي إعداد. كلمة المرور الفارغة عند التعديل تحفظ القيمة الحالية.</p>
    <form method="post" action="/admin/router-onboarding/save">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="admin-two-columns">
            <div class="form-group"><label>الاسم</label><input name="name" required value="<?= $h($router['name'] ?? '') ?>"></div>
            <div class="form-group"><label>Host / IP</label><input name="host" required dir="ltr" value="<?= $h($router['host'] ?? '') ?>"></div>
            <div class="form-group"><label>API Port</label><input name="api_port" type="number" value="<?= (int) ($router['api_port'] ?? 8728) ?>"></div>
            <div class="form-group"><label>API Username</label><input name="username" dir="ltr" value="<?= $h($router['username'] ?? '') ?>"></div>
            <div class="form-group"><label>Password</label><input name="password" type="password" autocomplete="new-password"></div>
            <div class="form-group"><label>الموقع</label><input name="location" value="<?= $h($router['location'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label>ملاحظات</label><textarea name="notes"><?= $h($router['notes'] ?? '') ?></textarea></div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <label><input type="checkbox" name="enabled" value="1" <?= !$router || !empty($router['enabled']) ? 'checked' : '' ?>> مفعّل</label>
            <label><input type="checkbox" name="is_default" value="1" <?= !empty($router['is_default']) ? 'checked' : '' ?>> افتراضي</label>
            <label><input type="radio" name="onboarding_mode" value="existing" <?= ($router['onboarding_mode'] ?? 'existing') === 'existing' ? 'checked' : '' ?>> تسجيل موجّه قائم بلا تغيير</label>
            <label><input type="radio" name="onboarding_mode" value="bootstrap" <?= ($router['onboarding_mode'] ?? '') === 'bootstrap' ? 'checked' : '' ?>> إنشاء تعليمات Bootstrap</label>
        </div>
        <h3>الأدوار</h3>
        <?php foreach (['user-manager'=>'User Manager','native-hotspot'=>'Native Hotspot','native-pppoe'=>'Native PPPoE','container-host'=>'GreenNet Container Host','management'=>'Management only'] as $value => $label): ?>
            <label style="display:inline-block;margin:8px"><input type="checkbox" name="roles[]" value="<?= $h($value) ?>" <?= in_array($value, $roles, true) ? 'checked' : '' ?>> <?= $h($label) ?></label>
        <?php endforeach; ?>
        <div><button class="btn btn-primary" type="submit"><?= $id > 0 ? 'حفظ' : 'تسجيل الموجّه' ?></button></div>
    </form>
</section>

<?php if ($router): ?>
<div class="admin-two-columns">
    <section class="admin-section-card">
        <h2 class="admin-section-title">3. اكتشاف القدرات</h2>
        <p class="admin-section-subtitle">هذا الإجراء ينفّذ قراءات RouterOS آمنة فقط.</p>
        <form method="post" action="/admin/router-onboarding/detect"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-primary" type="submit">فحص الاتصال والقدرات</button></form>
        <?php foreach ([
            'identity'=>'Identity','routeros_version'=>'RouterOS','architecture'=>'Architecture','board_name'=>'Board',
            'user_manager'=>'User Manager','hotspot_configured'=>'Hotspot configured','pppoe_configured'=>'PPPoE configured',
            'container_package'=>'Container package','container_mode'=>'Container mode','apps'=>'RouterOS Apps'
        ] as $key => $label): ?>
            <div class="admin-payment-item"><strong><?= $h($label) ?></strong><span><?= is_bool($capabilities[$key] ?? null) ? (!empty($capabilities[$key]) ? 'YES' : 'NO') : $h($capabilities[$key] ?? 'Unknown') ?></span></div>
        <?php endforeach; ?>
    </section>
    <section class="admin-section-card">
        <h2 class="admin-section-title">4–5. مراجعة الجاهزية</h2>
        <?php foreach (($readiness['checks'] ?? []) as $check): ?>
            <div class="admin-payment-item"><div><strong><?= $h($check['label'] ?? '-') ?></strong><div style="font-size:12px"><?= $h($check['message'] ?? '') ?></div></div><span class="admin-badge <?= ($check['status'] ?? '') === 'ready' ? 'admin-badge-success' : (($check['status'] ?? '') === 'unsupported' ? 'admin-badge-danger' : 'admin-badge-warning') ?>"><?= strtoupper($h($check['status'] ?? 'unknown')) ?></span></div>
        <?php endforeach; ?>
        <form method="post" action="/admin/router-onboarding/finish"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-primary" type="submit">حفظ نتيجة الجاهزية</button></form>
    </section>
</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">6. ملفات التثبيت اليدوي</h2>
    <p class="admin-section-subtitle">التوليد منفصل تماماً عن التطبيق. الملفات تحتوي placeholders ولا تحتوي كلمة مرور الموجّه.</p>
    <?php foreach (['bootstrap.rsc'=>'Bootstrap .rsc','greennet-app.yml'=>'RouterOS Apps YAML','greennet.env.example'=>'Environment template','README.txt'=>'Installation instructions'] as $file => $label): ?>
        <a class="admin-mini-btn" href="/admin/router-onboarding/artifact?id=<?= $id ?>&amp;file=<?= urlencode($file) ?>"><?= $h($label) ?></a>
    <?php endforeach; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">7. ربط الباقات</h2>
    <p class="admin-section-subtitle">المفقود لهذا الموجّه: <?= count($missingMappings) ?>. يتم الإكمال من شاشة Provisioning الحالية.</p>
    <a class="btn btn-primary" href="/admin/package-push?router_id=<?= $id ?>">فتح Package/Profile Provisioning</a>
</section>
<?php endif; ?>
