<?php
/**
 * PARTIAL — Secondary navigation for Report module pages.
 *
 * Variable: $activeTab (string) — current active tab key
 */

$activeTab = $activeTab ?? 'dashboard';

$tabs = [
    ['key' => 'dashboard',  'label' => t('reports.subnav.dashboard'), 'icon' => 'fa-chart-pie',            'route' => 'reports.index',           'permission' => 'reports.view'],
    ['key' => 'templates',  'label' => t('reports.subnav.templates'), 'icon' => 'fa-wand-magic-sparkles',  'route' => 'reports.templates.index', 'permission' => 'reports.view'],
];
?>

<ul class="nav nav-tabs rp-subnav mb-4">
    <?php foreach ($tabs as $tab): ?>
        <?php if (has_permission($tab['permission'])): ?>
        <li class="nav-item">
            <a class="nav-link<?= $activeTab === $tab['key'] ? ' active' : '' ?>"
               href="<?= e(route($tab['route'])) ?>">
                <i class="fa-solid <?= $tab['icon'] ?> me-1"></i><?= e($tab['label']) ?>
            </a>
        </li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>
