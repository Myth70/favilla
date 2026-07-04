<?php
/**
 * HTMX partial: admin trash table.
 * Variables: $items, $total, $page, $per_page, $total_pages
 */
$statusBadge = ['draft' => 'bg-secondary', 'scheduled' => 'bg-info', 'published' => 'bg-success'];
$statusLabel = [
    'draft'     => t('blog.status.draft'),
    'scheduled' => t('blog.status.scheduled'),
    'published' => t('blog.status.published'),
];
?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
        <div class="text-center text-muted py-5">
            <i class="fa-solid fa-check-circle fa-2x mb-2 d-block"></i>
            <?= e(t('blog.admin.trash.empty')) ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= e(t('blog.author.col_article')) ?></th>
                        <th><?= e(t('blog.admin.form.author_col')) ?></th>
                        <th><?= e(t('blog.author.col_status')) ?></th>
                        <th><?= e(t('blog.admin.trash.deleted_at_col')) ?></th>
                        <th class="text-end"><?= e(t('blog.author.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $a): ?>
                    <tr>
                        <td class="fw-medium"><?= e($a['title']) ?></td>
                        <td><small><?= e($a['author_name'] ?? '—') ?></small></td>
                        <td>
                            <span class="badge <?= $statusBadge[$a['status']] ?? 'bg-secondary' ?>">
                                <?= e($statusLabel[$a['status']] ?? $a['status']) ?>
                            </span>
                        </td>
                        <td><small><?= e(format_date($a['deleted_at'], 'short')) ?></small></td>
                        <td class="text-end text-nowrap">
                            <form method="post" action="<?= e(route('blog.admin.trash.restore', ['id' => $a['id']])) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success" title="<?= e(t('blog.admin.trash.restore')) ?>" data-bs-toggle="tooltip">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            </form>
                            <form method="post" action="<?= e(route('blog.admin.trash.destroy', ['id' => $a['id']])) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-outline-danger ms-1" title="<?= e(t('blog.admin.trash.destroy_permanently')) ?>" data-bs-toggle="tooltip"
                                        data-app-confirm="<?= e(t('blog.admin.trash.destroy_confirm')) ?>" data-app-confirm-label="<?= e(t('blog.author.delete')) ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if (($total_pages ?? 1) > 1): ?>
    <div class="card-footer d-flex align-items-center justify-content-between">
        <small class="text-muted"><?= e(t('blog.admin.trash.pagination_info', ['total' => $total, 'page' => $page, 'pages' => $total_pages])) ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">&laquo;</a>
                </li>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">&raquo;</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
