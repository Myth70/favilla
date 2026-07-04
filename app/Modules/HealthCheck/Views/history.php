<?php
/**
 * @var \App\Core\View $view
 * @var array $runs
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushStyle('css/healthcheck.css');
$view->start('content');
?>
<div class="container-fluid">

    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'    => 'fa-solid fa-clock-rotate-left',
        'adminTitle'   => t('healthcheck.history_title'),
        'adminButtons' => '<a href="' . e(route('healthcheck.index')) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-heart-pulse me-1"></i>' . e(t('healthcheck.buttons.back_to_check')) . '</a>',
    ]); ?>

    <div id="hc-history">
        <?php $view->include('HealthCheck/Views/partials/history_table', [
            'runs'  => $runs,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ]); ?>
    </div>

</div>
<?php $view->end(); ?>
