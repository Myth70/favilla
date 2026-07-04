<?php
/**
 * Blog index — public article listing.
 * Variables: $view, $items, $total, $page, $per_page, $total_pages,
 *            $categories, $tags, $filters, $layout
 */
$view->layout($layout ?? 'main');
$view->pushStyle('css/blog.css');
$view->pushScript('js/blog.js');
$view->start('content');

$currentCategory = $currentCategory ?? null;
$currentTag      = $currentTag ?? null;

// Pagination must stay on the current filtered view (category/tag/search/index) —
// otherwise page 2+ silently drops the category/tag/search context.
// Controllers (e.g. search()) may pass $paginationUrl explicitly; otherwise
// it's derived from the category/tag context, falling back to the plain index.
$paginationUrl = $paginationUrl ?? ($currentCategory
    ? route('blog.category', ['slug' => $currentCategory['slug']])
    : ($currentTag
        ? route('blog.tag', ['slug' => $currentTag['slug']])
        : route('blog.index')));

// Separate pinned articles on first page with no active filters
$pinnedItems  = [];
$regularItems = $items;
$showPinned   = ($page === 1) && empty($currentCategory) && empty($currentTag) && empty($filters['search']);
if ($showPinned) {
    $pinnedItems  = array_filter($items, fn($a) => !empty($a['is_pinned']));
    $regularItems = array_filter($items, fn($a) => empty($a['is_pinned']));
}
?>

<div class="container-fluid">

<?php
$blogButtons = '';
if (has_permission('blog.write')) {
    $blogButtons .= '<a href="' . e(route('blog.author.index')) . '" class="btn btn-outline-secondary btn-sm">' .
                    '<i class="fa-solid fa-newspaper me-1"></i>' . e(t('blog.author.my_articles')) . '</a>';
    $blogButtons .= '<a href="' . e(route('blog.create')) . '" class="btn btn-primary btn-sm">' .
                    '<i class="fa-solid fa-plus me-1"></i>' . e(t('blog.author.new_article')) . '</a>';
}
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('blog.title'),
    'moduleIcon'     => 'fa-solid fa-newspaper',
    'moduleSubtitle' => t('blog.public.index.subtitle'),
    'moduleButtons'  => $blogButtons,
]);
?>

