<?php
$view->layout('main');
$view->pushStyle('css/reports.css');
$view->pushScript('js/reports.js');
$view->start('content');
?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-clock-rotate-left',
    'adminTitle'    => t('reports.history.title'),
    'adminSubtitle' => t('reports.history.subtitle', ['count' => number_format((int) ($total ?? 0))]),
]); ?>

<?php $view->include('Reports/Views/partials/subnav', ['activeTab' => 'history']); ?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Search -->
            <div class="flex-grow-1 rp-toolbar-search">
                <input type="search"
                       name="q"
                       class="form-control form-control-sm"
                       placeholder="<?= e(t('reports.history.search_ph')) ?>"
                       value="<?= e($filters['q'] ?? '') ?>"
                       data-filter
                       hx-get="<?= e(route('reports.history.index')) ?>"
                       hx-trigger="keyup changed delay:400ms, search"
                       hx-target="#history-table"
                       hx-push-url="true"
                       hx-include="[data-filter]">
            </div>

            <!-- Module filter -->
            <select name="module"
                    class="form-select form-select-sm rp-filter-auto"
                    data-filter
                    hx-get="<?= e(route('reports.history.index')) ?>"
                    hx-trigger="change"
                    hx-target="#history-table"
                    hx-push-url="true"
                    hx-include="[data-filter]">
                <option value=""><?= e(t('reports.history.all_modules')) ?></option>
                <?php foreach ($modules as $mod): ?>
                <option value="<?= e($mod) ?>" <?= ($filters['module'] ?? '') === $mod ? 'selected' : '' ?>>
                    <?= e($mod) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <!-- Format filter -->
            <select name="format"
                    class="form-select form-select-sm rp-filter-auto"
                    data-filter
                    hx-get="<?= e(route('reports.history.index')) ?>"
                    hx-trigger="change"
                    hx-target="#history-table"
                    hx-push-url="true"
                    hx-include="[data-filter]">
                <option value=""><?= e(t('reports.history.all_formats')) ?></option>
                <option value="csv" <?= ($filters['format'] ?? '') === 'csv' ? 'selected' : '' ?>>CSV</option>
                <option value="excel" <?= ($filters['format'] ?? '') === 'excel' ? 'selected' : '' ?>>Excel</option>
                <option value="pdf" <?= ($filters['format'] ?? '') === 'pdf' ? 'selected' : '' ?>>PDF</option>
            </select>

            <!-- Date from -->
            <input type="date" name="date_from"
                     class="form-control form-control-sm rp-filter-auto"
                   value="<?= e($filters['date_from'] ?? '') ?>"
                   data-filter
                   hx-get="<?= e(route('reports.history.index')) ?>"
                   hx-trigger="change"
                   hx-target="#history-table"
                   hx-push-url="true"
                   hx-include="[data-filter]"
                   placeholder="<?= e(t('reports.history.date_from')) ?>">

            <!-- Date to -->
            <input type="date" name="date_to"
                     class="form-control form-control-sm rp-filter-auto"
                   value="<?= e($filters['date_to'] ?? '') ?>"
                   data-filter
                   hx-get="<?= e(route('reports.history.index')) ?>"
                   hx-trigger="change"
                   hx-target="#history-table"
                   hx-push-url="true"
                   hx-include="[data-filter]"
                   placeholder="<?= e(t('reports.history.date_to')) ?>">

            <!-- Hidden sort/dir -->
            <input type="hidden" name="sort" value="<?= e($filters['sort'] ?? '') ?>" data-filter>
            <input type="hidden" name="dir" value="<?= e($filters['dir'] ?? 'DESC') ?>" data-filter>

            <!-- Admin cleanup button -->
            <?php if ($adminView && has_permission('reports.admin')): ?>
            <form method="POST" action="<?= e(route('reports.history.cleanup')) ?>"
                  class="ms-auto">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-warning"
                        data-app-confirm="<?= e(t('reports.history.cleanup_confirm')) ?>"
                        data-app-confirm-class="btn-warning"
                        data-bs-toggle="tooltip" title="<?= e(t('reports.history.cleanup_tip')) ?>">
                    <i class="fa-solid fa-broom me-1"></i><?= e(t('reports.history.cleanup')) ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div id="history-table">
        <?php $view->include('Reports/Views/history/partials/history_table', get_defined_vars()); ?>
    </div>
</div>

<?php $view->end(); ?>
