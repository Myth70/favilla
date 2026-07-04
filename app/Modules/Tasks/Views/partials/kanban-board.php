<?php
/**
 * Kanban Board partial — reso sia da index.php (include) che dal controller (HTMX refresh).
 *
 * Variabili: $board, $tags, $statuses, $priorities, $canCreate, $canEdit, $canDelete
 */
?>
<div class="att-kanban-container">
    <?php foreach ($statuses as $statusKey => $statusMeta): ?>
    <div class="att-kanban-column" data-status="<?= e($statusKey) ?>">
        <div class="att-kanban-header att-kanban-header-<?= $statusMeta['color'] ?>">
            <span class="att-kanban-title">
                <i class="fa-solid <?= e($statusMeta['icon']) ?> me-1"></i>
                <?= e($statusMeta['label']) ?>
                <span class="badge bg-<?= $statusMeta['color'] ?> bg-opacity-25 ms-1"><?= count($board[$statusKey] ?? []) ?></span>
            </span>
            <?php if ($canCreate): ?>
            <button type="button"
                    class="att-kanban-add-btn"
                    data-status="<?= e($statusKey) ?>"
                    data-app-tooltip="true"
                    data-bs-placement="top"
                    title="<?= e(t('tasks.tooltip.add_in', ['status' => $statusMeta['label']])) ?>"
                    aria-label="<?= e(t('tasks.tooltip.add_task_in', ['status' => $statusMeta['label']])) ?>">
                <i class="fa-solid fa-plus"></i>
            </button>
            <?php endif; ?>
        </div>
        <div class="att-kanban-items" data-status="<?= e($statusKey) ?>">
            <?php foreach ($board[$statusKey] ?? [] as $task): ?>
            <?php
                $pMeta = $priorities[$task['priority']] ?? ['color' => 'secondary', 'label' => '?', 'icon' => 'fa-minus'];
                $isOverdue = !empty($task['due_date']) && $task['due_date'] < date('Y-m-d') && $statusKey !== 'done';
                $checkTotal = (int) ($task['checklist_total'] ?? 0);
                $checkDone  = (int) ($task['checklist_done'] ?? 0);
                $hasCalendarLink = isModuleEnabled('Calendar') && has_permission('calendar.view') && !empty($task['calendar_event_id']);
            ?>
            <div class="att-kanban-item" data-eid="<?= e((string) $task['id']) ?>" data-priority="<?= e($task['priority']) ?>">
                <!-- Priority indicator bar -->
                <div class="att-kanban-item-priority att-priority-<?= e($task['priority']) ?>"></div>
                <div class="att-kanban-item-body">
                    <!-- Title -->
                    <div class="att-kanban-item-title">
                        <?php if (!empty($task['color'])): ?>
                        <span class="att-color-dot att-color-dot-dynamic" style="--att-color: <?= e($task['color']) ?>;"></span>
                        <?php endif; ?>
                        <?= e($task['title']) ?>
                    </div>

                    <!-- Tags -->
                    <?php if (!empty($task['tags'])): ?>
                    <div class="att-kanban-item-tags">
                        <?php foreach ($task['tags'] as $tag): ?>
                        <span class="att-kanban-tag" style="--att-tag-color: <?= e($tag['color']) ?>"><?= e($tag['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Footer: due date, priority, checklist -->
                    <div class="att-kanban-item-footer">
                        <div class="att-kanban-item-meta">
                            <?php if (!empty($task['due_date'])): ?>
                            <span class="att-kanban-due <?= $isOverdue ? 'att-overdue' : '' ?>" title="<?= e(t('tasks.fields.due_date')) ?>">
                                <i class="fa-regular fa-calendar me-1"></i>
                                <?= e(date('d/m', strtotime($task['due_date']))) ?>
                                <?php if (!empty($task['due_time'])): ?>
                                    <?= e(substr($task['due_time'], 0, 5)) ?>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                            <span class="att-kanban-priority att-priority-text-<?= $pMeta['color'] ?>" title="<?= e(t('tasks.fields.priority')) ?>: <?= e($pMeta['label']) ?>">
                                <i class="fa-solid <?= e($pMeta['icon']) ?>"></i>
                            </span>
                            <?php if ($hasCalendarLink): ?>
                            <span class="att-kanban-priority text-info" title="<?= e(t('tasks.tooltip.open_event')) ?>">
                                <i class="fa-solid fa-calendar-check"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($checkTotal > 0): ?>
                        <span class="att-kanban-checklist <?= $checkDone === $checkTotal ? 'att-checklist-complete' : '' ?>" title="<?= e(t('tasks.checklist.label')) ?>: <?= $checkDone ?>/<?= $checkTotal ?>">
                            <i class="fa-solid fa-list-check me-1"></i><?= $checkDone ?>/<?= $checkTotal ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
