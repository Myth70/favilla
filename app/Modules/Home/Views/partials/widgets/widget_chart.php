<?php
/**
 * Chart widget partial (ApexCharts).
 * Variables: $widget (V2 format with data: {chartId, chartType, series, options})
 */
$data = $widget['data'] ?? [];
$chartId = e($data['chartId'] ?? ('chart-' . md5($widget['id'])));
$options = $data['options'] ?? [];
$displayTone = $widget['_displayTone'] ?? ['value' => 'var(--bs-primary)', 'rgb' => 'var(--bs-primary-rgb)'];

// Ensure options has chart.type if provided separately
if (!empty($data['chartType']) && !isset($options['chart']['type'])) {
    $options['chart']['type'] = $data['chartType'];
}
if (!empty($data['series']) && !isset($options['series'])) {
    $options['series'] = $data['series'];
}
// Set responsive height
if (!isset($options['chart']['height'])) {
    $options['chart']['height'] = 250;
}
?>
<div class="card border-0 shadow-sm h-100 hm-widget-card"
     style="--hm-widget-color: <?= e($displayTone['value']) ?>; --hm-widget-color-rgb: <?= e($displayTone['rgb']) ?>;">
    <div class="hm-chart-header">
        <div class="hm-chart-header-icon hm-chart-header-icon-dynamic">
            <i class="fa-solid <?= e($widget['icon'] ?? 'fa-chart-line') ?>"></i>
        </div>
        <h6 class="hm-chart-title"><?= e($widget['label'] ?? '') ?></h6>
    </div>
    <div class="card-body pt-2">
        <div id="<?= $chartId ?>" data-apex-chart="<?= e(json_encode($options, JSON_UNESCAPED_UNICODE)) ?>"></div>
    </div>
</div>
