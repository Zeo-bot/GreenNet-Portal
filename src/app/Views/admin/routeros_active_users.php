<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">👥</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>المتصلون الآن من MikroTik</p>

            <div class="status-pill">
                <span class="dot"></span>
                MikroTik + CRM
            </div>
        </div>

        <div class="notice">
            هذه الصفحة تدمج المستخدمين المتصلين حالياً من MikroTik مع بيانات CRM المحلية داخل GreenNet.
        </div>

        <div class="grid">

            <div class="stat">
                <div class="label">الإجمالي</div>
                <div class="value"><?= htmlspecialchars((string) ($total_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">Hotspot</div>
                <div class="value"><?= htmlspecialchars((string) ($hotspot_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">PPP</div>
                <div class="value"><?= htmlspecialchars((string) ($ppp_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">موجود في CRM</div>
                <div class="value"><?= htmlspecialchars((string) ($crm_found_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">غير موجود في CRM</div>
                <div class="value"><?= htmlspecialchars((string) ($crm_missing_count ?? 0)) ?></div>
            </div>

            <div class="stat">
                <div class="label">عليه دفع</div>
                <div class="value"><?= htmlspecialchars((string) ($due_count ?? 0)) ?></div>
            </div>

        </div>

        <div class="stat" style="margin-top:18px;">
            <div class="label">كل المتصلين الآن</div>

            <div style="font-size:13px; line-height:1.9; margin-top:10px;">
                <?php if (count($all_users) === 0): ?>
                    لا يوجد مستخدمون متصلون حالياً.
                <?php else: ?>
                    <?php foreach ($all_users as $user): ?>
                        <?php
                            $crmFound = (bool) ($user['crm_found'] ?? false);
                            $paymentStatus = (string) ($user['payment_status'] ?? 'not_registered');

                            $boxStyle = 'border-bottom:1px solid #e5e7eb; padding:12px 0;';

                            if ($paymentStatus === 'due') {
                                $boxStyle .= ' background:#fff7ed; border-radius:14px; padding:12px; margin-bottom:10px;';
                            }

                            if (!$crmFound) {
                                $boxStyle .= ' background:#fef2f2; border-radius:14px; padding:12px; margin-bottom:10px;';
                            }
                        ?>

                        <div style="<?= htmlspecialchars($boxStyle) ?>">

                            <strong><?= htmlspecialchars($user['username'] ?: '-') ?></strong>
                            —
                            <?= htmlspecialchars($user['type'] ?? '-') ?>
                            <br>

                            <strong>بيانات MikroTik</strong>
                            <br>

                            IP:
                            <?= htmlspecialchars($user['ip_address'] ?? '-') ?>
                            <br>

                            MAC / Caller ID:
                            <?= htmlspecialchars($user['mac_or_caller'] ?? '-') ?>
                            <br>

                            Service:
                            <?= htmlspecialchars($user['service'] ?? '-') ?>
                            <br>

                            Uptime:
                            <?= htmlspecialchars($user['uptime'] ?? '-') ?>
                            <br>

                            Bytes In:
                            <?= htmlspecialchars($user['bytes_in'] ?? '-') ?>
                            <br>

                            Bytes Out:
                            <?= htmlspecialchars($user['bytes_out'] ?? '-') ?>
                            <br><br>

                            <strong>بيانات GreenNet CRM</strong>
                            <br>

                            الحالة:
                            <?= $crmFound ? 'موجود في CRM' : 'غير موجود في CRM' ?>
                            <br>

                            اسم الزبون:
                            <?= htmlspecialchars(($user['display_name'] ?? '') !== '' ? $user['display_name'] : '-') ?>
                            <br>

                            الهاتف:
                            <?= htmlspecialchars(($user['phone'] ?? '') !== '' ? $user['phone'] : '-') ?>
                            <br>

                            حالة الدفع:
                            <?= htmlspecialchars($user['payment_label'] ?? '-') ?>
                            <br>

                            ملاحظات:
                            <?= htmlspecialchars(($user['notes'] ?? '') !== '' ? $user['notes'] : '-') ?>

                            <a class="btn btn-primary" href="/admin/customers/profile?username=<?= urlencode($user['username']) ?>">
                                ملف المشترك الكامل
                            </a>

                            <?php if ($crmFound): ?>
                                <a class="btn btn-outline" href="/admin/customers/edit?username=<?= urlencode($user['username']) ?>">
                                    تعديل بيانات CRM
                                </a>
                            <?php else: ?>
                                <div class="notice" style="background:#fee2e2;color:#991b1b; margin-top:12px; margin-bottom:0;">
                                    هذا المستخدم متصل على MikroTik لكنه غير مضاف بعد إلى CRM المحلي.
                                </div>
                            <?php endif; ?>

                            <a class="btn btn-outline" href="/dashboard?username=<?= urlencode($user['username']) ?>">
                                عرض لوحة المشترك
                            </a>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <a class="btn btn-primary" href="/admin/routeros/active-users">
            تحديث القائمة
        </a>

        <a class="btn btn-outline" href="/admin/customers">
            👥 إدارة الزبائن
        </a>

        <a class="btn btn-outline" href="/admin/routeros">
            العودة لاختبار MikroTik API
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet Active Users + CRM
        </div>

    </div>
</div>