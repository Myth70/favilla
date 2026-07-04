<?php
/**
 * Partial: single article card.
 * Variables: $article
 */
$coverUrl = cover_url($article['cover_image'] ?? null, 'blog');
$authorAvatar = !empty($article['author_avatar'])
    ? \App\Modules\Auth\Helpers\AvatarHelper::url($article['author_avatar'])
    : null;
$authorInitial = mb_strtoupper(mb_substr($article['author_name'] ?? '?', 0, 1));
?>

<div class="card bl-card mb-4">
    <?php if ($coverUrl): ?>
    <a href="<?= e(route('blog.show', ['slug' => $article['slug']])) ?>">
        <img src="<?= e($coverUrl) ?>" class="card-img-top bl-card-cover" alt="<?= e($article['title']) ?>">
    </a>
    <?php endif; ?>
    <div class="card-body">
        <div class="bl-card-meta mb-2 d-flex align-items-center flex-wrap gap-1">
            <?php if (!empty($article['is_pinned'])): ?>
                <span class="badge bl-pinned-badge me-1"><i class="fa-solid fa-thumbtack"></i></span>
            <?php endif; ?>
            <?php if (!empty($article['category_name'])): ?>
                <a href="<?= e(route('blog.category', ['slug' => $article['category_slug']])) ?>"
                   class="badge bg-primary text-decoration-none"><?= e($article['category_name']) ?></a>
            <?php endif; ?>
            <small class="text-muted ms-auto">
                <i class="fa-regular fa-calendar me-1"></i>
                <?= e(format_date($article['published_at'], 'compact')) ?>
            </small>
        </div>

        <h5 class="card-title">
            <a href="<?= e(route('blog.show', ['slug' => $article['slug']])) ?>" class="text-decoration-none text-body">
                <?= e($article['title']) ?>
            </a>
        </h5>

        <?php if (!empty($article['excerpt'])): ?>
            <p class="card-text text-muted small"><?= e($article['excerpt']) ?></p>
        <?php endif; ?>

        <!-- Author row -->
        <div class="d-flex align-items-center gap-2 mt-2 mb-3">
            <?php if ($authorAvatar): ?>
                <img src="<?= e($authorAvatar) ?>" class="rounded-circle" width="24" height="24" alt="">
            <?php else: ?>
                <div class="bl-avatar-xs rounded-circle"><?= e($authorInitial) ?></div>
            <?php endif; ?>
            <?php if (!empty($article['created_by'])): ?>
                <a href="<?= e(route('blog.author', ['id' => (int) $article['created_by']])) ?>"
                   class="small text-decoration-none text-muted fw-medium">
                    <?= e($article['author_name'] ?? t('blog.show.anonymous')) ?>
                </a>
            <?php else: ?>
                <small class="text-muted fw-medium"><?= e($article['author_name'] ?? t('blog.show.anonymous')) ?></small>
            <?php endif; ?>
        </div>

        <!-- Stats bar -->
        <div class="bl-card-stats d-flex align-items-center gap-3 pt-2 border-top">
            <?php if (!empty($article['reading_time'])): ?>
                <small class="bl-reading-time">
                    <i class="fa-regular fa-clock me-1"></i><?= (int) $article['reading_time'] ?> min
                </small>
            <?php endif; ?>
            <small class="bl-stat-item" title="<?= e(t('blog.show.views')) ?>">
                <i class="fa-regular fa-eye me-1"></i><?= number_format((int) ($article['view_count'] ?? 0)) ?>
            </small>
            <small class="bl-stat-item" title="<?= e(t('blog.show.likes')) ?>">
                <i class="fa-regular fa-heart me-1"></i><?= (int) ($article['likes_count'] ?? 0) ?>
            </small>
            <small class="bl-stat-item" title="<?= e(t('blog.show.comments')) ?>">
                <i class="fa-regular fa-comment me-1"></i><?= (int) ($article['comment_count'] ?? 0) ?>
            </small>
            <a href="<?= e(route('blog.show', ['slug' => $article['slug']])) ?>" class="btn btn-sm btn-outline-primary ms-auto">
                <?= e(t('blog.public.card.read_more')) ?> <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>
