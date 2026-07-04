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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="64" height="64" focusable="false" class="pf-hero-logo-svg">
                        <path d="M16 2 C13 7 5 10 5 18 C5 24.8 9.8 29.5 16 30 C22.2 29.5 27 24.8 27 18 C27 10 19 7 16 2Z" fill="#f97316"/>
                        <path d="M16 9 C14 13 10 16 10 20 C10 23.9 12.7 27 16 27 C19.3 27 22 23.9 22 20 C22 16 18 13 16 9Z" fill="#ea580c"/>
                        <path d="M16 15 C15 17 13 19 14 22 C14.7 24 16 25 16 25 C16 25 17.3 24 18 22 C19 19 17 17 16 15Z" fill="#fbbf24"/>
                    </svg>
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
