<?php
/**
 * Blog admin: comments management.
 * Variables: $view, $items, $total, $page, $per_page, $total_pages, $filters
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');
?>

<div class="container-fluid">
<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'  => 'fa-solid fa-comments',
    'adminTitle' => t('blog.show.comments'),
]); ?>

<div class="card mb-4">
    <div class="card-body">
        <form hx-get="<?= e(route('blog.admin.comments')) ?>"
              hx-target="#admin-comment-table"
              hx-include="#blog-comments-filters input, #blog-comments-filters select"
              hx-push-url="true"
              id="blog-comments-filters"
              class="row g-2 align-items-end">
            <div class="col-md-6">
                <input type="text" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       class="form-control" placeholder="<?= e(t('blog.admin.comments.search_placeholder')) ?>"
                       hx-trigger="keyup changed delay:400ms"
                       hx-get="<?= e(route('blog.admin.comments')) ?>"
                       hx-include="#blog-comments-filters input, #blog-comments-filters select"
                       hx-target="#admin-comment-table"
                       hx-push-url="true">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select"
                        hx-get="<?= e(route('blog.admin.comments')) ?>"
                        hx-include="#blog-comments-filters input, #blog-comments-filters select"
                        hx-target="#admin-comment-table"
                        hx-push-url="true">
                    <option value=""><?= e(t('blog.admin.articles.status_all')) ?></option>
                    <option value="pending"  <?= ($filters['status'] ?? '') === 'pending'  ? 'selected' : '' ?>><?= e(t('blog.admin.comments.status_pending')) ?></option>
                    <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>><?= e(t('blog.admin.comments.status_approved')) ?></option>
                    <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>><?= e(t('blog.admin.comments.status_rejected')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <a href="<?= e(route('blog.admin.comments')) ?>" class="btn btn-outline-secondary w-100"><?= e(t('blog.admin.articles.reset_filters')) ?></a>
            </div>
        </form>
    </div>
</div>

<div id="admin-comment-table">
    <?php $view->include('Blog/Views/admin/partials/comment_table', compact('items', 'total', 'page', 'per_page', 'total_pages', 'filters')); ?>
</div>
</div>

<?php $view->end(); ?>
