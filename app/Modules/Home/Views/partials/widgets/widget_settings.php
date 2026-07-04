<?php
/**
 * Widget settings offcanvas — drag & drop + toggle.
 * Variables: $allWidgets (from DashboardService::getAllAvailableWidgets)
 */
?>
<div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title">
        <i class="fa-solid fa-sliders me-2"></i><?= e(t('home.widget_settings.title')) ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(t('home.dashboard.close')) ?>"></button>
</div>
<div class="offcanvas-body p-0">
    <div class="p-3">
        <p class="text-muted small mb-3"><?= e(t('home.widget_settings.hint')) ?></p>

        <ul class="list-group list-group-flush" id="widget-settings-list">
            <?php foreach ($allWidgets as $widget): ?>
            <li class="list-group-item d-flex align-items-center gap-3 px-0 py-2 hm-widget-item"
                data-widget-id="<?= e($widget['id']) ?>">
                <span class="hm-drag-handle text-muted">
                    <i class="fa-solid fa-grip-vertical"></i>
                </span>
                <i class="fa-solid <?= e($widget['icon'] ?? 'fa-circle') ?> text-muted hm-widget-icon"></i>
                <span class="flex-grow-1 small fw-medium"><?= e($widget['label'] ?? '') ?></span>
                <span class="badge bg-light text-muted small"><?= e($widget['type'] ?? 'stat') ?></span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input hm-widget-toggle" type="checkbox"
                           role="switch"
                           <?= ($widget['_visible'] ?? true) ? 'checked' : '' ?>>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<div class="offcanvas-footer border-top p-3 d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="hm-widget-reset">
        <i class="fa-solid fa-rotate-left me-1"></i><?= e(t('home.widget_settings.reset')) ?>
    </button>
    <button type="button" class="btn btn-primary btn-sm ms-auto" id="hm-widget-save">
        <i class="fa-solid fa-check me-1"></i><?= e(t('home.widget_settings.save')) ?>
    </button>
</div>
