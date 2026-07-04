<?php
$view->layout('main');
$view->pushStyle('css/reports.css');
$view->pushScript('js/reports.js');
$view->start('content');

$templatesButtons = '';
if (has_permission('reports.admin')) {
    $templatesButtons .= '<a href="' . e(route('reports.templates.bundled')) . '" class="btn btn-sm btn-outline-secondary">'
        . '<i class="fa-solid fa-boxes-stacked me-1"></i>' . e(t('reports.templates.bundled_btn')) . '</a>';
}
if (has_permission('reports.create')) {
    $templatesButtons .= ($templatesButtons !== '' ? ' ' : '')
        . '<a href="' . e(route('reports.templates.new')) . '" class="btn btn-sm btn-primary">'
        . '<i class="fa-solid fa-plus me-1"></i>' . e(t('reports.templates.new_template')) . '</a>';
}
?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-wand-magic-sparkles',
    'adminTitle'    => t('reports.templates.title'),
    'adminSubtitle' => t('reports.templates.subtitle', ['count' => number_format((int) ($total ?? 0))]),
    'adminButtons'  => $templatesButtons,
]); ?>

<?php $view->include('Reports/Views/partials/subnav', ['activeTab' => 'templates']); ?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Search -->
            <div class="flex-grow-1 rp-toolbar-search">
                <input type="search"
                       name="q"
                       class="form-control form-control-sm"
                       placeholder="<?= e(t('reports.templates.search_ph')) ?>"
                       value="<?= e($filters['q'] ?? '') ?>"
                       data-filter
                       hx-get="<?= e(route('reports.templates.index')) ?>"
                       hx-trigger="keyup changed delay:400ms, search"
                       hx-target="#template-cards"
                       hx-push-url="true"
                       hx-include="[data-filter]">
            </div>

            <!-- Module filter -->
            <select name="module"
                    class="form-select form-select-sm rp-filter-auto"
                    data-filter
                    hx-get="<?= e(route('reports.templates.index')) ?>"
                    hx-trigger="change"
                    hx-target="#template-cards"
                    hx-push-url="true"
                    hx-include="[data-filter]">
                <option value=""><?= e(t('reports.templates.all_modules')) ?></option>
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
                    hx-get="<?= e(route('reports.templates.index')) ?>"
                    hx-trigger="change"
                    hx-target="#template-cards"
                    hx-push-url="true"
                    hx-include="[data-filter]">
                <option value=""><?= e(t('reports.templates.all_formats')) ?></option>
                <option value="csv" <?= ($filters['format'] ?? '') === 'csv' ? 'selected' : '' ?>>CSV</option>
                <option value="excel" <?= ($filters['format'] ?? '') === 'excel' ? 'selected' : '' ?>>Excel</option>
                <option value="pdf" <?= ($filters['format'] ?? '') === 'pdf' ? 'selected' : '' ?>>PDF</option>
            </select>

            <!-- Hidden sort/dir -->
            <input type="hidden" name="sort" value="<?= e($filters['sort'] ?? '') ?>" data-filter>
            <input type="hidden" name="dir" value="<?= e($filters['dir'] ?? 'DESC') ?>" data-filter>

            <!-- Actions -->
            <div class="ms-auto d-flex gap-2">
                <?php if (has_permission('reports.admin')): ?>
                <a href="<?= e(route('reports.templates.bundled')) ?>" class="btn btn-sm btn-outline-secondary"
                   data-bs-toggle="tooltip" title="<?= e(t('reports.templates.bundled_tip')) ?>">
                    <i class="fa-solid fa-boxes-stacked me-1"></i><?= e(t('reports.templates.bundled_short')) ?>
                </a>
                <?php endif; ?>
                <?php if (has_permission('reports.create')): ?>
                <a href="<?= e(route('reports.templates.new')) ?>" class="btn btn-sm btn-primary"
                   data-bs-toggle="tooltip" title="<?= e(t('reports.templates.new_tip')) ?>">
                    <i class="fa-solid fa-plus me-1"></i><?= e(t('reports.templates.new_template')) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div id="template-cards">
            <?php $view->include('Reports/Views/templates/partials/template_cards', get_defined_vars()); ?>
        </div>
    </div>
</div>

<?php $view->end(); ?>
