<?php
/**
 * Hero Admin Section — Standardized hero for admin pages.
 *
 * Parameters:
 *  - $adminTitle (string) — Page title (e.g., "Dashboard", "Utenti & ruoli")
 *  - $adminIcon (string|null) — Font Awesome icon class (e.g., "fa-solid fa-gauge-high")
 *                                 Fallback to shield icon if null
 *  - $adminSubtitle (string|null) — Optional subtitle (supports HTML)
 *  - $adminButtons (string) — HTML slot for action buttons (right side)
 *  - $patterns — (optional) Pattern context passed from layout
 *
 * Features:
 *  - Pattern background with neutral tint (no accent color)
 *  - Dark mode proof
 *  - Responsive layout
 *  - Same structure as pf-hero-module
 */

use App\Modules\Home\Helpers\PatternHelper;

$heroPatternClass = PatternHelper::resolveClass();
$adminIcon        = $adminIcon ?? null;
$adminSubtitle    = $adminSubtitle ?? null;
$adminButtons     = $adminButtons ?? '';
?>

<div class="card shadow-sm mb-4 overflow-hidden">
    <div class="pf-hero-header pf-hero-admin <?= e($heroPatternClass) ?>">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
            <!-- Icon -->
            <div class="pf-hero-icon" aria-hidden="true">
                <?php if ($adminIcon): ?>
                    <i class="<?= e($adminIcon) ?> pf-hero-icon-fa"></i>
                <?php else: ?>
                    <i class="fa-solid fa-shield-halved pf-hero-icon-fa"></i>
                <?php endif; ?>
            </div>

            <!-- Title & subtitle -->
            <div class="flex-grow-1">
                <div class="pf-hero-name text-body"><?= e($adminTitle) ?></div>
                <?php if ($adminSubtitle): ?>
                    <div class="pf-hero-email"><?= $adminSubtitle ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action buttons (optional) -->
        <?php if (!empty($adminButtons)): ?>
            <div class="d-flex align-items-center gap-2 flex-wrap pf-hero-actions">
                <?= $adminButtons ?>
            </div>
        <?php endif; ?>
    </div>
</div>
