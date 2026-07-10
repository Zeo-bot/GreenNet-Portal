<link rel="stylesheet" href="/css/subscriber-app.css">

<?php
    $announcements = is_array($announcements ?? null) ? $announcements : [];
    $username = (string) ($username ?? '-');
?>

<div class="subscriber-app">

    <section class="subscriber-hero">
        <div class="subscriber-hero-top">
            <div>
                <div class="subscriber-hello">إعلانات الشبكة</div>
                <div class="subscriber-username">GreenNet</div>
            </div>

            <div class="subscriber-status-pill">
                <?= htmlspecialchars($username) ?>
            </div>
        </div>

        <div class="subscriber-hero-grid">
            <div class="subscriber-hero-mini">
                <span>عدد الإعلانات</span>
                <strong><?= htmlspecialchars((string) count($announcements)) ?></strong>
            </div>

            <div class="subscriber-hero-mini">
                <span>القسم</span>
                <strong>تنبيهات</strong>
            </div>
        </div>
    </section>

    <section class="subscriber-card">
        <h2 class="subscriber-card-title">آخر الإعلانات</h2>

        <?php if (count($announcements) === 0): ?>
            <div class="subscriber-empty">
                لا توجد إعلانات حالياً.
            </div>
        <?php else: ?>
            <div class="subscriber-list">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="subscriber-card" style="box-shadow:none;margin-bottom:0;">
                        <h3 class="subscriber-card-title" style="font-size:15px;">
                            <?= htmlspecialchars((string) (($announcement['title'] ?? '') !== '' ? $announcement['title'] : 'إعلان')) ?>
                        </h3>

                        <div class="subscriber-muted">
                            <?= nl2br(htmlspecialchars((string) (($announcement['message'] ?? '') !== '' ? $announcement['message'] : ($announcement['content'] ?? '')))) ?>
                        </div>

                        <div class="subscriber-muted" style="margin-top:10px;">
                            <?= htmlspecialchars((string) (($announcement['created_at'] ?? '') !== '' ? $announcement['created_at'] : '')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php require BASE_PATH . '/app/Views/subscriber/_bottom_nav.php'; ?>