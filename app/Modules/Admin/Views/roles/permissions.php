<?php
/**
 * Admin role permissions assignment.
 * Variables: $view, $role, $grouped (by module), $assignedIds (array_flip'd)
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushScript('js/admin.js');
$view->start('content');
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-lock',
        'adminTitle'    => t('admin.roles.perms_page_title'),
        'adminSubtitle' => e($role['name']) . ' <code>' . e($role['slug']) . '</code>',
        'adminButtons'  => '<a href="' . e(route('admin.roles.index')) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> ' . e(t('admin.roles.back')) . '</a>',
    ]); ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($grouped)): ?>
        <div class="text-center text-muted py-4">
            <i class="fa-solid fa-lock-open fa-2x mb-2 d-block"></i>
            <?= e(t('admin.roles.no_perms')) ?>
        </div>
        <?php else: ?>
        <form id="perm-form"
              hx-post="<?= e(route('admin.roles.permissions.update', ['id' => $role['id']])) ?>"
              hx-swap="none">
            <?= csrf_field() ?>

            <!-- Select all / deselect all controls -->
            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-adm-perm-bulk="all"
                        data-adm-perm-form="perm-form">
                    <?= e(t('admin.roles.perms_select_all')) ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-adm-perm-bulk="none"
                        data-adm-perm-form="perm-form">
                    <?= e(t('admin.roles.perms_deselect_all')) ?>
                </button>
                <span id="perm-saved" class="d-none ms-2 text-success align-self-center">
                    <i class="fa-solid fa-check me-1"></i> <?= e(t('admin.roles.perms_saved')) ?>
                </span>
            </div>

            <?php foreach ($grouped as $module => $perms): ?>
            <div class="mb-4">
                <h6 class="border-bottom pb-2 text-muted text-uppercase small">
                    <i class="fa-solid fa-cube me-1"></i><?= e($module) ?>
                </h6>
                <div class="row g-2">
                    <?php foreach ($perms as $perm): ?>
                    <div class="col-sm-6 col-lg-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="permission_ids[]"
                                   value="<?= e($perm['id']) ?>"
                                   id="perm_<?= e($perm['id']) ?>"
                                   <?= isset($assignedIds[$perm['id']]) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="perm_<?= e($perm['id']) ?>">
                                <?= e(t_line('permissions', $perm['slug'] ?? '', $perm['name'] ?? '')) ?>
                                <code class="d-block small text-muted"><?= e($perm['slug']) ?></code>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i> <?= e(t('admin.roles.save_perms')) ?>
                </button>
                <a href="<?= e(route('admin.roles.index')) ?>" class="btn btn-outline-secondary"><?= e(t('admin.roles.cancel')) ?></a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>

<?php $view->end(); ?>
