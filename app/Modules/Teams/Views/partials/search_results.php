<?php if (empty($results)): ?>
    <?php if (!empty($q)): ?>
        <div class="text-muted text-center py-4">
            <i class="fa-solid fa-search fa-2x mb-2 opacity-25 d-block"></i>
            <small><?= e(t('teams.conv_list.no_results_for', ['query' => $q])) ?></small>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($results as $result): ?>
        <?php
        $convName = $result['conversation_type'] === 'group'
            ? ($result['conversation_name'] ?? t('teams.exception.default_group_name'))
            : ($result['other_user_name'] ?? t('teams.search.default_chat_name'));
            $senderAvatar = \App\Modules\Auth\Helpers\AvatarHelper::url($result['avatar_path'] ?? null);
            $senderInitials = \App\Modules\Auth\Helpers\AvatarHelper::initials($result['user_name'] ?? 'U');
            ?>
        <a href="<?= e(route('teams.show', ['id' => $result['conversation_id']])) ?>"
           class="list-group-item list-group-item-action py-2">
            <div class="d-flex align-items-start">
                <div class="me-2 mt-1">
                    <?php if ($senderAvatar): ?>
                        <img src="<?= e($senderAvatar) ?>" alt="" class="tm-avatar-img-sm">
                    <?php else: ?>
                        <span class="tm-avatar-initials-sm">
                            <?= e($senderInitials) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="fw-medium"><?= e($result['user_name'] ?? t('teams.exception.default_user_name')) ?></small>
                        <small class="text-muted"><?= e(format_date($result['created_at'], 'compact')) ?></small>
                    </div>
                    <div class="small text-truncate"><?= e(mb_strimwidth($result['body'], 0, 120, '...')) ?></div>
                    <small class="text-muted"><i class="fa-solid fa-comments me-1"></i><?= e($convName) ?></small>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
