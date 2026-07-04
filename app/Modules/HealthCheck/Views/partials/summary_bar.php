<?php
/**
 * @var array $summary ['ok' => int, 'warn' => int, 'fail' => int]
 */
$total = array_sum($summary);
$okPct = $total > 0 ? (int) round(($summary['ok'] / $total) * 100) : 0;
$globalState = $summary['fail'] > 0 ? t('healthcheck.summary.global_critical') : ($summary['warn'] > 0 ? t('healthcheck.summary.global_warning') : t('healthcheck.summary.global_stable'));
$globalClass = $summary['fail'] > 0 ? 'hc-fail' : ($summary['warn'] > 0 ? 'hc-warn' : 'hc-ok');
$focusText = $summary['fail'] > 0
    ? t('healthcheck.summary.focus_fail')
    : ($summary['warn'] > 0 ? t('healthcheck.summary.focus_warn') : t('healthcheck.summary.focus_ok'));
$issueCount = $summary['warn'] + $summary['fail'];
?>
<div class="hc-summary-shell mb-4">
    <div class="hc-summary-bar d-flex align-items-center gap-3 flex-wrap">
        <div class="hc-summary-badge <?= e($globalClass) ?>">
            <i class="fa-solid fa-wave-square me-1"></i>
            <?= e(t('healthcheck.summary.global_state')) ?> <strong class="ms-1"><?= e($globalState) ?></strong>
        </div>
        <div class="hc-summary-badge hc-ok">
            <i class="fa-solid fa-circle-check me-1"></i>
            <strong><?= e($summary['ok']) ?></strong> <?= e(t('healthcheck.summary.ok_checks')) ?>
        </div>
        <div class="hc-summary-badge hc-warn">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            <strong><?= e($summary['warn']) ?></strong> <?= e(t('healthcheck.summary.warnings')) ?>
        </div>
        <div class="hc-summary-badge hc-fail">
            <i class="fa-solid fa-circle-xmark me-1"></i>
            <strong><?= e($summary['fail']) ?></strong> <?= e(t('healthcheck.summary.errors')) ?>
        </div>
        <div class="ms-auto text-muted small">
            <?= e($total) ?> <?= e(t('healthcheck.summary.total_run')) ?>
        </div>
    </div>
    <div class="hc-summary-note">
        <i class="fa-solid fa-clipboard-check me-2"></i>
        <span><?= e($focusText) ?></span>
        <span class="hc-summary-separator"></span>
        <span><?= e(t('healthcheck.summary.issues_to_check', ['count' => $issueCount])) ?></span>
    </div>
</div>
