<?php
/**
 * Partial HTMX: summary + card dei check + timestamp.
 *
 * @var \App\Core\View $view
 * @var array $results
 * @var array $summary
 * @var array $groups
 * @var bool  $deep
 */
$executedAt = date('d/m/Y') . ' ' . t('healthcheck.content.date_at') . ' ' . date('H:i:s');
$allOk = $summary['warn'] === 0 && $summary['fail'] === 0;
$deep = $deep ?? false;
?>
<?php $view->include('HealthCheck/Views/partials/summary_bar', ['summary' => $summary]); ?>

<div class="d-flex justify-content-between align-items-center text-muted small mb-3">
    <span>
        <?php if ($deep): ?>
            <i class="fa-solid fa-magnifying-glass-chart me-1"></i><?= e(t('healthcheck.content.deep_scan')) ?>
        <?php else: ?>
            <i class="fa-solid fa-bolt me-1"></i><?= e(t('healthcheck.content.quick_checks')) ?>
        <?php endif; ?>
    </span>
    <span>
        <i class="fa-solid fa-clock me-1"></i><?= e(t('healthcheck.content.executed_at', ['date' => $executedAt])) ?>
    </span>
</div>

<?php if ($allOk): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-check fa-lg"></i>
        <span><?= e(t('healthcheck.content.all_ok')) ?></span>
    </div>
<?php endif; ?>

<div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-3">
    <?php foreach ($groups as $group): ?>
        <?php
        $warnCount = (int) ($group['counts']['warn'] ?? 0);
        $failCount = (int) ($group['counts']['fail'] ?? 0);
        if ($allOk || $warnCount > 0 || $failCount > 0):
        ?>
        <div class="col">
            <?php $view->include('HealthCheck/Views/partials/check_card', ['group' => $group]); ?>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
