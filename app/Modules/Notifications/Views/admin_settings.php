<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushStyle('css/nt-admin-settings.css'); ?>
<?php $view->pushScript('js/nt-admin-settings.js'); ?>
<?php $view->start('content'); ?>

<?php
$iconOptions = [
    'fa-solid fa-bell' => t('notifications.icon_label.bell'),
    'fa-solid fa-envelope' => t('notifications.icon_label.envelope'),
    'fa-solid fa-envelope-open' => t('notifications.icon_label.envelope_open'),
    'fa-solid fa-paper-plane' => t('notifications.icon_label.paper_plane'),
    'fa-solid fa-comment' => t('notifications.icon_label.comment'),
    'fa-solid fa-comments' => t('notifications.icon_label.comments'),
    'fa-solid fa-circle-info' => t('notifications.icon_label.circle_info'),
    'fa-solid fa-circle-check' => t('notifications.icon_label.circle_check'),
    'fa-solid fa-triangle-exclamation' => t('notifications.icon_label.triangle_exclamation'),
    'fa-solid fa-circle-exclamation' => t('notifications.icon_label.circle_exclamation'),
    'fa-solid fa-circle-xmark' => t('notifications.icon_label.circle_xmark'),
    'fa-solid fa-clock' => t('notifications.icon_label.clock'),
    'fa-solid fa-calendar-day' => t('notifications.icon_label.calendar_day'),
    'fa-solid fa-list-check' => t('notifications.icon_label.list_check'),
    'fa-solid fa-database' => t('notifications.icon_label.database'),
    'fa-solid fa-address-book' => t('notifications.icon_label.address_book'),
    'fa-solid fa-newspaper' => t('notifications.icon_label.newspaper'),
    'fa-solid fa-people-group' => t('notifications.icon_label.people_group'),
    'fa-solid fa-heart-pulse' => t('notifications.icon_label.heart_pulse'),
    'fa-solid fa-shield-halved' => t('notifications.icon_label.shield_halved'),
    'fa-solid fa-box-archive' => t('notifications.icon_label.box_archive'),
    'fa-solid fa-gear' => t('notifications.icon_label.gear'),
    'fa-solid fa-user-check' => t('notifications.icon_label.user_check'),
    'fa-solid fa-plus' => t('notifications.icon_label.plus'),
    'fa-solid fa-link' => t('notifications.icon_label.link'),
];

$colorOptions = [
    '' => t('notifications.color_label.default'),
    '#3b82f6' => t('notifications.color_label.blue'),
    '#8b5cf6' => t('notifications.color_label.purple'),
    '#ec4899' => t('notifications.color_label.pink'),
    '#ef4444' => t('notifications.color_label.red'),
    '#f97316' => t('notifications.color_label.orange'),
    '#22c55e' => t('notifications.color_label.green'),
    '#14b8a6' => t('notifications.color_label.teal'),
    '#64748b' => t('notifications.color_label.gray'),
];

$iconCatalog = [];
foreach ($iconOptions as $iconValue => $iconLabel) {
    $iconCatalog[] = ['value' => $iconValue, 'label' => $iconLabel];
}

$colorCatalog = [];
foreach ($colorOptions as $colorValue => $colorLabel) {
    $colorCatalog[] = ['value' => $colorValue, 'label' => $colorLabel];
}
?>

