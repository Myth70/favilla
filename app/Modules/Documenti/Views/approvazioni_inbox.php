<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Helpers\UiHelper;

$items = $result['items'] ?? $result['data'] ?? [];
$total = (int) ($result['total'] ?? count($items));
?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $heroButtons = $total > 0
            ? '<span class="dc-hero-pill"><i class="fa-solid fa-clock me-1" aria-hidden="true"></i>' . e(t('documenti.inbox.in_attesa_pill', ['count' => $total])) . '</span>'
            : '';
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('documenti.inbox.hero_title'),
    'moduleIcon'     => 'fa-solid fa-inbox',
    'moduleSubtitle' => t('documenti.inbox.subtitle'),
    'moduleButtons'  => $heroButtons,
]); ?>
    </div>

    <div class="col-12">
        <?php if (empty($items)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-inbox',
        'titolo'    => t('documenti.inbox.empty_titolo'),
        'messaggio' => t('documenti.inbox.empty_messaggio'),
    ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('documenti.widget.col_documento')) ?></th>
                            <th><?= e(t('documenti.widget.col_stato')) ?></th>
                            <th><?= e(t('documenti.widget.col_categoria')) ?></th>
                            <th><?= e(t('documenti.inbox.col_in_attesa_da')) ?></th>
                            <th class="text-end dc-no-print" style="width:6rem"><?= e(t('documenti.scadenze.col_azioni')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                    $aggiornato = $item['updated_at'] ?? $item['created_at'] ?? null;
                            $diffGiorni = $aggiornato ? (int) floor((time() - strtotime($aggiornato)) / 86400) : 0;
                            $rowCls = $diffGiorni >= 7 ? 'dc-urgent-soon' : '';
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
                            <td><?= StatoHelper::badge($item['stato']) ?></td>
                            <td><small><?= e($item['categoria_nome'] ?? '—') ?></small></td>
                            <td>
                                <span class="dc-urgent-chip <?= e($rowCls) ?>"
                                      data-bs-toggle="tooltip"
                                      title="<?= e(format_date($aggiornato, 'long')) ?>">
                                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                                    <?= e(UiHelper::timeAgo($aggiornato)) ?>
                                </span>
                            </td>
                            <td class="text-end dc-no-print">
                                <?= UiHelper::ariaButton(t('documenti.scadenze.apri_documento'), 'fa-arrow-right', [
                                    'href'  => route('documenti.show', ['id' => $item['id']]),
                                    'class' => 'btn btn-sm btn-outline-primary',
                                    'title' => t('documenti.scadenze.apri_documento'),
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
