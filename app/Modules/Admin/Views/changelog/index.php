<?php
/**
 * Changelog — lista release.
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushScript('js/admin.js');
$view->start('content');
?>

<?php
$_heroButtons = '';
if (has_permission('admin.changelog.manage')) {
    $_heroButtons = '<a href="' . e(route('admin.changelog.create')) . '" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> ' . e(t('admin.changelog.new_release')) . '</a>';
}
$view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-code-branch',
    'adminTitle'    => 'Changelog',
    'adminSubtitle' => e(t('admin.changelog.releases_count', ['count' => number_format($total)])),
    'adminButtons'  => $_heroButtons,
]);
?>

<!-- Filtri -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="search"
                       name="search"
                       class="form-control form-control-sm"
                       placeholder="<?= e(t('admin.changelog.search_ph')) ?>"
                       value="<?= e($filters['search']) ?>"
                       hx-get="<?= e(route('admin.changelog.index')) ?>"
                       hx-trigger="keyup changed delay:400ms"
                       hx-target="#ch-table-container"
                       hx-push-url="true"
                       hx-include="[name='published']">
            </div>
            <div class="col-md-3">
                <select name="published"
                        class="form-select form-select-sm"
                        hx-get="<?= e(route('admin.changelog.index')) ?>"
                        hx-trigger="change"
                        hx-target="#ch-table-container"
                        hx-push-url="true"
                        hx-include="[name='search']">
                    <option value=""><?= e(t('admin.changelog.all_releases')) ?></option>
                    <option value="1" <?= $filters['published'] === '1' ? 'selected' : '' ?>><?= e(t('admin.changelog.only_published')) ?></option>
                    <option value="0" <?= $filters['published'] === '0' ? 'selected' : '' ?>><?= e(t('admin.changelog.only_drafts')) ?></option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Tabella -->
<div class="card">
    <div class="card-body p-0" id="ch-table-container">
        <?php $view->include('Admin/Views/changelog/partials/table', get_defined_vars()); ?>
    </div>
</div>

<?php $view->end(); ?>
