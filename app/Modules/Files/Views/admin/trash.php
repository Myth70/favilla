<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushStyle('css/files.css'); ?>
<?php $view->pushScript('js/bulk-select.js'); ?>
<?php $view->pushScript('js/files.js'); ?>

<?php $view->start('content'); ?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'    => 'fa-solid fa-trash-can',
        'adminTitle'   => t('files.admin.trash_title'),
        'adminButtons' => '<a href="' . e(route('files.admin.index')) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>' . e(t('files.admin.manage_btn')) . '</a>',
    ]); ?>

<div class="d-flex align-items-center gap-2 mb-3">
  <input type="search"
         name="search"
      class="form-control form-control-sm fm-filter-search-trash"
         placeholder="<?= e(t('files.admin.trash_search_ph')) ?>"
         value="<?= e($filters['search']) ?>"
         hx-get="<?= route('files.admin.trash') ?>"
         hx-trigger="keyup changed delay:400ms, search"
         hx-target="#fm-trash-table"
         hx-push-url="true"
         hx-include="[name='search']">
  <span class="text-muted ms-auto small"><?= e(t('files.admin.trash_count', ['count' => $total])) ?></span>
</div>

<div id="fm-trash-table">
  <?php $view->include('Files/Views/admin/partials/trash_table', get_defined_vars()); ?>
</div>
</div>

<?php $view->end(); ?>
