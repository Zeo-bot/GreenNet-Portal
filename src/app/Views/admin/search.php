<?php
    $results = $search['results'] ?? [];
?>

<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">🔎</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>البحث عن مشترك</p>

            <div class="status-pill">
                <span class="dot"></span>
                CRM + MikroTik + User Manager
            </div>
        </div>

        <div class="notice">
            يمكنك البحث باسم المستخدم، الاسم، الهاتف، الملاحظات، IP، MAC / Caller ID، أو اسم البروفايل.
            البحث يشمل CRM المحلي، المتصلين الآن، Hotspot Users، PPP Secrets، و User Manager.
        </div>

        <form method="get" action="/admin/search">

            <div class="form-group">
                <label>كلمة البحث</label>
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($query ?? '') ?>"
                    placeholder="مثال: ahmad أو 09 أو 192.168 أو اسم البروفايل"
                    required
                >
            </div>

            <button class="btn btn-primary" type="submit">
                بحث
            </button>

        </form>

        <?php if (($query ?? '') !== ''): ?>

            <div class="grid">

                <div class="stat">
                    <div class="label">النتائج</div>
                    <div class="value"><?= htmlspecialchars((string) ($search['total_count'] ?? 0)) ?></div>
                </div>

                <div class="stat">
                    <div class="label">موجود CRM</div>
                    <div class="value"><?= htmlspecialchars((string) ($search['crm_count'] ?? 0)) ?></div>
                </div>

                <div class="stat">
                    <div class="label">متصل الآن</div>
                    <div class="value"><?= htmlspecialchars((string) ($search['online_count'] ?? 0)) ?></div>
                </div>

                <div class="stat">
                    <div class="label">غير موجود CRM</div>
                    <div class="value"><?= htmlspecialchars((string) ($search['missing_crm_count'] ?? 0)) ?></div>
                </div>

                <div class="stat">
                    <div class="label">من MikroTik</div>
                    <div class="value"><?= htmlspecialchars((string) ($search['mikrotik_directory_count'] ?? 0)) ?></div>
                </div>

                <div class="stat">
                    <div class="label">Hotspot Users</div>
                    <div class="value"><?= htmlspecialchars((string) ($search['hotspot_users_count'] ?? 0)) ?></div>
                </div>

                <div class="stat">
                    <div class="label">PPP Secrets</div>
                    <div class="value"><?= htmlspecialchars((string) ($search['ppp_secrets_count'] ?? 0)) ?></div>
                </div>

                <div class="stat">
                    <div class="label">User Manager</div>
                    <div class="value"><?= htmlspecialchars((string) ($search['user_manager_count'] ?? 0)) ?></div>
                </div>

            </div>

            <div class="stat" style="margin-top:18px;">
                <div class="label">نتائج البحث عن: <?= htmlspecialchars($query ?? '') ?></div>

                <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                    <?php if (count($results) === 0): ?>
                        لا توجد نتائج مطابقة.
                    <?php else: ?>
                        <?php foreach ($results as $row): ?>
                            <?php
                                $boxStyle = 'border-bottom:1px solid #e5e7eb; padding:12px 0;';

                                if (($row['online'] ?? false) === true) {
                                    $boxStyle .= ' background:#f0fdf4; border-radius:14px; padding:12px; margin-bottom:10px;';
                                }

                                if (($row['crm_found'] ?? false) === false) {
                                    $boxStyle .= ' background:#fef2f2; border-radius:14px; padding:12px; margin-bottom:10px;';
                                }

                                if (($row['payment_status'] ?? '') === 'due') {
                                    $boxStyle .= ' background:#fff7ed; border-radius:14px; padding:12px; margin-bottom:10px;';
                                }

                                $rowType = strtolower((string) ($row['type'] ?? 'hybrid'));

                                $selectedAccessType = 'hybrid';

                                if (str_contains($rowType, 'hotspot')) {
                                    $selectedAccessType = 'hotspot';
                                }

                                if (str_contains($rowType, 'ppp')) {
                                    $selectedAccessType = 'ppp';
                                }

                                $defaultNotesParts = [];

                                if (($row['mikrotik_source'] ?? '-') !== '-') {
                                    $defaultNotesParts[] = 'مستورد من ' . ($row['mikrotik_source'] ?? 'MikroTik');
                                }

                                if (($row['profile'] ?? '-') !== '-') {
                                    $defaultNotesParts[] = 'Profile: ' . ($row['profile'] ?? '-');
                                }

                                if (($row['comment'] ?? '-') !== '-') {
                                    $defaultNotesParts[] = 'Comment: ' . ($row['comment'] ?? '-');
                                }

                                $defaultNotes = implode(' - ', $defaultNotesParts);
                            ?>

                            <div style="<?= htmlspecialchars($boxStyle) ?>">

                                <strong><?= htmlspecialchars($row['username'] ?? '-') ?></strong>
                                —
                                <?= htmlspecialchars($row['type'] ?? '-') ?>
                                <br>

                                المصدر:
                                <?= htmlspecialchars($row['source'] ?? '-') ?>
                                <br>

                                مصدر MikroTik:
                                <?= htmlspecialchars($row['mikrotik_source'] ?? '-') ?>
                                <br>

                                حالة الاتصال:
                                <?= !empty($row['online']) ? 'متصل الآن' : 'غير متصل حالياً' ?>
                                <br>

                                حالة CRM:
                                <?= !empty($row['crm_found']) ? 'موجود' : 'غير موجود' ?>
                                <br>

                                الاسم:
                                <?= htmlspecialchars(($row['display_name'] ?? '') !== '' ? $row['display_name'] : '-') ?>
                                <br>

                                الهاتف:
                                <?= htmlspecialchars(($row['phone'] ?? '') !== '' ? $row['phone'] : '-') ?>
                                <br>

                                حالة الدفع:
                                <?= htmlspecialchars($row['payment_label'] ?? '-') ?>
                                <br>

                                Profile:
                                <?= htmlspecialchars($row['profile'] ?? '-') ?>
                                <br>

                                Disabled:
                                <?= htmlspecialchars($row['disabled'] ?? '-') ?>
                                <br>

                                Service:
                                <?= htmlspecialchars($row['service'] ?? '-') ?>
                                <br>

                                IP:
                                <?= htmlspecialchars($row['ip_address'] ?? '-') ?>
                                <br>

                                MAC / Caller ID:
                                <?= htmlspecialchars($row['mac_or_caller'] ?? '-') ?>
                                <br>

                                Uptime:
                                <?= htmlspecialchars($row['uptime'] ?? '-') ?>
                                <br>

                                Comment:
                                <?= htmlspecialchars(($row['comment'] ?? '') !== '' ? $row['comment'] : '-') ?>
                                <br>

                                ملاحظات CRM:
                                <?= htmlspecialchars(($row['notes'] ?? '') !== '' ? $row['notes'] : '-') ?>

                                <a class="btn btn-primary" href="/admin/customers/profile?username=<?= urlencode($row['username']) ?>">
                                    ملف المشترك الكامل
                                </a>

                                <?php if (!empty($row['crm_found'])): ?>

                                    <a class="btn btn-outline" href="/admin/customers/edit?username=<?= urlencode($row['username']) ?>">
                                        تعديل بيانات CRM
                                    </a>

                                <?php else: ?>

                                    <div class="notice" style="background:#fff7ed;color:#92400e; margin-top:12px;">
                                        هذا المستخدم موجود في MikroTik أو User Manager لكنه غير مضاف إلى CRM المحلي.
                                        يمكنك إضافته مباشرة من هنا.
                                    </div>

                                    <form method="post" action="/admin/customers/import-mikrotik">

                                        <input type="hidden" name="username" value="<?= htmlspecialchars($row['username'] ?? '') ?>">
                                        <input type="hidden" name="source" value="<?= htmlspecialchars($row['mikrotik_source'] ?? 'MikroTik') ?>">
                                        <input type="hidden" name="profile" value="<?= htmlspecialchars($row['profile'] ?? '') ?>">
                                        <input type="hidden" name="comment" value="<?= htmlspecialchars($row['comment'] ?? '') ?>">

                                        <div class="form-group">
                                            <label>اسم الزبون</label>
                                            <input
                                                type="text"
                                                name="display_name"
                                                placeholder="اختياري"
                                                value="<?= htmlspecialchars($row['display_name'] ?? '') ?>"
                                            >
                                        </div>

                                        <div class="form-group">
                                            <label>رقم الهاتف</label>
                                            <input
                                                type="text"
                                                name="phone"
                                                placeholder="09xxxxxxxx"
                                                value="<?= htmlspecialchars($row['phone'] ?? '') ?>"
                                            >
                                        </div>

                                        <div class="form-group">
                                            <label>نوع الوصول</label>
                                            <select name="access_type" class="input-select">
                                                <option value="hotspot" <?= $selectedAccessType === 'hotspot' ? 'selected' : '' ?>>Hotspot</option>
                                                <option value="ppp" <?= $selectedAccessType === 'ppp' ? 'selected' : '' ?>>PPP / PPPoE</option>
                                                <option value="hybrid" <?= $selectedAccessType === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label>حالة الدفع</label>
                                            <select name="payment_status" class="input-select">
                                                <option value="paid">مدفوع</option>
                                                <option value="due">عليه دفع</option>
                                                <option value="pending">مؤجل</option>
                                                <option value="unknown" selected>غير معروف</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label>ملاحظات</label>
                                            <textarea name="notes" rows="3"><?= htmlspecialchars($defaultNotes) ?></textarea>
                                        </div>

                                        <button class="btn btn-primary" type="submit">
                                            إضافة إلى CRM
                                        </button>

                                    </form>

                                <?php endif; ?>

                                <a class="btn btn-outline" href="/dashboard?username=<?= urlencode($row['username']) ?>">
                                    عرض لوحة المشترك
                                </a>

                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

        <a class="btn btn-outline" href="/admin/routeros/discovery">
            🔍 RouterOS Data Discovery
        </a>

        <a class="btn btn-outline" href="/admin/routeros/active-users">
            👥 المتصلون الآن
        </a>

        <a class="btn btn-outline" href="/admin/customers">
            إدارة الزبائن
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Smart Search
        </div>

    </div>
</div>