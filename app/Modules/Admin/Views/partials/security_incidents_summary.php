<?php
$periods = [
    '24h' => t('admin.incidents_summary.period_24h'),
    '7d'  => t('admin.incidents_summary.period_7d'),
    '30d' => t('admin.incidents_summary.period_30d'),
];
foreach ($periods as $key => $label):
    $items = $summary[$key] ?? [];
    $count = 0;
    foreach ($items as $item) $count += (int) $item['cnt'];
?>
<div class="mb-2">
    <strong><?= e($label) ?>:</strong> <?= e(t('admin.incidents_summary.count', ['count' => $count])) ?>
    <?php if ($count > 0): ?>
        <small class="text-muted">
            (<?php
            $groups = [];
            foreach ($items as $item) {
                $groups[] = e($item['type']) . ': ' . e($item['cnt']);
            }
            echo implode(', ', $groups);
            ?>)
        </small>
    <?php endif; ?>
</div>
<?php endforeach; ?>
