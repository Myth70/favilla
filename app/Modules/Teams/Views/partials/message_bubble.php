<?php
$isMine    = (int) ($msg['user_id'] ?? 0) === $currentUserId;
$isSystem  = ($msg['type'] ?? 'text') === 'system';
$isDeleted = !empty($msg['deleted_at']);
$isEdited  = !empty($msg['edited_at']);

// Determina se il messaggio è stato letto da altri
$isRead = false;
if (!empty($othersMaxReadAt)) {
    $isRead = $msg['created_at'] <= $othersMaxReadAt;
}

// Modifica e eliminazione consentite solo se nessun altro membro ha letto il messaggio
$canEdit = $isMine && !$isDeleted && !$isRead;
$canDelete = $isMine && !$isDeleted && !$isRead;

$avatarUrl = \App\Modules\Auth\Helpers\AvatarHelper::url($msg['avatar_path'] ?? null);
$initials  = \App\Modules\Auth\Helpers\AvatarHelper::initials($msg['user_name'] ?? 'U');
$time      = format_date($msg['created_at'], 'time');

/**
 * Renderizza il body di un messaggio applicando markdown leggero
 * (bold/italic/code/quote/link) + highlight @mention.
 */
$renderMessageBody = static function (string $body): string {
    return \App\Modules\Teams\Support\MarkdownRenderer::render($body);
};
?>

<?php if ($isSystem): ?>
<div class="tm-msg-system">
    <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i><?= e($msg['body']) ?></small>
</div>
<?php else: ?>
<div class="tm-msg <?= $isMine ? 'tm-msg-mine' : 'tm-msg-other' ?><?= !empty($isConsecutive) ? ' tm-msg-grouped' : '' ?>"
     id="tm-msg-<?= (int) $msg['id'] ?>"
     data-message-id="<?= (int) $msg['id'] ?>"
     data-user-id="<?= (int) ($msg['user_id'] ?? 0) ?>"
     data-timestamp="<?= e($msg['created_at']) ?>"
     <?php if (!empty($oob)): ?>hx-swap-oob="outerHTML"<?php endif; ?>>
    <?php if (!$isMine): ?>
    <div class="tm-msg-avatar">
        <?php if ($avatarUrl): ?>
            <img src="<?= e($avatarUrl) ?>" alt="" class="tm-avatar-img-sm">
        <?php else: ?>
            <span class="tm-avatar-initials-sm"
                  style="--tm-avatar-hue: <?= (int) (crc32((string) ($msg['user_name'] ?? 'U')) % 360) ?>"><?= e($initials) ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="tm-msg-content">
        <?php if (!$isMine): ?>
            <div class="tm-msg-sender"><?= e($msg['user_name'] ?? t('teams.exception.default_user_name')) ?></div>
        <?php endif; ?>
        <?php if (!empty($msg['reply_to_id'])): ?>
            <?php
            $replyParentDeleted = !empty($msg['reply_parent_deleted']);
            $replyParentBody    = $replyParentDeleted
                ? t('teams.message.deleted_placeholder')
                : (string) ($msg['reply_parent_body'] ?? '');
            $replyParentName    = (string) ($msg['reply_parent_user_name'] ?? t('teams.exception.default_user_name'));
            $replyParentPreview = mb_strimwidth($replyParentBody, 0, 80, '…');
            ?>
            <a class="tm-msg-reply-quote tm-scroll-to-msg"
               href="#tm-msg-<?= (int) $msg['reply_to_id'] ?>"
               data-target-id="tm-msg-<?= (int) $msg['reply_to_id'] ?>">
                <span class="tm-msg-reply-quote-name"><?= e($replyParentName) ?></span>
                <span class="tm-msg-reply-quote-preview <?= $replyParentDeleted ? 'tm-msg-deleted' : '' ?>"><?= e($replyParentPreview) ?></span>
            </a>
        <?php endif; ?>
        <?php
        $canReply       = !$isDeleted && !$isSystem;
$canPinHere     = !empty($canPin) && !$isDeleted && !$isSystem;
$isPinned       = !empty($msg['pinned_at']);
$canReact       = !$isDeleted && !$isSystem;
$canViewHistory = $isEdited && !$isDeleted;
$canViewReaders = $isMine && (($readByCount ?? 0) > 0);
$showDropdown   = $canReply || $canPinHere || $canEdit || $canDelete
                  || $canReact || $canViewHistory || $canViewReaders;
