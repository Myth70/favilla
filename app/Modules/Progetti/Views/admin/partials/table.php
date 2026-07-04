<?php
$filters = $filters ?? [];
$scope = (string) ($filters['scope'] ?? 'active');
$page = max(1, (int) ($page ?? 1));
$pages = max(1, (int) ($pages ?? 1));
$total = (int) ($total ?? 0);

$sortLink = function (string $column, string $label) use ($filters): string {
    $currentSort = (string) ($filters['sort'] ?? 'updated_at');
    $currentDir = strtolower((string) ($filters['dir'] ?? 'desc'));
    $active = $currentSort === $column;
    $nextDir = ($active && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = $active ? ($currentDir === 'asc' ? ' ↑' : ' ↓') : '';

    $payload = [
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'owner_id' => (int) ($filters['owner_id'] ?? 0),
        'scope' => (string) ($filters['scope'] ?? 'active'),
        'sort' => $column,
        'dir' => $nextDir,
        'page' => 1,
    ];

    return '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"'
        . ' hx-get="' . e(route('projects.admin.table')) . '"'
        . ' hx-target="#prj-admin-table"'
        . ' hx-swap="innerHTML"'
        . ' hx-vals=\'' . e(json_encode($payload, JSON_UNESCAPED_UNICODE)) . '\''
        . '>' . e($label . $arrow) . '</button>';
};
?>

<div class="card-body p-0">
    <?php if (empty($items)): ?>
        <div class="text-center text-muted py-5">
            <i class="fa-solid fa-box-open fa-2x d-block mb-2 opacity-50"></i>
            <?= $scope === 'trash' ? e(t('progetti.admin.empty_trash')) : e(t('progetti.admin.empty_active')) ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 prj-admin-table">
                <thead class="table-light">
                    <tr>
                        <th><?= $sortLink('name', t('progetti.admin.col_project')) ?></th>
                        <th><?= $sortLink('status', t('progetti.admin.col_status')) ?></th>
                        <th><?= $sortLink('end_date', t('progetti.admin.col_due')) ?></th>
                        <th><?= e(t('progetti.admin.col_owner')) ?></th>
                        <th class="text-center"><?= e(t('progetti.admin.col_tasks')) ?></th>
                        <th class="text-end"><?= $sortLink('budget_planned', t('progetti.admin.col_budget')) ?></th>
                        <?php if ($scope === 'trash'): ?>
                            <th><?= $sortLink('updated_at', t('progetti.admin.col_deleted_at')) ?></th>
                        <?php else: ?>
                            <th><?= $sortLink('updated_at', t('progetti.admin.col_updated_at')) ?></th>
                        <?php endif; ?>
                        <th class="text-end"><?= e(t('progetti.admin.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row):
                        $status = (string) ($row['status'] ?? 'planning');
                        $cfg = $statusLabels[$status] ?? ['label' => $status, 'color' => 'secondary'];
                        $taskDone = (int) ($row['tasks_done'] ?? 0);
                        $taskTotal = (int) ($row['tasks_total'] ?? 0);
                        $refDate = $scope === 'trash'
                            ? (string) ($row['deleted_at'] ?? '')
                            : (string) ($row['updated_at'] ?? '');
                    ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e((string) ($row['name'] ?? '')) ?></div>
                                <div class="small text-muted">
                                    <?= e((string) ($row['code'] ?: t('progetti.admin.no_code'))) ?>
                                    ·
                                    <?= e((string) ($row['client_name'] ?: t('progetti.admin.no_client'))) ?>
                                </div>
                            </td>
                            <td><span class="badge bg-<?= e((string) $cfg['color']) ?>"><?= e((string) $cfg['label']) ?></span></td>
                            <td class="small text-muted"><?= !empty($row['end_date']) ? e(format_date((string) $row['end_date'], 'short')) : '—' ?></td>
                            <td class="small"><?= e((string) ($row['owner_name'] ?? '—')) ?></td>
                            <td class="text-center"><span class="badge text-bg-light"><?= e((string) $taskDone) ?>/<?= e((string) $taskTotal) ?></span></td>
                            <td class="text-end small">€ <?= e(number_format((float) ($row['budget_planned'] ?? 0), 2, ',', '.')) ?></td>
                            <td class="small text-muted"><?= $refDate !== '' ? e(format_date($refDate, 'relative')) : '—' ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if ($scope === 'trash'): ?>
                                        <button type="button"
                                                class="btn btn-outline-success"
                                                data-prj-confirm-action="1"
                                                data-prj-confirm-action-url="<?= e(route('projects.admin.restore', ['id' => (int) $row['id']])) ?>"
                                                data-prj-confirm-method="POST"
                                                data-prj-confirm-title="<?= e(t('progetti.admin.restore_title')) ?>"
                                                data-prj-confirm-message="<?= e(t('progetti.admin.restore_message', ['name' => (string) $row['name']])) ?>"
                                                data-prj-confirm-submit="<?= e(t('progetti.admin.restore_submit')) ?>"
                                                data-prj-confirm-submit-class="btn-success"
                                                data-prj-confirm-icon="fa-rotate-left"
                                                title="<?= e(t('progetti.admin.restore_tip')) ?>">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-danger"
                                                data-prj-confirm-action="1"
                                                data-prj-confirm-action-url="<?= e(route('projects.admin.purge', ['id' => (int) $row['id']])) ?>"
                                                data-prj-confirm-method="DELETE"
                                                data-prj-confirm-title="<?= e(t('progetti.admin.purge_title')) ?>"
                                                data-prj-confirm-message="<?= e(t('progetti.admin.purge_message', ['name' => (string) $row['name']])) ?>"
                                                data-prj-confirm-submit="<?= e(t('progetti.admin.purge_submit')) ?>"
                                                data-prj-confirm-submit-class="btn-danger"
                                                data-prj-confirm-icon="fa-trash-can"
                                                title="<?= e(t('progetti.admin.purge_tip')) ?>">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= e(route('projects.show', ['id' => (int) $row['id']])) ?>"
                                           class="btn btn-outline-secondary"
                                           title="<?= e(t('progetti.admin.open_tip')) ?>">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                        <button type="button"
                                                class="btn btn-outline-warning"
                                                data-prj-confirm-action="1"
                                                data-prj-confirm-action-url="<?= e(route('projects.admin.move_to_trash', ['id' => (int) $row['id']])) ?>"
                                                data-prj-confirm-method="POST"
                                                data-prj-confirm-title="<?= e(t('progetti.admin.trash_title')) ?>"
                                                data-prj-confirm-message="<?= e(t('progetti.admin.trash_message', ['name' => (string) $row['name']])) ?>"
                                                data-prj-confirm-submit="<?= e(t('progetti.admin.trash_submit')) ?>"
                                                data-prj-confirm-submit-class="btn-warning"
                                                data-prj-confirm-icon="fa-trash"
                                                title="<?= e(t('progetti.admin.trash_tip')) ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= e(t('progetti.admin.page_of', ['page' => (string) $page, 'pages' => (string) $pages, 'total' => (string) $total])) ?></small>
        <div class="btn-group btn-group-sm">
            <?php if ($page > 1): ?>
                <button type="button"
                        class="btn btn-outline-secondary"
                        hx-get="<?= e(route('projects.admin.table')) ?>"
                        hx-target="#prj-admin-table"
                        hx-swap="innerHTML"
                        hx-vals='<?= e(json_encode(array_merge($filters, ['page' => $page - 1]), JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <button type="button"
                        class="btn btn-outline-secondary"
                        hx-get="<?= e(route('projects.admin.table')) ?>"
                        hx-target="#prj-admin-table"
                        hx-swap="innerHTML"
                        hx-vals='<?= e(json_encode(array_merge($filters, ['page' => $page + 1]), JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
