<?php
    $logs = is_array($logs ?? null) ? $logs : [];
    $stats = is_array($stats ?? null) ? $stats : [];
    $filters = is_array($filters ?? null) ? $filters : [];

    $badgeClass = function (string $level): string {
        return match ($level) {
            'error' => 'admin-badge admin-badge-danger',
            'warning' => 'admin-badge admin-badge-warning',
            default => 'admin-badge admin-badge-success',
        };
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">سجل العمليات</h1>
        <p class="admin-page-description">
            سجل التعديلات والأحداث المهمة داخل GreenNet. لاحقاً سيتم توسيعه ليشمل API Audit Logs.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/export/logs.csv">CSV</a>
        <a class="admin-mini-btn" href="/admin/security">الأمان</a>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-label">النتائج</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['total'] ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Info</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['info'] ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Warning</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['warning'] ?? 0)) ?></div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Error</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($stats['error'] ?? 0)) ?></div>
    </div>
</div>

<form class="admin-filter-bar" method="get" action="/admin/logs" style="grid-template-columns:1.5fr 1fr auto;">
    <div class="form-group">
        <label>بحث</label>
        <input
            type="text"
            name="q"
            value="<?= htmlspecialchars((string) ($filters['q'] ?? '')) ?>"
            placeholder="رسالة / context"
        >
    </div>

    <div class="form-group">
        <label>Level</label>
        <select name="level">
            <?php
                $levels = [
                    'all' => 'الكل',
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'error' => 'Error',
                ];
            ?>

            <?php foreach ($levels as $value => $label): ?>
                <option value="<?= htmlspecialchars($value) ?>" <?= (($filters['level'] ?? 'all') === $value) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn-primary" type="submit">تطبيق</button>
</form>

<section class="admin-section-card">
    <h2 class="admin-section-title">Logs</h2>

    <?php if (count($logs) === 0): ?>
        <div class="admin-empty-state">
            لا توجد سجلات مطابقة.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Level</th>
                        <th>Message</th>
                        <th>Context</th>
                        <th>Created At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                            $context = (string) ($log['context'] ?? '');
                            $prettyContext = $context;

                            $decoded = json_decode($context, true);

                            if (is_array($decoded)) {
                                $prettyContext = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        ?>

                        <tr>
                            <td>
                                <span class="admin-code">
                                    #<?= htmlspecialchars((string) ($log['id'] ?? '-')) ?>
                                </span>
                            </td>

                            <td>
                                <span class="<?= htmlspecialchars($badgeClass((string) ($log['level'] ?? 'info'))) ?>">
                                    <?= htmlspecialchars((string) ($log['level'] ?? 'info')) ?>
                                </span>
                            </td>

                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($log['message'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <?php if ($prettyContext !== '' && $prettyContext !== 'null'): ?>
                                    <div class="admin-json-box">
                                        <?= htmlspecialchars($prettyContext) ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars((string) ($log['created_at'] ?? '-')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</section>