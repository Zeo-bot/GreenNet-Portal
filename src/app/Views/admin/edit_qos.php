<div class="page">
    <div class="app-card">

        <div class="brand">
            <div class="brand-mark">⚙️</div>
            <h1><?= htmlspecialchars($app_name ?? 'GreenNet') ?></h1>
            <p>تعديل QoS Profile</p>

            <div class="status-pill">
                <span class="dot"></span>
                <?= htmlspecialchars($profile['name'] ?? '-') ?>
            </div>
        </div>

        <div class="notice">
            هذه الإعدادات لا تطبق على MikroTik حالياً.
            سنستخدمها لاحقاً عندما نربط RouterOS API.
        </div>

        <form method="post" action="/admin/qos/update">

            <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($profile['id'] ?? 0)) ?>">

            <div class="form-group">
                <label>اسم البروفايل</label>
                <input
                    type="text"
                    name="name"
                    value="<?= htmlspecialchars($profile['name'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>الوضع</label>
                <select name="mode" class="input-select">
                    <option value="smart" <?= ($profile['mode'] ?? '') === 'smart' ? 'selected' : '' ?>>Smart</option>
                    <option value="normal" <?= ($profile['mode'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="calls" <?= ($profile['mode'] ?? '') === 'calls' ? 'selected' : '' ?>>Calls Priority</option>
                    <option value="stability" <?= ($profile['mode'] ?? '') === 'stability' ? 'selected' : '' ?>>Stability</option>
                    <option value="limited" <?= ($profile['mode'] ?? '') === 'limited' ? 'selected' : '' ?>>Limited</option>
                    <option value="vip" <?= ($profile['mode'] ?? '') === 'vip' ? 'selected' : '' ?>>VIP</option>
                </select>
            </div>

            <div class="form-group">
                <label>الأولوية من 1 إلى 8</label>
                <input
                    type="number"
                    name="priority"
                    min="1"
                    max="8"
                    value="<?= htmlspecialchars((string) ($profile['priority'] ?? 5)) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>الوصف</label>
                <textarea name="description" class="input-textarea"><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input
                        type="checkbox"
                        name="is_active"
                        style="width:auto;"
                        <?= ((int) ($profile['is_active'] ?? 0) === 1) ? 'checked' : '' ?>
                    >
                    البروفايل فعال
                </label>
            </div>

            <button class="btn btn-primary" type="submit">
                حفظ التعديلات
            </button>

        </form>

        <a class="btn btn-outline" href="/admin/qos">
            العودة إلى Smart QoS
        </a>

        <a class="btn btn-outline" href="/admin">
            العودة للوحة المدير
        </a>

        <div class="footer">
            GreenNet QoS Profile Edit
        </div>

    </div>
</div>