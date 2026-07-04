<?php
/**
 * Attività — Dettaglio
 *
 * Variabili: $task, $statuses, $priorities, $canEdit, $canDelete
 */
$view->layout('main');
$view->pushStyle('css/tasks.css');
$view->pushScript('js/tasks.js');

$statusMeta   = $statuses[$task['status']] ?? ['label' => '?', 'color' => 'secondary', 'icon' => 'fa-question'];
$priorityMeta = $priorities[$task['priority']] ?? ['label' => '?', 'color' => 'secondary', 'icon' => 'fa-minus'];
$isOverdue    = !empty($task['due_date']) && $task['due_date'] < date('Y-m-d') && $task['status'] !== 'done';
$checkTotal   = (int) ($task['checklist_total'] ?? 0);
$checkDone    = (int) ($task['checklist_done'] ?? 0);
$hasCalendarLink = isModuleEnabled('Calendar') && has_permission('calendar.view') && !empty($task['calendar_event_id']);
$calendarDeleteConfirm = $hasCalendarLink
    ? t('tasks.detail.delete_confirm_calendar')
    : t('tasks.detail.delete_confirm');
?>

<?php $view->start('content'); ?>

<div class="container-fluid">

<?php
$heroButtons = '';
if ($canEdit) {
    $heroButtons .= '<a href="' . e(route('tasks.edit', ['id' => $task['id']])) . '" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="' . e(t('common.action.edit')) . '"><i class="fa-solid fa-pen"></i> ' . e(t('common.action.edit')) . '</a>';
}
$heroButtons .= $hasCalendarLink
    ? '<a href="' . e(route('calendar.show', ['id' => $task['calendar_event_id']])) . '" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="' . e(t('tasks.detail_page.open_event')) . '"><i class="fa-solid fa-calendar-check"></i> ' . e(t('tasks.actions.calendar')) . '</a>'
    : '';
