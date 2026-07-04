<?php
/**
 * HTMX partial — Audit Log table + pagination.
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters
 */

// Badge per azione: colore qui, etichetta dalla mappa condivisa admin.actions.
function alAuditBadge(string $action): string {
    $colors = [
        'login'                 => 'success',
        'logout'                => 'secondary',
        'password_changed'      => 'info',
        'password_reset'        => 'warning',
        'password_forgot_reset' => 'warning',
        'user_disabled'         => 'danger',
        'user_activated'        => 'success',
        'update_roles'          => 'info',
        'create'                => 'primary',
        'update'                => 'warning',
        'delete'                => 'danger',
    ];
    $color = $colors[$action] ?? 'secondary';
    $label = t('admin.actions.' . $action);
    if ($label === 'admin.actions.' . $action) {
        $label = $action; // fallback: azione non mappata
    }
    return '<span class="badge bg-' . $color . '">' . e($label) . '</span>';
}
?>

<?php if (empty($items)): ?>
<div class="text-center text-muted py-5">
    <i class="fa-solid fa-scroll fa-2x mb-2 d-block"></i>
    <?= e(t('admin.logs.audit_empty')) ?>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover table-sm mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap">
                    <a class="text-decoration-none text-dark"
                       hx-get="<?= e(route('admin.logs.audit')) ?>?sort=created_at&amp;dir=<?= ($filters['sort'] === 'created_at' && $filters['dir'] === 'DESC') ? 'ASC' : 'DESC' ?>&amp;<?= e(http_build_query(array_diff_key($filters, ['sort' => '', 'dir' => '']))) ?>"
                       hx-target="#adm-audit-table" hx-push-url="false">
                        <?= e(t('admin.logs.col_date')) ?>
                        <?php if ($filters['sort'] === 'created_at'): ?>
                        <i class="fa-solid fa-sort-<?= $filters['dir'] === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                        <?php endif; ?>
                    </a>
                </th>
                <th><?= e(t('admin.logs.col_user')) ?></th>
                <th><?= e(t('admin.logs.col_action')) ?></th>
                <th><?= e(t('admin.logs.col_entity')) ?></th>
                <th>IP</th>
                <th class="text-center"><?= e(t('admin.logs.col_detail')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $log): ?>
            <tr>
                <td class="text-nowrap small text-muted"
                    title="<?= e($log['created_at']) ?>">
                    <?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?>
                </td>
                <td class="small">
                    <?php if ($log['user_name']): ?>
                        <span data-bs-toggle="tooltip" title="<?= e($log['user_username'] ?? '') ?>">
                            <?= e($log['user_name']) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= alAuditBadge($log['action']) ?></td>
                <td class="small">
                    <?php if ($log['entity']): ?>
                        <code><?= e($log['entity']) ?></code>
                        <?php if ($log['entity_id']): ?>
                            <span class="text-muted">#<?= (int) $log['entity_id'] ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="small font-monospace text-muted"><?= e($log['ip'] ?? '—') ?></td>
                <td class="text-center">
                    <?php if ($log['old_value'] !== null || $log['new_value'] !== null): ?>
                    <button class="btn btn-sm btn-outline-secondary adm-detail-btn"
                            data-bs-toggle="tooltip"
                            title="<?= e(mb_strimwidth(($log['new_value'] ?? $log['old_value'] ?? ''), 0, 120, '…')) ?>"
                            data-old="<?= e($log['old_value'] ?? '') ?>"
                            data-new="<?= e($log['new_value'] ?? '') ?>">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Pagination -->
<?php if (($total_pages ?? 1) > 1): ?>
<div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
    <small class="text-muted">
        <?= e(t('admin.logs.pager_full', ['total' => number_format($total), 'page' => $page, 'pages' => $total_pages])) ?>
    </small>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <?php $qs = http_build_query(array_merge($filters, ['page' => $page - 1]), '', '&amp;'); ?>
                <a class="page-link"
                   hx-get="<?= e(route('admin.logs.audit')) ?>?<?= $qs ?>"
                   hx-target="#adm-audit-table" hx-push-url="false">&laquo;</a>
            </li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <?php $qs = http_build_query(array_merge($filters, ['page' => $i]), '', '&amp;'); ?>
                <a class="page-link"
                   hx-get="<?= e(route('admin.logs.audit')) ?>?<?= $qs ?>"
                   hx-target="#adm-audit-table" hx-push-url="false"><?= $i ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <?php $qs = http_build_query(array_merge($filters, ['page' => $page + 1]), '', '&amp;'); ?>
                <a class="page-link"
                   hx-get="<?= e(route('admin.logs.audit')) ?>?<?= $qs ?>"
                   hx-target="#adm-audit-table" hx-push-url="false">&raquo;</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php elseif (!empty($total)): ?>
<div class="card-footer">
    <small class="text-muted"><?= e(t('admin.logs.pager_count', ['total' => number_format($total)])) ?></small>
</div>
<?php endif; ?>

<!-- Modale dettaglio (unico nel DOM, riutilizzato) -->
<div class="modal fade" id="adm-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-code me-1"></i><?= e(t('admin.logs.modal_title')) ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('admin.logs.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted mb-1"><?= e(t('admin.logs.old_value')) ?></div>
                        <pre class="adm-detail-pre" id="adm-modal-old"></pre>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted mb-1"><?= e(t('admin.logs.new_value')) ?></div>
                        <pre class="adm-detail-pre" id="adm-modal-new"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

