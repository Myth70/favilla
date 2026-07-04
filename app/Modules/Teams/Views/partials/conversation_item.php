<?php
$isActive = ($activeId ?? null) == (int) $conv['id'];
$isGroup  = $conv['type'] === 'group';
$isHidden = !empty($conv['hidden_at']);
$displayName = $isGroup
    ? ($conv['name'] ?? t('teams.exception.default_group_name'))
    : ($conv['other_user_name'] ?? t('teams.exception.default_user_name'));
$avatarPath = $isGroup
    ? ($conv['avatar_path'] ?? null)
    : ($conv['other_user_avatar'] ?? null);
$unread     = (int) ($conv['unread_count'] ?? 0);
$lastMsg    = $conv['last_message_body'] ?? null;
$lastMsgAt  = $conv['last_message_at'] ?? $conv['created_at'];
$lastMsgUser     = $conv['last_message_user_name'] ?? null;
$lastMsgDeleted  = !empty($conv['last_message_deleted']);
$lastMsgType     = $conv['last_message_type'] ?? 'text';

$avatarUrl = $avatarPath
    ? \App\Modules\Auth\Helpers\AvatarHelper::url($avatarPath)
    : null;
$initials = \App\Modules\Auth\Helpers\AvatarHelper::initials($displayName);
?>
<a href="<?= e(route('teams.show', ['id' => $conv['id']])) ?>"
   class="tm-conv-item tm-conv-<?= $isGroup ? 'group' : 'direct' ?> <?= $isActive ? 'tm-conv-active' : '' ?> <?= $unread > 0 ? 'tm-conv-unread' : '' ?> <?= $isHidden ? 'tm-conv-hidden' : '' ?>"
   hx-get="<?= e(route('teams.show', ['id' => $conv['id']])) ?>"
   hx-target="#tm-chat-panel"
   hx-push-url="true">
    <div class="tm-conv-avatar">
        <?php if ($avatarUrl): ?>
            <img src="<?= e($avatarUrl) ?>" alt="" class="tm-avatar-img<?= $isGroup ? ' tm-avatar-img-group' : '' ?>">
        <?php elseif ($isGroup): ?>
            <span class="tm-avatar-initials tm-avatar-initials-group"
                  style="--tm-avatar-hue: <?= (int) (crc32($displayName) % 360) ?>"><?= e($initials) ?></span>
        <?php else: ?>
            <span class="tm-avatar-initials"
                  style="--tm-avatar-hue: <?= (int) (crc32($displayName) % 360) ?>"><?= e($initials) ?></span>
        <?php endif; ?>
        <?php if ($isGroup): ?>
            <span class="tm-conv-type-badge"
                  title="<?= isset($conv['member_count']) ? e(t('teams.conv_item.group_badge_tip_with_count', ['count' => (int) $conv['member_count']])) : e(t('teams.exception.default_group_name')) ?>"
                  aria-label="<?= e(t('teams.conv_item.group_conversation_aria')) ?>">
                <i class="fa-solid fa-users"></i>
            </span>
        <?php endif; ?>
    </div>
    <div class="tm-conv-info">
        <div class="tm-conv-top">
            <span class="tm-conv-name">
                <?php if ($isHidden): ?><i class="fa-solid fa-eye-slash me-1 text-muted tm-hidden-icon"></i><?php endif; ?>
                <?= e($displayName) ?>
            </span>
            <small class="tm-conv-time"><?= e(format_date($lastMsgAt, 'compact')) ?></small>
        </div>
        <div class="tm-conv-preview">
            <?php if ($lastMsgType === 'system'): ?>
                <em class="text-muted small"><?= e(mb_strimwidth($lastMsg ?? '', 0, 60, '...')) ?></em>
            <?php elseif ($lastMsgDeleted): ?>
                <em class="text-muted"><i class="fa-solid fa-ban me-1"></i><?= e(t('teams.message.deleted_placeholder')) ?></em>
            <?php elseif ($lastMsg): ?>
                <?php if ($isGroup && $lastMsgUser): ?>
                    <span class="tm-conv-preview-sender"><?= e($lastMsgUser) ?>:</span>
                <?php endif; ?>
                <?= e(mb_strimwidth($lastMsg, 0, 60, '...')) ?>
            <?php else: ?>
                <em class="text-muted"><?= e(t('teams.conv_item.no_messages')) ?></em>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($isHidden && !empty($showHidden)): ?>
        <button type="button"
                class="btn btn-sm btn-outline-secondary tm-conv-unhide-btn"
                hx-post="<?= e(route('teams.conversations.unhide', ['id' => $conv['id']])) ?>"
                hx-swap="none"
                title="<?= e(t('teams.conv_item.show_conversation_tip')) ?>">
            <i class="fa-solid fa-eye"></i>
        </button>
    <?php elseif ($unread > 0): ?>
        <span class="tm-unread-badge"><?= $unread > 99 ? '99+' : $unread ?></span>
    <?php endif; ?>
</a>