<div class="row">
    <!-- Main content -->
    <div class="col-lg-8">
        <?php if ($currentCategory): ?>
            <h4 class="mb-3">
                <i class="fa-solid fa-folder-open me-2 text-primary"></i>
                <?= e($currentCategory['name']) ?>
            </h4>
            <?php if (!empty($currentCategory['description'])): ?>
                <p class="text-muted mb-4"><?= e($currentCategory['description']) ?></p>
            <?php endif; ?>
        <?php elseif ($currentTag): ?>
            <h4 class="mb-3">
                <i class="fa-solid fa-tag me-2 text-primary"></i>
                <?= e($currentTag['name']) ?>
            </h4>
        <?php elseif (!empty($filters['search'])): ?>
            <h4 class="mb-3">
                <i class="fa-solid fa-magnifying-glass me-2 text-muted"></i>
                <?= e(t('blog.public.search.results_for', ['query' => $filters['search']])) ?>
            </h4>
        <?php endif; ?>

        <!-- Search + advanced filters -->
        <form action="<?= e(route('blog.index')) ?>" method="get" class="mb-4" id="blog-home-filters">
            <div class="input-group mb-2">
                <input type="text" name="q" value="<?= e($filters['search'] ?? '') ?>"
                       class="form-control" placeholder="<?= e(t('blog.public.index.search_placeholder')) ?>"
                       hx-get="<?= e(route('blog.index')) ?>"
                       hx-trigger="keyup changed delay:400ms"
                       hx-target="#blog-articles"
                       hx-push-url="true"
                       hx-include="#blog-home-filters input, #blog-home-filters select">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-sm-4">
                    <label class="form-label small text-muted mb-1"><?= e(t('blog.public.index.from')) ?></label>
                    <input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>"
                           class="form-control form-control-sm"
                           hx-get="<?= e(route('blog.index')) ?>"
                           hx-trigger="change"
                           hx-target="#blog-articles"
                           hx-push-url="true"
                           hx-include="#blog-home-filters input, #blog-home-filters select">
                </div>
                <div class="col-sm-4">
                    <label class="form-label small text-muted mb-1"><?= e(t('blog.public.index.to')) ?></label>
                    <input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>"
                           class="form-control form-control-sm"
                           hx-get="<?= e(route('blog.index')) ?>"
                           hx-trigger="change"
                           hx-target="#blog-articles"
                           hx-push-url="true"
                           hx-include="#blog-home-filters input, #blog-home-filters select">
                </div>
                <div class="col-sm-4">
                    <label class="form-label small text-muted mb-1"><?= e(t('blog.public.index.sort')) ?></label>
                    <select name="sort" class="form-select form-select-sm"
                            hx-get="<?= e(route('blog.index')) ?>"
                            hx-trigger="change"
                            hx-target="#blog-articles"
                            hx-push-url="true"
                            hx-include="#blog-home-filters input, #blog-home-filters select">
                        <option value="recent"  <?= ($filters['sort'] ?? 'recent') === 'recent'  ? 'selected' : '' ?>><?= e(t('blog.public.sort.recent')) ?></option>
                        <option value="popular" <?= ($filters['sort'] ?? '')      === 'popular' ? 'selected' : '' ?>><?= e(t('blog.public.sort.popular')) ?></option>
                    </select>
                </div>
            </div>
        </form>

        <!-- Pinned articles section -->
        <?php if (!empty($pinnedItems)): ?>
        <div class="bl-pinned-section mb-4">
            <h6 class="text-uppercase text-muted small fw-bold mb-3">
                <i class="fa-solid fa-thumbtack me-1"></i> <?= e(t('blog.public.index.pinned_section')) ?>
            </h6>
            <?php foreach ($pinnedItems as $article): ?>
                <?php $view->include('Blog/Views/public/partials/article_card', compact('article')); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Article list -->
        <div id="blog-articles">
            <?php if ($showPinned && !empty($pinnedItems)): ?>
                <?php
                // Pass only regular items to the list partial
                $listItems = array_values($regularItems);
                $view->include('Blog/Views/public/partials/article_list', [
                    'items' => $listItems,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => $total_pages,
                    'paginationUrl' => $paginationUrl,
                ]);
                ?>
            <?php else: ?>
                <?php $view->include('Blog/Views/public/partials/article_list', array_merge(compact('items', 'total', 'page', 'per_page', 'total_pages'), ['paginationUrl' => $paginationUrl])); ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Categories widget -->
        <div class="card mb-4">
            <div class="card-header"><i class="fa-solid fa-folder me-2"></i> <?= e(t('blog.public.index.categories')) ?></div>
            <div class="list-group list-group-flush">
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= e(route('blog.category', ['slug' => $cat['slug']])) ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?= ($currentCategory && (int)$currentCategory['id'] === (int)$cat['id']) ? ' active' : '' ?>">
                        <?= e($cat['name']) ?>
                        <span class="badge bg-primary rounded-pill"><?= (int) $cat['article_count'] ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                    <div class="list-group-item text-muted small"><?= e(t('blog.public.index.no_categories')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tags widget -->
        <?php if (!empty($tags)): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="fa-solid fa-tags me-2"></i> <?= e(t('blog.public.index.tags')) ?></div>
            <div class="card-body">
                <?php foreach ($tags as $t): ?>
                    <?php if ((int) $t['article_count'] > 0): ?>
                    <a href="<?= e(route('blog.tag', ['slug' => $t['slug']])) ?>"
                       class="badge bg-secondary text-decoration-none me-1 mb-1<?= ($currentTag && (int)$currentTag['id'] === (int)$t['id']) ? ' bg-primary' : '' ?>">
                        #<?= e($t['name']) ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div><!-- /.container-fluid -->

<?php $view->end(); ?>
