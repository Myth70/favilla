<?php
/**
 * Blog admin: trash (soft-deleted articles).
 * Variables: $view, $items, $total, $page, $per_page, $total_pages
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');
?>

<div class="container-fluid">
<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'    => 'fa-solid fa-trash-can',
    'adminTitle'   => t('blog.admin.trash.breadcrumb'),
    'adminButtons' => '<a href="' . e(route('blog.admin.articles')) . '" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>' . e(t('blog.admin.trash.back_to_articles')) . '</a>',
]); ?>

<div id="admin-trash-table">
    <?php $view->include('Blog/Views/admin/partials/trash_table', compact('items', 'total', 'page', 'per_page', 'total_pages')); ?>
</div>
</div>

<?php $view->end(); ?>
