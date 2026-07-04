<?php
/**
 * @var array $group
 */

$icons = [
    'ok'   => 'fa-circle-check text-success',
    'warn' => 'fa-triangle-exclamation text-warning',
    'fail' => 'fa-circle-xmark text-danger',
];

$checks = $group['checks'] ?? [];
$okCount = (int) ($group['counts']['ok'] ?? 0);
$warnCount = (int) ($group['counts']['warn'] ?? 0);
$failCount = (int) ($group['counts']['fail'] ?? 0);
$hasFail = $failCount > 0;
$hasWarn = $warnCount > 0;
$hasIssues = $hasFail || $hasWarn;
$headerClass = $hasFail ? 'border-danger' : ($hasWarn ? 'border-warning' : 'border-success');
$statusLabel = $hasFail ? t('healthcheck.card.status_critical') : ($hasWarn ? t('healthcheck.card.status_warn') : t('healthcheck.card.status_ok'));

$actionableChecks = array_filter($checks, fn($c) => $c['status'] !== 'ok');
?>
<div class="card mb-3 hc-card <?= $headerClass ?>">
    <div class="card-header d-flex align-items-center gap-2">
        <?php if ($hasFail): ?>
            <i class="fa-solid fa-circle-xmark text-danger"></i>
        <?php elseif ($hasWarn): ?>
            <i class="fa-solid fa-triangle-exclamation text-warning"></i>
        <?php else: ?>
            <i class="fa-solid fa-circle-check text-success"></i>
        <?php endif; ?>
        <div>
            <strong><?= e($group['label']) ?></strong>
            <?php if (!empty($group['description'])): ?>
                <div class="text-muted small"><?= e($group['description']) ?></div>
            <?php endif; ?>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="hc-state-pill <?= $hasFail ? 'hc-fail' : ($hasWarn ? 'hc-warn' : 'hc-ok') ?>"><?= e($statusLabel) ?></span>
            <?php if ($warnCount > 0): ?>
                <span class="hc-history-badge hc-warn" title="<?= e(t('healthcheck.card.warnings_tip')) ?>"><?= e($warnCount) ?></span>
            <?php endif; ?>
            <?php if ($failCount > 0): ?>
                <span class="hc-history-badge hc-fail" title="<?= e(t('healthcheck.card.errors_tip')) ?>"><?= e($failCount) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($hasIssues && !empty($actionableChecks)): ?>
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <tbody>
                <?php foreach ($actionableChecks as $check): ?>
                    <tr class="hc-row-<?= e($check['status']) ?>">
                        <td class="ps-3 py-2 hc-col-icon">
                            <i class="fa-solid <?= $icons[$check['status']] ?>"></i>
                        </td>
                        <td class="py-2 fw-medium"><?= e($check['name']) ?></td>
                        <td class="py-2 text-muted small pe-3"><?= e($check['detail']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</div>
