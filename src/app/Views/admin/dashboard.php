<?php
    $paymentsList = $payments ?? [];
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            لوحة المدير
        </h1>

        <p class="admin-page-description">
            مركز إدارة <?= htmlspecialchars($app_name ?? 'GreenNet') ?>: المشتركين، الاشتراكات، الباقات، التقارير، والجاهزية قبل التحكم بالـ MikroTik.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/settings">الإعدادات</a>
        <a class="admin-mini-btn" href="/admin/backup">Backup</a>
        <a class="admin-mini-btn danger" href="/admin/logout">خروج</a>
    </div>
</div>

<form method="get" action="/admin/search" style="margin-bottom:18px;">
    <div class="form-group">
        <label>بحث سريع عن مشترك</label>
        <input type="text" name="q" placeholder="اسم المستخدم / الهاتف / IP / الباقة">
    </div>

    <button class="btn btn-primary" type="submit">
        🔎 بحث
    </button>
</form>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">👥 إجمالي الزبائن</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($customers_count ?? 0)) ?></div>
        <div class="admin-stat-note">كل المشتركين داخل CRM</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">✅ مدفوع</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($customers_paid ?? 0)) ?></div>
        <div class="admin-stat-note">حالة الدفع paid</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">⚠️ غير مدفوع</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($customers_unpaid ?? 0)) ?></div>
        <div class="admin-stat-note">due / pending / unknown</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">💰 إجمالي الدفعات</div>
        <div class="admin-stat-value" style="font-size:20px;">
            <?= htmlspecialchars((string) ($total_paid ?? 0)) ?>
        </div>
        <div class="admin-stat-note"><?= htmlspecialchars($default_currency ?? 'SYP') ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">📢 الإعلانات</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) count($announcements ?? [])) ?></div>
        <div class="admin-stat-note">الفعالة: <?= htmlspecialchars((string) ($announcements_active ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">⚙️ QoS Profiles</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) count($qos_profiles ?? [])) ?></div>
        <div class="admin-stat-note">محلية داخل GreenNet</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">👤 حسابات المدير</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($admin_count ?? 0)) ?></div>
        <div class="admin-stat-note">حسابات إدارة النظام</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">🎨 الهوية</div>
        <div class="admin-stat-value" style="font-size:16px;">
            <?= !empty($site_logo_path) ? 'شعار مرفوع' : 'بدون شعار' ?>
        </div>
        <div class="admin-stat-note"><?= htmlspecialchars($network_name ?? ($app_name ?? 'GreenNet')) ?></div>
    </div>

</div>

<div class="admin-two-columns">

    <section class="admin-section-card">
        <h2 class="admin-section-title">إجراءات سريعة</h2>
        <p class="admin-section-subtitle">
            أهم الصفحات المستخدمة يومياً.
        </p>

        <div class="admin-action-grid">

            <a class="admin-action-card" href="/admin/customers/table">
                <div class="admin-action-icon">👥</div>
                <div class="admin-action-title">جدول الزبائن</div>
                <div class="admin-action-desc">بحث وفلاتر وإجراءات سريعة للمشتركين.</div>
            </a>

            <a class="admin-action-card" href="/admin/subscriptions">
                <div class="admin-action-icon">📅</div>
                <div class="admin-action-title">الاشتراكات</div>
                <div class="admin-action-desc">فعال، منتهي، قريب الانتهاء، بلا تجديد.</div>
            </a>

            <a class="admin-action-card" href="/admin/packages">
                <div class="admin-action-icon">📦</div>
                <div class="admin-action-title">الباقات</div>
                <div class="admin-action-desc">إدارة الباقات ومزامنتها مع MikroTik Profiles.</div>
            </a>

            <a class="admin-action-card" href="/admin/routeros">
                <div class="admin-action-icon">🧩</div>
                <div class="admin-action-title">MikroTik</div>
                <div class="admin-action-desc">حالة الاتصال والقراءة من RouterOS API.</div>
            </a>

            <a class="admin-action-card" href="/admin/reports">
                <div class="admin-action-icon">📊</div>
                <div class="admin-action-title">التقارير</div>
                <div class="admin-action-desc">الدفعات والتجديدات والباقات الأكثر استخداماً.</div>
            </a>

            <a class="admin-action-card" href="/admin/backup">
                <div class="admin-action-icon">💾</div>
                <div class="admin-action-title">Backup & Restore</div>
                <div class="admin-action-desc">نسخ احتياطي، استعادة، وتنظيف بيانات التطوير.</div>
            </a>

        </div>
    </section>

    <section class="admin-section-card">
        <h2 class="admin-section-title">حالة الدفع</h2>
        <p class="admin-section-subtitle">
            تفصيل سريع لحالات المشتركين داخل CRM.
        </p>

        <div style="display:flex; flex-wrap:wrap; gap:8px;">
            <span class="admin-badge admin-badge-success">
                مدفوع: <?= htmlspecialchars((string) ($customers_paid ?? 0)) ?>
            </span>

            <span class="admin-badge admin-badge-warning">
                عليه دفع: <?= htmlspecialchars((string) ($customers_due ?? 0)) ?>
            </span>

            <span class="admin-badge">
                مؤجل: <?= htmlspecialchars((string) ($customers_pending ?? 0)) ?>
            </span>

            <span class="admin-badge admin-badge-danger">
                غير معروف: <?= htmlspecialchars((string) ($customers_unknown ?? 0)) ?>
            </span>
        </div>

        <h2 class="admin-section-title" style="margin-top:22px;">آخر الدفعات</h2>

        <div class="admin-payment-list">
            <?php if (count($paymentsList) === 0): ?>
                <div class="admin-payment-item">
                    لا توجد دفعات حالياً.
                </div>
            <?php else: ?>
                <?php foreach ($paymentsList as $payment): ?>
                    <div class="admin-payment-item">
                        <div>
                            <div class="admin-payment-user">
                                <?= htmlspecialchars($payment['username'] ?? '-') ?>
                            </div>
                            <div style="color:#6b7280;font-size:12px;">
                                <?= htmlspecialchars($payment['paid_at'] ?? '-') ?>
                            </div>
                        </div>

                        <div class="admin-payment-amount">
                            <?= htmlspecialchars((string) ($payment['amount'] ?? 0)) ?>
                            <?= htmlspecialchars($payment['currency'] ?? ($default_currency ?? 'SYP')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">قائمة الجاهزية قبل MikroTik Write</h2>
    <p class="admin-section-subtitle">
        هذه العناصر ستُستكمل قبل أول أمر تعديل فعلي على MikroTik.
    </p>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">✓</div>
            <div>
                <strong>Settings & Branding</strong>
                <br>
                اسم الشبكة، الدعم، واتساب، اللون، والشعار قابلة للتعديل.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">✓</div>
            <div>
                <strong>Backup / Restore</strong>
                <br>
                النسخ الاحتياطي والاستعادة جاهزة قبل أي مخاطرة.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon warning">!</div>
            <div>
                <strong>API Diagnostics</strong>
                <br>
                سنضيف فحص اتصال RouterOS API بالتفصيل.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon warning">!</div>
            <div>
                <strong>Readiness Check</strong>
                <br>
                كشف الزبائن والباقات غير الجاهزة قبل الكتابة.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">↻</div>
            <div>
                <strong>Auto Match</strong>
                <br>
                ربط تلقائي بين MikroTik Profiles وباقات GreenNet.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">🔒</div>
            <div>
                <strong>Security Basics</strong>
                <br>
                CSRF، تغيير كلمة مرور المدير، وإغلاق صفحات التطوير.
            </div>
        </div>

    </div>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">صفحات النظام</h2>
    <p class="admin-section-subtitle">
        روابط إدارية إضافية.
    </p>

    <div class="admin-action-grid">

        <a class="admin-action-card" href="/admin/settings">
            <div class="admin-action-icon">🎨</div>
            <div class="admin-action-title">الإعدادات والهوية</div>
            <div class="admin-action-desc">الشعار، الألوان، أرقام الدعم، ورسائل واتساب.</div>
        </a>

        <a class="admin-action-card" href="/admin/announcements">
            <div class="admin-action-icon">📢</div>
            <div class="admin-action-title">الإعلانات</div>
            <div class="admin-action-desc">رسائل تظهر في لوحة المشترك.</div>
        </a>

        <a class="admin-action-card" href="/admin/qos">
            <div class="admin-action-icon">⚙️</div>
            <div class="admin-action-title">Smart QoS</div>
            <div class="admin-action-desc">Profiles محلية تمهيداً للربط مع MikroTik.</div>
        </a>

        <a class="admin-action-card" href="/admin/logs">
            <div class="admin-action-icon">🧾</div>
            <div class="admin-action-title">سجل العمليات</div>
            <div class="admin-action-desc">عمليات التجديد، النسخ، الاستعادة، والتعديلات.</div>
        </a>

        <a class="admin-action-card" href="/admin/system">
            <div class="admin-action-icon">🛠️</div>
            <div class="admin-action-title">حالة النظام</div>
            <div class="admin-action-desc">معلومات البيئة والإعدادات الفنية.</div>
        </a>

        <a class="admin-action-card" href="/dev/database">
            <div class="admin-action-icon">🧪</div>
            <div class="admin-action-title">فحص قاعدة البيانات</div>
            <div class="admin-action-desc">صفحة تطويرية ستُغلق في production.</div>
        </a>

    </div>
</section>

<div style="margin-top:22px;">
    <a class="btn btn-outline" href="/dashboard">
        معاينة لوحة المشترك
    </a>

    <a class="btn btn-danger" href="/admin/logout">
        تسجيل خروج المدير
    </a>
</div>