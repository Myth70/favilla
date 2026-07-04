<?php
/**
 * HTMX partial — bookmark button.
 * Variabili: $article, $isBookmarked
 */
?>
<button type="button"
        class="btn btn-sm <?= $isBookmarked ? 'btn-warning' : 'btn-outline-secondary' ?> bl-bookmark-btn"
        id="bl-bookmark-btn-<?= (int) $article['id'] ?>"
        hx-post="<?= e(route('blog.bookmark', ['slug' => $article['slug']])) ?>"
        hx-target="#bl-bookmark-btn-<?= (int) $article['id'] ?>"
        hx-swap="outerHTML"
        title="<?= e($isBookmarked ? t('blog.public.bookmark.remove') : t('blog.public.bookmark.add')) ?>">
    <i class="fa-<?= $isBookmarked ? 'solid' : 'regular' ?> fa-bookmark"></i>
</button>
