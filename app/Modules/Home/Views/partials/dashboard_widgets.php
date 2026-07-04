<?php
/**
 * HTMX partial: dashboard widget grid (single batched request).
 *
 * Each item is either:
 *   - ready: ['lazy' => false, 'widget' => <full widget>]  → rendered inline.
 *   - lazy:  ['lazy' => true,  'meta'   => <widget meta>]   → placeholder that
 *            loads its body separately via the home.widget endpoint, so slow
 *            widgets (e.g. weather / external I/O) don't block the grid.
 *
 * Variables: $dashboard  (array of items, see DashboardService::buildDashboard)
 */
$allowedTypes = ['stat', 'chart', 'list', 'html'];
?>

<?php if (!empty($dashboard)): ?>
<div class="row g-3" id="widgets-grid">
    <?php foreach ($dashboard as $item): ?>
    <?php if (!empty($item['lazy'])): ?>
        <?php
        $meta = $item['meta'];
        $id   = (string) ($meta['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $type = in_array($meta['type'] ?? 'stat', $allowedTypes, true) ? $meta['type'] : 'stat';
        $size = (int) ($meta['size'] ?? 2);
        $columnClass = 'col-sm-6 col-lg-' . $size;
        if ($type === 'stat' && $size === 3) {
            $columnClass .= ' hm-widget-col-stat-5up';
        }
        ?>
        <div class="<?= e($columnClass) ?>" data-widget-id="<?= e($id) ?>"
             hx-get="<?= e(route('home.widget', ['id' => $id])) ?>"
             hx-trigger="load"
             hx-swap="innerHTML">
            <div class="hm-widget-skeleton hm-widget-skeleton--<?= e($type) ?>" aria-hidden="true">
                <div class="hm-widget-skeleton-head">
                    <span class="hm-widget-skeleton-icon"><i class="fa-solid <?= e($meta['icon'] ?? 'fa-circle') ?>"></i></span>
                    <span class="hm-widget-skeleton-label"><?= e($meta['label'] ?? '') ?></span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php
        $widget = $item['widget'];
        $id     = (string) ($widget['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $type = in_array($widget['type'] ?? 'stat', $allowedTypes, true) ? $widget['type'] : 'stat';
        $size = (int) ($widget['size'] ?? 2);
        $columnClass = 'col-sm-6 col-lg-' . $size;
        if ($type === 'stat' && $size === 3) {
            $columnClass .= ' hm-widget-col-stat-5up';
        }
        ?>
        <div class="<?= e($columnClass) ?>" data-widget-id="<?= e($id) ?>">
            <?php $view->include('Home/Views/partials/widgets/widget_' . $type, ['widget' => $widget]); ?>
        </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="hm-empty-dashboard">
    <div class="hm-empty-dashboard-icon">
        <i class="fa-solid fa-cubes"></i>
    </div>
    <p><?= e(t('home.dashboard.empty_title')) ?></p>
    <p class="small opacity-50"><?= e(t('home.dashboard.empty_hint')) ?></p>
</div>
<?php endif; ?>
