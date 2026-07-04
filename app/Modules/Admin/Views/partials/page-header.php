<?php
/**
 * Admin standard page header.
 * Variables: $headerIcon, $headerTitle, $headerSubtitle (optional), $headerActions (optional HTML string)
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
        <div class="adm-page-icon">
            <i class="fa-solid <?= e($headerIcon ?? 'fa-cog') ?>"></i>
        </div>
        <div>
            <h1 class="adm-page-title"><?= e($headerTitle ?? $pageTitle ?? '') ?></h1>
            <?php if (!empty($headerSubtitle)): ?>
            <small class="text-muted"><?= $headerSubtitle ?></small>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($headerActions)): ?>
    <div class="d-flex gap-2">
        <?= $headerActions ?>
    </div>
    <?php endif; ?>
</div>
