<?php
/**
 * Partial: article cards list with pagination.
 * Variables: $items, $total, $page, $per_page, $total_pages
 * Optional: $paginationUrl — base URL (already built via route()) for page links.
 *           Defaults to blog.index for backward compatibility, but callers that
 *           filter by category/tag/author/search MUST pass their own URL,
 *           otherwise pagination silently drops back to the unfiltered index.
 */
use App\Services\FileUploadService;

$paginationBaseUrl = $paginationUrl ?? route('blog.index');
$paginationQuery   = $_GET;
unset($paginationQuery['page']);
$buildPageUrl = function (int $p) use ($paginationBaseUrl, $paginationQuery): string {
    return $paginationBaseUrl . '?' . http_build_query(array_merge($paginationQuery, ['page' => $p]));
};
?>

<?php if (empty($items)): ?>
    <div class="text-center text-muted py-5">
        <i class="fa-solid fa-newspaper fa-3x mb-3 d-block opacity-25"></i>
        <p><?= e(t('blog.public.index.no_articles_found')) ?></p>
    </div>
<?php else: ?>
    <?php foreach ($items as $article): ?>
        <?php $view->include('Blog/Views/public/partials/article_card', compact('article')); ?>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if (($total_pages ?? 1) > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?= e($buildPageUrl($page - 1)) ?>">&laquo;</a>
            </li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e($buildPageUrl($i)) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="<?= e($buildPageUrl($page + 1)) ?>">&raquo;</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>
