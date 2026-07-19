<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_audit_h')) {
    function gn_audit_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_audit_pdo')) {
    function gn_audit_pdo(): PDO
    {
        Database::migrate();
        return Database::connection();
    }
}

if (!function_exists('gn_audit_table_exists')) {
    function gn_audit_table_exists(string $table): bool
    {
        try {
            $stmt = gn_audit_pdo()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:name LIMIT 1");
            $stmt->execute(['name' => $table]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('gn_audit_ensure')) {
    function gn_audit_ensure(): void
    {
        gn_audit_pdo()->exec("
            CREATE TABLE IF NOT EXISTS api_audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_username TEXT DEFAULT '',
                action TEXT DEFAULT '',
                dataset TEXT DEFAULT '',
                username TEXT DEFAULT '',
                command TEXT DEFAULT '',
                params TEXT DEFAULT '',
                dry_run INTEGER DEFAULT 1,
                executed INTEGER DEFAULT 0,
                success INTEGER DEFAULT 0,
                router_response TEXT DEFAULT '',
                ip_address TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('gn_audit_short')) {
    function gn_audit_short(string $text, int $limit = 120): string
    {
        $text = trim($text);
        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit) . '...';
    }
}

if (!function_exists('gn_audit_pretty')) {
    function gn_audit_pretty(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) return $value;

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $value;
    }
}

if (!function_exists('gn_audit_badge_class')) {
    function gn_audit_badge_class(string $severity): string
    {
        return match ($severity) {
            'success' => 'gn-badge-success',
            'danger' => 'gn-badge-danger',
            'warning' => 'gn-badge-warning',
            'info' => 'gn-badge-info',
            default => 'gn-badge-primary',
        };
    }
}

if (!function_exists('gn_audit_badge_label')) {
    function gn_audit_badge_label(string $severity): string
    {
        return match ($severity) {
            'success' => 'Success',
            'danger' => 'Failed',
            'warning' => 'Warning',
            'info' => 'Dry Run',
            default => 'Info',
        };
    }
}

gn_audit_ensure();

$query = trim((string) ($_GET['q'] ?? ''));
$sourceFilter = trim((string) ($_GET['source'] ?? 'all'));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));

$events = [];

if (gn_audit_table_exists('api_audit_logs')) {
    try {
        $rows = gn_audit_pdo()->query("
            SELECT *
            FROM api_audit_logs
            ORDER BY datetime(created_at) DESC, id DESC
            LIMIT 250
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $executed = (int) ($row['executed'] ?? 0);
            $success = (int) ($row['success'] ?? 0);
            $dryRun = (int) ($row['dry_run'] ?? 1);
            $response = strtolower((string) ($row['router_response'] ?? ''));

            $severity = 'neutral';

            if ($executed === 1 && $success === 1) {
                $severity = 'success';
            } elseif ($executed === 1 && $success === 0) {
                $severity = 'danger';
            } elseif (str_contains($response, 'unreachable') || str_contains($response, 'timeout') || str_contains($response, 'failed')) {
                $severity = 'warning';
            } elseif ($dryRun === 1) {
                $severity = 'info';
            }

            $events[] = [
                'source' => 'MikroTik',
                'severity' => $severity,
                'time' => (string) ($row['created_at'] ?? ''),
                'title' => trim((string) ($row['action'] ?? 'MikroTik operation') . ' ' . ((string) ($row['username'] ?? '') !== '' ? 'for ' . (string) $row['username'] : '')),
                'action' => $dryRun === 1 ? 'DRY RUN' : ($executed === 1 ? 'EXECUTED' : 'PREVIEW'),
                'dataset' => (string) ($row['dataset'] ?? ''),
                'username' => (string) ($row['username'] ?? ''),
                'message' => (string) ($row['router_response'] ?? ''),
                'command' => (string) ($row['command'] ?? ''),
                'params' => (string) ($row['params'] ?? ''),
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'admin_username' => (string) ($row['admin_username'] ?? ''),
                'meta' => $row,
                'is_api' => true,
            ];
        }
    } catch (Throwable) {
        $events = [];
    }
}

if (gn_audit_table_exists('app_logs')) {
    try {
        $rows = gn_audit_pdo()->query("SELECT * FROM app_logs ORDER BY id DESC LIMIT 120")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $level = strtolower((string) ($row['level'] ?? $row['severity'] ?? 'info'));
            $message = (string) ($row['message'] ?? $row['event'] ?? $row['title'] ?? 'Application log');

            $severity = match ($level) {
                'error', 'critical', 'danger' => 'danger',
                'warning', 'warn' => 'warning',
                'success' => 'success',
                default => 'neutral',
            };

            $events[] = [
                'source' => 'App',
                'severity' => $severity,
                'time' => (string) ($row['created_at'] ?? $row['time'] ?? $row['date'] ?? ''),
                'title' => gn_audit_short($message, 90),
                'action' => strtoupper($level),
                'dataset' => '',
                'username' => (string) ($row['username'] ?? $row['user'] ?? ''),
                'message' => $message,
                'command' => '',
                'params' => (string) ($row['context'] ?? $row['data'] ?? $row['payload'] ?? ''),
                'ip_address' => '',
                'admin_username' => '',
                'meta' => $row,
                'is_api' => false,
            ];
        }
    } catch (Throwable) {
        // ignore
    }
}

