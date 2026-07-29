<?php
require BASE_PATH . '/app/Views/subscriber/_helpers.php';
$notifications = is_array($notifications ?? null) ? $notifications : [];
$unreadCount = count(array_filter($notifications, static fn (array $item): bool => empty($item['read'])));
?>

<div class="subscriber-app">
    <section class="subscriber-hero subscriber-hero-compact">
        <div class="subscriber-hero-top">
            <div><div class="subscriber-hello">مركز الإشعارات</div><h1 class="subscriber-username">آخر التنبيهات</h1></div>
            <span class="subscriber-status-pill"><?= $unreadCount > 0 ? $unreadCount . ' جديد' : count($notifications) . ' إشعار' ?></span>
        </div>
    </section>

    <section class="subscriber-card">
        <?php if ($notifications === []): ?>
            <div class="subscriber-empty">
                <strong>لا توجد إشعارات حالياً</strong>
                <span>ستظهر هنا تنبيهات الاشتراك والصيانة عند توفرها.</span>
            </div>
        <?php else: ?>
            <div class="subscriber-notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <article class="subscriber-notification <?= empty($notification['read']) ? 'is-unread' : '' ?>">
                        <span class="subscriber-notification-mark"></span>
                        <div>
                            <div class="subscriber-card-heading">
                                <h2><?= gn_subscriber_h($notification['title'] ?? 'إشعار') ?></h2>
                                <?php if (empty($notification['read'])): ?><span class="subscriber-badge success">جديد</span><?php endif; ?>
                            </div>
                            <p><?= nl2br(gn_subscriber_h($notification['body'] ?? '')) ?></p>
                            <time><?= gn_subscriber_date($notification['created_at'] ?? null) ?></time>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>
