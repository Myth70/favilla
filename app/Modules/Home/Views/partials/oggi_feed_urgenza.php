<?php
/**
 * Oggi feed — vista raggruppata per urgenza.
 * Variables: $items (array)
 */
$items = is_array($items ?? null) ? $items : [];

$groupDefs = [
    'overdue'     => ['label' => t('home.urgency.overdue'),     'icon' => 'fa-circle-exclamation', 'color' => 'danger'],
    'soon'        => ['label' => t('home.urgency.soon'),        'icon' => 'fa-bolt',               'color' => 'warning'],
    'today'       => ['label' => t('home.urgency.today'),       'icon' => 'fa-sun',                'color' => 'info'],
    'hours24'     => ['label' => t('home.urgency.hours24'),     'icon' => 'fa-moon',               'color' => 'secondary'],
    'unscheduled' => ['label' => t('home.urgency.unscheduled'), 'icon' => 'fa-inbox',              'color' => 'secondary'],
];

$grouped = [];
foreach ($groupDefs as $groupId => $def) {
    $grouped[$groupId] = array_merge($def, ['items' => []]);
}
foreach ($items as $item) {
    $g = (string) ($item['time_group'] ?? 'unscheduled');
    if (!isset($grouped[$g])) {
        $g = 'unscheduled';
    }
    $grouped[$g]['items'][] = $item;
}
?>

<div class="list-group list-group-flush hm-today-list" role="list">
    <?php foreach ($grouped as $groupId => $group): ?>
        <?php if (empty($group['items'])) continue; ?>
        <div class="hm-today-group-header" data-hm-group-id="<?= e($groupId) ?>">
            <i class="fa-solid <?= e($group['icon']) ?> text-<?= e($group['color']) ?>"></i>
            <span><?= e($group['label']) ?></span>
        </div>
        <?php foreach ($group['items'] as $item): ?>
            <?php $view->include('Home/Views/partials/oggi_feed_item', [
                'item'    => $item,
                'groupId' => $groupId,
            ]); ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
