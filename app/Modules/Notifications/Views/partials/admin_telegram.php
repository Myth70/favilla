<div class="card shadow-sm" id="telegram-bot">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div><i class="fa-brands fa-telegram me-2"></i><?= e(t('notifications.admin.bot_manage')) ?></div>
        <?php if (!empty($defaultBot['bot_username'])): ?>
            <span class="badge bg-success-subtle text-success-emphasis border"><?= e(t('notifications.admin.bot_default_prefix')) ?> @<?= e($defaultBot['bot_username']) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!empty($errors['name']) || !empty($errors['bot_token'])): ?>
        <div class="alert alert-danger py-2">
            <?= e($errors['name'] ?? $errors['bot_token'] ?? t('notifications.admin.bot_check_data')) ?>
        </div>
        <?php endif; ?>

        <div class="ntas-bot-grid">
            <?php foreach (($bots ?? []) as $bot): ?>
            <form method="POST" action="<?= e(route('admin.notifications.bot.save')) ?>" class="ntas-bot-card">
                <?= csrf_field() ?>
                <input type="hidden" name="bot_id" value="<?= (int) $bot['id'] ?>">

                <div class="ntas-bot-head">
                    <div>
                        <div class="ntas-event-title"><i class="fa-brands fa-telegram me-2"></i><?= e($bot['name']) ?></div>
                        <div class="ntas-module-desc"><?= !empty($bot['bot_username']) ? '@' . e($bot['bot_username']) : e(t('notifications.admin.bot_username_unset')) ?></div>
                    </div>
                    <div class="ntas-event-meta">
                        <?php if (!empty($bot['is_default'])): ?>
                        <span class="badge bg-success-subtle text-success-emphasis border"><?= e(t('notifications.admin.bot_default')) ?></span>
                        <?php endif; ?>
                        <span class="badge text-bg-light border"><?= !empty($bot['is_enabled']) ? e(t('notifications.admin.bot_enabled')) : e(t('notifications.admin.bot_disabled')) ?></span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('notifications.admin.bot_name')) ?></label>
                        <input type="text" class="form-control" name="name" value="<?= e($bot['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('notifications.admin.bot_username')) ?></label>
                        <input type="text" class="form-control" name="bot_username" value="<?= e($bot['bot_username'] ?? '') ?>" placeholder="favilla_bot">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= e(t('notifications.admin.bot_token')) ?></label>
                        <input type="password" class="form-control" name="bot_token" value="" placeholder="<?= e(t('notifications.admin.bot_token_keep')) ?>">
                    </div>
                </div>

                <div class="ntas-bot-switches">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="bot-enabled-<?= (int) $bot['id'] ?>" <?= !empty($bot['is_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="bot-enabled-<?= (int) $bot['id'] ?>"><?= e(t('notifications.admin.bot_enabled_label')) ?></label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="bot-default-<?= (int) $bot['id'] ?>" <?= !empty($bot['is_default']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="bot-default-<?= (int) $bot['id'] ?>"><?= e(t('notifications.admin.bot_default_label')) ?></label>
                    </div>
                </div>

                <div class="ntas-bot-meta text-muted">
                    <div><?= e(t('notifications.admin.bot_webhook')) ?> <code><?= e($bot['webhook_url'] ?? t('notifications.admin.bot_webhook_unset')) ?></code></div>
                    <div><?= e(t('notifications.admin.bot_deeplink')) ?> <code><?= e($bot['deep_link_base'] ?? t('notifications.admin.bot_deeplink_unset')) ?></code></div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('notifications.admin.bot_save')) ?></button>
                </div>
            </form>
            <?php endforeach; ?>

            <form method="POST" action="<?= e(route('admin.notifications.bot.save')) ?>" class="ntas-bot-card ntas-bot-card-create">
                <?= csrf_field() ?>
                <div class="ntas-bot-head">
                    <div>
                        <div class="ntas-event-title"><i class="fa-solid fa-plus me-2"></i><?= e(t('notifications.admin.bot_new')) ?></div>
                        <div class="ntas-module-desc"><?= e(t('notifications.admin.bot_new_hint')) ?></div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('notifications.admin.bot_name')) ?></label>
                        <input type="text" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= e(t('notifications.admin.bot_new_name')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(t('notifications.admin.bot_username')) ?></label>
                        <input type="text" class="form-control" name="bot_username" value="" placeholder="favilla_bot">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= e(t('notifications.admin.bot_token')) ?></label>
                        <input type="password" class="form-control <?= !empty($errors['bot_token']) ? 'is-invalid' : '' ?>" name="bot_token" value="" placeholder="<?= e(t('notifications.admin.bot_token_father')) ?>">
                    </div>
                </div>

                <div class="ntas-bot-switches">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="bot-enabled-new" checked>
                        <label class="form-check-label" for="bot-enabled-new"><?= e(t('notifications.admin.bot_enabled_label')) ?></label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="bot-default-new" <?= empty($bots) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="bot-default-new"><?= e(t('notifications.admin.bot_set_default')) ?></label>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-plus me-1"></i><?= e(t('notifications.admin.bot_create')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