usort($events, static fn (array $a, array $b): int => strtotime((string) ($b['time'] ?? '')) <=> strtotime((string) ($a['time'] ?? '')));

$events = array_values(array_filter($events, static function (array $event) use ($query, $sourceFilter, $statusFilter): bool {
    $haystack = strtolower(json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

    if ($query !== '' && !str_contains($haystack, strtolower($query))) return false;
    if ($sourceFilter === 'mikrotik' && ($event['source'] ?? '') !== 'MikroTik') return false;
    if ($sourceFilter === 'app' && ($event['source'] ?? '') !== 'App') return false;
    if ($statusFilter !== 'all' && $statusFilter !== (string) ($event['severity'] ?? 'neutral')) return false;

    return true;
}));

$total = count($events);
$mikrotikCount = count(array_filter($events, static fn (array $event): bool => ($event['source'] ?? '') === 'MikroTik'));
$dryRunCount = count(array_filter($events, static fn (array $event): bool => ($event['action'] ?? '') === 'DRY RUN'));
$warningCount = count(array_filter($events, static fn (array $event): bool => in_array(($event['severity'] ?? ''), ['warning', 'danger'], true)));
$events = array_slice($events, 0, 180);

?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Audit Center</h1>
        <p class="admin-page-description">مركز مراقبة العمليات الحساسة، Dry Run، أوامر MikroTik، وأحداث النظام.</p>
    </div>

    <div class="admin-header-actions">
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/mikrotik-dry-run">MikroTik Dry Run</a>
        <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/write-safety">Write Safety</a>
        <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/audit">Refresh</a>
    </div>
</div>

<section class="gn-audit-kpis">
    <div class="gn-audit-kpi">
        <div class="gn-audit-kpi-label">Total Events</div>
        <div class="gn-audit-kpi-value"><?= gn_audit_h($total) ?></div>
        <div class="gn-audit-kpi-note">حسب الفلتر الحالي</div>
    </div>

    <div class="gn-audit-kpi">
        <div class="gn-audit-kpi-label">MikroTik</div>
        <div class="gn-audit-kpi-value"><?= gn_audit_h($mikrotikCount) ?></div>
        <div class="gn-audit-kpi-note">API / Write / Dry Run</div>
    </div>

    <div class="gn-audit-kpi">
        <div class="gn-audit-kpi-label">Dry Runs</div>
        <div class="gn-audit-kpi-value"><?= gn_audit_h($dryRunCount) ?></div>
        <div class="gn-audit-kpi-note">محاكاة بدون تنفيذ</div>
    </div>

    <div class="gn-audit-kpi">
        <div class="gn-audit-kpi-label">Warnings / Failed</div>
        <div class="gn-audit-kpi-value"><?= gn_audit_h($warningCount) ?></div>
        <div class="gn-audit-kpi-note">بحاجة مراجعة</div>
    </div>
</section>

<section class="admin-section-card">
    <form method="get" action="/admin/audit" class="gn-audit-filters">
        <div class="form-group">
            <label>Search</label>
            <input type="search" name="q" value="<?= gn_audit_h($query) ?>" placeholder="username, action, command..." dir="ltr">
        </div>

        <div class="form-group">
            <label>Source</label>
            <select name="source">
                <option value="all" <?= $sourceFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="mikrotik" <?= $sourceFilter === 'mikrotik' ? 'selected' : '' ?>>MikroTik</option>
                <option value="app" <?= $sourceFilter === 'app' ? 'selected' : '' ?>>App Logs</option>
            </select>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="info" <?= $statusFilter === 'info' ? 'selected' : '' ?>>Dry Run / Info</option>
                <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Success</option>
                <option value="warning" <?= $statusFilter === 'warning' ? 'selected' : '' ?>>Warning</option>
                <option value="danger" <?= $statusFilter === 'danger' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <button class="gn-btn gn-btn-primary" type="submit">
                <span class="gn-icon">⌕</span>
                <span class="gn-btn-label">Apply</span>
            </button>
        </div>

        <div class="form-group">
            <label>&nbsp;</label>
            <a class="gn-btn gn-btn-secondary" href="/admin/audit">Clear</a>
        </div>
    </form>
</section>

<section class="gn-audit-card">
    <div class="gn-audit-card-header">
        <div>
            <h2>Audit Timeline</h2>
            <p>آخر العمليات والأحداث مرتبة من الأحدث إلى الأقدم.</p>
        </div>

        <span class="gn-table-count"><?= gn_audit_h(count($events)) ?></span>
    </div>

    <?php if (count($events) === 0): ?>
        <div class="gn-table-empty">لا توجد أحداث مطابقة للفلتر الحالي.</div>
    <?php else: ?>
        <div class="gn-table-scroll">
            <table class="gn-data-table gn-audit-table" data-gn-no-card="1">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Source</th>
                        <th>User</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($events as $event): ?>
                        <?php
                            $severity = (string) ($event['severity'] ?? 'neutral');
                            $badgeClass = gn_audit_badge_class($severity);
                            $badgeLabel = gn_audit_badge_label($severity);
                            $isApi = (bool) ($event['is_api'] ?? false);
                        ?>
                        <tr>
                            <td class="gn-table-date" dir="ltr">
                                <span class="gn-cell-main"><?= gn_audit_h((string) ($event['time'] ?? '-')) ?></span>
                                <?php if (!empty($event['ip_address'])): ?>
                                    <span class="gn-cell-sub" dir="ltr"><?= gn_audit_h((string) $event['ip_address']) ?></span>
                                <?php endif; ?>
                            </td>

                            <td class="gn-table-nowrap">
                                <span class="gn-audit-source <?= $isApi ? 'is-mikrotik' : 'is-app' ?>">
                                    <?= $isApi ? 'MikroTik' : 'App' ?>
                                </span>

                                <?php if (!empty($event['dataset'])): ?>
                                    <span class="gn-cell-sub" dir="ltr"><?= gn_audit_h((string) $event['dataset']) ?></span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($event['username'])): ?>
                                    <span class="gn-cell-main" dir="ltr"><?= gn_audit_h((string) $event['username']) ?></span>
                                <?php else: ?>
                                    <span class="gn-cell-sub">-</span>
                                <?php endif; ?>

                                <?php if (!empty($event['admin_username'])): ?>
                                    <span class="gn-cell-sub">Admin: <?= gn_audit_h((string) $event['admin_username']) ?></span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="gn-cell-main" dir="ltr"><?= gn_audit_h((string) ($event['title'] ?? 'Event')) ?></span>
                                <span class="gn-cell-sub"><?= gn_audit_h(gn_audit_short((string) ($event['message'] ?? ''), 120)) ?></span>
                            </td>

                            <td class="gn-table-status">
                                <span class="gn-badge <?= gn_audit_h($badgeClass) ?>"><?= gn_audit_h($badgeLabel) ?></span>
                                <span class="gn-cell-sub" dir="ltr"><?= gn_audit_h((string) ($event['action'] ?? '')) ?></span>
                            </td>

                            <td class="gn-table-actions">
                                <details class="gn-audit-details">
                                    <summary class="gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto">View</summary>

                                    <div class="gn-audit-details-body">
                                        <div class="gn-audit-detail-grid">
                                            <div>
                                                <strong>Message</strong>
                                                <p><?= gn_audit_h((string) ($event['message'] ?? '')) ?></p>
                                            </div>

                                            <?php if (!empty($event['command'])): ?>
                                                <div>
                                                    <strong>Command</strong>
                                                    <pre><?= gn_audit_h((string) $event['command']) ?></pre>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($event['params'])): ?>
                                                <div>
                                                    <strong>Parameters</strong>
                                                    <pre><?= gn_audit_h(gn_audit_pretty((string) $event['params'])) ?></pre>
                                                </div>
                                            <?php endif; ?>

                                            <div>
                                                <strong>Raw Event</strong>
                                                <pre><?= gn_audit_h(json_encode($event['meta'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>