<?php
/**
 * Stat widget partial.
 * Variables: $widget (V2 format with data: {value, subtitle, link, color})
 */
use App\Modules\Home\Services\DashboardStatTextFormatter;

$data = $widget['data'] ?? [];
$displayTone = $widget['_displayTone'] ?? ['value' => 'var(--bs-primary)', 'rgb' => 'var(--bs-primary-rgb)'];
$link = $data['link'] ?? '';
$hasLink = is_string($link) && $link !== '' && $link !== '#';
$textFormatter = app(DashboardStatTextFormatter::class);
$displayText = $textFormatter->format($widget['label'] ?? '', $data['subtitle'] ?? null);
$tag = $hasLink ? 'a' : 'div';
?>
<<?= $tag ?> <?php if ($hasLink): ?>href="<?= e($link) ?>" <?php endif; ?>class="card border-0 shadow-sm text-decoration-none h-100 hm-widget-card<?= $hasLink ? '' : ' hm-widget-card-static' ?>"
   style="--hm-widget-color: <?= e($displayTone['value']) ?>; --hm-widget-color-rgb: <?= e($displayTone['rgb']) ?>;">
    <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
        <div class="hm-stat-icon-wrap hm-stat-icon-dynamic">
            <i class="fa-solid <?= e($widget['icon'] ?? 'fa-circle') ?>"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
            <div class="hm-stat-value"><?= e($data['value'] ?? 0) ?></div>
            <div class="hm-stat-label"><?= e($displayText['label']) ?><?php if (!empty($displayText['subtitle'])): ?> <span class="opacity-50">&middot;</span> <?= e($displayText['subtitle']) ?><?php endif; ?></div>
        </div>
        <?php if ($hasLink): ?>
        <div class="hm-stat-arrow">
            <i class="fa-solid fa-chevron-right"></i>
        </div>
        <?php endif; ?>
    </div>
    <div class="hm-stat-accent hm-stat-accent-dynamic"></div>
</<?= $tag ?>>
