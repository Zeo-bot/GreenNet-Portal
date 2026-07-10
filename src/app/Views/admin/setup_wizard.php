<?php
    $steps = is_array($steps ?? null) ? $steps : [];

    $statusClass = function (string $status): string {
        return match ($status) {
            'done' => 'admin-badge admin-badge-success',
            'warning' => 'admin-badge admin-badge-warning',
            'danger' => 'admin-badge admin-badge-danger',
            default => 'admin-badge',
        };
    };

    $statusLabel = function (string $status): string {
        return match ($status) {
            'done' => 'جاهز',
            'warning' => 'يحتاج فحص',
            'danger' => 'مشكلة',
            'manual' => 'فحص يدوي',
            default => 'معلومة',
        };
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Setup Wizard النهائي</h1>
        <p class="admin-page-description">
            هذه الصفحة تجمع كل خطوات التحضير قبل الدخول إلى Sprint 10 والربط الفعلي مع MikroTik.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/router-setup">Router Setup</a>
        <a class="admin-mini-btn" href="/admin/readiness">Readiness</a>
        <a class="admin-mini-btn" href="/admin/write-safety">Write Safety</a>
    </div>
</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">خطوات الإعداد</h2>

    <div style="display:grid;gap:12px;">
        <?php foreach ($steps as $step): ?>
            <?php $status = (string) ($step['status'] ?? 'manual'); ?>

            <div style="border:1px solid #e5e7eb;border-radius:20px;padding:16px;background:#fff;">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <div class="admin-table-title">
                            <?= htmlspecialchars((string) ($step['title'] ?? 'Step')) ?>
                        </div>

                        <div style="margin-top:8px;line-height:1.8;">
                            <?= htmlspecialchars((string) ($step['message'] ?? '')) ?>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <span class="<?= htmlspecialchars($statusClass($status)) ?>">
                            <?= htmlspecialchars($statusLabel($status)) ?>
                        </span>

                        <?php if (($step['link'] ?? '') !== ''): ?>
                            <a class="admin-row-action primary" href="<?= htmlspecialchars((string) $step['link']) ?>">
                                فتح
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">قبل Sprint 10</h2>

    <div class="admin-checklist">
        <div class="admin-check-item">
            <div class="admin-check-icon">1</div>
            <div>
                <strong>اختبر Router Setup وAPI Diagnostics</strong>
                <br>
                يجب أن يكون الاتصال مستقر قبل أي كتابة مستقبلية.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">2</div>
            <div>
                <strong>نفذ Backup كامل</strong>
                <br>
                لا تدخل مرحلة Write بدون نسخة احتياطية حديثة.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">3</div>
            <div>
                <strong>اترك Safe Mode مفعلاً</strong>
                <br>
                في أول اختبارات Sprint 10 سنبدأ Dry Run ثم تنفيذ محدود.
            </div>
        </div>
    </div>
</section>