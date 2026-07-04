<?php
/**
 * Home - Changelog pubblico per utenti autenticati.
 * Variables: $items, $totalPublished, $latestVersion, $moduleSubtitle
 */
$view->layout('main');
$view->pushStyle('css/home-changelog.css');
$view->start('content');

$moduleButtons = '<a href="' . e(route('home.index')) . '" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="' . e(t('home.changelog.back_home')) . '">';
$moduleButtons .= '<i class="fa-solid fa-house me-1"></i>' . e(t('home.changelog.home'));
$moduleButtons .= '</a>';

$heroStats = [
    [
        'value' => (int) $totalPublished,
        'label' => t('home.changelog.releases'),
        'icon'  => 'fa-solid fa-code-branch',
        'color' => 'primary',
    ],
];

if (!empty($latestVersion)) {
    $heroStats[] = [
        'value' => 'v' . (string) $latestVersion,
        'label' => t('home.changelog.latest_version'),
        'icon'  => 'fa-solid fa-tag',
        'color' => 'success',
    ];
}
?>

<div class="container-fluid hc-page">
    <?php $view->include('partials/pf-hero-user', [
        'userName'     => t('home.changelog.title'),
        'userSubtitle' => $moduleSubtitle,
        'userUseFavillaLogo' => true,
        'userInitials' => 'CH',
        'userStats'    => $heroStats,
        'userButtons'  => $moduleButtons,
    ]); ?>

    <?php if (!empty($items)): ?>
        <div class="hc-timeline position-relative">
            <?php foreach ($items as $item): ?>
                <article class="card shadow-sm mb-3 hc-item">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="hc-bullet" aria-hidden="true"></span>
                                <span class="hc-version">v<?= e((string) ($item['version'] ?? '0.0.0')) ?></span>
                            </div>
                            <time class="text-muted small" datetime="<?= e((string) ($item['release_date'] ?? '')) ?>">
                                <i class="fa-regular fa-calendar me-1"></i><?= e(format_date((string) ($item['release_date'] ?? ''), 'long')) ?>
                            </time>
                        </div>
                        <h3 class="h5 mb-2"><?= e((string) ($item['title'] ?? t('home.changelog.fallback_title'))) ?></h3>
                        <div class="hc-notes small">
                            <?= nl2br(e((string) ($item['notes'] ?? t('home.changelog.no_notes')))) ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body p-4 text-center">
                <i class="fa-regular fa-folder-open fs-4 text-muted mb-2"></i>
                <p class="mb-0 text-muted"><?= e(t('home.changelog.empty')) ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php $view->end(); ?>
