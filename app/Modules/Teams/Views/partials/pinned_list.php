<?php
use App\Modules\Auth\Helpers\AvatarHelper;

?>
<div class="tm-pinned-list">
    <?php if (empty($pinnedMessages)): ?>
        <p class="text-muted text-center mb-0 py-2">
            <i class="fa-solid fa-thumbtack opacity-50 me-1"></i><?= e(t('teams.pinned.no_pinned')) ?>
        </p>
    <?php else: ?>
        <?php foreach ($pinnedMessages as $pm): ?>
            <?php
            $name      = (string) ($pm['user_name'] ?? t('teams.exception.default_user_name'));
            $avatarUrl = AvatarHelper::url($pm['avatar_path'] ?? null);
            $initials  = AvatarHelper::initials($name);
            $hue       = (int) (crc32($name) % 360);
            $preview   = mb_strimwidth((string) $pm['body'], 0, 140, '…');
            ?>
            <a class="tm-pinned-item tm-scroll-to-msg"
               href="#tm-msg-<?= (int) $pm['id'] ?>"
               data-target-id="tm-msg-<?= (int) $pm['id'] ?>"
               data-bs-dismiss="offcanvas">
                <?php if ($avatarUrl): ?>
                    <img src="<?= e($avatarUrl) ?>" alt="" class="tm-avatar-img-sm">
                <?php else: ?>
                    <span class="tm-avatar-initials-sm" style="--tm-avatar-hue: <?= $hue ?>"><?= e($initials) ?></span>
                <?php endif; ?>
                <div class="tm-pinned-info">
                    <div class="tm-pinned-meta">
                        <span class="tm-pinned-name"><?= e($name) ?></span>
                        <small class="text-muted"><?= e(format_date($pm['pinned_at'], 'compact')) ?></small>
                    </div>
                    <div class="tm-pinned-preview"><?= e($preview) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
