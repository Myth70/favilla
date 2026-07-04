<?php
/**
 * HTMX partial — like button.
 * Variabili: $article, $isLiked, $count
 */
?>
<button type="button"
        class="btn btn-sm <?= $isLiked ? 'btn-danger' : 'btn-outline-danger' ?> bl-like-btn"
        id="bl-like-btn-<?= (int) $article['id'] ?>"
        hx-post="<?= e(route('blog.like', ['slug' => $article['slug']])) ?>"
        hx-target="#bl-like-btn-<?= (int) $article['id'] ?>"
        hx-swap="outerHTML"
        title="<?= e($isLiked ? t('blog.public.like.remove') : t('blog.public.like.add')) ?>">
    <i class="fa-<?= $isLiked ? 'solid' : 'regular' ?> fa-heart me-1"></i><?= (int) $count ?>
</button>
