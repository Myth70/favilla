<?php
/**
 * Quick links widget partial (html type).
 * Variables: $widget (V2 format with data: {quickLinks})
 */
$quickLinks = $widget['data']['quickLinks'] ?? [];
?>
<div class="hm-list-header">
    <div class="hm-list-header-title">
        <div class="hm-list-header-icon hm-list-header-icon-primary">
            <i class="fa-solid <?= e($widget['icon'] ?? 'fa-grid-2') ?>"></i>
        </div>
        <h6 class="hm-list-title"><?= e($widget['label'] ?? t('home.widget.quick_access')) ?></h6>
    </div>
</div>
<div class="card-body pt-0 pb-3 px-3">
    <?php if (empty($quickLinks)): ?>
        <div class="hm-list-empty">
            <div class="hm-list-empty-icon">
                <i class="fa-solid fa-link"></i>
            </div>
            <p class="hm-list-empty-text"><?= e(t('home.widget.no_links')) ?></p>
        </div>
    <?php else: ?>
        <div class="hm-ql-grid">
            <?php foreach ($quickLinks as $item):
                $color = $item['color'] ?? 'primary';
            ?>
                <a href="<?= e(route($item['route'])) ?>" class="hm-quick-link hm-ql-<?= e($color) ?>">
                    <div class="hm-quick-link-icon">
                        <i class="fa-solid <?= e($item['icon']) ?>"></i>
                    </div>
                    <span class="hm-quick-link-label"><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
