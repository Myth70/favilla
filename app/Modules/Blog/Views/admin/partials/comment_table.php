<?php
/**
 * HTMX partial: admin comment table.
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters
 */
?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
        <div class="text-center text-muted py-5"><?= e(t('blog.admin.comments.empty')) ?></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= e(t('blog.admin.blacklist.user_label')) ?></th>
                        <th><?= e(t('blog.admin.form.comment_col')) ?></th>
                        <th><?= e(t('blog.author.col_article')) ?></th>
                        <th><?= e(t('blog.author.col_status')) ?></th>
                        <th><?= e(t('blog.admin.form.date_col')) ?></th>
                        <th class="text-end"><?= e(t('blog.author.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statusBadges = [
                        'pending'  => ['label' => t('blog.admin.comments.status_pending'), 'class' => 'bg-warning text-dark'],
                        'approved' => ['label' => t('blog.admin.comments.status_approved_short'), 'class' => 'bg-success'],
                        'rejected' => ['label' => t('blog.admin.comments.status_rejected_short'), 'class' => 'bg-secondary'],
                    ];
                    ?>
                    <?php foreach ($items as $c): ?>
                    <?php $st = $statusBadges[$c['status'] ?? 'approved'] ?? $statusBadges['approved']; ?>
                    <tr>
                        <td><small><?= e($c['user_name'] ?? t('blog.show.deleted_user')) ?></small></td>
                        <td>
                            <small class="text-truncate d-inline-block bl-comment-snippet">
                                <?= e(mb_substr($c['body'], 0, 100)) ?><?= mb_strlen($c['body']) > 100 ? '...' : '' ?>
                            </small>
                        </td>
                        <td>
                            <?php if (!empty($c['article_slug'])): ?>
                            <a href="<?= e(route('blog.show', ['slug' => $c['article_slug']])) ?>" class="small text-decoration-none">
                                <?= e(mb_substr($c['article_title'] ?? '', 0, 40)) ?>
                            </a>
                            <?php else: ?>
                                <small class="text-muted">—</small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= e($st['class']) ?>"><?= e($st['label']) ?></span></td>
                        <td><small><?= e(format_date($c['created_at'], 'compact')) ?></small></td>
                        <td class="text-end text-nowrap">
                            <?php if (($c['status'] ?? 'approved') !== 'approved'): ?>
                            <form method="post" action="<?= e(route('blog.admin.comments.approve', ['id' => $c['id']])) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-success" title="<?= e(t('blog.admin.comments.approve')) ?>">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (($c['status'] ?? 'approved') !== 'rejected'): ?>
                            <form method="post" action="<?= e(route('blog.admin.comments.reject', ['id' => $c['id']])) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-warning" title="<?= e(t('blog.admin.comments.reject')) ?>"
                                        data-app-confirm="<?= e(t('blog.admin.comments.reject_confirm')) ?>" data-app-confirm-label="<?= e(t('blog.admin.comments.reject')) ?>">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(route('blog.admin.comments.delete', ['id' => $c['id']])) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" title="<?= e(t('blog.author.delete')) ?>"
                                        data-app-confirm="<?= e(t('blog.admin.comments.delete_confirm')) ?>" data-app-confirm-label="<?= e(t('blog.author.delete')) ?>">
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
        <small class="text-muted"><?= e(t('blog.admin.comments.pagination_info', ['total' => $total, 'page' => $page, 'pages' => $total_pages])) ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <?php $qs = http_build_query(array_merge($filters, ['page' => $page - 1]), '', '&amp;'); ?>
                    <a class="page-link" href="?<?= $qs ?>"
                       hx-get="<?= e(route('blog.admin.comments')) ?>?<?= $qs ?>"
                       hx-target="#admin-comment-table">&laquo;</a>
                </li>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <?php $qs = http_build_query(array_merge($filters, ['page' => $i]), '', '&amp;'); ?>
                    <a class="page-link" href="?<?= $qs ?>"
                       hx-get="<?= e(route('blog.admin.comments')) ?>?<?= $qs ?>"
                       hx-target="#admin-comment-table"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <?php $qs = http_build_query(array_merge($filters, ['page' => $page + 1]), '', '&amp;'); ?>
                    <a class="page-link" href="?<?= $qs ?>"
                       hx-get="<?= e(route('blog.admin.comments')) ?>?<?= $qs ?>"
                       hx-target="#admin-comment-table">&raquo;</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
