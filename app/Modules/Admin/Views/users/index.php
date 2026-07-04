<?php
/**
 * Admin users — view utenti.
 * Variables: $view, $items, $total, $page, $per_page, $total_pages, $roles, $filters
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushScript('js/admin.js');
$view->start('content');
?>

<?php
$_heroButtons = '';
if (has_permission('admin.users.create')) {
    $_heroButtons = '<a href="' . e(route('admin.users.create')) . '" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> ' . e(t('admin.users.new_user')) . '</a>';
}
$view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-users',
    'adminTitle'    => t('admin.users.page_title'),
    'adminSubtitle' => t('admin.users.subtitle', ['count' => number_format($total)]),
    'adminButtons'  => $_heroButtons,
]);
?>

<?php $view->include('Admin/Views/partials/admin-subnav'); ?>

<div class="container-fluid app-page-wide">

<div class="card mb-4">
    <div class="card-body p-2">
        <form id="filter-form"
              hx-get="<?= e(route('admin.users.index')) ?>"
              hx-target="#users-table"
              hx-push-url="true"
              hx-trigger="change from:select, submit"
              class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1"><?= e(t('admin.users.search')) ?></label>
                <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       class="form-control" placeholder="<?= e(t('admin.users.search_ph')) ?>"
                       hx-trigger="keyup changed delay:400ms, search"
                       hx-get="<?= e(route('admin.users.index')) ?>"
                       hx-target="#users-table"
                       hx-push-url="true">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1"><?= e(t('admin.users.role')) ?></label>
                <select name="role_id" class="form-select">
                    <option value=""><?= e(t('admin.users.all_roles')) ?></option>
                    <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role['id']) ?>" <?= ($filters['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                        <?= e($role['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1"><?= e(t('admin.users.status')) ?></label>
                <select name="is_active" class="form-select">
                    <option value=""><?= e(t('admin.users.all')) ?></option>
                    <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>><?= e(t('admin.users.active_plural')) ?></option>
                    <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>><?= e(t('admin.users.inactive_plural')) ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="<?= e(route('admin.users.index')) ?>" class="btn btn-outline-secondary w-100">
                    <i class="fa-solid fa-xmark me-1"></i> <?= e(t('admin.users.reset')) ?>
                </a>
            </div>
        </form>
    </div>
    <div id="users-table">
        <?php $view->include('Admin/Views/users/partials/table', compact('items', 'total', 'page', 'per_page', 'total_pages', 'roles', 'filters')); ?>
    </div>
</div>

<!-- Bulk action toolbar (mostrata da JS quando ≥1 checkbox selezionata) -->
<?php if (has_permission('admin.users.edit')): ?>
<div id="adm-bulk-toolbar"
     class="adm-bulk-toolbar d-none"
     data-bulk-url="<?= e(route('admin.users.bulk')) ?>"
     aria-live="polite"
     aria-label="<?= e(t('admin.users.bulk_aria')) ?>">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <span id="adm-bulk-count" class="fw-semibold small text-body"></span>
        <?= csrf_field() ?>
        <button type="button" class="btn btn-sm btn-success" data-bulk-action="activate"
                data-bs-toggle="tooltip" title="<?= e(t('admin.users.bulk_activate_tip')) ?>">
            <i class="fa-solid fa-circle-check me-1"></i><?= e(t('admin.users.bulk_activate')) ?>
        </button>
        <button type="button" class="btn btn-sm btn-warning" data-bulk-action="deactivate"
                data-bs-toggle="tooltip" title="<?= e(t('admin.users.bulk_deactivate_tip')) ?>">
            <i class="fa-solid fa-ban me-1"></i><?= e(t('admin.users.bulk_deactivate')) ?>
        </button>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-user-tag me-1"></i><?= e(t('admin.users.bulk_assign_role')) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($roles as $role): ?>
                <li>
                    <button class="dropdown-item" type="button"
                            data-bulk-action="assign_role"
                            data-role-id="<?= (int) $role['id'] ?>">
                        <?= e($role['name']) ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <button type="button" class="btn btn-sm btn-link text-muted ms-auto" id="adm-bulk-cancel">
            <i class="fa-solid fa-xmark me-1"></i><?= e(t('admin.users.bulk_cancel')) ?>
        </button>
    </div>
</div>
<?php endif; ?>

</div>

<?php $view->end(); ?>
