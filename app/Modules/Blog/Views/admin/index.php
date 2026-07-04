<?php
/**
 * Blog admin dashboard.
 * Variables: $view, $articleCounts, $commentCount, $categoryCount, $tagCount
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');
?>

<div class="container-fluid">
<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'  => 'fa-solid fa-newspaper',
    'adminTitle' => t('blog.admin.title'),
]); ?>

<!-- Stats cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success"><?= (int) ($articleCounts['published'] ?? 0) ?></div>
                <small class="text-muted"><?= e(t('blog.status.published')) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-2 fw-bold text-info"><?= (int) ($articleCounts['scheduled'] ?? 0) ?></div>
                <small class="text-muted"><?= e(t('blog.status.scheduled')) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-2 fw-bold text-secondary"><?= (int) ($articleCounts['draft'] ?? 0) ?></div>
                <small class="text-muted"><?= e(t('blog.status.draft')) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary"><?= (int) $commentCount ?></div>
                <small class="text-muted"><?= e(t('blog.show.comments')) ?></small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($pinnedCount)): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="fa-solid fa-thumbtack"></i>
    <span><?= e(tc('blog.admin.pinned_count', (int) $pinnedCount)) ?></span>
</div>
<?php endif; ?>

<!-- Quick links -->
<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= e(route('blog.admin.articles')) ?>" class="card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="fa-solid fa-newspaper fa-2x text-primary"></i>
                <div>
                    <h6 class="mb-0"><?= e(t('blog.admin.quick_links.articles_title')) ?></h6>
                    <small class="text-muted"><?= e(t('blog.admin.quick_links.articles_subtitle')) ?></small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(route('blog.admin.comments')) ?><?= !empty($commentStatusCounts['pending']) ? '?status=pending' : '' ?>"
           class="card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="fa-solid fa-comments fa-2x text-success"></i>
                <div>
                    <h6 class="mb-0"><?= e(t('blog.show.comments')) ?></h6>
                    <small class="text-muted">
                        <?= e(t('blog.admin.quick_links.comments_subtitle')) ?>
                        <?php if (!empty($commentStatusCounts['pending'])): ?>
                            <span class="badge bg-warning text-dark ms-1">
                                <?= e(tc('blog.admin.quick_links.pending_count', (int) $commentStatusCounts['pending'])) ?>
                            </span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(route('blog.admin.categories')) ?>" class="card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="fa-solid fa-folder fa-2x text-warning"></i>
                <div>
                    <h6 class="mb-0"><?= e(t('blog.admin.categories.breadcrumb')) ?></h6>
                    <small class="text-muted"><?= e(t('blog.admin.quick_links.categories_subtitle')) ?></small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(route('blog.admin.tags')) ?>" class="card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="fa-solid fa-tags fa-2x text-info"></i>
                <div>
                    <h6 class="mb-0"><?= e(t('blog.admin.tags.breadcrumb')) ?></h6>
                    <small class="text-muted"><?= e(tc('blog.admin.quick_links.tag_count', (int) $tagCount)) ?></small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= e(route('blog.admin.blacklist')) ?>" class="card text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="fa-solid fa-user-slash fa-2x text-danger"></i>
                <div>
                    <h6 class="mb-0"><?= e(t('blog.admin.blacklist.breadcrumb')) ?></h6>
                    <small class="text-muted"><?= e(t('blog.admin.quick_links.blacklist_subtitle')) ?></small>
                </div>
            </div>
        </a>
    </div>
</div>

</div>

<?php $view->end(); ?>
