<?php
/**
 * Oggi — hero stats con layout curato.
 * Variables: $todayHeroStats
 *
 * Render override per pf-hero-user (passato via statsPartialPath).
 * Sostituisce la riga pf-hero-stat di default con un set di card più aerate:
 * icona in cerchio colorato + valore prominente + label.
 */
$d = is_array($todayHeroStats ?? null) ? $todayHeroStats : [];

$completed     = (int) ($d['completed']      ?? 0);
$progressTotal = (int) ($d['progress_total'] ?? 0);
$progressPct   = (int) ($d['progress_pct']   ?? 0);
$overdue       = (int) ($d['overdue']        ?? 0);
$nextEvent     = $d['next_event']            ?? null;
$nextEta       = $d['next_event_eta']        ?? null;

$nextTime  = ($nextEvent && isset($nextEvent['time'])) ? (string) $nextEvent['time'] : null;
$nextTitle = ($nextEvent && isset($nextEvent['title'])) ? (string) $nextEvent['title'] : '';

if ($nextEta === null) {
    $nextSubtitle = t('home.hero_stats.none_scheduled');
} elseif ($nextEta <= 60) {
    $nextSubtitle = t('home.hero_stats.in_under_minute');
} elseif ($nextEta < 3600) {
    $nextSubtitle = t('home.hero_stats.in_minutes', ['count' => (int) round($nextEta / 60)]);
} elseif ($nextEta < 86400) {
    $nextSubtitle = t('home.hero_stats.in_hours', ['count' => (int) floor($nextEta / 3600)]);
} else {
    $nextSubtitle = $nextTitle !== '' ? $nextTitle : t('home.hero_stats.in_agenda');
}

$overdueColor = $overdue > 0 ? 'danger' : 'success';
$overdueIcon  = $overdue > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check';
$overdueSub   = $overdue > 0
    ? t('home.hero_stats.to_recover')
    : t('home.hero_stats.all_under_control');

$progressColor = $progressTotal > 0 && $progressPct >= 100 ? 'success' : ($progressTotal > 0 ? 'info' : 'secondary');
?>

<div class="hm-today-hero-stats">

    <?php /* ── Progresso oggi ─────────────────────────────────────── */ ?>
    <div class="hm-today-hero-stat hm-today-hero-stat--<?= e($progressColor) ?>">
        <div class="hm-today-hero-stat-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="hm-today-hero-stat-body">
            <div class="hm-today-hero-stat-value">
                <?php if ($progressTotal > 0): ?>
                    <span class="hm-today-hero-stat-main"><?= (int) $completed ?></span><span class="hm-today-hero-stat-suffix">/<?= (int) $progressTotal ?></span>
                <?php else: ?>
                    <span class="hm-today-hero-stat-main">—</span>
                <?php endif; ?>
            </div>
            <div class="hm-today-hero-stat-label"><?= e(t('home.hero_stats.progress_today')) ?></div>
            <?php if ($progressTotal > 0): ?>
                <div class="hm-today-hero-stat-bar" role="progressbar"
                     aria-valuenow="<?= (int) $progressPct ?>" aria-valuemin="0" aria-valuemax="100"
                     aria-label="<?= e(t('home.hero_stats.progress_aria')) ?>">
                    <span style="width: <?= (int) $progressPct ?>%"></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php /* ── In ritardo ─────────────────────────────────────────── */ ?>
    <div class="hm-today-hero-stat hm-today-hero-stat--<?= e($overdueColor) ?>">
        <div class="hm-today-hero-stat-icon">
            <i class="fa-solid <?= e($overdueIcon) ?>"></i>
        </div>
        <div class="hm-today-hero-stat-body">
            <div class="hm-today-hero-stat-value">
                <span class="hm-today-hero-stat-main"><?= (int) $overdue ?></span>
            </div>
            <div class="hm-today-hero-stat-label"><?= e(t('home.hero_stats.overdue')) ?></div>
            <div class="hm-today-hero-stat-sub"><?= e($overdueSub) ?></div>
        </div>
    </div>

    <?php /* ── Prossimo evento ───────────────────────────────────── */ ?>
    <div class="hm-today-hero-stat hm-today-hero-stat--info">
        <div class="hm-today-hero-stat-icon">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
        <div class="hm-today-hero-stat-body">
            <div class="hm-today-hero-stat-value">
                <?php if ($nextTime !== null): ?>
                    <span class="hm-today-hero-stat-main hm-today-hero-stat-time"><?= e($nextTime) ?></span>
                <?php else: ?>
                    <span class="hm-today-hero-stat-main hm-today-hero-stat-empty">—</span>
                <?php endif; ?>
            </div>
            <div class="hm-today-hero-stat-label"><?= e(t('home.hero_stats.next_event')) ?></div>
            <div class="hm-today-hero-stat-sub text-truncate" title="<?= e($nextTitle) ?>"><?= e($nextSubtitle) ?></div>
        </div>
    </div>

</div>
