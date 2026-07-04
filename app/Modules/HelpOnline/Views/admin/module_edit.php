<div class="card shadow-sm mb-3">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e(t('helponline.admin.edit_module')) ?></span>
        <a href="<?= e(route('helponline.admin.modules')) ?>" class="btn btn-sm btn-outline-secondary"><?= e(t('helponline.admin.back_modules')) ?></a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= e(route('helponline.admin.modules.update', ['id' => (int) ($id ?? 0)])) ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-md-4">
                <label class="form-label"><?= e(t('helponline.admin.module_key')) ?></label>
                <input type="text" name="module_key" class="form-control" value="<?= e((string) ($module_key ?? '')) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label"><?= e(t('helponline.admin.module_name')) ?></label>
                <input type="text" name="module_name" class="form-control" value="<?= e((string) ($module_name ?? '')) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label"><?= e(t('helponline.admin.label')) ?></label>
                <input type="text" name="label" class="form-control" value="<?= e((string) ($label ?? '')) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label"><?= e(t('helponline.admin.description')) ?></label>
                <textarea name="description" class="form-control" rows="2"><?= e((string) ($description ?? '')) ?></textarea>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label"><?= e(t('helponline.admin.audience')) ?></label>
                <select name="audience_default" class="form-select">
                    <option value="user" <?= (($audience_default ?? 'user') === 'user') ? 'selected' : '' ?>><?= e(t('helponline.admin.audience_user')) ?></option>
                    <option value="admin" <?= (($audience_default ?? '') === 'admin') ? 'selected' : '' ?>><?= e(t('helponline.admin.audience_admin')) ?></option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label"><?= e(t('helponline.admin.locale')) ?></label>
                <input type="text" name="locale_default" class="form-control" value="<?= e((string) ($locale_default ?? 'it')) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label"><?= e(t('helponline.admin.route')) ?></label>
                <input type="text" name="route_name" class="form-control" value="<?= e((string) ($route_name ?? '')) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label"><?= e(t('helponline.admin.permission')) ?></label>
                <input type="text" name="permission_slug" class="form-control" value="<?= e((string) ($permission_slug ?? '')) ?>">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label"><?= e(t('helponline.admin.order')) ?></label>
                <input type="number" name="sort_order" class="form-control" value="<?= (int) ($sort_order ?? 0) ?>">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label"><?= e(t('common.label.status')) ?></label>
                <select name="is_active" class="form-select">
                    <option value="1" <?= ((int) ($is_active ?? 1) === 1) ? 'selected' : '' ?>><?= e(t('helponline.admin.on')) ?></option>
                    <option value="0" <?= ((int) ($is_active ?? 1) === 0) ? 'selected' : '' ?>><?= e(t('helponline.admin.off')) ?></option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= e(t('helponline.admin.save_module')) ?></button>
                <a href="<?= e(route('helponline.admin.modules')) ?>" class="btn btn-outline-secondary"><?= e(t('common.action.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
