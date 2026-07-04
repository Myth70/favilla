<?php
/**
 * Pagina lista completa notifiche.
 * Variables: $items, $total, $page, $pages, $filter
 */
$view->layout('main');
$view->pushScript('js/bulk-select.js');
$view->start('content');

use App\Modules\Auth\Helpers\AvatarHelper;

$nProfile   = $userProfile ?? [];
$nStats     = $notificationStats ?? ['total' => 0, 'unread' => 0, 'read' => 0];
$nAvatarUrl = AvatarHelper::url($nProfile['avatar'] ?? null);
$nInitials  = AvatarHelper::initials($nProfile['name'] ?? 'U');

$nHeroStats = [
    ['value' => (int) ($nStats['total'] ?? 0),  'label' => t('notifications.index.stat_total'),  'icon' => 'fa-solid fa-bell',      'color' => 'primary'],
    ['value' => (int) ($nStats['unread'] ?? 0), 'label' => t('notifications.index.stat_unread'), 'icon' => 'fa-solid fa-envelope',  'color' => 'warning'],
    ['value' => (int) ($nStats['read'] ?? 0),   'label' => t('notifications.index.stat_read'),   'icon' => 'fa-solid fa-check',     'color' => 'success'],
];
?>

<div class="container-fluid">

<?php
$filterLabel = match($filter) { 'unread' => t('notifications.filter.unread'), 'read' => t('notifications.filter.read'), default => t('notifications.filter.all') };
$nSubtitle = ($nProfile['name'] ?? '') . ' — ' . t('notifications.index.view_prefix') . ' ' . $filterLabel;

$nButtons = '<div class="btn-group btn-group-sm" role="group" aria-label="' . e(t('notifications.index.filter_aria')) . '">' .
    '<a href="' . e(route('notifications.index')) . '" class="btn ' . ($filter === null ? 'btn-secondary' : 'btn-outline-secondary') . '">' . e(t('notifications.filter.all')) . '</a>' .
    '<a href="' . e(route('notifications.index')) . '?filter=unread" class="btn ' . ($filter === 'unread' ? 'btn-secondary' : 'btn-outline-secondary') . '">' . e(t('notifications.filter.unread')) . '</a>' .
    '<a href="' . e(route('notifications.index')) . '?filter=read" class="btn ' . ($filter === 'read' ? 'btn-secondary' : 'btn-outline-secondary') . '">' . e(t('notifications.filter.read')) . '</a>' .
    '</div>';
if ($filter !== 'read') {
    $nButtons .= '<form method="POST" action="' . e(route('notifications.read-all')) . '">' .
        csrf_field() .
        '<button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-check-double me-1"></i> ' . e(t('notifications.index.mark_all_read')) . '</button>' .
        '</form>';
}
$nButtons .= '<a href="' . e(route('notifications.settings')) . '" class="btn btn-sm btn-outline-primary">' .
             '<i class="fa-solid fa-sliders me-1"></i>' . e(t('notifications.index.settings')) . '</a>';

$view->include('partials/pf-hero-user', [
    'userName'     => t('notifications.my_notifications'),
    'userSubtitle' => $nSubtitle,
    'userAvatar'   => $nAvatarUrl ?? null,
    'userInitials' => $nInitials,
    'userStats'    => $nHeroStats,
    'userButtons'  => $nButtons,
]);
?>

<div class="row g-4">

    <!-- ================================================================
         Lista notifiche
         ================================================================ -->
    <div class="col-12">
        <div class="card shadow-sm">

            <!-- Card header: titolo -->
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <span class="pf-card-header-icon"><i class="fa-solid fa-bell"></i></span>
                    <?= e(t('notifications.index.card_title')) ?>
                    <?php if ($total > 0): ?>
                        <span class="badge bg-secondary ms-2 fw-normal nt-total-badge"><?= $total ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lista notifiche -->
            <div class="card-body p-0 nt-list-card"
                 id="nt-list-container"
                 hx-get="<?= e(route('notifications.index')) ?><?= $filter ? '?filter=' . e($filter) : '' ?>"
                 hx-trigger="notifAllRead from:body"
                 hx-target="#nt-list-container"
                 hx-swap="innerHTML">
                <?php $view->include('Notifications/Views/partials/list_rows', get_defined_vars()); ?>
            </div>

        </div>
    </div>

</div>

<?php $view->end(); ?>
