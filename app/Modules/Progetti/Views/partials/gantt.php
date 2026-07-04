<?php
$weeks       = $weeks ?? [];
$rows        = $rows ?? [];
$todayOffset = $today_offset ?? null;
$weekCount   = max(1, count($weeks));

$taskStatuses      = \App\Modules\Progetti\Services\ProgettiService::getTaskStatuses();
$milestoneStatuses = \App\Modules\Progetti\Services\ProgettiService::getMilestoneStatuses();
?>

<?php if (empty($weeks) || empty($rows)): ?>
<div class="text-center text-muted py-5">
    <i class="fa-solid fa-bars-staggered fa-2x d-block mb-2 opacity-50"></i>
    <p class="small"><?= e(t('progetti.gantt.no_data')) ?><br><?= e(t('progetti.gantt.no_data_hint')) ?></p>
</div>
<?php else: ?>

<!-- ── Legenda ──────────────────────────────────────────────────── -->
<div class="prj-gantt-legend d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="fw-semibold text-muted me-1 prj-gantt-legend-title"><?= e(t('progetti.gantt.legend')) ?></span>
    <?php foreach ($taskStatuses as $sKey => $sMeta): ?>
    <span class="prj-legend-item">
        <span class="prj-legend-dot is-task-<?= e($sKey) ?>"></span>
        <?= e($sMeta['label']) ?>
    </span>
    <?php endforeach; ?>
    <span class="prj-legend-sep">·</span>
    <span class="fw-semibold text-muted me-1"><?= e(t('progetti.gantt.milestone_legend')) ?></span>
    <?php foreach ($milestoneStatuses as $msKey => $msMeta): ?>
    <span class="prj-legend-item">
        <span class="prj-legend-dot is-ms-<?= e($msKey) ?>"></span>
        <?= e($msMeta['label']) ?>
    </span>
    <?php endforeach; ?>
    <?php if ($todayOffset !== null): ?>
    <span class="prj-legend-sep">·</span>
    <span class="prj-legend-item">
        <span class="prj-legend-today-dot"></span>
        <?= e(t('progetti.gantt.today')) ?>
    </span>
    <?php endif; ?>
</div>

<!-- ── Griglia ──────────────────────────────────────────────────── -->
<div class="prj-gantt-scroll">
    <div class="prj-gantt-grid"
         data-prj-weeks="<?= (int) $weekCount ?>"
         data-prj-min-width="<?= (int) max(500, $weekCount * 55 + 200) ?>">

        <!-- Intestazione colonne -->
        <div class="prj-gantt-head">
            <div class="prj-gantt-cell prj-gantt-label"><?= e(t('progetti.gantt.header_item')) ?></div>
            <?php foreach ($weeks as $i => $w): ?>
            <div class="prj-gantt-cell <?= $todayOffset === $i ? 'prj-gantt-head-today' : '' ?>">
                <?= e((string) ($w['label'] ?? '')) ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Righe task / milestone -->
        <?php foreach ($rows as $r):
            $offset   = max(0, (int) ($r['offset'] ?? 0));
            $duration = max(1, (int) ($r['duration'] ?? 1));
            $kind     = (string) ($r['row_type'] ?? 'task');
            $status   = (string) ($r['task_status'] ?? 'todo');
            $startFmt = (string) ($r['start_date_fmt'] ?? '');
            $endFmt   = (string) ($r['end_date_fmt'] ?? '');
            $label    = (string) ($r['row_label'] ?? '');

            if ($kind === 'task') {
                $statusLabel = $taskStatuses[$status]['label'] ?? $status;
                $barClass    = 'is-task is-task-' . $status;
                $tooltip     = $label . ' · ' . $startFmt . ' – ' . $endFmt . ' · ' . $statusLabel;
            } else {
                $msStatusLabel = $milestoneStatuses[$status]['label'] ?? $status;
                $barClass = 'is-milestone is-ms-' . $status;
                $tooltip  = '⬦ ' . $label . ' · ' . $endFmt . ' · ' . $msStatusLabel;
            }
        ?>
        <div class="prj-gantt-row">
            <div class="prj-gantt-cell prj-gantt-label" title="<?= e($label) ?>">
                <?php if ($kind === 'milestone'): ?>
                <i class="fa-solid fa-diamond fa-xs me-1 text-<?= e($milestoneStatuses[$status]['color'] ?? 'secondary') ?>"></i>
                <?php else: ?>
                <i class="fa-solid <?= e($taskStatuses[$status]['icon'] ?? 'fa-circle') ?> fa-xs me-1 text-<?= e($taskStatuses[$status]['color'] ?? 'secondary') ?>"></i>
                <?php endif; ?>
                <?= e($label) ?>
            </div>
            <div class="prj-gantt-track<?= $todayOffset !== null ? ' prj-gantt-track-today' : '' ?>"<?= $todayOffset !== null ? ' style="--prj-today:' . (int) $todayOffset . '"' : '' ?>>
                <div class="prj-gantt-bar <?= e($barClass) ?>"
                     data-prj-offset="<?= (int) $offset ?>"
                     data-prj-duration="<?= (int) $duration ?>"
                     data-prj-gantt-type="<?= e($kind) ?>"
                     data-prj-gantt-id="<?= (int) ($r['row_id'] ?? 0) ?>"
                     title="<?= e($tooltip) ?>"
                     role="button">
                    <span class="prj-gantt-bar-label"><?= e($label) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- SVG overlay per frecce dipendenze -->
        <svg class="prj-gantt-arrows" id="prj-gantt-arrows"></svg>
    </div>
</div>
<?php endif; ?>
