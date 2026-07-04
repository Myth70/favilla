<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead>
        <tr>
            <th><?= e(t('helponline.admin.col_question')) ?></th>
            <th><?= e(t('helponline.admin.col_module')) ?></th>
            <th><?= e(t('helponline.admin.audience')) ?></th>
            <th class="text-end"><?= e(t('helponline.admin.col_weight')) ?></th>
            <th class="text-end"><?= e(t('helponline.admin.col_alias')) ?></th>
            <th><?= e(t('common.label.status')) ?></th>
            <th class="text-end"><?= e(t('common.label.actions')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($entries)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4"><?= e(t('helponline.admin.no_entries')) ?></td></tr>
        <?php else: ?>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e((string) ($entry['question'] ?? '')) ?></div>
                        <?php if (!empty($entry['excerpt'])): ?>
                            <div class="small text-muted"><?= e((string) $entry['excerpt']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) ($entry['module_name'] ?? '')) ?></td>
                    <td><?= e((string) ($entry['audience'] ?? 'user')) ?> / <?= e((string) ($entry['locale'] ?? 'it')) ?></td>
                    <td class="text-end"><?= (int) ($entry['ranking_weight'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($entry['aliases'] ?? 0) ?></td>
                    <td>
                        <?php if ((int) ($entry['is_active'] ?? 0) === 1): ?>
                            <span class="badge text-bg-success"><?= e(t('helponline.admin.active')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= e(t('helponline.admin.inactive')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="<?= e(route('helponline.admin.entries.edit', ['id' => (int) ($entry['id'] ?? 0)])) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-pen-to-square me-1"></i><?= e(t('common.action.edit')) ?>
                            </a>
                            <form method="POST" action="<?= e(route('helponline.admin.entries.delete', ['id' => (int) ($entry['id'] ?? 0)])) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-app-confirm="<?= e(t('helponline.admin.delete_entry_confirm')) ?>">
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
