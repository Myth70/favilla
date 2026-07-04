<?php
/**
 * Public author page — lista articoli pubblicati di uno specifico autore.
 * Variables: $view, $author, $items, $total, $page, $per_page, $total_pages,
 *            $filters, $categories, $tags
 */
$view->layout($layout ?? 'main');
$view->pushStyle('css/blog.css');
$view->start('content');

$authorAvatar = !empty($author['avatar_path'])
    ? \App\Modules\Auth\Helpers\AvatarHelper::url($author['avatar_path'])
    : null;
$authorInitial = mb_strtoupper(mb_substr($author['name'] ?? '?', 0, 1));
?>

<div class="container-fluid">

<?php
$view->include('partials/pf-hero-module', [
    'moduleName'     => $author['name'] ?? t('blog.public.author.fallback'),
    'moduleIcon'     => 'fa-solid fa-user-pen',
    'moduleSubtitle' => t('blog.public.author.subtitle', ['name' => $author['name'] ?? '']),
]);
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Author card -->
        <div class="card mb-4">
            <div class="card-body d-flex align-items-center gap-3">
                <?php if ($authorAvatar): ?>
                    <img src="<?= e($authorAvatar) ?>" class="rounded-circle" width="64" height="64" alt="">
                <?php else: ?>
                    <div class="bl-avatar-lg rounded-circle"><?= e($authorInitial) ?></div>
                <?php endif; ?>
                <div>
                    <h5 class="mb-1"><?= e($author['name'] ?? t('blog.show.anonymous')) ?></h5>
                    <small class="text-muted">
                        <?= e(tc('blog.public.author.article_count', (int) $total)) ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form action="<?= e(route('blog.author', ['id' => $author['id']])) ?>" method="get" class="row g-2 mb-3" id="blog-author-filters">
            <div class="col-md-7">
                <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       class="form-control" placeholder="<?= e(t('blog.public.author.search_placeholder')) ?>"
                       hx-get="<?= e(route('blog.author', ['id' => $author['id']])) ?>"
                       hx-trigger="keyup changed delay:400ms"
                       hx-include="#blog-author-filters input, #blog-author-filters select"
                       hx-target="#blog-author-articles"
                       hx-push-url="true">
            </div>
            <div class="col-md-3">
                <select name="sort" class="form-select"
                        hx-get="<?= e(route('blog.author', ['id' => $author['id']])) ?>"
                        hx-include="#blog-author-filters input, #blog-author-filters select"
                        hx-target="#blog-author-articles"
                        hx-push-url="true">
                    <option value="recent"  <?= ($filters['sort'] ?? 'recent') === 'recent'  ? 'selected' : '' ?>><?= e(t('blog.public.sort.recent')) ?></option>
                    <option value="popular" <?= ($filters['sort'] ?? '')      === 'popular' ? 'selected' : '' ?>><?= e(t('blog.public.sort.popular')) ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="<?= e(route('blog.author', ['id' => $author['id']])) ?>" class="btn btn-outline-secondary w-100"><?= e(t('blog.public.author.reset')) ?></a>
            </div>
        </form>

        <div id="blog-author-articles">
            <?php $view->include('Blog/Views/public/partials/article_list', array_merge(
                compact('items', 'total', 'page', 'per_page', 'total_pages'),
                ['paginationUrl' => route('blog.author', ['id' => $author['id']])]
            )); ?>
        </div>
    </div>

    <div class="col-lg-4">
        <a href="<?= e(route('blog.index')) ?>" class="btn btn-outline-secondary w-100 mb-3">
            <i class="fa-solid fa-arrow-left me-1"></i> <?= e(t('blog.public.author.all_articles')) ?>
        </a>

        <div class="card mb-4">
            <div class="card-header"><i class="fa-solid fa-folder me-2"></i> <?= e(t('blog.public.index.categories')) ?></div>
            <div class="list-group list-group-flush">
                <?php foreach (($categories ?? []) as $cat): ?>
                    <a href="<?= e(route('blog.category', ['slug' => $cat['slug']])) ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <?= e($cat['name']) ?>
                        <span class="badge bg-primary rounded-pill"><?= (int) $cat['article_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</div>

<?php $view->end(); ?>