?>
        <div class="tm-msg-bubble">
            <?php if ($isDeleted): ?>
                <em class="tm-msg-deleted">
                    <i class="fa-solid fa-ban me-1"></i><?= e(t('teams.message.deleted_placeholder')) ?>
                </em>
            <?php else: ?>
                <?php
        $attachments = $msg['attachments'] ?? [];
                $hasBodyText = trim((string) $msg['body']) !== '' && $msg['body'] !== t('teams.exception.default_attachment_body');
                ?>
                <?php if (!empty($attachments)): ?>
                    <?php
                    // Separa immagini (rendering inline + lightbox) da altri file (chip link).
                    $imageAttachments = [];
                    $otherAttachments = [];
                    foreach ($attachments as $att) {
                        $mime = strtolower((string) ($att['mime_type'] ?? ''));
                        $ext  = strtolower((string) ($att['extension'] ?? ''));
                        $isImg = str_starts_with($mime, 'image/')
                            || in_array($ext, ['png','jpg','jpeg','gif','webp','bmp'], true);
                        if ($isImg) {
                            $imageAttachments[] = $att;
                        } else {
                            $otherAttachments[] = $att;
                        }
                    }
                ?>
                    <?php if (!empty($imageAttachments)): ?>
                        <div class="tm-msg-images<?= count($imageAttachments) > 1 ? ' tm-msg-images-grid' : '' ?>">
                            <?php foreach ($imageAttachments as $img): ?>
                                <?php $imgUrl = route('teams.attachments.show', ['attachmentId' => (int) $img['id']]); ?>
                                <img src="<?= e($imgUrl) ?>"
                                     data-fullsrc="<?= e($imgUrl) ?>"
                                     data-caption="<?= e($img['original_name'] ?? '') ?>"
                                     alt="<?= e($img['original_name'] ?: t('teams.message.image_alt_fallback')) ?>"
                                     class="tm-msg-image"
                                     loading="lazy">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($hasBodyText): ?>
                        <div class="<?= !empty($imageAttachments) ? 'mt-1' : '' ?> mb-1"><?= $renderMessageBody((string) $msg['body']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($otherAttachments)): ?>
                        <div class="tm-attachment-list">
                            <?php foreach ($otherAttachments as $att): ?>
                                <a href="<?= e(route('teams.attachments.show', ['attachmentId' => (int) $att['id']])) ?>"
                                   target="_blank"
                                   rel="noopener"
                                   class="btn btn-sm tm-attachment-btn"
                                   title="<?= e(t('teams.message.open_attachment_tip')) ?>">
                                    <i class="fa-solid fa-paperclip me-1"></i><?= e($att['original_name'] ?: t('teams.exception.default_attachment_body')) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?= $renderMessageBody((string) $msg['body']) ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php /* META: ultimo figlio della bubble, float:right ⇒ si infila
                     alla fine dell'ultima riga di testo se c'è spazio,
                     altrimenti finisce sulla riga successiva in basso a destra. */ ?>
            <?php if ($showDropdown): ?>
            <div class="tm-msg-meta-wrap dropdown">
                <button type="button"
                        class="tm-msg-meta"
                        data-bs-toggle="dropdown"
                        data-bs-display="dynamic"
                        data-bs-boundary="viewport"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-label="<?= e(t('teams.message.actions_aria', ['time' => $time])) ?>">
                    <?php if ($isEdited && !$isDeleted): ?>
                        <span class="tm-msg-edited-inline"><?= e(t('teams.message.edited_inline')) ?></span>
                    <?php endif; ?>
                    <small class="tm-msg-time"><?= e($time) ?></small>
                    <?php if ($isMine && !$isDeleted): ?>
                        <?php if (($readByCount ?? 0) > 0): ?>
                            <i class="fa-solid fa-check-double tm-msg-status tm-msg-status-read" aria-hidden="true"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-check tm-msg-status tm-msg-status-unread" aria-hidden="true"></i>
                        <?php endif; ?>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end tm-msg-actions-menu">
                    <?php if ($canReply): ?>
                    <li>
                        <button type="button" class="dropdown-item tm-reply-btn"
                                data-message-id="<?= (int) $msg['id'] ?>"
                                data-user-name="<?= e($msg['user_name'] ?? t('teams.exception.default_user_name')) ?>"
                                data-body="<?= e(mb_strimwidth((string) $msg['body'], 0, 80, '…')) ?>">
                            <i class="fa-solid fa-reply me-2"></i><?= e(t('teams.message.reply_btn')) ?>
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($canReact): ?>
                    <li>
                        <button type="button"
                                class="dropdown-item tm-reaction-add-btn"
                                data-message-id="<?= (int) $msg['id'] ?>">
                            <i class="fa-regular fa-face-smile me-2"></i><?= e(t('teams.message.add_reaction_btn')) ?>
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($canPinHere): ?>
                    <li>
                        <button type="button" class="dropdown-item"
                                hx-post="<?= e(route('teams.messages.pin', ['id' => $msg['conversation_id'], 'messageId' => $msg['id']])) ?>"
                                hx-swap="none">
                            <i class="fa-solid fa-thumbtack me-2"></i><?= $isPinned ? e(t('teams.message.unpin_btn')) : e(t('teams.message.pin_btn')) ?>
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                    <li>
                        <button type="button" class="dropdown-item tm-edit-msg-btn"
                                data-message-id="<?= (int) $msg['id'] ?>"
                                data-conv-id="<?= (int) $msg['conversation_id'] ?>"
                                data-body="<?= e($msg['body']) ?>">
                            <i class="fa-solid fa-pen me-2"></i><?= e(t('teams.message.edit_btn')) ?>
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($canViewHistory): ?>
                    <li>
                        <button type="button"
                                class="dropdown-item tm-edit-history-btn"
                                hx-get="<?= e(route('teams.messages.history', ['id' => $msg['conversation_id'], 'messageId' => $msg['id']])) ?>"
                                hx-target="#tm-edit-history-body"
                                data-bs-toggle="modal"
                                data-bs-target="#tm-edit-history-modal"
                                title="<?= e(t('teams.message.edited_on_tip', ['date' => format_date($msg['edited_at'], 'short')])) ?>">
                            <i class="fa-solid fa-clock-rotate-left me-2"></i><?= e(t('teams.message.view_history_btn')) ?>
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($canViewReaders): ?>
                    <li>
                        <button type="button"
                                class="dropdown-item tm-readers-btn"
                                data-message-id="<?= (int) $msg['id'] ?>"
                                hx-get="<?= e(route('teams.messages.readers', ['id' => $msg['conversation_id'], 'messageId' => $msg['id']])) ?>"
                                hx-target="#tm-readers-popover-body">
                            <i class="fa-solid fa-check-double me-2"></i><?= e(tc('teams.message.viewed_by', (int) $readByCount)) ?>
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                                hx-delete="<?= e(route('teams.messages.destroy', [
                                'id'        => $msg['conversation_id'],
                                'messageId' => $msg['id'],
                            ])) ?>"
                                hx-target="#tm-msg-<?= (int) $msg['id'] ?>"
                                hx-swap="outerHTML"
                                data-app-confirm="<?= e(t('teams.message.delete_confirm_message')) ?>"
                                data-app-confirm-label="<?= e(t('teams.message.delete_btn')) ?>">
                            <i class="fa-solid fa-trash me-2"></i><?= e(t('teams.message.delete_btn')) ?>
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php else: ?>
            <?php /* Fallback non-cliccabile (es. messaggio eliminato senza letture). */ ?>
            <span class="tm-msg-meta-wrap">
                <span class="tm-msg-meta" aria-hidden="true">
                    <small class="tm-msg-time"><?= e($time) ?></small>
                </span>
            </span>
            <?php endif; ?>
        </div>
        <?php if (!$isSystem && !$isDeleted): ?>
        <?php $view->include('Teams/Views/partials/reactions_bar', [
            'messageId'      => (int) $msg['id'],
            'conversationId' => (int) $msg['conversation_id'],
            'reactions'      => $reactions ?? [],
            'currentUserId'  => $currentUserId,
        ]); ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
