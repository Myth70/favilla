<?php
/**
 * Task detail partial (per HTMX modal da kanban).
 *
 * Variabili: $task, $statuses, $priorities, $canEdit, $canDelete
 */
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
<div class="modal-header">
    <h5 class="modal-title">
        <?php if (!empty($task['color'])): ?>
        <span class="att-color-dot att-color-dot-dynamic" style="--att-color: <?= e($task['color']) ?>;"></span>
        <?php endif; ?>
        <?= e($task['title']) ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
</div>
<div class="modal-body">
    <!-- Badges -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <span class="badge bg-<?= $statusMeta['color'] ?>">
            <i class="fa-solid <?= e($statusMeta['icon']) ?> me-1"></i><?= e($statusMeta['label']) ?>
        </span>
        <span class="badge bg-<?= $priorityMeta['color'] ?> bg-opacity-75">
            <i class="fa-solid <?= e($priorityMeta['icon']) ?> me-1"></i><?= e($priorityMeta['label']) ?>
        </span>
        <?php if (!empty($task['due_date'])): ?>
        <span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-secondary bg-opacity-50' ?>">
            <i class="fa-regular fa-calendar me-1"></i>
            <?= e(date('d/m/Y', strtotime($task['due_date']))) ?>
            <?php if (!empty($task['due_time'])): ?> <?= e(substr($task['due_time'], 0, 5)) ?><?php endif; ?>
        </span>
        <?php endif; ?>
        <?php if ($hasCalendarLink): ?>
        <a href="<?= e(route('calendar.show', ['id' => $task['calendar_event_id']])) ?>"
           class="badge text-bg-info text-decoration-none"
           data-bs-toggle="tooltip"
           title="<?= e(t('tasks.tooltip.open_event')) ?>">
            <i class="fa-solid fa-calendar-check me-1"></i><?= e(t('tasks.actions.in_calendar')) ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- Tags -->
    <?php if (!empty($task['tags'])): ?>
    <div class="d-flex gap-1 mb-3 flex-wrap">
        <?php foreach ($task['tags'] as $tag): ?>
        <span class="badge att-tag-chip" style="--att-tag-color: <?= e($tag['color']) ?>;">
            <?= e($tag['name']) ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Description -->
    <?php if (!empty($task['description'])): ?>
    <div class="att-description mb-3"><?= nl2br(e($task['description'])) ?></div>
    <?php endif; ?>

    <!-- Checklist -->
    <?php if (!empty($task['checklist'])): ?>
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="small"><i class="fa-solid fa-list-check me-1"></i><?= e(t('tasks.checklist.label')) ?></strong>
            <?php if ($checkTotal > 0): ?>
            <div class="d-flex align-items-center gap-2">
                <div class="progress att-progress-fixed-60 att-progress-xs">
                    <div class="progress-bar bg-success att-progress-fill" style="--att-progress: <?= round($checkDone / $checkTotal * 100) ?>%;"></div>
                </div>
                <small class="text-muted"><?= $checkDone ?>/<?= $checkTotal ?></small>
            </div>
            <?php endif; ?>
        </div>
        <ul class="list-unstyled mb-0 small">
            <?php foreach ($task['checklist'] as $cl): ?>
            <li class="d-flex align-items-center gap-2 py-1">
                <i class="fa-<?= $cl['is_done'] ? 'solid fa-square-check text-success' : 'regular fa-square text-muted' ?>"></i>
                <span class="<?= $cl['is_done'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= e($cl['text']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Meta -->
    <div class="small text-muted">
        <?= e(t('tasks.detail.created')) ?>: <?= format_date($task['created_at'], 'compact') ?>
        <?php if ($task['completed_at']): ?>
        · <?= e(t('tasks.detail.completed')) ?>: <?= format_date($task['completed_at'], 'compact') ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($task['due_date']) && isModuleEnabled('Calendar') && !$hasCalendarLink): ?>
    <div class="alert alert-light border small mt-3 mb-0">
        <i class="fa-solid fa-circle-info me-1"></i>
        <?= e(t('tasks.detail.no_link_warning')) ?>
    </div>
    <?php endif; ?>
</div>
<div class="modal-footer">
    <?php if ($canDelete): ?>
    <button type="button" class="btn btn-outline-danger me-auto att-modal-delete-btn"
            data-task-id="<?= e((string) $task['id']) ?>"
            data-confirm-message="<?= e($calendarDeleteConfirm) ?>">
        <i class="fa-solid fa-trash me-1"></i><?= e(t('common.action.delete')) ?>
    </button>
    <?php endif; ?>
    <a href="<?= e(route('tasks.show', ['id' => $task['id']])) ?>" class="btn btn-outline-secondary">
        <i class="fa-solid fa-expand me-1"></i><?= e(t('tasks.actions.detail')) ?>
    </a>
    <?php if ($hasCalendarLink): ?>
    <a href="<?= e(route('calendar.show', ['id' => $task['calendar_event_id']])) ?>" class="btn btn-outline-info">
        <i class="fa-solid fa-calendar-check me-1"></i><?= e(t('tasks.actions.calendar')) ?>
    </a>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <button type="button" class="btn btn-primary att-modal-edit-btn"
            data-task-id="<?= e((string) $task['id']) ?>">
        <i class="fa-solid fa-pen me-1"></i><?= e(t('common.action.edit')) ?>
    </button>
    <?php endif; ?>
</div>