$heroButtons .= '<a href="' . e(route('tasks.index')) . '" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('tasks.detail_page.back_kanban')) . '"><i class="fa-solid fa-arrow-left"></i> ' . e(t('tasks.actions.kanban')) . '</a>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => $task['title'] ?? t('tasks.title'),
    'moduleIcon'     => 'fa-solid fa-list-check',
    'moduleSubtitle' => t('tasks.detail_page.subtitle', ['id' => (string) $task['id']]),
    'moduleButtons'  => $heroButtons,
]);
?>

    <div class="row g-4">

        <!-- Colonna sinistra: info -->
        <div class="col-lg-4">
            <!-- Card info -->
            <div class="card att-card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-circle-info"></i></span>
                    <span class="fw-semibold"><?= e(t('common.label.info')) ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">ID</dt>
                        <dd class="col-7">#<?= e((string) $task['id']) ?></dd>

                        <dt class="col-5 text-muted"><?= e(t('tasks.fields.status')) ?></dt>
                        <dd class="col-7">
                            <span class="badge bg-<?= $statusMeta['color'] ?>">
                                <i class="fa-solid <?= e($statusMeta['icon']) ?> me-1"></i><?= e($statusMeta['label']) ?>
                            </span>
                        </dd>

                        <dt class="col-5 text-muted"><?= e(t('tasks.fields.priority')) ?></dt>
                        <dd class="col-7">
                            <span class="badge bg-<?= $priorityMeta['color'] ?> bg-opacity-75">
                                <i class="fa-solid <?= e($priorityMeta['icon']) ?> me-1"></i><?= e($priorityMeta['label']) ?>
                            </span>
                        </dd>

                        <dt class="col-5 text-muted"><?= e(t('tasks.fields.due_date')) ?></dt>
                        <dd class="col-7">
                            <?php if (!empty($task['due_date'])): ?>
                                <?php if ($isOverdue): ?>
                                    <span class="text-danger fw-bold">
                                        <i class="fa-solid fa-exclamation-triangle me-1"></i>
                                        <?= e(date('d/m/Y', strtotime($task['due_date']))) ?>
                                    </span>
                                <?php else: ?>
                                    <?= e(date('d/m/Y', strtotime($task['due_date']))) ?>
                                <?php endif; ?>
                                <?php if (!empty($task['due_time'])): ?>
                                    <?= e(t('tasks.detail_page.at')) ?> <?= e(substr($task['due_time'], 0, 5)) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </dd>

                        <?php if ($checkTotal > 0): ?>
                        <dt class="col-5 text-muted"><?= e(t('tasks.checklist.label')) ?></dt>
                        <dd class="col-7">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1 att-progress-sm">
                                    <div class="progress-bar bg-success att-progress-fill" style="--att-progress: <?= $checkTotal > 0 ? round($checkDone / $checkTotal * 100) : 0 ?>%;"></div>
                                </div>
                                <small class="text-muted"><?= $checkDone ?>/<?= $checkTotal ?></small>
                            </div>
                        </dd>
                        <?php endif; ?>

                        <dt class="col-5 text-muted"><?= e(t('tasks.actions.calendar')) ?></dt>
                        <dd class="col-7">
                            <?php if ($hasCalendarLink): ?>
                                <a href="<?= e(route('calendar.show', ['id' => $task['calendar_event_id']])) ?>"
                                   class="badge text-bg-info text-decoration-none"
                                   data-bs-toggle="tooltip"
                                   title="<?= e(t('tasks.table.open_calendar_link')) ?>">
                                    <i class="fa-solid fa-calendar-check me-1"></i><?= e(t('tasks.detail_page.linked_event')) ?>
                                </a>
                            <?php elseif (!empty($task['due_date']) && isModuleEnabled('Calendar')): ?>
                                <span class="text-muted"><?= e(t('tasks.detail_page.no_linked_event')) ?></span>
                            <?php else: ?>
                                <span class="text-muted"><?= e(t('tasks.detail_page.not_applicable')) ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-5 text-muted"><?= e(t('tasks.detail_page.created_at')) ?></dt>
                        <dd class="col-7"><?= e(date('d/m/Y H:i', strtotime($task['created_at']))) ?></dd>

                        <?php if ($task['completed_at']): ?>
                        <dt class="col-5 text-muted"><?= e(t('tasks.detail_page.completed_at')) ?></dt>
                        <dd class="col-7"><?= e(date('d/m/Y H:i', strtotime($task['completed_at']))) ?></dd>
                        <?php endif; ?>
                    </dl>

                    <?php if (!empty($task['tags'])): ?>
                    <div class="mt-3">
                        <small class="text-muted d-block mb-1"><?= e(t('tasks.fields.tags')) ?>:</small>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php foreach ($task['tags'] as $tag): ?>
                            <span class="badge att-tag-chip" style="--att-tag-color: <?= e($tag['color']) ?>;">
                                <?= e($tag['name']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Azioni -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-cog"></i></span>
                    <span class="fw-semibold"><?= e(t('common.label.actions')) ?></span>
                </div>
                <div class="card-body d-grid gap-2">
                    <?php if ($canEdit): ?>
                    <a href="<?= e(route('tasks.edit', ['id' => $task['id']])) ?>"
                       class="btn btn-outline-primary">
                        <i class="fa-solid fa-pen me-1"></i><?= e(t('common.action.edit')) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($hasCalendarLink): ?>
                    <a href="<?= e(route('calendar.show', ['id' => $task['calendar_event_id']])) ?>"
                       class="btn btn-outline-info">
                        <i class="fa-solid fa-calendar-check me-1"></i><?= e(t('tasks.table.open_calendar')) ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?= e(route('tasks.index')) ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('tasks.detail_page.back_kanban')) ?>
                    </a>
                    <a href="<?= e(route('tasks.list')) ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-list me-1"></i><?= e(t('tasks.detail_page.back_list')) ?>
                    </a>
                    <?php if (!empty($task['due_date']) && isModuleEnabled('Calendar') && !$hasCalendarLink): ?>
                    <div class="small text-muted pt-1">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <?= e(t('tasks.detail_page.no_link_warning2')) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Zona pericolosa -->
            <?php if ($canDelete): ?>
            <div class="card border-danger">
                <div class="card-header d-flex align-items-center gap-2 text-danger">
                    <span class="app-card-icon"><i class="fa-solid fa-triangle-exclamation text-danger"></i></span>
                    <span class="fw-semibold"><?= e(t('common.label.danger_zone')) ?></span>
                </div>
                <div class="card-body">
                    <form method="POST"
                          action="<?= e(route('tasks.destroy', ['id' => $task['id']])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-outline-danger w-100"
                                data-app-confirm="<?= e($calendarDeleteConfirm) ?>"
                                data-app-confirm-label="<?= e(t('common.action.delete')) ?>">
                            <i class="fa-solid fa-trash me-1"></i><?= e(t('common.action.delete')) ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Colonna destra: contenuto + checklist -->
        <div class="col-lg-8">
            <!-- Descrizione -->
            <div class="card att-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if (!empty($task['color'])): ?>
                        <span class="att-color-dot att-color-dot-dynamic me-2" style="--att-color: <?= e($task['color']) ?>;"></span>
                        <?php endif; ?>
                        <?= e($task['title']) ?>
                    </h5>
                    <span class="badge bg-<?= $statusMeta['color'] ?>"><?= e($statusMeta['label']) ?></span>
                </div>
                <div class="card-body">
                    <?php if (!empty($task['description'])): ?>
                        <div class="att-description"><?= nl2br(e($task['description'])) ?></div>
                    <?php else: ?>
                        <p class="text-muted fst-italic"><?= e(t('tasks.detail_page.no_description')) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Checklist -->
            <div class="card att-card">
                <div class="card-header d-flex justify-content-between align-items-center gap-3">
                    <span class="d-flex align-items-center gap-2">
                        <span class="app-card-icon"><i class="fa-solid fa-list-check"></i></span>
                        <span class="fw-semibold"><?= e(t('tasks.checklist.label')) ?></span>
                        <?php if ($checkTotal > 0): ?>
                        <span class="badge bg-secondary ms-1"><?= $checkDone ?>/<?= $checkTotal ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($checkTotal > 0): ?>
                    <div class="progress att-progress-fixed-100 att-progress-sm">
                        <div class="progress-bar bg-success att-progress-fill" style="--att-progress: <?= round($checkDone / $checkTotal * 100) ?>%;"></div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div id="att-checklist-container"
                         data-task-id="<?= e((string) $task['id']) ?>"
                         data-checklist-url="<?= e(route('tasks.checklist.store', ['id' => $task['id']])) ?>"
                         data-csrf="<?= e(csrf_token()) ?>">
                        <?php $view->include('Tasks/Views/partials/checklist', [
                            'checklist' => $task['checklist'] ?? [],
                            'taskId'    => $task['id'],
                            'canEdit'   => $canEdit,
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php $view->end(); ?>
