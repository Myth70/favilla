<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead>
        <tr>
            <th><?= e(t('helponline.admin.col_module')) ?></th>
            <th><?= e(t('helponline.admin.col_key')) ?></th>
            <th><?= e(t('helponline.admin.col_audience_locale')) ?></th>
            <th class="text-end"><?= e(t('helponline.admin.col_qa')) ?></th>
            <th class="text-end"><?= e(t('helponline.admin.col_alias')) ?></th>
            <th><?= e(t('common.label.status')) ?></th>
            <th class="text-end"><?= e(t('common.label.actions')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($modules)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4"><?= e(t('helponline.admin.no_modules')) ?></td></tr>
        <?php else: ?>
            <?php foreach ($modules as $module): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e((string) ($module['label'] ?? $module['module_name'] ?? '')) ?></div>
                        <div class="small text-muted"><?= e((string) ($module['module_name'] ?? '')) ?></div>
                    </td>
                    <td><code><?= e((string) ($module['module_key'] ?? '')) ?></code></td>
                    <td><?= e((string) ($module['audience_default'] ?? 'user')) ?> / <?= e((string) ($module['locale_default'] ?? 'it')) ?></td>
                    <td class="text-end"><?= (int) ($module['entries'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($module['aliases'] ?? 0) ?></td>
                    <td>
                        <?php if ((int) ($module['is_active'] ?? 0) === 1): ?>
                            <span class="badge text-bg-success"><?= e(t('helponline.admin.active')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= e(t('helponline.admin.inactive')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="<?= e(route('helponline.admin.modules.edit', ['id' => (int) ($module['id'] ?? 0)])) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-pen-to-square me-1"></i><?= e(t('common.action.edit')) ?>
                            </a>
                            <form method="POST" action="<?= e(route('helponline.admin.modules.delete', ['id' => (int) ($module['id'] ?? 0)])) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-app-confirm="<?= e(t('helponline.admin.delete_module_confirm')) ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
