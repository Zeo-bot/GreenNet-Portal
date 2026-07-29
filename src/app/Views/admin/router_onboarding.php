<?php
$router = is_array($router ?? null) ? $router : null;
$roles = is_array($roles ?? null) ? $roles : [];
$capabilities = is_array($capabilities ?? null) ? $capabilities : [];
$readiness = is_array($readiness ?? null) ? $readiness : [];
$missingMappings = is_array($missing_mappings ?? null) ? $missing_mappings : [];
$methods = is_array($installation_methods ?? null) ? $installation_methods : [];
$deployment = is_array($deployment ?? null) ? $deployment : [];
$warnings = is_array($deployment_warnings ?? null) ? $deployment_warnings : [];
$h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$id = (int) ($router['id'] ?? 0);
$selectedMethod = (string) ($deployment['method'] ?? 'container');
?>
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">إعداد موجّه GreenNet</h1>
        <p class="admin-page-description">تسجيل الموجّه، فحص قدراته بالقراءة فقط، ثم تجهيز ملفات تثبيت يراجعها المشغّل وينفذها يدويًا.</p>
    </div>
    <a class="admin-mini-btn" href="/admin/routers">سجل الموجّهات</a>
</div>
<?php if (($message ?? '') !== ''): ?><div class="notice"><?= $h($message) ?></div><?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">1. الموجّه والأدوار</h2>
    <form method="post" action="/admin/router-onboarding/save">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="admin-two-columns">
            <div class="form-group"><label>الاسم</label><input name="name" required value="<?= $h($router['name'] ?? '') ?>"></div>
            <div class="form-group"><label>Host / IP</label><input name="host" required dir="ltr" value="<?= $h($router['host'] ?? '') ?>"></div>
            <div class="form-group"><label>API Port</label><input name="api_port" type="number" value="<?= (int) ($router['api_port'] ?? 8728) ?>"></div>
            <div class="form-group"><label>API Username</label><input name="username" dir="ltr" value="<?= $h($router['username'] ?? '') ?>"></div>
            <div class="form-group"><label>كلمة مرور API</label><input name="password" type="password" autocomplete="new-password"></div>
            <div class="form-group"><label>الموقع</label><input name="location" value="<?= $h($router['location'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label>ملاحظات</label><textarea name="notes"><?= $h($router['notes'] ?? '') ?></textarea></div>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <label><input type="checkbox" name="enabled" value="1" <?= !$router || !empty($router['enabled']) ? 'checked' : '' ?>> مفعّل</label>
            <label><input type="checkbox" name="is_default" value="1" <?= !empty($router['is_default']) ? 'checked' : '' ?>> افتراضي</label>
            <label><input type="radio" name="onboarding_mode" value="existing" <?= ($router['onboarding_mode'] ?? 'existing') === 'existing' ? 'checked' : '' ?>> موجّه قائم بلا تغيير</label>
            <label><input type="radio" name="onboarding_mode" value="bootstrap" <?= ($router['onboarding_mode'] ?? '') === 'bootstrap' ? 'checked' : '' ?>> تشغيل GreenNet على هذا الراوتر</label>
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
        <h2 class="admin-section-title">2. اكتشاف القدرات</h2>
        <p class="admin-section-subtitle">قراءات RouterOS آمنة فقط، وتشمل أسماء الموارد وعناوينها اللازمة لكشف التعارضات الواضحة.</p>
        <form method="post" action="/admin/router-onboarding/detect"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-primary" type="submit">فحص الاتصال والقدرات</button></form>
        <?php foreach (['identity'=>'الهوية','routeros_version'=>'RouterOS','architecture'=>'المعمارية','board_name'=>'الموديل','container_package'=>'Container','container_mode'=>'Container mode','apps'=>'RouterOS Apps'] as $key => $label): ?>
            <div class="admin-payment-item"><strong><?= $h($label) ?></strong><span><?= is_bool($capabilities[$key] ?? null) ? (!empty($capabilities[$key]) ? 'متاح' : 'غير متاح') : $h($capabilities[$key] ?? 'غير معروف') ?></span></div>
        <?php endforeach; ?>
    </section>
    <section class="admin-section-card">
        <h2 class="admin-section-title">3. الجاهزية</h2>
        <?php foreach (($readiness['checks'] ?? []) as $check): ?>
            <div class="admin-payment-item"><div><strong><?= $h($check['label'] ?? '-') ?></strong><div style="font-size:12px"><?= $h($check['message'] ?? '') ?></div></div><span class="admin-badge <?= ($check['status'] ?? '') === 'ready' ? 'admin-badge-success' : 'admin-badge-warning' ?>"><?= $h($check['status'] ?? 'unknown') ?></span></div>
        <?php endforeach; ?>
        <form method="post" action="/admin/router-onboarding/finish"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-primary" type="submit">حفظ الجاهزية</button></form>
    </section>
</div>

