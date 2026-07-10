<?php
    $mode = (string) ($mode ?? 'list');

    $customers = is_array($customers ?? null) ? $customers : [];
    $counts = is_array($counts ?? null) ? $counts : [];

    $customer = is_array($customer ?? null) ? $customer : [];
    $username = (string) ($username ?? ($customer['username'] ?? ''));
    $q = (string) ($q ?? '');

    $error = (string) ($error ?? '');
    $message = (string) ($message ?? '');
    $generatedPin = (string) ($generated_pin ?? '');

    $hasPassword = !empty($customer['subscriber_password_hash']);
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            <?= $mode === 'list' ? 'كلمات مرور المشتركين' : 'كلمة مرور المشترك' ?>
        </h1>

        <p class="admin-page-description">
            إدارة كلمات مرور/PIN دخول المشتركين إلى تطبيق GreenNet.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/customers/password">كل كلمات المرور</a>
        <a class="admin-mini-btn" href="/admin/customers/table">جدول الزبائن</a>

        <?php if ($username !== ''): ?>
            <a class="admin-mini-btn" href="/admin/customers/profile?username=<?= urlencode($username) ?>">صفحة المشترك</a>
            <a class="admin-mini-btn" href="/dashboard?username=<?= urlencode($username) ?>">معاينة التطبيق</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="notice" style="background:#fee2e2;color:#991b1b;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($message !== ''): ?>
    <div class="notice" style="background:#f0fdf4;color:#166534;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($generatedPin !== ''): ?>
    <section class="admin-section-card">
        <h2 class="admin-section-title">PIN الجديد</h2>

        <p class="admin-section-subtitle">
            انسخ هذا الرقم وأرسله للمشترك. لن يظهر مرة ثانية بعد مغادرة الصفحة.
        </p>

        <div class="admin-json-box" style="font-size:28px;text-align:center;direction:ltr;">
<?= htmlspecialchars($generatedPin) ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($mode === 'list'): ?>

    <div class="admin-stats-grid">

        <div class="admin-stat-card">
            <div class="admin-stat-label">كل الزبائن</div>
            <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['total'] ?? 0)) ?></div>
            <div class="admin-stat-note">CRM Customers</div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">كلمة مرور مفعلة</div>
            <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['with_password'] ?? 0)) ?></div>
            <div class="admin-stat-note">Ready to login</div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">بدون كلمة مرور</div>
            <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['without_password'] ?? 0)) ?></div>
            <div class="admin-stat-note">Need PIN</div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">مقفلة مؤقتاً</div>
            <div class="admin-stat-value"><?= htmlspecialchars((string) ($counts['locked'] ?? 0)) ?></div>
            <div class="admin-stat-note">Locked accounts</div>
        </div>

    </div>

    <section class="admin-section-card">
        <h2 class="admin-section-title">بحث عن مشترك</h2>

        <form method="get" action="/admin/customers/password" class="admin-filter-bar">
            <div class="form-group">
                <label>بحث</label>
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($q) ?>"
                    placeholder="username / name / phone"
                    dir="ltr"
                >
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-primary" type="submit">بحث</button>
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <a class="btn btn-outline" href="/admin/customers/password">إلغاء البحث</a>
            </div>
        </form>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">قائمة المشتركين</h2>

        <?php if (count($customers) === 0): ?>
            <div class="admin-empty-state">
                لا يوجد مشتركين للعرض.
            </div>
        <?php else: ?>
            <div class="admin-table-responsive">
                <table class="admin-table" style="min-width:980px;">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>الاسم</th>
                            <th>الهاتف</th>
                            <th>حالة كلمة المرور</th>
                            <th>آخر دخول</th>
                            <th>محاولات</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($customers as $row): ?>
                            <?php
                                $rowUsername = (string) ($row['username'] ?? '');
                                $rowHasPassword = !empty($row['subscriber_password_hash']);
                                $lockedUntil = (string) ($row['locked_until'] ?? '');
                                $isLocked = false;

                                if ($lockedUntil !== '') {
                                    $timestamp = strtotime($lockedUntil);

                                    if ($timestamp !== false && $timestamp > time()) {
                                        $isLocked = true;
                                    }
                                }
                            ?>

                            <tr>
                                <td>
                                    <span class="admin-code"><?= htmlspecialchars($rowUsername) ?></span>
                                </td>

                                <td>
                                    <div class="admin-table-title">
                                        <?= htmlspecialchars((string) (($row['full_name'] ?? '') !== '' ? $row['full_name'] : '-')) ?>
                                    </div>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($row['phone'] ?? '') !== '' ? $row['phone'] : '-')) ?>
                                </td>

                                <td>
                                    <?php if ($isLocked): ?>
                                        <span class="admin-badge admin-badge-danger">مقفل مؤقتاً</span>
                                    <?php elseif ($rowHasPassword): ?>
                                        <span class="admin-badge admin-badge-success">مفعلة</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-warning">غير مفعلة</span>
                                    <?php endif; ?>

                                    <?php if (($row['password_changed_at'] ?? '') !== ''): ?>
                                        <div class="admin-table-subtitle">
                                            <?= htmlspecialchars((string) ($row['password_changed_at'] ?? '')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($row['last_login_at'] ?? '') !== '' ? $row['last_login_at'] : '-')) ?>
                                </td>

                                <td>
                                    <span class="admin-badge">
                                        <?= htmlspecialchars((string) ($row['failed_login_attempts'] ?? 0)) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="admin-row-actions">
                                        <a
                                            class="admin-row-action primary"
                                            href="/admin/customers/password?username=<?= urlencode($rowUsername) ?>"
                                        >
                                            تعيين / توليد PIN
                                        </a>

                                        <a
                                            class="admin-row-action"
                                            href="/dashboard?username=<?= urlencode($rowUsername) ?>"
                                        >
                                            معاينة
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>
        <?php endif; ?>
    </section>

