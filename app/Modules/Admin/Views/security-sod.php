<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>
<?php $view->start('content'); ?>

<?php
$heroButtons = '';
if (has_permission('admin.security.manage')) {
    $heroButtons = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newConstraintModal">'
                 . '<i class="fa-solid fa-plus me-1"></i> ' . e(t('admin.sod.new_constraint_btn')) . '</button>';
}

$view->include('partials/pf-hero-admin', [
    'adminTitle'    => t('admin.sod.page_title'),
    'adminIcon'     => 'fa-solid fa-users-between-lines',
    'adminSubtitle' => t('admin.sod.subtitle'),
    'adminButtons'  => $heroButtons,
]);
?>
<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="adm-stat-box text-center h-100">
                <div class="adm-stat-value adm-text-accent"><?= e($stats['active'] ?? 0) ?></div>
                <div class="adm-stat-label"><?= e(t('admin.sod.active_count')) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="adm-stat-box text-center h-100">
                <div class="adm-stat-value text-muted"><?= e($stats['total'] ?? 0) ?></div>
                <div class="adm-stat-label"><?= e(t('admin.sod.total_count')) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="adm-stat-box text-center h-100">
                <div class="adm-stat-value <?= ($stats['violations'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>"><?= e($stats['violations'] ?? 0) ?></div>
                <div class="adm-stat-label"><?= e(t('admin.sod.current_violations')) ?></div>
            </div>
        </div>
    </div>

    <!-- Violations alert -->
    <?php if (!empty($violations)): ?>
    <div class="card adm-card border-danger mb-4">
        <div class="card-header bg-danger bg-opacity-10 d-flex align-items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-danger"></i>
            <span class="fw-semibold text-danger"><?= e(t('admin.sod.violations_detected')) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table adm-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= e(t('admin.sod.col_user')) ?></th>
                            <th><?= e(t('admin.sod.col_role1')) ?></th>
                            <th><?= e(t('admin.sod.col_role2')) ?></th>
                            <th><?= e(t('admin.sod.col_reason_constraint')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($violations as $v): ?>
                        <tr>
                            <td>
                                <i class="fa-solid fa-user-xmark text-danger me-1"></i>
                                <?= e($v['user_name']) ?>
                                <small class="text-muted d-block"><?= e($v['email']) ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= e($v['role1_name']) ?></span></td>
                            <td><span class="badge bg-secondary"><?= e($v['role2_name']) ?></span></td>
                            <td class="text-muted"><?= e($v['reason']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Constraints table -->
    <div class="card adm-card mb-4">
        <div class="card-header adm-card-header d-flex align-items-center gap-2">
            <i class="fa-solid fa-lock text-muted"></i>
            <span class="fw-semibold"><?= e(t('admin.sod.constraints_title')) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($constraints)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-users-between-lines fa-2x mb-2 opacity-50"></i>
                    <p><?= e(t('admin.sod.empty')) ?><br>
                    <small><?= e(t('admin.sod.empty_hint')) ?></small></p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table adm-table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= e(t('admin.sod.col_role1')) ?></th>
                            <th><?= e(t('admin.sod.col_role2')) ?></th>
                            <th><?= e(t('admin.sod.col_reason')) ?></th>
                            <th><?= e(t('admin.sod.col_status')) ?></th>
                            <?php if (has_permission('admin.security.manage')): ?>
                            <th class="text-end"><?= e(t('admin.sod.col_actions')) ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($constraints as $c): ?>
                        <tr>
                            <td><span class="badge bg-primary bg-opacity-75"><?= e($c['role1_name']) ?></span></td>
                            <td><span class="badge bg-primary bg-opacity-75"><?= e($c['role2_name']) ?></span></td>
                            <td class="text-muted"><?= e($c['reason']) ?></td>
                            <td>
                                <?php if ($c['enabled']): ?>
                                    <span class="badge bg-success"><?= e(t('admin.sod.badge_active')) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= e(t('admin.sod.badge_disabled')) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if (has_permission('admin.security.manage')): ?>
                            <td class="text-end">
                                <form method="POST" action="<?= e(route('admin.security.sod.toggle', ['id' => $c['id']])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e($c['enabled'] ? t('admin.sod.toggle_disable') : t('admin.sod.toggle_enable')) ?>">
                                        <i class="fa-solid <?= $c['enabled'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" action="<?= e(route('admin.security.sod.delete', ['id' => $c['id']])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            data-app-confirm="<?= e(t('admin.sod.delete_confirm')) ?>"
                                            data-app-confirm-label="<?= e(t('admin.sod.delete_label')) ?>"
                                            data-app-confirm-class="btn-danger"
                                            data-bs-toggle="tooltip" title="<?= e(t('admin.sod.delete_tip')) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info card -->
    <div class="card adm-card">
        <div class="card-header adm-card-header d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-info text-muted"></i>
            <span class="fw-semibold"><?= e(t('admin.sod.info_title')) ?></span>
        </div>
        <div class="card-body">
            <p class="text-muted mb-2"><?= e(t('admin.sod.info_intro')) ?></p>
            <p class="text-muted mb-2"><?= t('admin.sod.info_examples') ?></p>
            <ul class="text-muted mb-0">
                <li><?= t('admin.sod.info_ex1') ?></li>
                <li><?= e(t('admin.sod.info_ex2')) ?></li>
                <li><?= e(t('admin.sod.info_ex3')) ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Modal: Nuovo vincolo -->
<?php if (has_permission('admin.security.manage')): ?>
<div class="modal fade" id="newConstraintModal" tabindex="-1" aria-labelledby="newConstraintLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= e(route('admin.security.sod.store')) ?>" id="adm-sod-form">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newConstraintLabel">
                        <i class="fa-solid fa-plus"></i><?= e(t('admin.sod.modal_title')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('admin.sod.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="role_id_1" class="form-label"><?= e(t('admin.sod.first_role')) ?></label>
                        <select name="role_id_1" id="role_id_1" class="form-select" data-sod-role="a" required>
                            <option value=""><?= e(t('admin.sod.select_placeholder')) ?></option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= e($role['id']) ?>"><?= e($role['name']) ?> (<?= e($role['slug']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="role_id_2" class="form-label"><?= e(t('admin.sod.second_role')) ?></label>
                        <select name="role_id_2" id="role_id_2" class="form-select" data-sod-role="b" required>
                            <option value=""><?= e(t('admin.sod.select_placeholder')) ?></option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= e($role['id']) ?>"><?= e($role['name']) ?> (<?= e($role['slug']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div id="adm-sod-role-warning" class="form-text text-danger d-none">
                            <?= e(t('admin.sod.roles_must_differ')) ?>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label for="reason" class="form-label"><?= e(t('admin.sod.reason_label')) ?></label>
                        <textarea name="reason" id="reason" class="form-control" rows="3" required placeholder="<?= e(t('admin.sod.reason_ph')) ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('admin.sod.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary" id="adm-sod-submit"><i class="fa-solid fa-check me-1"></i><?= e(t('admin.sod.create_btn')) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php $view->end(); ?>
