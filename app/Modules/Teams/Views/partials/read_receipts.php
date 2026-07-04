<?php
use App\Modules\Auth\Helpers\AvatarHelper;

?>
<div class="tm-read-receipts">
    <?php if (empty($readers)): ?>
        <small class="text-muted"><?= e(t('teams.read_receipts.no_readers')) ?></small>
    <?php else: ?>
        <div class="tm-read-receipts-title">
            <i class="fa-solid fa-check-double me-1"></i><?= e(t('teams.read_receipts.read_by', ['count' => count($readers)])) ?>
        </div>
        <ul class="tm-read-receipts-list">
            <?php foreach ($readers as $r): ?>
                <?php
                $avatarUrl = AvatarHelper::url($r['avatar_path'] ?? null);
                $name      = (string) ($r['name'] ?? t('teams.exception.default_user_name'));
                $initials  = AvatarHelper::initials($name);
                $hue       = (int) (crc32($name) % 360);
                ?>
                <li class="tm-read-receipts-item">
                    <?php if ($avatarUrl): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="" class="tm-avatar-img-sm">
                    <?php else: ?>
                        <span class="tm-avatar-initials-sm" style="--tm-avatar-hue: <?= $hue ?>"><?= e($initials) ?></span>
                    <?php endif; ?>
                    <div class="tm-read-receipts-info">
                        <div class="tm-read-receipts-name"><?= e($name) ?></div>
                        <small class="text-muted"><?= e(format_date($r['last_read_at'], 'compact')) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