<?php else: ?>

    <section class="admin-section-card">
        <h2 class="admin-section-title">بيانات المشترك</h2>

        <?php if (count($customer) === 0): ?>
            <div class="admin-empty-state">
                لا توجد بيانات لعرضها.
            </div>
        <?php else: ?>
            <div class="admin-stats-grid">

                <div class="admin-stat-card">
                    <div class="admin-stat-label">Username</div>
                    <div class="admin-stat-value" style="font-size:18px;direction:ltr;">
                        <?= htmlspecialchars((string) ($customer['username'] ?? '-')) ?>
                    </div>
                    <div class="admin-stat-note">اسم الدخول</div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-label">Full Name</div>
                    <div class="admin-stat-value" style="font-size:18px;">
                        <?= htmlspecialchars((string) (($customer['full_name'] ?? '') !== '' ? $customer['full_name'] : '-')) ?>
                    </div>
                    <div class="admin-stat-note">الاسم</div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-label">Password Status</div>
                    <div class="admin-stat-value" style="font-size:17px;">
                        <?php if ($hasPassword): ?>
                            <span class="admin-badge admin-badge-success">مفعلة</span>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-danger">غير مفعلة</span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-stat-note">
                        <?= htmlspecialchars((string) (($customer['password_changed_at'] ?? '') !== '' ? $customer['password_changed_at'] : 'لم يتم التعيين')) ?>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-label">Login Security</div>
                    <div class="admin-stat-value" style="font-size:17px;">
                        محاولات:
                        <?= htmlspecialchars((string) ($customer['failed_login_attempts'] ?? 0)) ?>
                    </div>
                    <div class="admin-stat-note">
                        Locked:
                        <?= htmlspecialchars((string) (($customer['locked_until'] ?? '') !== '' ? $customer['locked_until'] : '-')) ?>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </section>

    <?php if ($username !== ''): ?>
        <section class="admin-section-card">
            <h2 class="admin-section-title">تعيين كلمة مرور يدوية</h2>

            <p class="admin-section-subtitle">
                استخدم PIN بسيط مثل 6 أرقام أو كلمة مرور أقوى حسب رغبتك.
            </p>

            <form method="post" action="/admin/customers/password" class="form-card">

                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" name="action" value="set">

                <div class="form-grid">

                    <div class="form-group">
                        <label>كلمة المرور / PIN الجديد</label>
                        <input
                            type="text"
                            name="password"
                            placeholder="مثال: 123456"
                            required
                            dir="ltr"
                            autocomplete="off"
                        >
                    </div>

                    <div class="form-group">
                        <label>خيارات</label>

                        <label style="display:flex;gap:8px;align-items:center;margin-top:12px;">
                            <input type="checkbox" name="must_change_password" value="1">
                            إجبار المشترك على تغيير كلمة المرور لاحقاً
                        </label>
                    </div>

                </div>

                <button class="btn btn-primary" type="submit">
                    حفظ كلمة المرور
                </button>

            </form>
        </section>

        <section class="admin-section-card">
            <h2 class="admin-section-title">توليد PIN تلقائي</h2>

            <p class="admin-section-subtitle">
                يقوم النظام بتوليد PIN عشوائي من 6 أرقام وتخزينه مشفراً. يظهر الرقم مرة واحدة فقط.
            </p>

            <form method="post" action="/admin/customers/password">

                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" name="action" value="generate">

                <label style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
                    <input type="checkbox" name="must_change_password" value="1">
                    إجبار المشترك على تغيير كلمة المرور لاحقاً
                </label>

                <button class="btn btn-danger" type="submit">
                    توليد PIN جديد
                </button>

            </form>
        </section>
    <?php endif; ?>

<?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">ملاحظات أمان</h2>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">🔒</div>
            <div>
                <strong>لا يتم تخزين كلمة المرور كنص واضح</strong>
                <br>
                يتم حفظها باستخدام password_hash داخل قاعدة البيانات.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">🛡️</div>
            <div>
                <strong>حماية من التخمين</strong>
                <br>
                بعد عدة محاولات خاطئة يتم قفل الحساب مؤقتاً.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">👁️</div>
            <div>
                <strong>المعاينة كمدير منفصلة</strong>
                <br>
                رابط /dashboard?username= يعمل فقط للمدير المسجل دخوله.
            </div>
        </div>

    </div>
</section>