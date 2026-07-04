<?php
/**
 * Attività — Kanban Board (vista principale)
 *
 * Variabili: $board, $tags, $stats, $statuses, $priorities,
 *            $canCreate, $canEdit, $canDelete
 */
$view->layout('main');
$view->pushStyle('css/jkanban.min.css');
$view->pushStyle('css/tasks.css');
$view->pushScript('js/vendor/Sortable.min.js');
$view->pushScript('js/jkanban.min.js');
$view->pushScript('js/tasks.js');

use App\Modules\Auth\Helpers\AvatarHelper;

$attProfileName = $user['name'] ?? t('common.user.fallback_name');
$attAvatarUrl   = AvatarHelper::url($_SESSION['user_avatar'] ?? null);
$attInitials    = AvatarHelper::initials($attProfileName);

$attHeroStats = [];
foreach ($statuses as $key => $statusMeta) {
    $attHeroStats[] = [
        'value' => count($board[$key] ?? []),
        'label' => $statusMeta['label'] ?? ucfirst((string) $key),
        'icon'  => 'fa-solid ' . ($statusMeta['icon'] ?? 'fa-circle'),
        'color' => $statusMeta['color'] ?? 'secondary',
    ];
}
if (($stats['overdue'] ?? 0) > 0) {
    $attHeroStats[] = [
        'value' => (int) $stats['overdue'],
        'label' => t('tasks.stats.overdue'),
        'icon'  => 'fa-solid fa-exclamation-triangle',
        'color' => 'danger',
    ];
}
?>

<?php $view->start('content'); ?>

<div class="container-fluid">

    <?php
    $attButtons = '<a href="' . e(route('tasks.list')) . '" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="' . e(t('tasks.tooltip.list_view')) . '">' .
                  '<i class="fa-solid fa-list me-1"></i>' . e(t('tasks.actions.list')) . '</a>';
        if (isModuleEnabled('Calendar') && has_permission('calendar.view')) {
            $attButtons .= '<a href="' . e(route('tasks.list') . '?scope=linked') . '" class="btn btn-outline-info btn-sm" data-bs-toggle="tooltip" title="' . e(t('tasks.tooltip.linked_tasks')) . '">' .
                                         '<i class="fa-solid fa-calendar-check me-1"></i>' . e(t('tasks.actions.in_calendar')) . '</a>';
        }
    if ($canCreate) {
      $attButtons .= '<button type="button" class="btn btn-primary btn-sm" id="att-btn-new" data-bs-toggle="tooltip" title="' . e(t('tasks.tooltip.new')) . '">' .
                     '<i class="fa-solid fa-plus me-1"></i>' . e(t('tasks.actions.new')) . '</button>';
    }
    $view->include('partials/pf-hero-user', [
      'userName'    => t('tasks.title'),
      'userSubtitle' => $attProfileName . ' - ' . t('tasks.subtitle'),
      'userAvatar'  => $attAvatarUrl ?? null,
      'userInitials' => $attInitials,
      'userStats'   => $attHeroStats,
      'userButtons' => $attButtons,
    ]);
    ?>

    <!-- Kanban Board -->
    <div id="att-kanban-wrapper"
         data-board-url="<?= e(route('tasks.board')) ?>"
         data-create-url="<?= e(route('tasks.create')) ?>"
         data-show-url="<?= e(route('tasks.show', ['id' => '__ID__'])) ?>"
         data-edit-url="<?= e(route('tasks.edit', ['id' => '__ID__'])) ?>"
         data-move-url="<?= e(route('tasks.move', ['id' => '__ID__'])) ?>"
         data-toggle-url="<?= e(route('tasks.toggle', ['id' => '__ID__'])) ?>"
         data-destroy-url="<?= e(route('tasks.destroy', ['id' => '__ID__'])) ?>"
         data-store-url="<?= e(route('tasks.store')) ?>"
         data-csrf="<?= e(csrf_token()) ?>"
         data-can-create="<?= $canCreate ? '1' : '0' ?>"
         data-can-edit="<?= $canEdit ? '1' : '0' ?>"
         data-can-delete="<?= $canDelete ? '1' : '0' ?>">

        <div id="att-kanban-board">
            <?php $view->include('Tasks/Views/partials/kanban-board', get_defined_vars()); ?>
        </div>
    </div>

</div>

<!-- Modal shell -->
<div class="modal fade" id="att-modal" tabindex="-1" aria-labelledby="att-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" id="att-modal-content">
            <!-- Caricato dinamicamente -->
        </div>
    </div>
</div>

<?php $view->end(); ?>
