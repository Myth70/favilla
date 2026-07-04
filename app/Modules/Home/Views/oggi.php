<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/home-today.css'); ?>
<?php $view->pushScript('js/home-today.js'); ?>
<?php $view->start('content'); ?>

<?php
$hour = (int) date('H');
if ($hour < 12) {
    $saluto = t('home.greeting.morning');
} elseif ($hour < 18) {
    $saluto = t('home.greeting.afternoon');
} else {
    $saluto = t('home.greeting.evening');
}

$userFirstName = trim((string) ($user['name'] ?? ''));
if ($userFirstName !== '') {
    // Take only the first name to keep the title short and personal.
    $userFirstName = (string) explode(' ', $userFirstName)[0];
}
$heroTitle = $userFirstName !== ''
    ? $saluto . ', ' . $userFirstName . '.'
    : $saluto . '.';

$todayFull = format_date(date('Y-m-d'), 'relative');

$todayStats  = $todayFeed['stats']  ?? [];
$overdueN    = (int) ($todayStats['overdue_tasks']   ?? 0);
$completedN  = (int) ($todayStats['completed_today'] ?? 0);
$openN       = (int) ($todayStats['open_today']      ?? 0);
$progressTot = (int) ($todayStats['progress_total']  ?? 0);
$nextEvent   = $todayStats['next_event'] ?? null;
$totalItems  = (int) ($todayStats['total_items']     ?? count($todayFeed['items'] ?? []));

// Smart greeting subtitle — context-aware
$nowTs = time();
if ($overdueN > 0) {
    $greeting = t('home.oggi.overdue_summary', ['count' => $overdueN]);
} elseif ($totalItems === 0) {
    $greeting = t('home.oggi.free_day');
} elseif ($nextEvent && isset($nextEvent['ts']) && ($nextEvent['ts'] - $nowTs) <= 1800 && ($nextEvent['ts'] - $nowTs) >= 0) {
    $greeting = t('home.oggi.soon', ['title' => (string) ($nextEvent['title'] ?? t('home.oggi.next_event'))]);
} else {
    $greeting = $todayFull;
}

$progressPct   = $progressTot > 0 ? (int) round(($completedN / $progressTot) * 100) : 0;
$nextEventDelta = ($nextEvent && isset($nextEvent['ts'])) ? max(0, (int) $nextEvent['ts'] - $nowTs) : null;

$todayStatsData = [
    'completed'       => $completedN,
    'progress_total'  => $progressTot,
    'progress_pct'    => $progressPct,
    'overdue'         => $overdueN,
    'next_event'      => $nextEvent,
    'next_event_eta'  => $nextEventDelta,
];

$todayButtons = '<a href="' . e(route('home.index')) . '" class="btn btn-sm rounded-pill hm-switch-btn">'
    . '<i class="fa-solid fa-house me-1"></i>' . e(t('home.oggi.dashboard')) . '</a>';
?>

<div class="container-fluid hm-today-page">
    <div class="hm-today-hero">
        <?php $view->include('partials/pf-hero-user', [
            'userName'                 => $heroTitle,
            'userSubtitle'             => $greeting,
            'userUseFavillaLogoPlain' => true,
            'userButtons'              => $todayButtons,
            'statsPartialPath'         => 'Home/Views/partials/oggi_hero_stats',
            'todayHeroStats'           => $todayStatsData,
        ]); ?>
    </div>

    <div id="oggi-feed"
         class="hm-today-feed"
         hx-get="<?= e(route('home.today.feed')) ?>"
         hx-trigger="every 60s, refreshTodayFeed from:body"
         hx-indicator="#oggi-feed-indicator"
         hx-swap="innerHTML">
        <?php $view->include('Home/Views/partials/oggi_feed', [
            'todayFeed'      => $todayFeed ?? ['items' => [], 'counts' => [], 'stats' => [], 'generated_at' => date('Y-m-d H:i:s')],
            'completedToday' => $completedToday ?? [],
        ]); ?>
    </div>

    <div id="oggi-feed-indicator" class="htmx-indicator hm-today-indicator text-muted mt-3" aria-live="polite">
        <i class="fa-solid fa-rotate fa-spin me-1"></i><?= e(t('home.oggi.refreshing')) ?>
    </div>
</div>

<?php $view->end(); ?>
