<?php

declare(strict_types=1);

use GreenNet\Core\Database;

if (!function_exists('gn_router_h')) {
    function gn_router_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gn_router_pdo')) {
    function gn_router_pdo(): PDO
    {
        Database::migrate();

        return Database::connection();
    }
}

if (!function_exists('gn_router_ensure')) {
    function gn_router_ensure(): void
    {
        gn_router_pdo()->exec("
            CREATE TABLE IF NOT EXISTS greennet_router_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        gn_router_pdo()->exec("
            CREATE TABLE IF NOT EXISTS router_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

if (!function_exists('gn_router_set')) {
    function gn_router_set(string $key, string $value): void
    {
        foreach (['greennet_router_settings', 'router_settings'] as $table) {
            $stmt = gn_router_pdo()->prepare("
                INSERT INTO {$table} (setting_key, setting_value, created_at, updated_at)
                VALUES (:key, :value, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT(setting_key) DO UPDATE SET
                    setting_value = excluded.setting_value,
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }
}

if (!function_exists('gn_router_get')) {
    function gn_router_get(array $keys, string $fallback = ''): string
    {
        gn_router_ensure();

        foreach (['greennet_router_settings', 'router_settings'] as $table) {
            foreach ($keys as $key) {
                try {
                    $stmt = gn_router_pdo()->prepare("SELECT setting_value FROM {$table} WHERE setting_key = :key LIMIT 1");
                    $stmt->execute(['key' => $key]);
                    $value = $stmt->fetchColumn();

                    if (is_string($value) && trim($value) !== '') {
                        return $value;
                    }
                } catch (Throwable) {
                    // Continue to next source.
                }
            }
        }

        foreach ($keys as $key) {
            $env = $_ENV[$key] ?? getenv($key);

            if (is_string($env) && trim($env) !== '') {
                return $env;
            }
        }

        return $fallback;
    }
}

gn_router_ensure();

$saved = false;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $host = trim((string) ($_POST['mikrotik_host'] ?? $_POST['host'] ?? ''));
    $port = trim((string) ($_POST['mikrotik_api_port'] ?? $_POST['api_port'] ?? $_POST['port'] ?? '8728'));
    $username = trim((string) ($_POST['mikrotik_username'] ?? $_POST['username'] ?? ''));
    $password = trim((string) ($_POST['mikrotik_password'] ?? $_POST['password'] ?? ''));
    $timeout = trim((string) ($_POST['mikrotik_timeout'] ?? $_POST['timeout'] ?? '3'));

    if ($host === '') {
        $error = 'عنوان الراوتر مطلوب.';
    } elseif ((int) $port <= 0) {
        $error = 'منفذ API غير صحيح.';
    } elseif ($username === '') {
        $error = 'اسم مستخدم API مطلوب.';
    } else {
        try {
            $pairs = [
                'MIKROTIK_HOST' => $host,
                'MIKROTIK_API_PORT' => $port,
                'MIKROTIK_USERNAME' => $username,
                'MIKROTIK_PASSWORD' => $password,
                'MIKROTIK_TIMEOUT' => $timeout,

                'mikrotik_host' => $host,
                'mikrotik_api_port' => $port,
                'mikrotik_username' => $username,
                'mikrotik_password' => $password,
                'mikrotik_timeout' => $timeout,

                'host' => $host,
                'api_port' => $port,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'timeout' => $timeout,
            ];

            foreach ($pairs as $key => $value) {
                gn_router_set($key, $value);
            }

            $saved = true;
        } catch (Throwable $e) {
            $error = 'فشل حفظ الإعدادات: ' . $e->getMessage();
        }
    }
}

$host = gn_router_get(['MIKROTIK_HOST', 'mikrotik_host', 'host'], '192.168.250.2');
$port = gn_router_get(['MIKROTIK_API_PORT', 'mikrotik_api_port', 'api_port', 'port'], '8728');
$username = gn_router_get(['MIKROTIK_USERNAME', 'mikrotik_username', 'username'], 'apiuser');
$password = gn_router_get(['MIKROTIK_PASSWORD', 'mikrotik_password', 'password'], '');
$timeout = gn_router_get(['MIKROTIK_TIMEOUT', 'mikrotik_timeout', 'timeout'], '3');

$readyHost = trim($host) !== '';
$readyPort = (int) $port > 0;
$readyUser = trim($username) !== '';
$readyPassword = trim($password) !== '';
$readyTimeout = (int) $timeout > 0;

$steps = [
    [
        'title' => 'Router Address',
        'desc' => 'تحديد IP الراوتر أو DNS الخاص به.',
        'done' => $readyHost,
    ],
    [
        'title' => 'API Port',
        'desc' => 'منفذ RouterOS API، غالباً 8728.',
        'done' => $readyPort,
    ],
    [
        'title' => 'Credentials',
        'desc' => 'اسم مستخدم API وكلمة المرور.',
        'done' => $readyUser && $readyPassword,
    ],
    [
        'title' => 'Safe Timeout',
        'desc' => 'تحديد timeout قصير حتى لا تتجمد الصفحة.',
        'done' => $readyTimeout,
    ],
    [
        'title' => 'Diagnostics',
        'desc' => 'الفحص الحقيقي يتم من صفحة API Diagnostics وليس من هذه الصفحة.',
        'done' => false,
    ],
];

$readyCount = count(array_filter($steps, static fn (array $step): bool => (bool) $step['done']));
$totalSteps = count($steps);
$overallReady = $readyHost && $readyPort && $readyUser && $readyPassword && $readyTimeout;

?>

<style>
    .gn-router-page {
        display: grid;
        gap: 18px;
    }

    .gn-router-hero {
        position: relative;
        overflow: hidden;
        padding: 24px;
        border-radius: var(--gn-radius-xl);
        background:
            radial-gradient(circle at 0% 0%, rgba(20, 184, 110, 0.22), transparent 30%),
            linear-gradient(135deg, var(--gn-surface), var(--gn-surface-2));
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-router-hero h1 {
        margin: 0;
        color: var(--gn-text);
        font-size: 30px;
        font-weight: 950;
        letter-spacing: -0.05em;
    }

    .gn-router-hero p {
        margin: 10px 0 0;
        max-width: 900px;
        color: var(--gn-text-soft);
        line-height: 1.8;
    }

    .gn-router-status {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-top: 18px;
        min-height: 38px;
        padding: 7px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 950;
    }

    .gn-router-status.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-router-status.is-warning {
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-router-status.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
    }

    .gn-router-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(340px, 0.85fr);
        gap: 18px;
        align-items: start;
    }

    .gn-router-card {
        padding: 18px;
        border-radius: var(--gn-radius-xl);
        background: var(--gn-surface);
        border: 1px solid var(--gn-border);
        box-shadow: var(--gn-shadow-sm);
    }

    .gn-router-card h2 {
        margin: 0 0 12px;
        color: var(--gn-text);
        font-size: 20px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .gn-router-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .gn-router-form-grid .is-wide {
        grid-column: 1 / -1;
    }

    .gn-router-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .gn-router-steps {
        display: grid;
        gap: 12px;
    }

    .gn-router-step {
        position: relative;
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: 12px;
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-router-step-icon {
        width: 42px;
        height: 42px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 950;
        background: var(--gn-warning-soft);
        color: var(--gn-warning);
    }

    .gn-router-step.is-done .gn-router-step-icon {
        background: var(--gn-success-soft);
        color: var(--gn-success);
    }

    .gn-router-step-title {
        margin: 0;
        color: var(--gn-text);
        font-size: 15px;
        font-weight: 950;
    }

    .gn-router-step-desc {
        margin: 4px 0 0;
        color: var(--gn-text-soft);
        line-height: 1.6;
        font-size: 13px;
    }

    .gn-router-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 16px;
    }

    .gn-router-summary-item {
        padding: 12px;
        border-radius: var(--gn-radius-md);
        background: var(--gn-surface-2);
        border: 1px solid var(--gn-border);
    }

    .gn-router-summary-label {
        display: block;
        color: var(--gn-muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .gn-router-summary-value {
        display: block;
        color: var(--gn-text);
        font-size: 13px;
        font-weight: 900;
        direction: ltr;
        text-align: left;
        word-break: break-word;
    }

    .gn-router-alert {
        padding: 14px 16px;
        border-radius: var(--gn-radius-lg);
        font-weight: 900;
        line-height: 1.7;
    }

    .gn-router-alert.is-success {
        background: var(--gn-success-soft);
        color: var(--gn-success);
        border: 1px solid rgba(22, 163, 74, 0.24);
    }

    .gn-router-alert.is-danger {
        background: var(--gn-danger-soft);
        color: var(--gn-danger);
        border: 1px solid rgba(220, 38, 38, 0.24);
    }

    .gn-router-note {
        margin-top: 14px;
        padding: 14px;
        border-radius: var(--gn-radius-lg);
        background: var(--gn-info-soft);
        color: var(--gn-info);
        line-height: 1.7;
        font-weight: 800;
    }

    html[data-theme="greennet-dark"] .gn-router-hero,
    html[data-theme="greennet-dark"] .gn-router-card {
        background: linear-gradient(180deg, rgba(16, 32, 25, 0.94), rgba(10, 25, 17, 0.92));
    }

    @media (max-width: 1200px) {
        .gn-router-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .gn-router-form-grid,
        .gn-router-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="gn-router-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">إعداد الراوتر</h1>
            <p class="admin-page-description">
                إعداد اتصال GreenNet مع MikroTik API. هذه الصفحة لا تتصل بالراوتر حتى لا تسبب Timeout.
            </p>
        </div>

        <div class="admin-header-actions">
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/api/diagnostics">API Diagnostics</a>
            <a class="gn-btn gn-btn-secondary gn-btn-sm" href="/admin/write-safety">Write Safety</a>
            <a class="gn-btn gn-btn-primary gn-btn-sm" href="/admin/router-setup">Refresh</a>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="gn-router-alert is-success">
            تم حفظ إعدادات الراوتر محلياً. استخدم API Diagnostics للفحص الحقيقي.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="gn-router-alert is-danger">
            <?= gn_router_h($error) ?>
        </div>
    <?php endif; ?>

    <section class="gn-router-hero">
        <h1><?= $overallReady ? 'الإعدادات الأساسية مكتملة' : 'الإعدادات بحاجة إكمال' ?></h1>
        <p>
            تم تعطيل أي فحص مباشر داخل هذه الصفحة لمنع مشكلة 504 Gateway Time-out.
            بعد الحفظ، افتح صفحة API Diagnostics لاختبار الاتصال الكامل مع RouterOS.
        </p>

        <div class="gn-router-status <?= $overallReady ? 'is-success' : 'is-warning' ?>">
            <?= $overallReady ? '✅' : '⚠' ?>
            جاهزية الإعدادات: <?= gn_router_h($readyCount) ?> / <?= gn_router_h($totalSteps) ?>
        </div>
    </section>

    <section class="gn-router-layout">
        <div class="gn-router-card">
            <h2>بيانات الاتصال</h2>

            <form method="post" action="/admin/router-setup">
                <div class="gn-router-form-grid">
                    <div class="form-group">
                        <label>Router Host / IP</label>
                        <input type="text" name="mikrotik_host" value="<?= gn_router_h($host) ?>" dir="ltr" placeholder="192.168.250.2" required>
                    </div>

                    <div class="form-group">
                        <label>API Port</label>
                        <input type="number" name="mikrotik_api_port" value="<?= gn_router_h($port) ?>" dir="ltr" placeholder="8728" required>
                    </div>

                    <div class="form-group">
                        <label>API Username</label>
                        <input type="text" name="mikrotik_username" value="<?= gn_router_h($username) ?>" dir="ltr" placeholder="apiuser" required>
                    </div>

                    <div class="form-group">
                        <label>API Password</label>
                        <input type="password" name="mikrotik_password" value="<?= gn_router_h($password) ?>" dir="ltr" placeholder="••••••••">
                    </div>

                    <div class="form-group">
                        <label>Timeout Seconds</label>
                        <input type="number" name="mikrotik_timeout" value="<?= gn_router_h($timeout) ?>" dir="ltr" min="1" max="20">
                    </div>

                    <div class="form-group">
                        <label>Connection Test</label>
                        <input type="text" value="Disabled here — use API Diagnostics" readonly dir="ltr">
                    </div>
                </div>

                <input type="hidden" name="host" value="<?= gn_router_h($host) ?>">
                <input type="hidden" name="api_port" value="<?= gn_router_h($port) ?>">
                <input type="hidden" name="port" value="<?= gn_router_h($port) ?>">
                <input type="hidden" name="username" value="<?= gn_router_h($username) ?>">
                <input type="hidden" name="password" value="<?= gn_router_h($password) ?>">
                <input type="hidden" name="timeout" value="<?= gn_router_h($timeout) ?>">

                <div class="gn-router-actions">
                    <button class="gn-btn gn-btn-primary gn-btn-lg" type="submit">
                        حفظ الإعدادات
                    </button>

                    <a class="gn-btn gn-btn-secondary gn-btn-lg" href="/admin/api/diagnostics">
                        فحص API كامل
                    </a>
                </div>
            </form>

            <div class="gn-router-note">
                ملاحظة: صفحة Router Setup الآن لا تفحص الاتصال تلقائياً حتى لا يتوقف Nginx/PHP إذا الراوتر غير متاح.
            </div>
        </div>

        <aside class="gn-router-card">
            <h2>Readiness Steps</h2>

            <div class="gn-router-steps">
                <?php foreach ($steps as $index => $step): ?>
                    <div class="gn-router-step <?= $step['done'] ? 'is-done' : '' ?>">
                        <div class="gn-router-step-icon">
                            <?= $step['done'] ? '✓' : ($index + 1) ?>
                        </div>

                        <div>
                            <h3 class="gn-router-step-title"><?= gn_router_h($step['title']) ?></h3>
                            <p class="gn-router-step-desc"><?= gn_router_h($step['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="gn-router-summary">
                <div class="gn-router-summary-item">
                    <span class="gn-router-summary-label">Host</span>
                    <span class="gn-router-summary-value"><?= gn_router_h($host) ?></span>
                </div>

                <div class="gn-router-summary-item">
                    <span class="gn-router-summary-label">Port</span>
                    <span class="gn-router-summary-value"><?= gn_router_h($port) ?></span>
                </div>

                <div class="gn-router-summary-item">
                    <span class="gn-router-summary-label">Username</span>
                    <span class="gn-router-summary-value"><?= gn_router_h($username) ?></span>
                </div>

                <div class="gn-router-summary-item">
                    <span class="gn-router-summary-label">Timeout</span>
                    <span class="gn-router-summary-value"><?= gn_router_h($timeout) ?> sec</span>
                </div>
            </div>
        </aside>
    </section>

</div>