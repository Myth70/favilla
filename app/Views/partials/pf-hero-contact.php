<?php
use App\Modules\Home\Helpers\PatternHelper;

$heroPatternClass = PatternHelper::resolveClass();
$contactName = $contactName ?? 'Contatto';
$contactSubtitle = $contactSubtitle ?? null;
$contactAvatar = $contactAvatar ?? null;
$contactInitials = $contactInitials ?? 'C';
$contactAvatarBg = $contactAvatarBg ?? '#6c757d';
$contactCategoryName = $contactCategoryName ?? null;
$contactCategoryColor = $contactCategoryColor ?? '#6c757d';
$contactFavoriteButton = $contactFavoriteButton ?? '';
$contactButtons = $contactButtons ?? '';
$contactStats = $contactStats ?? [];
?>

<div class="pf-hero-contact-card ct-hero-section ct-hero-card">
    <div class="pf-hero-contact-banner ct-hero-banner <?= e($heroPatternClass) ?>">
        <?php if ($contactCategoryName): ?>
            <span class="ct-hero-cat-badge"
                  style="--ct-cat-color: <?= e($contactCategoryColor) ?>;">
                <?= e($contactCategoryName) ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="pf-hero-contact-content ct-hero-content">
        <div class="ct-avatar ct-avatar-hero ct-avatar-dynamic pf-hero-contact-avatar"
             style="--ct-avatar-bg: <?= $contactAvatar ? 'transparent' : e($contactAvatarBg) ?>;">
            <?php if ($contactAvatar): ?>
                <img src="<?= e($contactAvatar) ?>" alt="<?= e($contactName) ?>">
            <?php else: ?>
                <?= e($contactInitials) ?>
            <?php endif; ?>
        </div>

        <div class="pf-hero-contact-body ct-hero-body">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h2 class="pf-hero-contact-name ct-hero-name"><?= e($contactName) ?></h2>
                <?= $contactFavoriteButton ?>
            </div>

            <?php if ($contactSubtitle): ?>
                <div class="pf-hero-contact-subtitle ct-hero-sub"><?= e($contactSubtitle) ?></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($contactButtons)): ?>
            <div class="pf-hero-contact-actions ct-hero-side">
                <div class="d-flex align-items-center gap-2">
                    <?= $contactButtons ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($contactStats)): ?>
        <div class="pf-hero-stats">
            <div class="d-flex flex-wrap">
                <?php foreach ($contactStats as $index => $stat): ?>
                    <div class="pf-hero-stat<?= $index > 0 ? ' pf-hero-stat-bordered' : '' ?><?= !empty($stat['className']) ? ' ' . e($stat['className']) : '' ?>">
                        <i class="<?= e($stat['icon'] ?? '') ?> pf-hero-stat-icon text-<?= e($stat['color'] ?? 'primary') ?>"></i>
                        <span class="pf-hero-stat-value"><?= e((string) ($stat['value'] ?? '')) ?></span>
                        <span class="pf-hero-stat-label"><?= e($stat['label'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>