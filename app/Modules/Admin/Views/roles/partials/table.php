<?php
/**
 * Roles table partial (used both standalone and as HTMX tab content).
 * Variables: $roles
 */

$canManage = has_permission('admin.roles.manage');
?>
<div class="d-flex justify-content-end mb-3">
    <a href="<?= e(route('admin.roles.create')) ?>" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-1"></i> <?= e(t('admin.roles.new_role')) ?>
    </a>
</div>

<?php if (empty($roles)): ?>
<div class="text-center text-muted py-5">
    <i class="fa-solid fa-user-tag fa-2x mb-2 d-block"></i>
    <?= e(t('admin.roles.empty')) ?>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover table-sm mb-0 align-middle adm-table">
        <thead>
            <tr>
                <th><?= e(t('admin.roles.col_name')) ?></th>
                <th><?= e(t('admin.roles.col_slug')) ?></th>
                <th><?= e(t('admin.roles.col_desc')) ?></th>
                <th class="text-center"><?= e(t('admin.roles.col_users')) ?></th>
                <th class="text-end"><?= e(t('admin.roles.col_actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $role): ?>
            <tr class="adm-clickable-row"
                <?php if ($canManage): ?>data-href="<?= e(route('admin.roles.edit', ['id' => $role['id']])) ?>"<?php endif; ?>>
                <td>
                    <a href="<?= e(route('admin.roles.edit', ['id' => $role['id']])) ?>" class="text-decoration-none text-body fw-semibold">
                        <?= e($role['name']) ?>
                    </a>
                </td>
                <td><code class="small text-muted"><?= e($role['slug']) ?></code></td>
                <td class="text-muted small"><?= e($role['description'] ?? '—') ?></td>
                <td class="text-center">
                    <span class="badge bg-secondary"><?= (int) $role['user_count'] ?></span>
                </td>
                <td class="text-end text-nowrap">
                    <a href="<?= e(route('admin.roles.edit', ['id' => $role['id']])) ?>"
                       class="btn btn-sm btn-icon adm-action-btn adm-action-primary" data-bs-toggle="tooltip" title="<?= e(t('admin.roles.manage_tip')) ?>" aria-label="<?= e(t('admin.roles.manage_tip')) ?>">
                        <i class="fa-solid fa-gear"></i>
                    </a>
                    <form method="post"
                          action="<?= e(route('admin.roles.clone', ['id' => $role['id']])) ?>"
                          class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-icon adm-action-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.roles.clone_tip')) ?>" aria-label="<?= e(t('admin.roles.clone_tip')) ?>"
                                data-app-confirm="<?= e(t('admin.roles.clone_confirm', ['name' => $role['name']])) ?>"
                                data-app-confirm-label="<?= e(t('admin.roles.clone_label')) ?>"
                                data-app-confirm-class="btn-primary">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </form>
                    <?php if ((int) $role['user_count'] === 0): ?>
                    <form method="post"
                          action="<?= e(route('admin.roles.destroy', ['id' => $role['id']])) ?>"
                          class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-sm btn-icon adm-action-btn text-danger" data-bs-toggle="tooltip" title="<?= e(t('admin.roles.delete_tip')) ?>"
                                data-app-confirm="<?= e(t('admin.roles.delete_confirm', ['name' => $role['name']])) ?>">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="d-inline-block ms-1"
                          data-bs-toggle="tooltip"
                          title="<?= e(t('admin.roles.cannot_delete_tip')) ?>">
                        <button class="btn btn-sm btn-icon adm-action-btn pe-none text-danger" disabled>
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
