<?php
/**
 * Profile stats — compact counters (integrated in hero card footer).
 * Variables: $stats
 */

$cards = [];

$cards[] = [
    'value' => $stats['days_registered'] ?? 0,
    'label' => t('auth.profile.stat_days'),
    'icon'  => 'fa-solid fa-calendar-days',
    'color' => 'primary',
];

$cards[] = [
    'value' => $stats['total_logins'] ?? 0,
    'label' => t('auth.profile.stat_logins'),
    'icon'  => 'fa-solid fa-right-to-bracket',
    'color' => 'secondary',
];

if (isset($stats['files_uploaded']) && isModuleEnabled('Files')) {
    $cards[] = [
        'value' => $stats['files_uploaded'],
        'label' => t('auth.profile.stat_files'),
        'icon'  => 'fa-solid fa-file',
        'color' => 'info',
    ];
}


?>

<div class="d-flex flex-wrap">
    <?php foreach ($cards as $i => $card): ?>
        <div class="pf-hero-stat <?= $i > 0 ? 'pf-hero-stat-bordered' : '' ?>">
            <i class="<?= $card['icon'] ?> text-<?= $card['color'] ?> pf-hero-stat-icon"></i>
            <span class="pf-hero-stat-value"><?= e(number_format($card['value'])) ?></span>
            <span class="pf-hero-stat-label"><?= e($card['label']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
