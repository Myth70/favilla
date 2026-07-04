<?php
$columns       = $columns ?? [];
$board         = $board ?? [];
$pid           = (int) ($project['id'] ?? 0);
$canEdit       = has_permission('progetti.edit');
$currentUserId = (int) auth()['id'];

$taskStatuses   = \App\Modules\Progetti\Services\ProgettiService::getTaskStatuses();
$priorityConfig = \App\Modules\Progetti\Services\ProgettiService::getPriorityConfig();
?>

<!-- ── Legenda ──────────────────────────────────────────────────── -->
<div class="prj-kanban-legend d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="fw-semibold text-muted me-1 small"><?= e(t('progetti.kanban.columns_legend')) ?></span>
    <?php foreach ($taskStatuses as $sKey => $sMeta): ?>
    <span class="prj-legend-item">
        <span class="prj-legend-dot is-task-<?= e($sKey) ?>"></span>
        <?= e($sMeta['label']) ?>
    </span>
    <?php endforeach; ?>
    <span class="prj-legend-sep">·</span>
    <span class="fw-semibold text-muted me-1 small"><?= e(t('progetti.kanban.priority_legend')) ?></span>
    <?php foreach ($priorityConfig as $pKey => $pMeta): ?>
    <span class="prj-legend-item">
        <span class="prj-legend-dot prj-legend-priority-<?= e($pKey) ?>"></span>
        <?= e($pMeta['label']) ?>
    </span>
    <?php endforeach; ?>
</div>

<div class="prj-kanban-container"
     data-prj-move-url="<?= e(route('projects.tasks.move', ['id' => $pid, 'taskId' => '__TID__'])) ?>"
     data-prj-csrf="<?= e(csrf_token()) ?>"
     data-prj-can-edit="<?= $canEdit ? '1' : '0' ?>"
     data-prj-current-user="<?= $currentUserId ?>">

    <?php foreach ($columns as $key => $meta): ?>
    <div class="prj-kanban-column" data-status="<?= e($key) ?>">
        <div class="prj-kanban-header prj-kanban-header-<?= e($meta['color'] ?? 'secondary') ?>">
            <span class="prj-kanban-title">
                <i class="fa-solid <?= e($taskStatuses[$key]['icon'] ?? 'fa-circle') ?> me-1"></i>
                <?= e($meta['label'] ?? $key) ?>
                <span class="badge bg-<?= e($meta['color'] ?? 'secondary') ?> bg-opacity-25 ms-1"><?= count($board[$key] ?? []) ?></span>
            </span>
        </div>
        <div class="prj-kanban-items" data-status="<?= e($key) ?>">
            <?php foreach (($board[$key] ?? []) as $task):
                $isOverdue = !empty($task['due_date']) && $task['due_date'] < date('Y-m-d') && $key !== 'done';
            ?>
            <div class="prj-kanban-card" data-task-id="<?= (int) $task['id'] ?>" data-assigned-user="<?= (int) ($task['assigned_user_id'] ?? 0) ?>">
                <div class="prj-kanban-card-priority prj-priority-<?= e($task['priority'] ?? 'medium') ?>"></div>
                <div class="prj-kanban-card-body">
                    <div class="prj-kanban-card-title"><?= e($task['title']) ?></div>
                    <?php if ((int) ($task['checklist_total'] ?? 0) > 0):
                        $clPct = (int) $task['checklist_total'] > 0
                            ? round((int) $task['checklist_done'] / (int) $task['checklist_total'] * 100)
                            : 0;
                    ?>
                    <div class="prj-kanban-checklist-bar mt-1" data-bs-toggle="tooltip" title="<?= e(t('progetti.kanban.checklist_tooltip', ['done' => (int) $task['checklist_done'], 'total' => (int) $task['checklist_total']])) ?>">
                        <div class="progress prj-kanban-progress-thin">
                            <div class="progress-bar bg-success" style="--prj-pct:<?= $clPct ?>%"></div>
                        </div>
                        <small class="text-muted prj-kanban-cl-label"><?= (int) $task['checklist_done'] ?>/<?= (int) $task['checklist_total'] ?></small>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($task['assigned_user_name'])): ?>
                    <div class="prj-kanban-card-assignee">
                        <i class="fa-solid fa-user"></i><?= e($task['assigned_user_name']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="prj-kanban-card-meta">
                        <span>
                            <?php if (!empty($task['due_date'])): ?>
                            <span class="prj-kanban-due <?= $isOverdue ? 'prj-overdue' : '' ?>">
                                <i class="fa-regular fa-calendar me-1"></i><?= e(format_date((string)$task['due_date'], 'compact')) ?>
                            </span>
                            <?php endif; ?>
                        </span>
                        <span>
                            <?php if ((float) ($task['estimated_hours'] ?? 0) > 0): ?>
                            <span class="prj-kanban-hours"><i class="fa-regular fa-clock me-1"></i><?= e(number_format((float) $task['estimated_hours'], 1)) ?>h</span>
                            <?php endif; ?>
                            <span class="ms-1" title="<?= e($priorityConfig[$task['priority']]['label'] ?? '') ?>">
                                <i class="fa-solid fa-flag text-<?= e($priorityConfig[$task['priority']]['color'] ?? 'secondary') ?>"></i>
                            </span>
                        </span>
                    </div>
                    <?php if ($canEdit || (int) ($task['assigned_user_id'] ?? 0) === $currentUserId): ?>
                    <select class="form-select form-select-sm mt-2 prj-quick-status prj-quick-status-hover"
                            data-url="<?= e(route('projects.tasks.quick_status', ['id' => $pid, 'taskId' => (int) $task['id']])) ?>"
                            data-csrf="<?= e(csrf_token()) ?>">
                        <?php foreach ($columns as $sKey => $sMeta): ?>
                        <option value="<?= e($sKey) ?>" <?= ($task['status'] ?? '') === $sKey ? 'selected' : '' ?>>
                            <?= e($taskStatuses[$sKey]['label'] ?? $sKey) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($board[$key] ?? [])): ?>
            <div class="text-muted small text-center py-3 opacity-50">
                <i class="fa-solid fa-inbox d-block mb-1"></i><?= e(t('progetti.kanban.no_tasks')) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
