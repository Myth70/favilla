<?php
/**
 * Hero User Section — Standardized hero for user profile and module index pages.
 *
 * Parameters:
 *  - $userName (string) — User or section name
 *  - $userSubtitle (string|null) — Subtitle (e.g., email, description)
 *  - $userAvatar (string|null) — Avatar image URL (shows initials if null)
 *  - $userUseFavillaLogo (bool) — Show Favilla logo in avatar circle when true
 *  - $userUseFavillaLogoPlain (bool) — Show Favilla logo without icon wrapper when true
 *  - $userInitials (string) — Fallback initials if no avatar
 *  - $userStats (array) — Array of stat objects:
 *                         [{icon: '...', label: '...', value: '...', color: '...'}, ...]
 *                         Fallback if $statsPartialPath not provided
 *  - $statsPartialPath (string|null) — Optional: Path to partial for rendering stats
 *                                       Example: 'Auth/Views/partials/stats_cards'
 *                                       If provided, includes this partial instead of rendering $userStats array
 *  - $userButtons (string|null) — HTML slot for buttons (optional, right side)
 *
 * Features:
 *  - Automatic pattern + accent color support via PatternHelper
 *  - Avatar with initials fallback
 *  - Stats bar display
 *  - Responsive layout
 */

use App\Modules\Home\Helpers\PatternHelper;

$heroPatternClass  = PatternHelper::resolveClass();
$userName          = $userName ?? 'Utente';
$userSubtitle      = $userSubtitle ?? null;
$userAvatar        = $userAvatar ?? null;
$userUseFavillaLogo = (bool) ($userUseFavillaLogo ?? false);
$userUseFavillaLogoPlain = (bool) ($userUseFavillaLogoPlain ?? false);
$userInitials      = $userInitials ?? 'U';
$userStats         = $userStats ?? [];
$statsPartialPath  = $statsPartialPath ?? null;
$userButtons       = $userButtons ?? null;
?>

<div class="card shadow-sm overflow-hidden mb-3">
    <div class="pf-hero-header <?= e($heroPatternClass) ?>">
        <div class="d-flex align-items-center gap-4 flex-grow-1">
            <!-- Avatar -->
            <?php if ($userAvatar): ?>
                <img src="<?= e($userAvatar) ?>" alt="" class="pf-avatar pf-avatar-img">
            <?php elseif ($userUseFavillaLogoPlain): ?>
                <div class="pf-hero-logo-plain" aria-hidden="true">
                    <img class="pf-hero-logo-svg" src="<?= e(asset('images/logo.svg')) ?>" width="64" height="64" alt="">
                </div>
            <?php elseif ($userUseFavillaLogo): ?>
                <div class="pf-hero-icon" aria-hidden="true">
                    <img class="pf-hero-logo-svg" src="<?= e(asset('images/logo.svg')) ?>" width="64" height="64" alt="">
                </div>
            <?php else: ?>
                <div class="pf-avatar"><?= e($userInitials) ?></div>
            <?php endif; ?>

            <!-- User info -->
            <div class="flex-grow-1">
                <div class="pf-hero-name text-body"><?= e($userName) ?></div>
                <?php if ($userSubtitle): ?>
                    <div class="pf-hero-email"><?= e($userSubtitle) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action buttons (optional) -->
        <?php if (!empty($userButtons)): ?>
            <div class="d-flex align-items-center gap-2 flex-wrap pf-hero-actions">
                <?= $userButtons ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stats bar (optional) -->
    <?php if ($statsPartialPath): ?>
        <!-- Custom stats partial -->
        <div class="pf-hero-stats">
            <?php $view->include($statsPartialPath, get_defined_vars()); ?>
        </div>
    <?php elseif (!empty($userStats)): ?>
        <!-- Default stats rendering -->
        <div class="pf-hero-stats">
            <div class="d-flex flex-wrap">
                <?php foreach ($userStats as $i => $stat): ?>
                    <div class="pf-hero-stat <?= $i > 0 ? 'pf-hero-stat-bordered' : '' ?>">
                        <i class="<?= e($stat['icon'] ?? '') ?> text-<?= e($stat['color'] ?? 'primary') ?> pf-hero-stat-icon"></i>
                        <span class="pf-hero-stat-value"><?= e(is_numeric($stat['value'] ?? null) ? number_format($stat['value']) : ($stat['value'] ?? '')) ?></span>
                        <span class="pf-hero-stat-label"><?= e($stat['label'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
