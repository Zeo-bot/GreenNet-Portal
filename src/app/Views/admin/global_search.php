<?php
    $q = (string) ($q ?? '');
    $groups = is_array($groups ?? null) ? $groups : [];

    $labels = [
        'customers' => 'الزبائن',
        'renewal_requests' => 'طلبات التجديد',
        'payments' => 'الدفعات',
        'packages' => 'الباقات',
        'notifications' => 'الإشعارات',
        'logs' => 'Logs',
        'timeline_notes' => 'ملاحظات Timeline',
    ];
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Global Search</h1>
        <p class="admin-page-description">
            بحث موحد داخل GreenNet: زبائن، دفعات، طلبات تجديد، باقات، إشعارات، Logs، وملاحظات Timeline.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/customers/table">الزبائن</a>
        <a class="admin-mini-btn" href="/admin/audit">Audit</a>
    </div>
</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">بحث</h2>

    <form method="get" action="/admin/global-search" class="admin-filter-bar">
        <div class="form-group" style="grid-column:1 / -1;">
            <label>اكتب أي شيء</label>
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="username / phone / payment / package / log"
                dir="ltr"
                autofocus
            >
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">بحث</button>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <a class="btn btn-outline" href="/admin/global-search">إلغاء</a>
        </div>
    </form>
</section>

<?php if ($q === ''): ?>

    <section class="admin-section-card">
        <div class="admin-empty-state">
            اكتب كلمة بحث لعرض النتائج من كل أجزاء النظام.
        </div>
    </section>

<?php else: ?>

    <?php foreach ($groups as $groupKey => $rows): ?>
        <section class="admin-section-card">
            <div class="admin-page-header" style="margin-bottom:12px;">
                <h2 class="admin-section-title" style="margin:0;">
                    <?= htmlspecialchars($labels[$groupKey] ?? $groupKey) ?>
                </h2>

                <span class="admin-badge">
                    <?= htmlspecialchars((string) count($rows)) ?>
                </span>
            </div>

            <?php if (count($rows) === 0): ?>
                <div class="admin-empty-state">
                    لا توجد نتائج.
                </div>
            <?php else: ?>
                <div style="display:grid;gap:10px;">
                    <?php foreach ($rows as $row): ?>
                        <div style="border:1px solid #e5e7eb;border-radius:18px;padding:14px;background:#fff;">
                            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                                <div>
                                    <div class="admin-table-title">
                                        <?= htmlspecialchars((string) ($row['_title'] ?? 'Result')) ?>
                                    </div>

                                    <?php if (($row['_subtitle'] ?? '') !== ''): ?>
                                        <div class="admin-table-subtitle" style="margin-top:6px;">
                                            <?= htmlspecialchars((string) $row['_subtitle']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (($row['_link'] ?? '') !== ''): ?>
                                    <a class="admin-row-action primary" href="<?= htmlspecialchars((string) $row['_link']) ?>">
                                        فتح
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

<?php endif; ?>