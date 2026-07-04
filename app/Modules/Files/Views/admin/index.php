<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushStyle('css/files.css'); ?>
<?php $view->pushScript('js/bulk-select.js'); ?>
<?php $view->pushScript('js/files.js'); ?>

<?php $view->start('content'); ?>

<div class="container-fluid app-page-wide">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'  => 'fa-solid fa-folder-open',
        'adminTitle' => t('files.admin.title'),
    ]); ?>

<!-- Stats widget -->
<div id="fm-stats-widget">
  <?php $view->include('Files/Views/admin/partials/stats_widget', get_defined_vars()); ?>
</div>

<!-- Filter bar -->
<div class="card shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">

      <input type="search"
             name="search"
              class="form-control form-control-sm fm-filter-search"
             placeholder="<?= e(t('files.admin.search_ph')) ?>"
             value="<?= e($filters['search']) ?>"
             data-filter
             hx-get="<?= route('files.admin.index') ?>"
             hx-trigger="keyup changed delay:400ms, search"
             hx-target="#fm-admin-table"
             hx-include="[data-filter]">

      <select name="user_id"
              class="form-select form-select-sm fm-filter-auto"
              data-filter
              hx-get="<?= route('files.admin.index') ?>"
              hx-trigger="change"
              hx-target="#fm-admin-table"
              hx-include="[data-filter]">
        <option value=""><?= e(t('files.admin.all_users')) ?></option>
        <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= (string)($filters['user_id'] ?? '') === (string)$u['id'] ? 'selected' : '' ?>>
            <?= e($u['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="mime_group"
              class="form-select form-select-sm fm-filter-auto"
              data-filter
              hx-get="<?= route('files.admin.index') ?>"
              hx-trigger="change"
              hx-target="#fm-admin-table"
              hx-include="[data-filter]">
        <option value=""><?= e(t('files.admin.all_types')) ?></option>
        <option value="image"    <?= $filters['mime_group'] === 'image'    ? 'selected' : '' ?>><?= e(t('files.admin.images')) ?></option>
        <option value="document" <?= $filters['mime_group'] === 'document' ? 'selected' : '' ?>><?= e(t('files.admin.documents')) ?></option>
        <option value="archive"  <?= $filters['mime_group'] === 'archive'  ? 'selected' : '' ?>><?= e(t('files.admin.archives')) ?></option>
        <option value="text"     <?= $filters['mime_group'] === 'text'     ? 'selected' : '' ?>><?= e(t('files.admin.text')) ?></option>
      </select>

      <input type="date" name="date_from"
              class="form-control form-control-sm fm-filter-auto"
             value="<?= e($filters['date_from']) ?>"
             data-filter
             hx-get="<?= route('files.admin.index') ?>"
             hx-trigger="change"
             hx-target="#fm-admin-table"
             hx-include="[data-filter]"
             placeholder="<?= e(t('files.admin.date_from')) ?>">

      <input type="date" name="date_to"
              class="form-control form-control-sm fm-filter-auto"
             value="<?= e($filters['date_to']) ?>"
             data-filter
             hx-get="<?= route('files.admin.index') ?>"
             hx-trigger="change"
             hx-target="#fm-admin-table"
             hx-include="[data-filter]"
             placeholder="<?= e(t('files.admin.date_to')) ?>">

      <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>" data-filter>
      <input type="hidden" name="dir"  value="<?= e($filters['dir']) ?>"  data-filter>

      <div class="ms-auto d-flex gap-2">
        <a href="<?= route('files.admin.trash') ?>" class="btn btn-sm btn-outline-warning">
          <i class="fa-solid fa-trash-can me-1"></i><?= e(t('files.admin.trash_btn')) ?>
        </a>
        <a href="<?= route('files.admin.export') ?>?<?= http_build_query(array_filter($filters)) ?>"
           class="btn btn-sm btn-outline-secondary">
          <i class="fa-solid fa-file-csv me-1"></i><?= e(t('files.admin.export_csv')) ?>
        </a>
      </div>

    </div>
  </div>
</div>

<!-- Table (HTMX swap target) -->
<div id="fm-admin-table">
  <?php $view->include('Files/Views/admin/partials/table', get_defined_vars()); ?>
</div>
</div>

<?php $view->end(); ?>
