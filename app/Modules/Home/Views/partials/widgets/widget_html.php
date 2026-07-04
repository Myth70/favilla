<?php
/**
 * HTML widget partial — delegates rendering to a module-provided partial.
 * Variables: $widget (V2 format with data: {partial})
 */
$data = $widget['data'] ?? [];
$partial = $data['partial'] ?? null;
?>
<div class="card border-0 shadow-sm h-100 hm-widget-card">
    <?php if ($partial): ?>
        <?php $view->include($partial, ['widget' => $widget]); ?>
    <?php else: ?>
        <div class="card-body">
            <p class="text-muted text-center py-3 mb-0"><small><?= e(t('home.widget.not_configured')) ?></small></p>
        </div>
    <?php endif; ?>
</div>
