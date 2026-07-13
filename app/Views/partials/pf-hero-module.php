<?php
/**
 * Hero Module Section — Standardized hero for module index pages.
 *
 * Parameters:
 *  - $moduleName (string) — Module name (e.g., "Contatti", "Attività")
 *  - $moduleIcon (string|null) — Font Awesome icon class (e.g., "fa-solid fa-address-book")
 *                                 Fallback to Favilla logo if null
 *  - $moduleSubtitle (string|null) — Optional subtitle
 *  - $moduleButtons (string) — HTML slot for buttons (right side)
 *  - $patterns — (optional) Pattern context passed from layout
 *
 * Features:
 *  - Automatic pattern + accent color support via PatternHelper
 *  - Responsive layout
 *  - Icon or logo fallback
 */

use App\Modules\Home\Helpers\PatternHelper;

$heroPatternClass = PatternHelper::resolveClass();
$moduleIcon       = $moduleIcon ?? null;
$moduleSubtitle   = $moduleSubtitle ?? null;
$moduleButtons    = $moduleButtons ?? '';
?>

<div class="card shadow-sm mb-4 overflow-hidden">
    <div class="pf-hero-header <?= e($heroPatternClass) ?>">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
            <!-- Icon or logo -->
            <div class="pf-hero-icon" aria-hidden="true">
                <?php if ($moduleIcon): ?>
                    <!-- Module icon in accent color -->
                    <i class="<?= e($moduleIcon) ?> pf-hero-icon-fa"></i>
                <?php else: ?>
                    <!-- Fallback: Favilla logo -->
                    <img class="pf-hero-logo-svg" src="<?= e(asset('images/logo.svg')) ?>" width="64" height="64" alt="">
                <?php endif; ?>
            </div>

            <!-- Module info -->
            <div class="flex-grow-1">
                <div class="pf-hero-name text-body"><?= e($moduleName) ?></div>
                <?php if ($moduleSubtitle): ?>
                    <div class="pf-hero-email"><?= $moduleSubtitle ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Module action buttons (optional) -->
        <?php if (!empty($moduleButtons)): ?>
            <div class="d-flex align-items-center gap-2 flex-wrap pf-hero-actions">
                <?= $moduleButtons ?>
            </div>
        <?php endif; ?>
    </div>
</div>
