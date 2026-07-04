<?php if (empty($users) && !empty($q)): ?>
    <div class="text-muted small py-2 text-center"><?= e(t('teams.user_search.no_users_found')) ?></div>
<?php elseif (!empty($users)): ?>
    <div class="list-group list-group-flush tm-user-search-list">
        <?php foreach ($users as $user): ?>
        <?php
        $uAvatarUrl = \App\Modules\Auth\Helpers\AvatarHelper::url($user['avatar_path'] ?? null);
            $uInitials  = \App\Modules\Auth\Helpers\AvatarHelper::initials($user['name'] ?? 'U');
            ?>
        <button type="button"
                class="list-group-item list-group-item-action d-flex align-items-center py-2 tm-user-select-btn"
                data-user-id="<?= (int) $user['id'] ?>"
                data-user-name="<?= e($user['name']) ?>"
                data-user-email="<?= e($user['email']) ?>"
                data-user-avatar="<?= e($uAvatarUrl ?? '') ?>"
                data-user-initials="<?= e($uInitials) ?>">
            <div class="me-2">
                <?php if ($uAvatarUrl): ?>
                    <img src="<?= e($uAvatarUrl) ?>" alt="" class="tm-avatar-img-sm">
                <?php else: ?>
                    <span class="tm-avatar-initials-sm">
                        <?= e($uInitials) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div>
                <div class="small fw-medium"><?= e($user['name']) ?></div>
                <div class="text-muted tm-user-email"><?= e($user['email']) ?></div>
            </div>
        </button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