<?php if (($router['onboarding_mode'] ?? '') === 'bootstrap'): ?>
<section class="admin-section-card">
    <h2 class="admin-section-title">4. إعداد تثبيت GreenNet</h2>
    <form method="post" action="/admin/router-onboarding/prepare">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group"><label>طريقة التثبيت</label>
            <?php foreach (['apps'=>'RouterOS Apps','container'=>'Traditional Container'] as $method => $label): $info = $methods[$method] ?? []; ?>
                <label style="display:block;margin:8px 0"><input type="radio" name="method" value="<?= $method ?>" <?= $selectedMethod === $method ? 'checked' : '' ?> <?= empty($info['supported']) ? 'disabled' : '' ?>> <?= $label ?><?= empty($info['supported']) ? ' — ' . $h($info['reason'] ?? 'غير مدعوم') : '' ?></label>
            <?php endforeach; ?>
        </div>
        <div class="admin-two-columns">
            <?php foreach ([
                'app_name'=>'اسم GreenNet','storage_path'=>'مسار التخزين الدائم','container_ip'=>'IP الحاوية','gateway_ip'=>'RouterOS Gateway',
                'prefix'=>'Prefix','veth_name'=>'اسم veth','bridge_name'=>'جسر GreenNet','http_port'=>'منفذ HTTP',
                'timezone'=>'المنطقة الزمنية','automation_interval'=>'فاصل الأتمتة بالثواني','admin_username'=>'اسم مدير GreenNet','image_reference'=>'مرجع الصورة/الأرشيف'
            ] as $field => $label): ?>
                <div class="form-group"><label><?= $label ?></label><input name="<?= $field ?>" required dir="<?= in_array($field, ['container_ip','gateway_ip','image_reference'], true) ? 'ltr' : 'auto' ?>" value="<?= $h($deployment[$field] ?? '') ?>"></div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary" type="submit">تجهيز ملخص التثبيت</button>
    </form>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">5. ملخص التثبيت</h2>
    <?php foreach ([
        'الموجّه'=>$deployment['router_name'] ?? '', 'الموديل'=>$deployment['router_model'] ?? '', 'RouterOS'=>$deployment['routeros_version'] ?? '',
        'المعمارية'=>$deployment['architecture'] ?? '', 'الطريقة'=>$selectedMethod === 'apps' ? 'RouterOS Apps' : 'Traditional Container',
        'التخزين'=>$deployment['storage_path'] ?? '', 'شبكة الحاوية'=>($deployment['container_ip'] ?? '') . '/' . ($deployment['prefix'] ?? ''),
        'الوصول'=>'http://' . ($deployment['container_ip'] ?? '') . ':' . ($deployment['http_port'] ?? ''), 'الأتمتة'=>($deployment['automation_interval'] ?? '') . ' ثانية',
        'البيانات الدائمة'=>($deployment['storage_path'] ?? '') . '/data'
    ] as $label => $value): ?><div class="admin-payment-item"><strong><?= $h($label) ?></strong><span dir="ltr"><?= $h($value) ?></span></div><?php endforeach; ?>
    <?php foreach ($warnings as $warning): ?><div class="notice"><?= $h($warning) ?></div><?php endforeach; ?>
    <div class="admin-two-columns">
        <div><h3>سيضيف</h3><p>موارد GreenNet المسماة، التخزين، الحاوية وشبكتها فقط.</p></div>
        <div><h3>لن يغيّر</h3><p>Hotspot أو PPPoE أو الجسور القائمة أو المسار الافتراضي أو سياسة الجدار الناري العامة.</p></div>
    </div>
    <form method="post" action="/admin/router-onboarding/generate">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group"><label>كلمة مرور مدير GreenNet — تستخدم لهذا التنزيل فقط ولا تُحفظ</label><input type="password" name="admin_password" minlength="12" required autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit">تنزيل <?= $selectedMethod === 'apps' ? 'GreenNet Apps YAML' : 'bootstrap.rsc' ?> الحساس</button>
    </form>
    <p><a class="admin-mini-btn" href="/admin/router-onboarding/artifact?id=<?= $id ?>&amp;file=README.txt">تعليمات قصيرة</a>
    <a class="admin-mini-btn" href="/admin/router-onboarding/artifact?id=<?= $id ?>&amp;file=greennet.env.example">قالب البيئة</a></p>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">6. حالة التثبيت</h2>
    <p>الحالة الحالية: <strong><?= $h($router['onboarding_status'] ?? 'registered') ?></strong>. إنشاء الملف لا يعني نجاح التثبيت.</p>
    <form method="post" action="/admin/router-onboarding/installation-status" style="display:inline-block"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="status" value="operator_installed"><button class="admin-mini-btn" type="submit">أبلغ المشغّل أنه ثُبّت</button></form>
    <form method="post" action="/admin/router-onboarding/installation-status" style="display:inline-block"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="status" value="needs_attention"><button class="admin-mini-btn" type="submit">يحتاج انتباهًا</button></form>
    <form method="post" action="/admin/router-onboarding/check-greennet" style="display:inline-block"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-primary" type="submit">فحص GreenNet</button></form>
</section>
<?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">7. ربط الباقات</h2>
    <p class="admin-section-subtitle">التعيينات المفقودة: <?= count($missingMappings) ?>.</p>
    <a class="btn btn-primary" href="/admin/package-push?router_id=<?= $id ?>">فتح Package/Profile Provisioning</a>
</section>
<?php endif; ?>
