<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $moduleButtons = '';
if (!empty($result['total'])) {
    $moduleButtons .= '<span class="dc-hero-pill" title="' . e(t('documenti.index.totale_pill')) . '"><i class="fa-solid fa-files" aria-hidden="true"></i>' . number_format((int)$result['total'], 0, ',', '.') . '</span>';
}
if (has_permission('documenti.inbox')) {
    $moduleButtons .= '<a href="' . e(route('documenti.inbox')) . '" class="btn btn-outline-primary btn-sm">'
        . '<i class="fa-solid fa-inbox me-1" aria-hidden="true"></i>' . e(t('documenti.index.inbox_btn')) . '</a>';
}
if (has_permission('documenti.create')) {
    $moduleButtons .= '<a href="' . e(route('documenti.create')) . '" class="btn btn-primary btn-sm">'
        . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>' . e(t('documenti.index.nuovo_btn')) . '</a>';
}
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('documenti.title'),
    'moduleIcon'     => 'fa-solid fa-file-alt',
    'moduleSubtitle' => t('documenti.index.subtitle'),
    'moduleButtons'  => $moduleButtons,
]);
?>
    </div>

    <div class="col-12">
        <?php $view->include('Documenti/Views/partials/filtri', compact('filters', 'categorie')); ?>
    </div>

    <div class="col-12">
        <?php $view->include('Documenti/Views/partials/documenti_table', [
    'result'  => $result,
    'filters' => $filters,
]); ?>
    </div>

</div>
</div>
<?php $view->end(); ?>
