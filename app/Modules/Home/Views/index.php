<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/dashboard-widgets.css'); ?>
<?php $view->pushScript('js/vendor/Sortable.min.js'); ?>
<?php $view->pushScript('js/dashboard-widgets.js'); ?>
<?php $view->start('content'); ?>

<?php
$homeButtons = '<button type="button"'
    . ' class="btn btn-sm btn-outline-secondary rounded-pill"'
    . ' data-bs-toggle="offcanvas"'
    . ' data-bs-target="#widgetSettingsOffcanvas"'
    . ' hx-get="' . e(route('home.widgets.settings')) . '"'
    . ' hx-target="#widgetSettingsOffcanvasBody"'
    . ' hx-swap="innerHTML"'
    . ' hx-trigger="click">'
    . '<i class="fa-solid fa-sliders me-1"></i>' . e(t('home.dashboard.configure')) . '</button>'
    . ' <a href="' . e(route('home.today')) . '" class="btn btn-sm rounded-pill hm-switch-btn">'
    . '<i class="fa-solid fa-bolt me-1"></i>' . e(t('home.dashboard.today_btn')) . '</a>';
?>

<div class="container-fluid">

<?php $view->include('partials/pf-hero-user', [
    'userName' => t('home.dashboard.welcome'),
    'userSubtitle' => t('home.dashboard.hello', ['name' => $user['name'] ?? t('common.user.fallback_name')]),
    'userUseFavillaLogoPlain' => true,
    'userButtons' => $homeButtons,
]); ?>


        <!-- Dashboard grid: rendered inline in one request (fast widgets), with
             slow widgets lazy-loading separately. The whole grid refreshes as a
             single request periodically and on save/reset. -->
        <div id="dashboard-widgets"
             hx-get="<?= e(route('home.widgets')) ?>"
             hx-trigger="every 300s, refreshWidgets from:body, refresh"
             hx-swap="innerHTML">
            <?php $view->include('Home/Views/partials/dashboard_widgets', ['dashboard' => $dashboard ?? []]); ?>
        </div>

        <!-- Widget settings offcanvas -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="widgetSettingsOffcanvas">
            <div id="widgetSettingsOffcanvasBody">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title"><?= e(t('home.dashboard.configure')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(t('home.dashboard.close')) ?>"></button>
                </div>
                <div class="offcanvas-body text-center text-muted py-5">
                    <i class="fa-solid fa-spinner fa-spin fa-lg"></i>
                </div>
            </div>
        </div>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
    window.hmWidgetRoutes = {
        save: <?= json_encode(route('home.widgets.layout')) ?>,
        reset: <?= json_encode(route('home.widgets.reset')) ?>
    };
</script>

<?php $view->end(); ?>
