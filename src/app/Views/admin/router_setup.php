<?php
    $settings = is_array($settings ?? null) ? $settings : [];
    $testResult = is_array($test_result ?? null) ? $test_result : null;
    $detectResult = is_array($detect_result ?? null) ? $detect_result : null;
    $saved = !empty($saved);

    $runtimeMode = (string) ($settings['runtime_mode'] ?? 'external');
    $accessMode = (string) ($settings['access_mode'] ?? 'hybrid');
    $authBackend = (string) ($settings['auth_backend'] ?? 'user-manager');

    $statusBadge = function (?array $result): string {
        if ($result === null) {
            return 'admin-badge';
        }

        return !empty($result['ok']) ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-danger';
    };

    $statusText = function (?array $result): string {
        if ($result === null) {
            return 'لم يتم الفحص';
        }

        return !empty($result['ok']) ? 'ناجح' : 'فشل';
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Router Setup Wizard</h1>

        <p class="admin-page-description">
            إعداد الراوتر النشط الذي سيستخدمه GreenNet. هذه الصفحة تحفظ الإعدادات داخل قاعدة البيانات وتبقي .env كخيار احتياطي.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/api/diagnostics">API Diagnostics</a>
        <a class="admin-mini-btn" href="/admin/readiness">Readiness</a>
        <a class="admin-mini-btn" href="/admin/api/browser">API Browser</a>
    </div>
</div>

<?php if ($saved): ?>
    <div class="notice" style="background:#f0fdf4;color:#166534;">
        تم حفظ إعدادات الراوتر.
    </div>
<?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">وضع التشغيل</h2>

    <p class="admin-section-subtitle">
        اختر أين يعمل GreenNet. حالياً كل الأوضاع تستخدم نفس API، لكن هذا الاختيار سيصبح مهماً لاحقاً للـ Container و YAML.
    </p>

    <form method="post" action="/admin/router-setup">

        <div class="admin-filter-bar" style="grid-template-columns:repeat(3,minmax(0,1fr));">

            <div class="form-group">
                <label>Runtime Mode</label>
                <select name="runtime_mode">
                    <option value="external" <?= $runtimeMode === 'external' ? 'selected' : '' ?>>
                        External Server
                    </option>

                    <option value="routeros-container" <?= $runtimeMode === 'routeros-container' ? 'selected' : '' ?>>
                        RouterOS Container
                    </option>

                    <option value="routeros-app" <?= $runtimeMode === 'routeros-app' ? 'selected' : '' ?>>
                        RouterOS App / YAML 7.22+
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>Router Name</label>
                <input
                    type="text"
                    name="router_name"
                    value="<?= htmlspecialchars((string) ($settings['router_name'] ?? 'Main MikroTik')) ?>"
                    placeholder="Main MikroTik"
                >
            </div>

            <div class="form-group">
                <label>Access Mode</label>
                <select name="access_mode">
                    <option value="hotspot" <?= $accessMode === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                    <option value="ppp" <?= $accessMode === 'ppp' ? 'selected' : '' ?>>PPP / PPPoE</option>
                    <option value="user-manager" <?= $accessMode === 'user-manager' ? 'selected' : '' ?>>User Manager</option>
                    <option value="hybrid" <?= $accessMode === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                </select>
            </div>

        </div>

        <div class="admin-filter-bar" style="grid-template-columns:1.2fr 0.7fr 1fr 1fr;">

            <div class="form-group">
                <label>Router IP / Host</label>
                <input
                    type="text"
                    name="host"
                    value="<?= htmlspecialchars((string) ($settings['host'] ?? '')) ?>"
                    placeholder="192.168.88.1 أو 172.31.255.1"
                    required
                    dir="ltr"
                >
            </div>

            <div class="form-group">
                <label>API Port</label>
                <input
                    type="number"
                    name="api_port"
                    value="<?= htmlspecialchars((string) ($settings['api_port'] ?? 8728)) ?>"
                    placeholder="8728"
                    required
                    dir="ltr"
                >
            </div>

            <div class="form-group">
                <label>Username</label>
                <input
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars((string) ($settings['username'] ?? '')) ?>"
                    placeholder="apiuser"
                    required
                    dir="ltr"
                >
            </div>

            <div class="form-group">
                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    value=""
                    placeholder="اتركها فارغة للإبقاء على القديمة"
                    dir="ltr"
                >
            </div>

        </div>

        <div class="admin-filter-bar" style="grid-template-columns:1fr 1fr 1fr;">

            <div class="form-group">
                <label>Auth Backend</label>
                <select name="auth_backend">
                    <option value="user-manager" <?= $authBackend === 'user-manager' ? 'selected' : '' ?>>User Manager</option>
                    <option value="routeros-local" <?= $authBackend === 'routeros-local' ? 'selected' : '' ?>>RouterOS Local</option>
                    <option value="freeradius" <?= $authBackend === 'freeradius' ? 'selected' : '' ?>>FreeRADIUS</option>
                    <option value="hybrid" <?= $authBackend === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                </select>
            </div>

            <div class="form-group">
                <label>RouterOS YAML Plan</label>
                <input
                    type="text"
                    value="محسوب للخطة المستقبلية — RouterOS 7.22+"
                    disabled
                >
            </div>

            <div class="form-group">
                <label>Container Host IP المقترح</label>
                <input
                    type="text"
                    value="172.31.255.1"
                    disabled
                    dir="ltr"
                >
            </div>

        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
            <button class="btn btn-primary" type="submit" name="action" value="save">
                حفظ فقط
            </button>

            <button class="btn btn-outline" type="submit" name="action" value="test">
                حفظ + اختبار الاتصال
            </button>

            <button class="btn btn-danger" type="submit" name="action" value="detect">
                حفظ + Detect Services
            </button>
        </div>

    </form>
</section>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">Connection Test</div>
        <div class="admin-stat-value" style="font-size:17px;">
            <span class="<?= htmlspecialchars($statusBadge($testResult)) ?>">
                <?= htmlspecialchars($statusText($testResult)) ?>
            </span>
        </div>
        <div class="admin-stat-note">
            <?= htmlspecialchars((string) ($testResult['message'] ?? 'لم يتم الاختبار بعد')) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Router Identity</div>
        <div class="admin-stat-value" style="font-size:18px;">
            <?= htmlspecialchars((string) (($testResult['identity'] ?? '') !== '' ? $testResult['identity'] : ($settings['identity'] ?? '-'))) ?>
        </div>
        <div class="admin-stat-note">
            Last Seen:
            <?= htmlspecialchars((string) ($settings['last_seen'] ?? '-')) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">RouterOS Version</div>
        <div class="admin-stat-value" style="font-size:18px;">
            <?= htmlspecialchars((string) (($testResult['routeros_version'] ?? '') !== '' ? $testResult['routeros_version'] : ($settings['routeros_version'] ?? '-'))) ?>
        </div>
        <div class="admin-stat-note">
            Architecture:
            <?= htmlspecialchars((string) (($testResult['architecture'] ?? '') !== '' ? $testResult['architecture'] : ($settings['architecture'] ?? '-'))) ?>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Board</div>
        <div class="admin-stat-value" style="font-size:18px;">
            <?= htmlspecialchars((string) (($testResult['board_name'] ?? '') !== '' ? $testResult['board_name'] : ($settings['board_name'] ?? '-'))) ?>
        </div>
        <div class="admin-stat-note">
            Uptime:
            <?= htmlspecialchars((string) (($testResult['uptime'] ?? '') !== '' ? $testResult['uptime'] : ($settings['uptime'] ?? '-'))) ?>
        </div>
    </div>

</div>

<?php if ($detectResult !== null): ?>
    <section class="admin-section-card">
        <h2 class="admin-section-title">Detect Services Result</h2>

        <p class="admin-section-subtitle">
            هذه النتائج تساعدنا لاحقاً في First Run Wizard و Auto Match و YAML Installer.
        </p>

        <div class="admin-stats-grid">

            <div class="admin-stat-card">
                <div class="admin-stat-label">Hotspot Users</div>
                <div class="admin-stat-value">
                    <?= htmlspecialchars((string) ($detectResult['summary']['hotspot_users'] ?? 0)) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">PPP Secrets</div>
                <div class="admin-stat-value">
                    <?= htmlspecialchars((string) ($detectResult['summary']['ppp_secrets'] ?? 0)) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">User Manager Users</div>
                <div class="admin-stat-value">
                    <?= htmlspecialchars((string) ($detectResult['summary']['user_manager_users'] ?? 0)) ?>
                </div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-label">Suggested Mode</div>
                <div class="admin-stat-value" style="font-size:18px;">
                    <?= htmlspecialchars((string) ($detectResult['summary']['suggested_access_mode'] ?? 'hybrid')) ?>
                </div>
            </div>

        </div>

        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:920px;">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Command</th>
                        <th>Status</th>
                        <th>Rows</th>
                        <th>Time</th>
                        <th>Message</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (($detectResult['checks'] ?? []) as $check): ?>
                        <tr>
                            <td>
                                <div class="admin-table-title">
                                    <?= htmlspecialchars((string) ($check['label'] ?? '-')) ?>
                                </div>
                            </td>

                            <td>
                                <span class="admin-code">
                                    <?= htmlspecialchars((string) ($check['command'] ?? '-')) ?>
                                </span>
                            </td>

                            <td>
                                <?php if (!empty($check['ok'])): ?>
                                    <span class="admin-badge admin-badge-success">OK</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-danger">Failed</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="admin-badge">
                                    <?= htmlspecialchars((string) ($check['rows_count'] ?? 0)) ?>
                                </span>
                            </td>

                            <td>
                                <span class="admin-code">
                                    <?= htmlspecialchars((string) ($check['duration_ms'] ?? 0)) ?> ms
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars((string) ($check['message'] ?? '-')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    </section>
<?php endif; ?>

<section class="admin-section-card">
    <h2 class="admin-section-title">خطة التشغيل المستقبلية</h2>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">1</div>
            <div>
                <strong>External Server</strong>
                <br>
                GreenNet يعمل على Windows / Linux / VPS ويتصل بالراوتر عبر API.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">2</div>
            <div>
                <strong>RouterOS Container</strong>
                <br>
                GreenNet يعمل داخل MikroTik، والاتصال الداخلي المقترح هو 172.31.255.1.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">Y</div>
            <div>
                <strong>RouterOS Apps YAML 7.22+</strong>
                <br>
                هذا الخيار محفوظ ضمن خطتنا للتنصيب الأوتوماتيكي عبر YAML.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon warning">!</div>
            <div>
                <strong>لا يوجد Write حالياً</strong>
                <br>
                هذه الصفحة تحفظ إعدادات الاتصال فقط. لا تنفذ أي تعديل على MikroTik.
            </div>
        </div>

    </div>
</section>