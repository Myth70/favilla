<?php
/**
 * HTMX partial: author articles table + pagination.
 * Variables: $items, $total, $page, $per_page, $total_pages, $filters
 */
$statusBadge = [
    'draft'     => 'bg-secondary',
    'published' => 'bg-success',
];
$statusLabel = [
    'draft'     => t('blog.status.draft'),
    'published' => t('blog.status.published'),
];
?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
        <div class="text-center text-muted py-5">
            <i class="fa-solid fa-pen-to-square fa-2x mb-2 d-block"></i>
            <?= e(t('blog.author.no_articles')) ?> <a href="<?= e(route('blog.create')) ?>"><?= e(t('blog.author.write_first')) ?></a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= e(t('blog.author.col_article')) ?></th>
                        <th class="text-center bl-col-status"><?= e(t('blog.author.col_status')) ?></th>
                        <th class="text-center d-none d-md-table-cell bl-col-stat" title="<?= e(t('blog.show.views')) ?>"><i class="fa-regular fa-eye"></i></th>
                        <th class="text-center d-none d-md-table-cell bl-col-stat" title="<?= e(t('blog.show.likes')) ?>"><i class="fa-regular fa-heart"></i></th>
                        <th class="text-center d-none d-md-table-cell bl-col-stat" title="<?= e(t('blog.show.comments')) ?>"><i class="fa-regular fa-comment"></i></th>
                        <th class="text-end bl-col-actions"><?= e(t('blog.author.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $article): ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?= e($article['title']) ?></div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <?php if (!empty($article['category_name'])): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-normal bl-category-badge">
                                        <?= e($article['category_name']) ?>
                                    </span>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <i class="fa-regular fa-calendar me-1"></i><?= e(format_date($article['created_at'], 'compact')) ?>
                                </small>
                                <?php if (!empty($article['reading_time'])): ?>
                                    <small class="text-muted">
                                        <i class="fa-regular fa-clock me-1"></i><?= (int) $article['reading_time'] ?> min
                                    </small>
                                <?php endif; ?>
                            </div>
                            <!-- Mobile-only stats -->
                            <div class="d-flex d-md-none gap-3 mt-1">
                                <small class="text-muted" title="<?= e(t('blog.show.views')) ?>"><i class="fa-regular fa-eye me-1"></i><?= number_format((int) ($article['view_count'] ?? 0)) ?></small>
                                <small class="text-muted" title="<?= e(t('blog.show.likes')) ?>"><i class="fa-regular fa-heart me-1"></i><?= (int) ($article['likes_count'] ?? 0) ?></small>
                                <small class="text-muted" title="<?= e(t('blog.show.comments')) ?>"><i class="fa-regular fa-comment me-1"></i><?= (int) ($article['comment_count'] ?? 0) ?></small>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $statusBadge[$article['status']] ?? 'bg-secondary' ?>">
                                <?= $statusLabel[$article['status']] ?? $article['status'] ?>
                            </span>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <span class="fw-medium"><?= number_format((int) ($article['view_count'] ?? 0)) ?></span>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <span class="fw-medium"><?= (int) ($article['likes_count'] ?? 0) ?></span>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <span class="fw-medium"><?= (int) ($article['comment_count'] ?? 0) ?></span>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($article['status'] === 'published'): ?>
                                <a href="<?= e(route('blog.show', ['slug' => $article['slug']])) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="<?= e(t('blog.author.view')) ?>">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            <?php endif; ?>
                            <a href="<?= e(route('blog.edit', ['id' => $article['id']])) ?>"
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

    <?php $view->include('partials/pagination', array_merge(get_defined_vars(), [
        'routeName' => 'blog.author.index',
        'hxTarget'  => '#author-table',
        'label'     => t('blog.author.pagination_label'),
    ])); ?>
</div>
