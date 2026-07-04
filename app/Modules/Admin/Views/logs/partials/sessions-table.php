<?php
/**
 * HTMX partial — Sessions table + pagination.
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters, $users
 */
$now = new DateTimeImmutable();
?>

<?php if (empty($items)): ?>
<div class="text-center text-muted py-5">
    <i class="fa-solid fa-id-card fa-2x mb-2 d-block"></i>
    <?= e(t('admin.logs.sessions_empty')) ?>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover table-sm mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th><?= e(t('admin.logs.col_user')) ?></th>
                <th>IP</th>
                <th>User Agent</th>
                <th class="text-nowrap">
                    <a class="text-decoration-none text-dark"
                       hx-get="<?= e(route('admin.logs.sessions')) ?>?sort=last_activity&amp;dir=<?= ($filters['sort'] === 'last_activity' && $filters['dir'] === 'DESC') ? 'ASC' : 'DESC' ?>&amp;<?= e(http_build_query(array_diff_key($filters, ['sort' => '', 'dir' => '']))) ?>"
                       hx-target="#adm-sessions-table" hx-push-url="false">
                        <?= e(t('admin.logs.col_last_activity')) ?>
                        <?php if ($filters['sort'] === 'last_activity'): ?>
                        <i class="fa-solid fa-sort-<?= $filters['dir'] === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                        <?php endif; ?>
                    </a>
                </th>
                <th class="text-nowrap"><?= e(t('admin.logs.col_expires')) ?></th>
                <th class="text-center"><?= e(t('admin.logs.col_status')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $sess): ?>
            <?php
                $expiresAt = new DateTimeImmutable($sess['expires_at']);
                $isActive  = $expiresAt > $now;
            ?>
            <tr>
                <td class="small">
                    <?php if ($sess['user_name']): ?>
                        <span data-bs-toggle="tooltip" title="<?= e($sess['user_username'] ?? '') ?>">
                            <?= e($sess['user_name']) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="small font-monospace text-muted"><?= e($sess['ip'] ?? '—') ?></td>
                <td class="small text-muted adm-truncate"
                    data-bs-toggle="tooltip" title="<?= e($sess['user_agent'] ?? '') ?>">
                    <?= e(mb_strimwidth($sess['user_agent'] ?? '—', 0, 60, '…')) ?>
                </td>
                <td class="small text-nowrap text-muted"
                    data-bs-toggle="tooltip" title="<?= e($sess['last_activity']) ?>">
                    <?= e(date('d/m/Y H:i', strtotime($sess['last_activity']))) ?>
                </td>
                <td class="small text-nowrap <?= $isActive ? '' : 'text-muted' ?>"
                    data-bs-toggle="tooltip" title="<?= e($sess['expires_at']) ?>">
                    <?= e(date('d/m/Y H:i', strtotime($sess['expires_at']))) ?>
                </td>
                <td class="text-center">
                    <?php if ($isActive): ?>
                        <span class="badge bg-success"><?= e(t('admin.logs.active')) ?></span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><?= e(t('admin.logs.expired')) ?></span>
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
                   hx-get="<?= e(route('admin.logs.sessions')) ?>?<?= $qs ?>"
                   hx-target="#adm-sessions-table" hx-push-url="false">&laquo;</a>
            </li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <?php $qs = http_build_query(array_merge($filters, ['page' => $i]), '', '&amp;'); ?>
                <a class="page-link"
                   hx-get="<?= e(route('admin.logs.sessions')) ?>?<?= $qs ?>"
                   hx-target="#adm-sessions-table" hx-push-url="false"><?= $i ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <?php $qs = http_build_query(array_merge($filters, ['page' => $page + 1]), '', '&amp;'); ?>
                <a class="page-link"
                   hx-get="<?= e(route('admin.logs.sessions')) ?>?<?= $qs ?>"
                   hx-target="#adm-sessions-table" hx-push-url="false">&raquo;</a>
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

