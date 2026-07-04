<?php
$view->layout('main');
$view->pushStyle('css/progetti.css');
$view->pushScript('js/progetti.js');

$scope = (string) ($filters['scope'] ?? 'active');
$activeListUrl = route('projects.admin.index');
$trashListUrl = route('projects.admin.trash');
?>

<?php $view->start('content'); ?>
<div class="container-fluid prj-page">

    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-screwdriver-wrench',
        'adminTitle'    => t('progetti.admin.title'),
        'adminSubtitle' => t('progetti.admin.subtitle'),
        'adminButtons'  => '<a href="' . e(route('projects.index')) . '" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>' . e(t('progetti.admin.back_to_projects')) . '</a>',
    ]); ?>

    <div class="row g-2 mb-3">
        <div class="col-6 col-xl-2">
            <div class="card shadow-sm prj-admin-stat-card">
                <div class="card-body py-2">
                    <div class="text-muted small"><?= e(t('progetti.admin.stat_total')) ?></div>
                    <div class="h5 mb-0"><?= e((string) ($stats['total_projects'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card shadow-sm prj-admin-stat-card">
                <div class="card-body py-2">
                    <div class="text-muted small"><?= e(t('progetti.admin.stat_active')) ?></div>
                    <div class="h5 mb-0 text-primary"><?= e((string) ($stats['active_projects'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card shadow-sm prj-admin-stat-card">
                <div class="card-body py-2">
                    <div class="text-muted small"><?= e(t('progetti.admin.stat_completed')) ?></div>
                    <div class="h5 mb-0 text-success"><?= e((string) ($stats['completed_projects'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card shadow-sm prj-admin-stat-card">
                <div class="card-body py-2">
                    <div class="text-muted small"><?= e(t('progetti.admin.stat_cancelled')) ?></div>
                    <div class="h5 mb-0 text-secondary"><?= e((string) ($stats['cancelled_projects'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card shadow-sm prj-admin-stat-card">
                <div class="card-body py-2">
                    <div class="text-muted small"><?= e(t('progetti.admin.stat_trash')) ?></div>
                    <div class="h5 mb-0 text-danger"><?= e((string) ($stats['trashed_projects'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form id="prj-admin-filters"
                  class="row g-2 align-items-end"
                  hx-get="<?= e(route('projects.admin.table')) ?>"
                  hx-target="#prj-admin-table"
                  hx-swap="innerHTML"
                  hx-trigger="input changed delay:350ms from:[name='q'], change from:[name='status'], change from:[name='owner_id'], change from:[name='scope']">
                <div class="col-md-4">
                    <input type="text"
                           class="form-control form-control-sm"
                           name="q"
                           value="<?= e((string) ($filters['q'] ?? '')) ?>"
                           placeholder="<?= e(t('progetti.admin.search_placeholder')) ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="status">
                        <option value=""><?= e(t('progetti.admin.all_statuses')) ?></option>
                        <?php foreach ($statusLabels as $status => $cfg): ?>
                            <option value="<?= e($status) ?>" <?= (($filters['status'] ?? '') === $status) ? 'selected' : '' ?>>
                                <?= e($cfg['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="owner_id">
                        <option value="0"><?= e(t('progetti.admin.all_owners')) ?></option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= e((string) $owner['id']) ?>" <?= ((int) ($filters['owner_id'] ?? 0) === (int) $owner['id']) ? 'selected' : '' ?>>
                                <?= e((string) $owner['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="btn-group w-100" role="group" aria-label="<?= e(t('progetti.admin.scope_aria')) ?>">
                        <input type="radio" class="btn-check" name="scope" id="prj-scope-active" value="active" <?= $scope === 'active' ? 'checked' : '' ?>>
                        <label class="btn btn-sm btn-outline-primary" for="prj-scope-active"><?= e(t('progetti.admin.scope_active')) ?></label>

                        <input type="radio" class="btn-check" name="scope" id="prj-scope-trash" value="trash" <?= $scope === 'trash' ? 'checked' : '' ?>>
                        <label class="btn btn-sm btn-outline-danger" for="prj-scope-trash"><?= e(t('progetti.admin.scope_trash')) ?></label>
                    </div>
                </div>

                <input type="hidden" name="sort" value="<?= e((string) ($filters['sort'] ?? 'updated_at')) ?>">
                <input type="hidden" name="dir" value="<?= e((string) ($filters['dir'] ?? 'desc')) ?>">
                <input type="hidden" name="page" value="1">
            </form>

            <div class="d-flex justify-content-between align-items-center mt-2">
                <small class="text-muted"><?= e(t('progetti.admin.realtime_hint')) ?></small>
                <a href="<?= e($scope === 'trash' ? $activeListUrl : $trashListUrl) ?>" class="btn btn-sm <?= $scope === 'trash' ? 'btn-outline-primary' : 'btn-outline-danger' ?>">
                    <i class="fa-solid <?= $scope === 'trash' ? 'fa-list' : 'fa-trash-can' ?> me-1"></i>
                    <?= $scope === 'trash' ? e(t('progetti.admin.show_active')) : e(t('progetti.admin.open_trash')) ?>
                </a>
            </div>
        </div>
    </div>

    <div id="prj-admin-table" class="card shadow-sm">
        <?php $view->include('Progetti/Views/admin/partials/table', get_defined_vars()); ?>
    </div>

    <div class="modal fade" id="prjActionConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i id="prj-confirm-modal-icon" class="fa-solid fa-triangle-exclamation text-danger me-2"></i><span id="prj-confirm-modal-title"><?= e(t('progetti.admin.confirm_action')) ?></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('progetti.show.close_modal_aria')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="prj-confirm-modal-message"><?= e(t('progetti.admin.confirm_message')) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('progetti.admin.cancel')) ?></button>
                    <form method="POST" id="prj-confirm-modal-form" data-prj-no-ajax="1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" id="prj-confirm-modal-method" value="POST">
                        <button type="submit" class="btn btn-danger" id="prj-confirm-modal-submit"><?= e(t('progetti.admin.confirm_submit')) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $view->end(); ?>
