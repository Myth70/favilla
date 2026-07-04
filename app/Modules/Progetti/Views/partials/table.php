<?php
$statusConfig = \App\Modules\Progetti\Services\ProgettiService::getProjectStatuses();

$sortLink = function (string $col, string $label) use ($filters): string {
    $active = ($filters['sort'] ?? '') === $col;
    $nextDir = ($active && ($filters['dir'] ?? 'desc') === 'asc') ? 'desc' : 'asc';
    $arrow = $active ? (($filters['dir'] ?? 'desc') === 'asc' ? ' ↑' : ' ↓') : '';
    $qs = http_build_query(array_merge($filters, ['sort' => $col, 'dir' => $nextDir, 'page' => 1]));
    $url = route('projects.search') . '?' . $qs;
    return '<a href="' . e($url) . '" class="text-decoration-none text-reset prj-table-link" data-prj-table-link="1">' . e($label) . $arrow . '</a>';
};
?>

<div class="card-body p-0">
    <?php if (empty($items)): ?>
    <div class="text-center text-muted py-5">
        <i class="fa-solid fa-diagram-project fa-2x d-block mb-2 opacity-50"></i>
        <?= e(t('progetti.index.no_projects')) ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th><?= $sortLink('name', t('progetti.table.col_project')) ?></th>
                <th class="text-center"><?= $sortLink('status', t('progetti.table.col_status')) ?></th>
                <th><?= $sortLink('end_date', t('progetti.table.col_due')) ?></th>
                <th class="text-center"><?= e(t('progetti.table.col_progress')) ?></th>
                <th class="text-end"><?= e(t('progetti.table.col_budget')) ?></th>
                <th class="text-end"><?= e(t('progetti.table.col_actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $row):
                $sc = $statusConfig[$row['status']] ?? $statusConfig['planning'];
                $showUrl = route('projects.show', ['id' => (int) $row['id']]);
                $endDate = ($row['end_date'] ?? '') !== '' ? format_date((string)$row['end_date'], 'short') : '—';
                $isLate = !empty($row['end_date']) && ($row['status'] ?? '') !== 'completed' && $row['end_date'] < date('Y-m-d');
                $isDone = ($row['status'] ?? '') === 'completed';
                $tasksDone = (int) ($row['tasks_done'] ?? 0);
                $tasksTotal = (int) ($row['tasks_total'] ?? 0);
                $pct = $tasksTotal > 0 ? round($tasksDone / $tasksTotal * 100) : 0;
            ?>
            <tr class="prj-click-row <?= $isLate ? 'prj-row-late' : '' ?> <?= $isDone ? 'prj-row-completed' : '' ?>"
                data-href="<?= e($showUrl) ?>">
                <td>
                    <div class="fw-semibold"><?= e($row['name']) ?></div>
                    <div class="text-muted small">
                        <?= e((string) ($row['client_name'] ?: t('progetti.table.no_client'))) ?>
                        <?php if (!empty($row['code'])): ?>
                        · <span class="text-body-secondary"><?= e($row['code']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($row['owner_name'])): ?>
                        · <i class="fa-solid fa-user-tie me-1"></i><?= e($row['owner_name']) ?>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-center"><span class="badge bg-<?= e($sc['color']) ?>"><?= e($sc['label']) ?></span></td>
                <td>
                    <span class="<?= $isLate ? 'text-danger fw-semibold' : 'text-muted' ?> small">
                        <?php if ($isLate): ?><i class="fa-solid fa-exclamation-triangle me-1"></i><?php endif; ?>
                        <?= e($endDate) ?>
                    </span>
                    <?php if (!empty($row['end_date']) && !$isDone):
                        $dlEnd = \DateTime::createFromFormat('Y-m-d', $row['end_date']);
                        $dlToday = new \DateTime('today');
                        $dlDiff = $dlToday->diff($dlEnd);
                        $dlDays = $dlDiff->invert ? -(int)$dlDiff->days : (int)$dlDiff->days;
                        $dlColor = $dlDays < 0 ? 'danger' : ($dlDays <= 7 ? 'warning' : 'success');
                    ?>
                    <div class="small">
                        <span class="badge bg-<?= $dlColor ?> bg-opacity-75 fw-normal">
                            <?php if ($dlDays < 0): ?><?= e(t('progetti.table.overdue_by', ['days' => abs($dlDays)])) ?><?php elseif ($dlDays === 0): ?><?= e(t('progetti.table.due_today')) ?><?php else: ?><?= e(t('progetti.table.due_in_days', ['days' => $dlDays])) ?><?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <div class="progress prj-table-progress flex-grow-1">
                            <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : 'primary' ?>"
                                 data-prj-progress="<?= (int) $pct ?>"></div>
                        </div>
                        <small class="text-muted"><?= $tasksDone ?>/<?= $tasksTotal ?></small>
                    </div>
                </td>
                <td class="text-end small text-muted">€ <?= e(number_format((float) ($row['budget_planned'] ?? 0), 0, ',', '.')) ?></td>
                <td class="text-end">
                    <?php if (has_permission('progetti.edit')): ?>
                    <button type="button"
                       class="btn btn-sm btn-outline-warning"
                       data-bs-toggle="tooltip" title="<?= e(t('progetti.table.edit_tip')) ?>"
                       data-prj-edit-url="<?= e(route('projects.edit', ['id' => (int) $row['id']])) ?>"
                       data-no-row-nav="1">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <?php endif; ?>
                    <?php if (has_permission('progetti.delete')): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="tooltip"
                            title="<?= e(t('progetti.table.delete_tip')) ?>"
                            data-prj-confirm-action="1"
                            data-prj-confirm-title="<?= e(t('progetti.table.delete_title')) ?>"
                            data-prj-confirm-message="<?= e(t('progetti.table.delete_message', ['name' => (string) $row['name']])) ?>"
                            data-prj-confirm-action-url="<?= e(route('projects.destroy', ['id' => (int) $row['id']])) ?>"
                            data-prj-confirm-submit="<?= e(t('progetti.table.delete_submit')) ?>"
                            data-prj-confirm-icon="fa-diagram-project"
                            data-no-row-nav="1">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if (($pages ?? 1) > 1): ?>
<nav class="card-footer d-flex justify-content-between align-items-center small text-muted">
    <span><?= e(t('progetti.table.results_page', ['total' => (int) $total, 'page' => (int) $page, 'pages' => (int) $pages])) ?></span>
    <ul class="pagination pagination-sm mb-0">
        <?php $baseParams = $filters; ?>
        <li class="page-item <?= ($page ?? 1) <= 1 ? 'disabled' : '' ?>">
            <?php $qs = http_build_query(array_merge($baseParams, ['page' => ($page ?? 1) - 1])); ?>
            <a class="page-link" href="<?= e(route('projects.search') . '?' . $qs) ?>"
               data-prj-table-link="1">&lsaquo;</a>
        </li>
        <?php for ($i = 1; $i <= min($pages, 10); $i++): ?>
        <li class="page-item <?= $i === (int) $page ? 'active' : '' ?>">
            <?php $qs = http_build_query(array_merge($baseParams, ['page' => $i])); ?>
            <a class="page-link" href="<?= e(route('projects.search') . '?' . $qs) ?>"
               data-prj-table-link="1"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= ($page ?? 1) >= ($pages ?? 1) ? 'disabled' : '' ?>">
            <?php $qs = http_build_query(array_merge($baseParams, ['page' => ($page ?? 1) + 1])); ?>
            <a class="page-link" href="<?= e(route('projects.search') . '?' . $qs) ?>"
               data-prj-table-link="1">&rsaquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
