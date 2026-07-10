<?php
    $data = is_array($data ?? null) ? $data : [];
    $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
    $message = (string) ($message ?? '');
    $messageType = (string) ($message_type ?? 'success');

    $missingUsers = is_array($data['router_users_missing_in_crm'] ?? null) ? $data['router_users_missing_in_crm'] : [];
    $missingProfiles = is_array($data['router_profiles_missing_as_packages'] ?? null) ? $data['router_profiles_missing_as_packages'] : [];
    $linkSuggestions = is_array($data['package_link_suggestions'] ?? null) ? $data['package_link_suggestions'] : [];
    $duplicates = is_array($data['duplicates'] ?? null) ? $data['duplicates'] : [];
    $routerErrors = is_array($data['router_errors'] ?? null) ? $data['router_errors'] : [];

    $userKey = function (array $user): string {
        return base64_encode(json_encode([
            'username' => (string) ($user['username'] ?? ''),
            'source_type' => (string) ($user['source_type'] ?? ''),
            'id' => (string) ($user['id'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    };

    $profileKey = function (array $profile): string {
        return base64_encode(json_encode([
            'name' => (string) ($profile['name'] ?? ''),
            'source_type' => (string) ($profile['source_type'] ?? ''),
            'id' => (string) ($profile['id'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    };
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Auto Match / Auto Link</h1>

        <p class="admin-page-description">
            هذه الصفحة تنظف وتربط بيانات GreenNet مع MikroTik محلياً فقط. لا يوجد أي أمر كتابة على MikroTik في هذه المرحلة.
        </p>
    </div>

    <div class="admin-header-actions">
        <a class="admin-mini-btn" href="/admin/health">Health</a>
        <a class="admin-mini-btn" href="/admin/readiness">Readiness</a>
        <a class="admin-mini-btn" href="/admin/api/browser">API Browser</a>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="notice" style="<?= $messageType === 'warning' ? 'background:#fff7ed;color:#92400e;' : 'background:#f0fdf4;color:#166534;' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if (count($routerErrors) > 0): ?>
    <section class="admin-section-card">
        <h2 class="admin-section-title">Router API Warnings</h2>

        <?php foreach ($routerErrors as $error): ?>
            <div class="notice" style="background:#fee2e2;color:#991b1b;">
                <strong><?= htmlspecialchars((string) ($error['source'] ?? 'RouterOS')) ?></strong>
                <br>
                <?= htmlspecialchars((string) ($error['message'] ?? '-')) ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-label">CRM Customers</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['customers_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Local database</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">GreenNet Packages</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['packages_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Local packages</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Router Users</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['router_users_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Hotspot / PPP / UM</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Router Profiles</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['router_profiles_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Profiles read-only</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Missing Users</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['missing_users_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Router → CRM</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Missing Profiles</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['missing_profiles_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Router → Packages</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Link Suggestions</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['link_suggestions_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">CRM package links</div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-label">Duplicates</div>
        <div class="admin-stat-value"><?= htmlspecialchars((string) ($summary['duplicates_count'] ?? 0)) ?></div>
        <div class="admin-stat-note">Same username</div>
    </div>

</div>

<section class="admin-section-card">
    <h2 class="admin-section-title">1) مستخدمو MikroTik غير الموجودين في CRM</h2>

    <p class="admin-section-subtitle">
        استيراد المستخدمين هنا ينشئ زبائن محليين داخل GreenNet فقط. لا يغير أي شيء على MikroTik.
    </p>

    <?php if (count($missingUsers) === 0): ?>
        <div class="admin-empty-state">
            لا يوجد مستخدمون ناقصون. كل مستخدمي MikroTik المقروئين موجودون في CRM أو لا توجد بيانات للعرض.
        </div>
    <?php else: ?>
        <form method="post" action="/admin/auto-match/import-users">

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <button class="btn btn-primary" type="submit">
                    استيراد المحدد إلى CRM
                </button>

                <span class="admin-badge">
                    <?= htmlspecialchars((string) count($missingUsers)) ?> مستخدم
                </span>
            </div>

            <div class="admin-table-responsive">
                <table class="admin-table" style="min-width:920px;">
                    <thead>
                        <tr>
                            <th>اختيار</th>
                            <th>Username</th>
                            <th>Source</th>
                            <th>Profile</th>
                            <th>Disabled</th>
                            <th>Comment</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($missingUsers as $user): ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="users[]"
                                        value="<?= htmlspecialchars($userKey($user)) ?>"
                                        checked
                                    >
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars((string) ($user['username'] ?? '-')) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($user['source_label'] ?? '-')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($user['profile'] ?? '') !== '' ? $user['profile'] : '-')) ?>
                                </td>

                                <td>
                                    <?php if (!empty($user['disabled'])): ?>
                                        <span class="admin-badge admin-badge-warning">Disabled</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-success">Enabled</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($user['comment'] ?? '') !== '' ? $user['comment'] : '-')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </form>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">2) Profiles غير المستوردة كباقات</h2>

    <p class="admin-section-subtitle">
        استيراد Profiles كباقات ينشئ باقات GreenNet محلية يمكن تعديل السعر والمدة والرصيد لها لاحقاً.
    </p>

    <?php if (count($missingProfiles) === 0): ?>
        <div class="admin-empty-state">
            لا يوجد Profiles ناقصة. كل Profiles المقروءة من MikroTik مرتبطة بباقات GreenNet أو لا توجد بيانات.
        </div>
    <?php else: ?>
        <form method="post" action="/admin/auto-match/import-profiles">

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <button class="btn btn-primary" type="submit">
                    استيراد المحدد كباقات
                </button>

                <span class="admin-badge">
                    <?= htmlspecialchars((string) count($missingProfiles)) ?> Profile
                </span>
            </div>

            <div class="admin-table-responsive">
                <table class="admin-table" style="min-width:920px;">
                    <thead>
                        <tr>
                            <th>اختيار</th>
                            <th>Profile</th>
                            <th>Source</th>
                            <th>Rate Limit</th>
                            <th>Shared Users</th>
                            <th>Comment</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($missingProfiles as $profile): ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="profiles[]"
                                        value="<?= htmlspecialchars($profileKey($profile)) ?>"
                                        checked
                                    >
                                </td>

                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars((string) ($profile['name'] ?? '-')) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($profile['source_label'] ?? '-')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($profile['rate_limit'] ?? '') !== '' ? $profile['rate_limit'] : '-')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($profile['shared_users'] ?? '') !== '' ? $profile['shared_users'] : '-')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($profile['comment'] ?? '') !== '' ? $profile['comment'] : '-')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </form>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">3) اقتراحات ربط الزبائن بالباقات</h2>

    <p class="admin-section-subtitle">
        إذا كان الزبون موجوداً في CRM ومستخدم MikroTik لديه Profile مطابق لباقة GreenNet، يمكن ربطه تلقائياً محلياً.
    </p>

    <?php if (count($linkSuggestions) === 0): ?>
        <div class="admin-empty-state">
            لا توجد اقتراحات ربط حالياً. قد تحتاج أولاً لاستيراد Profiles كباقات ثم إعادة فتح هذه الصفحة.
        </div>
    <?php else: ?>
        <form method="post" action="/admin/auto-match/link-packages">

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <button class="btn btn-primary" type="submit">
                    تنفيذ ربط الباقات المقترح
                </button>

                <span class="admin-badge">
                    <?= htmlspecialchars((string) count($linkSuggestions)) ?> اقتراح
                </span>
            </div>

            <div class="admin-table-responsive">
                <table class="admin-table" style="min-width:920px;">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Router Source</th>
                            <th>Router Profile</th>
                            <th>الباقة الحالية</th>
                            <th>الباقة المقترحة</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($linkSuggestions as $suggestion): ?>
                            <tr>
                                <td>
                                    <span class="admin-code">
                                        <?= htmlspecialchars((string) ($suggestion['username'] ?? '-')) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($suggestion['router_source'] ?? '-')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($suggestion['router_profile'] ?? '-')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) (($suggestion['current_package_name'] ?? '') !== '' ? $suggestion['current_package_name'] : 'بلا باقة')) ?>
                                </td>

                                <td>
                                    <span class="admin-badge admin-badge-success">
                                        <?= htmlspecialchars((string) ($suggestion['package_name'] ?? '-')) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </form>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">4) أسماء مستخدمين مكررة على MikroTik</h2>

    <p class="admin-section-subtitle">
        التكرار لا يتم إصلاحه تلقائياً لأنه يحتاج قرار يدوي. هذه نتيجة مهمة قبل أي Write مستقبلاً.
    </p>

    <?php if (count($duplicates) === 0): ?>
        <div class="admin-empty-state">
            لا يوجد تكرار أسماء مستخدمين بين المصادر المقروءة.
        </div>
    <?php else: ?>
        <div class="admin-table-responsive">
            <table class="admin-table" style="min-width:920px;">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Count</th>
                        <th>Sources</th>
                        <th>إجراء</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($duplicates as $duplicate): ?>
                        <?php
                            $sources = [];

                            foreach (($duplicate['items'] ?? []) as $item) {
                                $sources[] = (string) ($item['source_label'] ?? '-');
                            }
                        ?>

                        <tr>
                            <td>
                                <span class="admin-code">
                                    <?= htmlspecialchars((string) ($duplicate['username'] ?? '-')) ?>
                                </span>
                            </td>

                            <td>
                                <span class="admin-badge admin-badge-warning">
                                    <?= htmlspecialchars((string) ($duplicate['count'] ?? 0)) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars(implode(' / ', array_unique($sources))) ?>
                            </td>

                            <td>
                                <a
                                    class="admin-row-action primary"
                                    href="/admin/search?q=<?= urlencode((string) ($duplicate['username'] ?? '')) ?>"
                                >
                                    فتح البحث
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    <?php endif; ?>
</section>

<section class="admin-section-card">
    <h2 class="admin-section-title">ملاحظات أمان</h2>

    <div class="admin-checklist">

        <div class="admin-check-item">
            <div class="admin-check-icon">✓</div>
            <div>
                <strong>لا يوجد Write على MikroTik</strong>
                <br>
                الاستيراد والربط هنا يتمان داخل قاعدة بيانات GreenNet فقط.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">2</div>
            <div>
                <strong>بعد الاستيراد</strong>
                <br>
                افتح الباقات وعدّل السعر والمدة والرصيد حسب خطتك التجارية.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon">3</div>
            <div>
                <strong>بعد الربط</strong>
                <br>
                شغّل Readiness Check للتأكد أن الأخطاء الحرجة قلت.
            </div>
        </div>

        <div class="admin-check-item">
            <div class="admin-check-icon future">→</div>
            <div>
                <strong>المرحلة التالية</strong>
                <br>
                Subscriber App / PWA أو API Audit Log حسب ما نقرر.
            </div>
        </div>

    </div>
</section>