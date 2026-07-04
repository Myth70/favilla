<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Helpers\UiHelper;

$items = $result['items'] ?? $result['data'] ?? [];
?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $heroButtons = '';
if (!empty($items)) {
    $heroButtons .= '<button type="button" class="btn btn-sm btn-outline-secondary dc-no-print" onclick="window.print()" title="' . e(t('documenti.scadenze.stampa_btn')) . '">'
        . '<i class="fa-solid fa-print me-1" aria-hidden="true"></i>' . e(t('documenti.scadenze.stampa_btn')) . '</button>';
}
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('documenti.scadenze.title'),
    'moduleIcon'     => 'fa-solid fa-calendar-exclamation',
    'moduleSubtitle' => t('documenti.scadenze.subtitle'),
    'moduleButtons'  => $heroButtons,
]); ?>
    </div>

    <div class="col-12">
        <?php if (empty($items)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-calendar-check',
        'titolo'    => t('documenti.scadenze.empty_titolo'),
        'messaggio' => t('documenti.scadenze.empty_messaggio'),
    ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('documenti.widget.col_documento')) ?></th>
                            <th><?= e(t('documenti.widget.col_categoria')) ?></th>
                            <th><?= e(t('documenti.widget.col_scadenza')) ?></th>
                            <th><?= e(t('documenti.scadenze.col_urgenza')) ?></th>
                            <th class="text-end dc-no-print" style="width:7rem"><?= e(t('documenti.scadenze.col_azioni')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item):
                            $giorni  = (int) ceil((strtotime($item['scade_il']) - time()) / 86400);
                            $urgCls  = StatoHelper::urgencyClass($giorni);
                            $urgLab  = StatoHelper::urgencyLabel($giorni);
                            ?>
                        <tr>
                            <td>
                                <a href="<?= e(route('documenti.show', ['id' => $item['id']])) ?>" class="fw-semibold text-decoration-none">
                                    <?= e($item['titolo']) ?>
                                </a>
                                <?php if (!empty($item['protocollo'])): ?>
                                    <small class="d-block"><code class="dc-code"><?= e($item['protocollo']) ?></code></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= e($item['categoria_nome'] ?? '—') ?></small></td>
                            <td><?= e(format_date($item['scade_il'], 'short')) ?></td>
                            <td>
                                <span class="dc-urgent-chip <?= e($urgCls) ?>">
                                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                    <?= e($urgLab) ?>
                                </span>
                            </td>
                            <td class="text-end dc-no-print">
                                <?= UiHelper::ariaButton(t('documenti.scadenze.apri_documento'), 'fa-arrow-up-right-from-square', [
                                        'href'  => route('documenti.show', ['id' => $item['id']]),
                                        'class' => 'btn btn-sm btn-outline-primary',
                                    ]) ?>
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
<?php $view->end(); ?>
