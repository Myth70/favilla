<?php
/**
 * Partial: single comment with replies.
 * Variables: $comment, $article, $canComment
 */
?>

<div class="bl-comment mb-3" id="comment-<?= (int) $comment['id'] ?>">
    <div class="d-flex gap-3">
        <div class="bl-comment-avatar">
            <?php $commentAvatar = \App\Modules\Auth\Helpers\AvatarHelper::url($comment['user_avatar'] ?? null); ?>
            <?php if ($commentAvatar): ?>
                <img src="<?= e($commentAvatar) ?>"
                     class="rounded-circle" width="36" height="36" alt="">
            <?php else: ?>
                <div class="bl-comment-initials rounded-circle">
                    <?= e(mb_strtoupper(mb_substr($comment['user_name'] ?? '?', 0, 1))) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex-grow-1">
            <div class="bl-comment-header">
                <strong><?= e($comment['user_name'] ?? t('blog.show.deleted_user')) ?></strong>
                <small class="text-muted ms-2"><?= e(format_date($comment['created_at'], 'relative')) ?></small>
            </div>
            <div class="bl-comment-body mt-1">
                <?= nl2br(e($comment['body'])) ?>
            </div>

            <?php if ($canComment && empty($comment['parent_id'])): ?>
            <div class="mt-2">
                <button class="btn btn-sm btn-link text-muted p-0 bl-reply-toggle" data-comment-id="<?= (int) $comment['id'] ?>">
                    <i class="fa-solid fa-reply me-1"></i> <?= e(t('blog.show.reply')) ?>
                </button>
                <form method="post"
                      action="<?= e(route('blog.comments.reply', ['slug' => $article['slug'], 'id' => $comment['id']])) ?>"
                      class="mt-2 d-none bl-reply-form" id="reply-form-<?= (int) $comment['id'] ?>">
                    <?= csrf_field() ?>
                    <div class="input-group input-group-sm">
                        <input type="text" name="body" class="form-control" placeholder="<?= e(t('blog.show.write_reply_placeholder')) ?>" maxlength="2000" required>
                        <button class="btn btn-primary" type="submit">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Replies -->
            <?php if (!empty($comment['replies'])): ?>
            <div class="bl-replies ms-3 mt-2 ps-3 border-start">
                <?php foreach ($comment['replies'] as $reply): ?>
                <div class="bl-comment mb-2" id="comment-<?= (int) $reply['id'] ?>">
                    <div class="d-flex gap-2">
                        <div class="bl-comment-avatar">
                            <?php $replyAvatar = \App\Modules\Auth\Helpers\AvatarHelper::url($reply['user_avatar'] ?? null); ?>
                            <?php if ($replyAvatar): ?>
                                <img src="<?= e($replyAvatar) ?>"
                                     class="rounded-circle" width="28" height="28" alt="">
                            <?php else: ?>
                                <div class="bl-comment-initials bl-comment-initials-sm rounded-circle">
                                    <?= e(mb_strtoupper(mb_substr($reply['user_name'] ?? '?', 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="bl-comment-header">
                                <strong class="small"><?= e($reply['user_name'] ?? t('blog.show.deleted_user')) ?></strong>
                                <small class="text-muted ms-2"><?= e(format_date($reply['created_at'], 'relative')) ?></small>
                            </div>
                            <div class="bl-comment-body mt-1 small">
                                <?= nl2br(e($reply['body'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
