<?php
$view->layout('main');
$view->pushStyle('css/progetti.css');
$view->pushScript('js/progetti.js');

$statusConfig = \App\Modules\Progetti\Services\ProgettiService::getProjectStatuses();
?>

<?php $view->start('content'); ?>
<div class="container-fluid prj-page">

    <?php
    $prjButtons = '';
    $prjButtons .= '<a href="' . e(route('projects.my_tasks')) . '" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="' . e(t('progetti.index.my_tasks_btn')) . '">' .
                    '<i class="fa-solid fa-list-check me-1"></i>' . e(t('progetti.index.my_tasks_btn')) . '</a>';
    if (has_permission('progetti.create')) {
        $prjButtons .= '<a href="' . e(route('projects.create')) . '" class="btn btn-primary btn-sm">' .
                        '<i class="fa-solid fa-plus me-1"></i>' . e(t('progetti.index.new_project_btn')) . '</a>';
    }
    $view->include('partials/pf-hero-module', [
        'moduleName'     => t('progetti.title'),
        'moduleIcon'     => 'fa-solid fa-diagram-project',
        'moduleSubtitle' => t('progetti.index.subtitle'),
        'moduleButtons'  => $prjButtons,
    ]);
    ?>

    <!-- Filters -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end" id="prj-filters" data-prj-search-url="<?= e(route('projects.search')) ?>">
                <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm" name="q"
                           id="prj-filter-q"
                           placeholder="<?= e(t('progetti.index.search_placeholder')) ?>"
                           value="<?= e((string) ($filters['q'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" id="prj-filter-status" class="form-select form-select-sm">
                        <option value=""><?= e(t('progetti.index.all_statuses')) ?></option>
                        <?php foreach ($statusConfig as $k => $cfg): ?>
                        <option value="<?= e($k) ?>" <?= (($filters['status'] ?? '') === $k) ? 'selected' : '' ?>>
                            <?= e($cfg['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="<?= e(route('projects.index')) ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fa-solid fa-rotate-left me-1"></i><?= e(t('progetti.index.reset_filters')) ?>
                    </a>
                </div>
                <?php if (has_permission('progetti.export') && isModuleEnabled('Reports')): ?>
                <div class="col-auto">
                    <a href="<?= e(route('reports.export.quick') . '?source=projects_summary') ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('progetti.index.export_csv')) ?>">
                        <i class="fa-solid fa-file-export me-1"></i><?= e(t('progetti.index.export')) ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div id="prj-table" class="card shadow-sm">
        <?php $view->include('Progetti/Views/partials/table', get_defined_vars()); ?>
    </div>

    <div class="modal fade" id="prjEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" id="prj-edit-modal-content">
                <div class="modal-body py-5 text-center text-muted">
                    <i class="fa-solid fa-spinner fa-spin fa-2x d-block mb-3"></i>
                    <?= e(t('progetti.index.loading_edit_module')) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal conferma azione generico (stesso pattern di show.php) -->
    <div class="modal fade" id="prjActionConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i id="prj-confirm-modal-icon" class="fa-solid fa-triangle-exclamation text-danger me-2"></i><span id="prj-confirm-modal-title"><?= e(t('progetti.show.confirm_action')) ?></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('progetti.show.close_modal_aria')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="prj-confirm-modal-message"><?= e(t('progetti.show.confirm_message')) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('progetti.show.cancel')) ?></button>
                    <form method="POST" id="prj-confirm-modal-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-danger" id="prj-confirm-modal-submit"><?= e(t('progetti.show.confirm_submit')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
<?php $view->end(); ?>
