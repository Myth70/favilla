<?php
/**
 * Oggi feed — vista timeline cronologica.
 * Variables: $items (array)
 *
 * Bucket logic:
 *  - passati     → due_ts < now (overdue scheduled)
 *  - scheduled   → due_ts >= now AND time_group != 'unscheduled'
 *  - unscheduled → due_ts === null OR time_group === 'unscheduled'
 *
 * Timeline rows go from min(currentHour, earliestScheduledHour) to 23, step 1h.
 * "Adesso" marker injected in the current hour's row at minute% offset.
 */
$items = is_array($items ?? null) ? $items : [];
$nowTs = time();

$passati = [];
$scheduled = [];
$unscheduled = [];

foreach ($items as $it) {
    $ts = $it['due_ts'] ?? null;
    $g  = (string) ($it['time_group'] ?? 'unscheduled');
    if ($ts === null || $g === 'unscheduled') {
        $unscheduled[] = $it;
    } elseif ((int) $ts < $nowTs) {
        $passati[] = $it;
    } else {
        $scheduled[] = $it;
    }
}

$nowHour = (int) date('H', $nowTs);
$nowMinute = (int) date('i', $nowTs);
$nowOffsetPct = (int) round(($nowMinute / 60) * 100);

$startHour = $nowHour;
foreach ($scheduled as $it) {
    $h = (int) date('H', (int) $it['due_ts']);
    if ($h < $startHour) {
        $startHour = $h;
    }
}
$startHour = max(0, $startHour);

$byHour = [];
foreach ($scheduled as $it) {
    $h = (int) date('H', (int) $it['due_ts']);
    $byHour[$h][] = $it;
}
ksort($byHour);
?>

<div class="hm-today-tl-wrap">

    <?php if (!empty($unscheduled)): ?>
    <div class="hm-today-tl-strip hm-today-tl-strip--unscheduled border-bottom px-3 py-2">
        <div class="small text-body-secondary fw-semibold mb-2">
            <i class="fa-solid fa-inbox me-1"></i><?= e(t('home.timeline.unscheduled')) ?>
        </div>
        <div class="hm-today-tl-strip-items">
            <?php foreach ($unscheduled as $item): ?>
                <?php $view->include('Home/Views/partials/oggi_feed_item', ['item' => $item, 'groupId' => 'unscheduled']); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($passati)): ?>
    <div class="hm-today-tl-strip hm-today-tl-strip--passati border-bottom px-3 py-2">
        <div class="small text-danger fw-semibold mb-2">
            <i class="fa-solid fa-circle-exclamation me-1"></i><?= e(t('home.timeline.past')) ?>
        </div>
        <div class="hm-today-tl-strip-items">
            <?php foreach ($passati as $item): ?>
                <?php $view->include('Home/Views/partials/oggi_feed_item', ['item' => $item, 'groupId' => 'overdue']); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($scheduled) || $nowHour <= 23): ?>
    <div class="hm-today-tl" data-hm-today-tl>
        <?php for ($h = $startHour; $h <= 23; $h++): ?>
            <?php $hourItems = $byHour[$h] ?? []; ?>
            <div class="hm-today-tl-row<?= $h === $nowHour ? ' hm-today-tl-row--now' : '' ?>" data-hm-tl-hour="<?= (int) $h ?>">
                <div class="hm-today-tl-hour"><?= sprintf('%02d:00', $h) ?></div>
                <div class="hm-today-tl-track">
                    <?php if ($h === $nowHour): ?>
                        <div class="hm-today-tl-now"
                             style="--hm-now-offset: <?= (int) $nowOffsetPct ?>%"
                             aria-label="<?= e(t('home.timeline.now', ['time' => date('H:i', $nowTs)])) ?>">
                            <span class="hm-today-tl-now-label"><?= e(t('home.timeline.now', ['time' => date('H:i', $nowTs)])) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($hourItems)): ?>
                        <div class="hm-today-tl-empty-row" aria-hidden="true"></div>
                    <?php else: ?>
                        <?php foreach ($hourItems as $item): ?>
                            <?php $view->include('Home/Views/partials/oggi_feed_item', ['item' => $item, 'groupId' => 'hour-' . $h]); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>
