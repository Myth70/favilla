<?php
/**
 * @var \App\Core\View $view
 * @var array $results
 * @var array $summary ['ok' => int, 'warn' => int, 'fail' => int]
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushStyle('css/healthcheck.css');
$view->start('content');

$hcActions = '';
if (has_permission('healthcheck.history')) {
    $hcActions .= '<a href="' . e(route('healthcheck.history')) . '" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="' . e(t('healthcheck.buttons.history')) . '"><i class="fa-solid fa-clock-rotate-left me-1"></i>' . e(t('healthcheck.buttons.history')) . '</a>';
}
if (has_permission('healthcheck.export')) {
    $hcActions .= '<a href="' . e(route('healthcheck.export')) . '" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="' . e(t('healthcheck.buttons.export_csv')) . '"><i class="fa-solid fa-file-csv me-1"></i>' . e(t('healthcheck.buttons.export_csv')) . '</a>';
}
$hcActions .= '<button type="button" class="btn btn-sm btn-outline-primary" hx-get="' . e(route('healthcheck.deep')) . '" hx-target="#hc-content" hx-indicator="#hc-spinner" data-bs-toggle="tooltip" title="' . e(t('healthcheck.tooltip.deep_scan')) . '"><i class="fa-solid fa-magnifying-glass-chart me-1"></i>' . e(t('healthcheck.buttons.deep_scan')) . '</button>';
$hcActions .= '<button type="button" class="btn btn-sm btn-outline-secondary" hx-get="' . e(route('healthcheck.index')) . '" hx-target="#hc-content" hx-indicator="#hc-spinner" data-bs-toggle="tooltip" title="' . e(t('healthcheck.buttons.refresh')) . '"><i class="fa-solid fa-rotate me-1" id="hc-spinner"></i>' . e(t('healthcheck.buttons.refresh')) . '</button>';
?>
<div class="container-fluid">

    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-heart-pulse',
        'adminTitle'    => t('healthcheck.title'),
        'adminSubtitle' => t('healthcheck.subtitle'),
        'adminButtons'  => $hcActions,
    ]); ?>

    <!-- Trigger silenzioso per il caricamento iniziale: non è il target, non viene ri-processato -->
    <div hx-get="<?= e(route('healthcheck.index')) ?>"
         hx-trigger="load"
         hx-target="#hc-content"
         hx-swap="innerHTML"
         class="d-none"
         aria-hidden="true"></div>

    <div id="hc-content">
        <div class="hc-loading-overlay">
            <div class="hc-spinner-wrap">
                <div class="hc-pulse-ring"></div>
                <i class="fa-solid fa-heart-pulse hc-pulse-icon text-primary"></i>
                <p class="mt-3 text-muted small"><?= e(t('healthcheck.loading')) ?></p>
            </div>
        </div>
    </div>

</div>
<?php $view->end(); ?>