<div class="container-fluid ntas-page">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'  => 'fa-solid fa-bell',
        'adminTitle' => t('notifications.admin.dispatcher'),
    ]); ?>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm ntas-stat-card">
                <div class="card-body">
                    <div class="ntas-stat-label"><?= e(t('notifications.admin.stat_dispatch')) ?></div>
                    <div class="ntas-stat-value"><?= e((string) array_sum($dispatchStats ?? [])) ?></div>
                    <div class="ntas-stat-meta"><?= e(t('notifications.admin.stat_dispatch_meta')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm ntas-stat-card">
                <div class="card-body">
                    <div class="ntas-stat-label"><?= e(t('notifications.admin.stat_queue')) ?></div>
                    <div class="ntas-stat-value"><?= e((string) (($queueStats['email']['pending'] ?? 0) + ($queueStats['telegram']['pending'] ?? 0))) ?></div>
                    <div class="ntas-stat-meta"><?= e(t('notifications.admin.stat_queue_meta')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm ntas-stat-card">
                <div class="card-body">
                    <div class="ntas-stat-label"><?= e(t('notifications.admin.stat_failed')) ?></div>
                    <div class="ntas-stat-value"><?= e((string) (($deliveryStats['email']['failed'] ?? 0) + ($deliveryStats['telegram']['failed'] ?? 0) + ($deliveryStats['in_app']['failed'] ?? 0))) ?></div>
                    <div class="ntas-stat-meta"><?= e(t('notifications.admin.stat_failed_meta')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm ntas-stat-card">
                <div class="card-body">
                    <div class="ntas-stat-label"><?= e(t('notifications.admin.stat_bot')) ?></div>
                    <div class="ntas-stat-value"><?= !empty($defaultBot) ? e(t('notifications.admin.bot_active')) : e(t('notifications.admin.bot_absent')) ?></div>
                    <div class="ntas-stat-meta"><?= !empty($defaultBot['bot_username']) ? '@' . e($defaultBot['bot_username']) : e(t('notifications.admin.bot_configure')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-events" type="button" role="tab">
                <i class="fa-solid fa-diagram-project me-1"></i><?= e(t('notifications.admin.tab_events')) ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-bot" type="button" role="tab">
                <i class="fa-brands fa-telegram me-1"></i><?= e(t('notifications.admin.tab_bot')) ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-queue" type="button" role="tab">
                <i class="fa-solid fa-wave-square me-1"></i><?= e(t('notifications.admin.tab_queue')) ?>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-events" role="tabpanel">
            <?php $view->include('Notifications/Views/partials/admin_events', get_defined_vars()); ?>
        </div>
        <div class="tab-pane fade" id="pane-bot" role="tabpanel">
            <?php $view->include('Notifications/Views/partials/admin_telegram', get_defined_vars()); ?>
        </div>
        <div class="tab-pane fade" id="pane-queue" role="tabpanel">
            <?php $view->include('Notifications/Views/partials/admin_queue', get_defined_vars()); ?>
        </div>
    </div>

    <?php $view->include('Notifications/Views/partials/admin_template_guide', get_defined_vars()); ?>
</div>

<!-- Event edit modal shell -->
<div class="modal fade" id="ntas-event-modal" tabindex="-1" aria-labelledby="ntasEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" id="ntas-event-modal-content">
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden"><?= e(t('notifications.admin.loading')) ?></span></div>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="ntas-icon-catalog"><?= json_encode($iconCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/json" id="ntas-color-catalog"><?= json_encode($colorCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/json" id="ntas-context-map"><?= json_encode($contextVariables ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div class="modal fade" id="ntasIconPickerModal" tabindex="-1" aria-labelledby="ntasIconPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ntasIconPickerModalLabel"><i class="fa-solid fa-icons"></i><?= e(t('notifications.admin.icon_picker_title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="search" class="form-control" id="ntasIconSearch" placeholder="<?= e(t('notifications.admin.icon_search_ph')) ?>">
                </div>
                <div class="ntas-icon-custom mb-3">
                    <label class="form-label mb-1" for="ntasIconCustomClass"><?= e(t('notifications.admin.icon_custom_label')) ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="ntasIconCustomClass" placeholder="fa-solid fa-rocket">
                        <button type="button" class="btn btn-outline-primary" id="ntasApplyCustomIcon"><?= e(t('notifications.admin.icon_custom_apply')) ?></button>
                    </div>
                </div>
                <div class="ntas-icon-grid" id="ntasIconGrid"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ntasColorPickerModal" tabindex="-1" aria-labelledby="ntasColorPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ntasColorPickerModalLabel"><i class="fa-solid fa-palette"></i><?= e(t('notifications.admin.color_picker_title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="ntas-color-grid" id="ntasColorGrid"></div>
            </div>
        </div>
    </div>
</div>

<?php $view->end(); ?>
