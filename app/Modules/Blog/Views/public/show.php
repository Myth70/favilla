<?php
/**
 * Blog single article view.
 * Variables: $view, $article, $tags, $comments, $canComment, $isLogged, $layout
 */
$view->layout($layout ?? 'main');
$view->pushStyle('css/blog.css');
$view->pushStyle('css/quill.snow.css');
$view->pushScript('js/blog.js');
$view->start('content');

$coverUrl = cover_url($article['cover_image'] ?? null, 'blog');

$authorAvatar = $article['author_avatar']
    ? \App\Modules\Auth\Helpers\AvatarHelper::url($article['author_avatar'])
    : null;

$authorInitial = mb_strtoupper(mb_substr($article['author_name'] ?? '?', 0, 1));
?>

<!-- Reading progress bar -->
<div class="bl-progress-bar" id="bl-reading-progress"></div>

<div class="container-fluid py-4">
<div class="row">
    <div class="col-12">
        <article class="bl-article">
            <!-- Hero cover image -->
            <?php if ($coverUrl): ?>
            <figure class="bl-cover-hero mb-0">
                <img src="<?= e($coverUrl) ?>" class="bl-cover-hero-img" alt="<?= e($article['title']) ?>">
                <div class="bl-cover-overlay"></div>
            </figure>
            <?php endif; ?>

            <div class="bl-article-paper <?= $coverUrl ? 'bl-has-cover' : '' ?>">
            <!-- Header -->
            <header class="bl-article-header mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if (!empty($article['category_name'])): ?>
                            <a href="<?= e(route('blog.category', ['slug' => $article['category_slug']])) ?>"
                               class="bl-category-pill text-decoration-none">
                                <i class="fa-solid fa-folder-open me-1"></i><?= e($article['category_name']) ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($article['is_pinned'])): ?>
                            <span class="badge bl-pinned-badge"><i class="fa-solid fa-thumbtack me-1"></i><?= e(t('blog.public.index.pinned_section')) ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= e(route('blog.pdf', ['slug' => $article['slug']])) ?>"
                       class="btn btn-outline-secondary btn-sm" target="_blank" title="<?= e(t('blog.show.download_pdf')) ?>">
                        <i class="fa-solid fa-file-pdf me-1"></i> PDF
                    </a>
                </div>

                <h1 class="bl-article-title"><?= e($article['title']) ?></h1>

                <?php if (!empty($article['excerpt'])): ?>
                    <p class="bl-article-excerpt"><?= e($article['excerpt']) ?></p>
                <?php endif; ?>

                <div class="bl-article-meta d-flex align-items-center flex-wrap gap-3 mt-3">
                    <div class="bl-meta-author d-flex align-items-center gap-2">
                        <?php if ($authorAvatar): ?>
                            <img src="<?= e($authorAvatar) ?>" class="rounded-circle" width="36" height="36" alt="">
                        <?php else: ?>
                            <div class="bl-avatar-sm rounded-circle"><?= e($authorInitial) ?></div>
                        <?php endif; ?>
                        <div>
                            <?php if (!empty($article['created_by'])): ?>
                                <a href="<?= e(route('blog.author', ['id' => (int) $article['created_by']])) ?>"
                                   class="fw-semibold d-block text-decoration-none text-body">
                                    <?= e($article['author_name'] ?? t('blog.show.anonymous')) ?>
                                </a>
                            <?php else: ?>
                                <span class="fw-semibold d-block"><?= e($article['author_name'] ?? t('blog.show.anonymous')) ?></span>
                            <?php endif; ?>
                            <small class="text-muted"><?= e(format_date($article['published_at'], 'long')) ?></small>
                        </div>
                    </div>
                    <div class="bl-meta-details d-flex align-items-center gap-3 text-muted flex-wrap ms-auto">
                        <?php if (!empty($article['reading_time'])): ?>
                        <span class="bl-meta-chip" title="<?= e(t('blog.show.reading_time')) ?>">
                            <i class="fa-regular fa-clock me-1"></i><?= (int) $article['reading_time'] ?> min
                        </span>
                        <?php endif; ?>
                        <span class="bl-meta-chip" title="<?= e(t('blog.show.views')) ?>">
                            <i class="fa-regular fa-eye me-1"></i><?= number_format((int) ($article['view_count'] ?? 0)) ?>
                        </span>
                        <span class="bl-meta-chip" title="<?= e(t('blog.show.likes')) ?>">
                            <i class="fa-regular fa-heart me-1"></i><?= (int) ($likesCount ?? 0) ?>
                        </span>
                        <a href="#comments" class="bl-meta-chip text-decoration-none" title="<?= e(t('blog.show.comments')) ?>">
                            <i class="fa-regular fa-comment me-1"></i><?= count($comments) ?>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Like & Bookmark actions -->
            <div class="bl-actions-bar d-flex align-items-center gap-2">
                <?php $view->include('Blog/Views/public/partials/like_button', [
                    'article'  => $article,
                    'isLiked'  => $isLiked  ?? false,
                    'count'    => $likesCount ?? 0,
                ]); ?>
                <?php $view->include('Blog/Views/public/partials/bookmark_button', [
                    'article'      => $article,
                    'isBookmarked' => $isBookmarked ?? false,
                ]); ?>
                <?php if (!empty($isBookmarked)): ?>
                <small class="text-muted ms-1">
                    <i class="fa-solid fa-circle-check text-success me-1"></i><?= e(t('blog.show.saved')) ?>
                </small>
                <?php endif; ?>
            </div>

            <!-- Table of contents (populated via JS for articles with 3+ headings) -->
            <nav id="bl-toc" class="bl-toc d-none mb-4"></nav>

            <!-- Content -->
            <div class="bl-article-content ql-snow">
                <?php /* Difesa in profondità: il contenuto è sanitizzato a
                         write-time, ma qualunque percorso di scrittura che
                         salti il service non deve diventare XSS persistente. */ ?>
                <div class="ql-editor"><?= \App\Modules\Blog\Services\BlogContentSanitizer::sanitize((string) $article['content']) ?></div>
            </div>

            <!-- Tags -->
            <?php if (!empty($tags)): ?>
            <div class="bl-article-tags mt-4 pt-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <i class="fa-solid fa-tags text-muted me-1"></i>
                    <?php foreach ($tags as $tag): ?>
                        <a href="<?= e(route('blog.tag', ['slug' => $tag['slug']])) ?>"
                           class="bl-tag-pill text-decoration-none">
                            #<?= e($tag['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Author card -->
            <div class="bl-author-card mt-4">
                <div class="d-flex align-items-center gap-3">
                    <?php if ($authorAvatar): ?>
                        <img src="<?= e($authorAvatar) ?>" class="rounded-circle bl-author-avatar" width="56" height="56" alt="">
                    <?php else: ?>
                        <div class="bl-avatar-lg rounded-circle"><?= e($authorInitial) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="text-muted small text-uppercase ls-wide mb-1"><?= e(t('blog.show.written_by')) ?></div>
                        <?php if (!empty($article['created_by'])): ?>
                            <a href="<?= e(route('blog.author', ['id' => (int) $article['created_by']])) ?>"
                               class="fw-semibold fs-6 text-decoration-none text-body">
                                <?= e($article['author_name'] ?? t('blog.show.anonymous')) ?>
                            </a>
                        <?php else: ?>
                            <div class="fw-semibold fs-6"><?= e($article['author_name'] ?? t('blog.show.anonymous')) ?></div>
                        <?php endif; ?>
                        <small class="text-muted d-block"><?= e(format_date($article['published_at'], 'long')) ?></small>
                    </div>
                </div>
            </div>
            </div><!-- /.bl-article-paper -->
        </article>

        <!-- Comments section -->
        <section id="comments" class="bl-comments bl-article-paper mt-4 pt-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0">
                    <i class="fa-regular fa-comments me-2"></i>
                    <?= e(t('blog.show.comments')) ?>
                    <?php if (count($comments) > 0): ?>
                        <span class="bl-comment-count"><?= count($comments) ?></span>
                    <?php endif; ?>
                </h4>
            </div>

            <?php if ($canComment): ?>
            <div class="bl-comment-form mb-4">
                <div class="d-flex gap-3">
                    <?php
                    $currentUser = auth();
                    $myAvatar = !empty($currentUser['avatar'])
                        ? \App\Modules\Auth\Helpers\AvatarHelper::url($currentUser['avatar'])
                        : null;
                    $myInitial = mb_strtoupper(mb_substr($currentUser['name'] ?? '?', 0, 1));
                    ?>
                    <?php if ($myAvatar): ?>
                        <img src="<?= e($myAvatar) ?>" class="rounded-circle flex-shrink-0" width="40" height="40" alt="">
                    <?php else: ?>
                        <div class="bl-avatar-sm rounded-circle flex-shrink-0"><?= e($myInitial) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(route('blog.comments.store', ['slug' => $article['slug']])) ?>" class="flex-grow-1">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <textarea name="body" rows="3" class="form-control"
                                      placeholder="<?= e(t('blog.show.write_comment_placeholder')) ?>" maxlength="2000" required></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-paper-plane me-1"></i> <?= e(t('blog.show.publish')) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Comment threads -->
            <?php foreach ($comments as $comment): ?>
                <?php $view->include('Blog/Views/public/partials/comment_thread', compact('comment', 'article', 'canComment')); ?>
            <?php endforeach; ?>

            <?php if (empty($comments)): ?>
                <div class="text-center py-4">
                    <i class="fa-regular fa-comment-dots fa-2x text-muted mb-2 d-block"></i>
                    <p class="text-muted mb-0"><?= e(t('blog.show.no_comments_yet')) ?></p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Related articles -->
        <?php if (!empty($related ?? [])): ?>
        <section class="bl-related mt-4 pt-3">
            <h5 class="mb-3"><i class="fa-regular fa-newspaper me-2"></i> <?= e(t('blog.show.related_articles')) ?></h5>
            <div class="row g-3">
                <?php foreach ($related as $rel): ?>
                <div class="col-md-6 col-lg-3">
                    <a href="<?= e(route('blog.show', ['slug' => $rel['slug']])) ?>"
                       class="text-decoration-none text-body">
                        <div class="card h-100 bl-related-card">
                            <?php $relCover = cover_url($rel['cover_image'] ?? null, 'blog'); ?>
                            <?php if ($relCover): ?>
                                <img src="<?= e($relCover) ?>" class="card-img-top bl-related-cover" alt="">
                            <?php endif; ?>
                            <div class="card-body">
                                <?php if (!empty($rel['category_name'])): ?>
                                    <small class="text-primary fw-semibold d-block mb-1">
                                        <?= e($rel['category_name']) ?>
                                    </small>
                                <?php endif; ?>
                                <h6 class="card-title mb-2"><?= e($rel['title']) ?></h6>
                                <small class="text-muted">
                                    <i class="fa-regular fa-clock me-1"></i><?= (int) ($rel['reading_time'] ?? 1) ?> min
                                    <span class="ms-2">
                                        <i class="fa-regular fa-eye me-1"></i><?= number_format((int) ($rel['view_count'] ?? 0)) ?>
                                    </span>
                                </small>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Back link -->
        <div class="mt-4 pt-3 border-top">
            <a href="<?= e(route('blog.index')) ?>" class="text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i> <?= e(t('blog.show.back_to_blog')) ?>
            </a>
        </div>
    </div>
</div>
</div><!-- /.container-fluid -->

<?php $view->end(); ?>
