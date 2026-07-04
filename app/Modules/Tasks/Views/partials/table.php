<?php
/**
 * Tabella lista attività (partial HTMX)
 *
 * Variabili: $items, $total, $pages, $page, $filters, $statuses, $priorities
 */
$sh = sort_context(
    $filters['sort'] ?? '',
    $filters['dir']  ?? 'desc',
    $filters,
    route('tasks.list'),
    '#att-list-table',
    ['class' => 'att-sort-link']
);
?>

<?php if (empty($items)): ?>
    <div class="card shadow-sm">
        <div class="card-body att-empty-state">
            <div class="att-empty-icon">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <h5 class="mb-1"><?= e(t('tasks.table.empty_title')) ?></h5>
            <p class="text-muted mb-3"><?= e(t('tasks.table.empty_hint')) ?></p>
            <?php if (has_permission('tasks.create')): ?>
            <a href="<?= e(route('tasks.create')) ?>" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-plus me-1"></i><?= e(t('tasks.actions.new')) ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 att-list-table">
                <thead>
                    <tr>
                        <th class="att-col-dot"></th>
                        <th><?= $sh('title', t('tasks.table.col_task')) ?></th>
                        <th class="att-col-status"><?= $sh('status', t('tasks.fields.status')) ?></th>
                        <th class="att-col-priority"><?= $sh('priority', t('tasks.fields.priority')) ?></th>
                        <th class="att-col-due"><?= $sh('due_date', t('tasks.fields.due_date')) ?></th>
                        <th class="att-col-checklist"><?= e(t('tasks.checklist.label')) ?></th>
                        <th class="att-col-actions text-end"><?= e(t('common.label.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item):
                    $sMeta    = $statuses[$item['status']] ?? ['label' => '?', 'color' => 'secondary', 'icon' => 'fa-question'];
                    $pMeta    = $priorities[$item['priority']] ?? ['label' => '?', 'color' => 'secondary', 'icon' => 'fa-minus'];
                    $isOverdue = !empty($item['due_date']) && $item['due_date'] < date('Y-m-d') && $item['status'] !== 'done';
                    $isDone    = $item['status'] === 'done';
                    $checkTotal = (int) ($item['checklist_total'] ?? 0);
                    $checkDone  = (int) ($item['checklist_done'] ?? 0);
                    $progressPct = $checkTotal > 0 ? (int) round($checkDone / $checkTotal * 100) : 0;
                    $hasCalendarLink = isModuleEnabled('Calendar') && has_permission('calendar.view') && !empty($item['calendar_event_id']);
                    $calendarDeleteConfirm = $hasCalendarLink
                        ? t('tasks.table.delete_confirm_calendar')
                        : t('tasks.table.delete_confirm');
                ?>
                    <tr class="<?= $isDone ? 'att-row-done' : '' ?> <?= $isOverdue ? 'att-row-overdue' : '' ?>">
                        <td>
                            <?php if (!empty($item['color'])): ?>
                            <span class="att-color-dot att-color-dot-dynamic" style="--att-color: <?= e($item['color']) ?>;"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= e(route('tasks.show', ['id' => $item['id']])) ?>"
                               class="att-title-link <?= $isDone ? 'text-decoration-line-through text-muted' : '' ?>">
                                <?= e($item['title']) ?>
                            </a>
                            <?php if ($hasCalendarLink): ?>
                            <div class="d-flex gap-1 mt-1 flex-wrap">
                                <a href="<?= e(route('calendar.show', ['id' => $item['calendar_event_id']])) ?>"
                                   class="badge rounded-pill text-bg-info text-decoration-none"
                                   data-bs-toggle="tooltip"
                                   title="<?= e(t('tasks.table.open_calendar_link')) ?>">
                                    <i class="fa-solid fa-calendar-check me-1"></i><?= e(t('tasks.actions.in_calendar')) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($item['tags'])): ?>
                            <div class="d-flex gap-1 mt-1 flex-wrap">
                                <?php foreach ($item['tags'] as $tag): ?>
                                <span class="att-kanban-tag" style="--att-tag-color: <?= e($tag['color']) ?>"><?= e($tag['name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $sMeta['color'] ?>-subtle text-<?= $sMeta['color'] ?>-emphasis border border-<?= $sMeta['color'] ?>-subtle">
                                <i class="fa-solid <?= e($sMeta['icon']) ?> me-1"></i><?= e($sMeta['label']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="att-priority-chip att-priority-chip-<?= e($pMeta['color']) ?>">
                                <i class="fa-solid <?= e($pMeta['icon']) ?>"></i>
                                <span><?= e($pMeta['label']) ?></span>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($item['due_date'])): ?>
                                <span class="<?= $isOverdue ? 'text-danger fw-semibold' : 'text-body-secondary' ?> small">
                                    <i class="fa-<?= $isOverdue ? 'solid fa-triangle-exclamation' : 'regular fa-calendar' ?> me-1"></i>
                                    <?= e(date('d/m/Y', strtotime($item['due_date']))) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($checkTotal > 0): ?>
                            <div class="att-checklist-cell" data-bs-toggle="tooltip" title="<?= e(t('tasks.table.checklist_done', ['done' => $checkDone, 'total' => $checkTotal])) ?>">
                                <div class="progress att-progress-xs att-progress-fixed-60">
                                    <div class="progress-bar att-progress-fill <?= $progressPct === 100 ? 'bg-success' : 'bg-primary' ?>"
                                         style="--att-progress: <?= $progressPct ?>%;"></div>
                                </div>
                                <small class="text-muted"><?= $checkDone ?>/<?= $checkTotal ?></small>
                            </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end">
                                <a href="<?= e(route('tasks.show', ['id' => $item['id']])) ?>"
                                   class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="<?= e(t('tasks.actions.detail')) ?>">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <?php if ($hasCalendarLink): ?>
                                <a href="<?= e(route('calendar.show', ['id' => $item['calendar_event_id']])) ?>"
                                   class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="<?= e(t('tasks.table.open_calendar')) ?>">
                                    <i class="fa-solid fa-calendar-check"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (has_permission('tasks.edit')): ?>
                                <a href="<?= e(route('tasks.edit', ['id' => $item['id']])) ?>"
                                   class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('common.action.edit')) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (has_permission('tasks.delete')): ?>
                                <form method="POST" action="<?= e(route('tasks.destroy', ['id' => $item['id']])) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            data-app-confirm="<?= e($calendarDeleteConfirm) ?>"
                                            data-app-confirm-label="<?= e(t('common.action.delete')) ?>"
                                            data-bs-toggle="tooltip" title="<?= e(t('common.action.delete')) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                <?= e(t('tasks.list.results', ['count' => (int) $total])) ?> — <?= e(t('tasks.list.page_info', ['page' => (int) $page, 'pages' => (int) $pages])) ?>
            </small>
            <ul class="pagination pagination-sm mb-0">
                <?php $baseParams = $filters; ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <?php $qs = http_build_query(array_merge($baseParams, ['page' => $page - 1])); ?>
                    <a class="page-link" href="<?= e(route('tasks.list')) ?>?<?= e($qs) ?>"
                       hx-get="<?= e(route('tasks.list')) ?>?<?= e($qs) ?>"
                       hx-target="#att-list-table" hx-push-url="true" aria-label="<?= e(t('common.pagination.previous')) ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                </li>
                <?php for ($i = 1; $i <= min($pages, 10); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <?php $qs = http_build_query(array_merge($baseParams, ['page' => $i])); ?>
                    <a class="page-link" href="<?= e(route('tasks.list')) ?>?<?= e($qs) ?>"
                       hx-get="<?= e(route('tasks.list')) ?>?<?= e($qs) ?>"
                       hx-target="#att-list-table" hx-push-url="true"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <?php $qs = http_build_query(array_merge($baseParams, ['page' => $page + 1])); ?>
                    <a class="page-link" href="<?= e(route('tasks.list')) ?>?<?= e($qs) ?>"
                       hx-get="<?= e(route('tasks.list')) ?>?<?= e($qs) ?>"
                       hx-target="#att-list-table" hx-push-url="true" aria-label="<?= e(t('common.pagination.next')) ?>">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
