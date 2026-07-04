<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
$okCount = 0;
$koCount = 0;
foreach (($checks ?? []) as $c) {
    if (($c['status'] ?? '') === 'ok') {
        $okCount++;
    } else {
        $koCount++;
    }
}
?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $heroButtons = '<span class="dc-hero-pill"><i class="fa-solid fa-check me-1 text-success" aria-hidden="true"></i>' . $okCount . ' OK</span>'
                     . ($koCount > 0
                        ? '<span class="dc-hero-pill text-danger ms-2"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>' . $koCount . ' KO</span>'
                        : '');
$heroButtons .= '<button type="button" class="btn btn-sm btn-outline-secondary ms-2"'
             . ' hx-get="' . e(route('documenti.admin.health')) . '"'
             . ' hx-target="#dc-health-table"'
             . ' hx-select="#dc-health-table"'
             . ' hx-trigger="click"'
             . ' aria-label="' . e(t('documenti.admin_health.aggiorna_tip')) . '" title="' . e(t('documenti.admin_health.aggiorna_tip')) . '">'
             . '<i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('documenti.admin_health.hero_title'),
    'moduleIcon'     => 'fa-solid fa-heart-pulse',
    'moduleSubtitle' => t('documenti.admin_health.subtitle'),
    'moduleButtons'  => $heroButtons,
]); ?>
    </div>

    <div class="col-12">
        <div id="dc-health-table">
        <?php if (empty($checks)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-stethoscope',
        'titolo'    => t('documenti.admin_health.empty_titolo'),
        'messaggio' => '',
    ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('documenti.admin_health.componente_col')) ?></th>
                            <th><?= e(t('documenti.widget.col_stato')) ?></th>
                            <th><?= e(t('documenti.admin_health.dettaglio_col')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $check):
                            $status = (string) ($check['status'] ?? (!empty($check['ok']) ? 'ok' : 'error'));
                            $ok     = $status === 'ok';
                            $label  = (string) ($check['label'] ?? $check['name'] ?? '');
                            $detail = (string) ($check['detail'] ?? '');
                            $hint   = (string) ($check['hint'] ?? '');
                            ?>
                        <tr class="<?= $ok ? '' : ($status === 'warning' ? 'table-warning' : 'table-danger') ?>">
                            <td class="fw-semibold"><?= e($label) ?></td>
                            <td>
                                <?php if ($status === 'ok'): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i><?= e(t('documenti.admin_health.stato_ok')) ?></span>
                                <?php elseif ($status === 'warning'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i><?= e(t('documenti.admin_health.stato_warning')) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1" aria-hidden="true"></i><?= e(t('documenti.admin_health.stato_ko')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($detail !== ''): ?>
                                    <small><?= e($detail) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">—</small>
                                <?php endif; ?>
                                <?php if (!$ok && $hint !== ''): ?>
                                    <div class="small text-warning-emphasis mt-1">
                                        <i class="fa-solid fa-lightbulb me-1" aria-hidden="true"></i><?= e($hint) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        </div>
    </div>

</div>
</div>
<?php $view->end(); ?>
