<?php
/**
 * Articoli salvati dall'utente (bookmark).
 * Variabili: $view, $items, $total, $page, $per_page, $total_pages
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');
?>

<div class="container-fluid">
<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="mb-0"><i class="fa-solid fa-bookmark me-2 text-warning"></i><?= e(t('blog.saved.title')) ?></h5>
    <a href="<?= e(route('blog.index')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('blog.show.back_to_blog')) ?>
    </a>
</div>

<?php if (empty($items)): ?>
<div class="text-center py-5 text-muted">
    <i class="fa-regular fa-bookmark fa-3x mb-3 d-block"></i>
    <p class="mb-0"><?= e(t('blog.saved.empty')) ?></p>
    <a href="<?= e(route('blog.index')) ?>" class="btn btn-primary btn-sm mt-3">
        <?= e(t('blog.saved.browse_cta')) ?>
    </a>
</div>
<?php else: ?>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4" id="saved-articles">
    <?php foreach ($items as $article): ?>
    <?php $view->include('Blog/Views/public/partials/article_card', compact('article')); ?>
    <?php endforeach; ?>
</div>

<?php $view->include('partials/pagination', [
    'page'        => $page,
    'total_pages' => $total_pages,
    'total'       => $total,
    'routeName'   => 'blog.saved',
    'hxTarget'    => '#saved-articles',
    'filters'     => [],
    'label'       => t('blog.saved.pagination_label'),
]); ?>
<?php endif; ?>

</div>

<?php $view->end(); ?>
