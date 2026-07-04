<?php
/**
 * HTMX partial: admin article table.
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters
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
        <div class="text-center text-muted py-5"><?= e(t('blog.public.index.no_articles_found')) ?></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center bl-col-check">
                            <input type="checkbox" id="ba-select-all" class="form-check-input">
                        </th>
                        <th><?= e(t('blog.author.col_article')) ?></th>
                        <th><?= e(t('blog.admin.form.author_col')) ?></th>
                        <th><?= e(t('blog.author.col_status')) ?></th>
                        <th class="text-center"><?= e(t('blog.public.index.pinned_section')) ?></th>
                        <th><?= e(t('blog.author.form.visibility_label')) ?></th>
                        <th class="text-center"><i class="fa-solid fa-eye" title="<?= e(t('blog.show.views')) ?>"></i></th>
                        <th class="text-center"><i class="fa-solid fa-heart" title="<?= e(t('blog.show.likes')) ?>"></i></th>
                        <th class="text-center"><?= e(t('blog.show.comments')) ?></th>
                        <th><?= e(t('blog.admin.form.date_col')) ?></th>
                        <th class="text-end"><?= e(t('blog.author.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $a): ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ba-row-check" name="ids[]" value="<?= (int) $a['id'] ?>" form="ba-batch-form">
                        </td>
                        <td class="fw-medium">
                            <?= e($a['title']) ?>
                            <?php if (!empty($a['reading_time'])): ?>
                                <br><small class="text-muted"><i class="fa-regular fa-clock"></i> <?= (int) $a['reading_time'] ?> min</small>
                            <?php endif; ?>
                        </td>
                        <td><small><?= e($a['author_name'] ?? '—') ?></small></td>
                        <td>
                            <span class="badge <?= $statusBadge[$a['status']] ?? 'bg-secondary' ?>">
                                <?= e($statusLabel[$a['status']] ?? $a['status']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($a['is_pinned'])): ?>
                                <form method="post" action="<?= e(route('blog.admin.articles.unpin', ['id' => $a['id']])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-warning" title="<?= e(t('blog.flash.article_unpinned')) ?>" data-bs-toggle="tooltip">
                                        <i class="fa-solid fa-thumbtack"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(route('blog.admin.articles.pin', ['id' => $a['id']])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= e(t('blog.author.form.pin_label')) ?>" data-bs-toggle="tooltip">
                                        <i class="fa-regular fa-thumbtack"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php if (($a['visibility'] ?? 'all') === 'all'): ?>
                                    <?= e(t('blog.admin.form.visibility_all_short')) ?>
                                <?php else: ?>
                                    <?= e($a['visibility']) ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td class="text-center"><small><?= (int) ($a['view_count'] ?? 0) ?></small></td>
                        <td class="text-center">
                            <?php if (($a['likes_count'] ?? 0) > 0): ?>
                                <span class="badge bg-danger-subtle text-danger">
                                    <i class="fa-solid fa-heart me-1"></i><?= (int) $a['likes_count'] ?>
                                </span>
                            <?php else: ?>
                                <small class="text-muted">0</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int) ($a['comment_count'] ?? 0) ?></td>
                        <td><small><?= e(format_date($a['created_at'], 'compact')) ?></small></td>
                        <td class="text-end text-nowrap">
                            <?php if ($a['status'] === 'published'): ?>
                            <a href="<?= e(route('blog.show', ['slug' => $a['slug']])) ?>"
                               class="btn btn-sm btn-outline-secondary" title="<?= e(t('blog.author.view')) ?>">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php endif; ?>
                            <a href="<?= e(route('blog.edit', ['id' => $a['id']])) ?>"
                               class="btn btn-sm btn-outline-primary ms-1" title="<?= e(t('blog.author.edit')) ?>">
                                <i class="fa-solid fa-pen"></i>
                            </a>
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
        <small class="text-muted"><?= e(t('blog.admin.articles.pagination_info', ['total' => $total, 'page' => $page, 'pages' => $total_pages])) ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <?php $qs = http_build_query(array_merge($filters, ['page' => $page - 1]), '', '&amp;'); ?>
                    <a class="page-link" href="?<?= $qs ?>"
                       hx-get="<?= e(route('blog.admin.articles')) ?>?<?= $qs ?>"
                       hx-target="#admin-article-table">&laquo;</a>
                </li>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <?php $qs = http_build_query(array_merge($filters, ['page' => $i]), '', '&amp;'); ?>
                    <a class="page-link" href="?<?= $qs ?>"
                       hx-get="<?= e(route('blog.admin.articles')) ?>?<?= $qs ?>"
                       hx-target="#admin-article-table"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <?php $qs = http_build_query(array_merge($filters, ['page' => $page + 1]), '', '&amp;'); ?>
                    <a class="page-link" href="?<?= $qs ?>"
                       hx-get="<?= e(route('blog.admin.articles')) ?>?<?= $qs ?>"
                       hx-target="#admin-article-table">&raquo;</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
