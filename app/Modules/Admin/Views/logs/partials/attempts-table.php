<?php
/**
 * HTMX partial — Login Attempts table + pagination.
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters
 */
?>

<?php if (empty($items)): ?>
<div class="text-center text-muted py-5">
    <i class="fa-solid fa-shield-halved fa-2x mb-2 d-block"></i>
    <?= e(t('admin.logs.attempts_empty')) ?>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover table-sm mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap">
                    <a class="text-decoration-none text-dark"
                       hx-get="<?= e(route('admin.logs.attempts')) ?>?sort=created_at&amp;dir=<?= ($filters['sort'] === 'created_at' && $filters['dir'] === 'DESC') ? 'ASC' : 'DESC' ?>&amp;<?= e(http_build_query(array_diff_key($filters, ['sort' => '', 'dir' => '']))) ?>"
                       hx-target="#adm-attempts-table" hx-push-url="false">
                        <?= e(t('admin.logs.col_date')) ?>
                        <?php if ($filters['sort'] === 'created_at'): ?>
                        <i class="fa-solid fa-sort-<?= $filters['dir'] === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                        <?php endif; ?>
                    </a>
                </th>
                <th>Email</th>
                <th>IP</th>
                <th class="text-center"><?= e(t('admin.logs.col_result')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $attempt): ?>
            <tr class="<?= $attempt['success'] ? '' : 'table-danger bg-opacity-25' ?>">
                <td class="text-nowrap small text-muted">
                    <?= e(date('d/m/Y H:i:s', strtotime($attempt['created_at']))) ?>
                </td>
                <td class="small"><?= e($attempt['email']) ?></td>
                <td class="small font-monospace text-muted"><?= e($attempt['ip_address']) ?></td>
                <td class="text-center">
                    <?php if ($attempt['success']): ?>
                        <span class="badge bg-success">
                            <i class="fa-solid fa-check me-1"></i><?= e(t('admin.logs.succeeded')) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger">
                            <i class="fa-solid fa-xmark me-1"></i><?= e(t('admin.logs.failed')) ?>
                        </span>
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
                   hx-get="<?= e(route('admin.logs.attempts')) ?>?<?= $qs ?>"
                   hx-target="#adm-attempts-table" hx-push-url="false">&laquo;</a>
            </li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <?php $qs = http_build_query(array_merge($filters, ['page' => $i]), '', '&amp;'); ?>
                <a class="page-link"
                   hx-get="<?= e(route('admin.logs.attempts')) ?>?<?= $qs ?>"
                   hx-target="#adm-attempts-table" hx-push-url="false"><?= $i ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <?php $qs = http_build_query(array_merge($filters, ['page' => $page + 1]), '', '&amp;'); ?>
                <a class="page-link"
                   hx-get="<?= e(route('admin.logs.attempts')) ?>?<?= $qs ?>"
                   hx-target="#adm-attempts-table" hx-push-url="false">&raquo;</a>
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
