<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Helpers\UiHelper;

$items = $result['items'] ?? $result['data'] ?? [];
$total = (int) ($result['total'] ?? count($items));
$users = $users ?? [];
?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $heroButtons = '<span class="dc-hero-pill"><i class="fa-solid fa-files me-1" aria-hidden="true"></i>'
                     . e(t('documenti.admin_elenco.totali_pill', ['count' => number_format($total, 0, ',', '.')])) . '</span>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('documenti.admin_elenco.hero_title'),
    'moduleIcon'     => 'fa-solid fa-file-lines',
    'moduleSubtitle' => t('documenti.admin_elenco.subtitle'),
    'moduleButtons'  => $heroButtons,
]); ?>
    </div>

    <?php if (!empty($filters)): ?>
    <div class="col-12">
        <?php $view->include('Documenti/Views/partials/filtri', [
    'filters'   => $filters,
    'categorie' => $categorie ?? [],
    'action'    => route('documenti.admin.elenco'),
]); ?>
    </div>
    <?php endif; ?>

    <div class="col-12">
        <?php if (empty($items)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-folder-open',
        'titolo'    => t('documenti.admin_elenco.empty_titolo'),
        'messaggio' => t('documenti.admin_elenco.empty_messaggio'),
    ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="card-header py-2 small text-muted">
                <?= e(tc('documenti.table.count', $total)) ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= e(t('documenti.filtri.protocollo_col')) ?></th>
                            <th><?= e(t('documenti.create.titolo_label')) ?></th>
                            <th><?= e(t('documenti.admin_elenco.owner_col')) ?></th>
                            <th><?= e(t('documenti.widget.col_stato')) ?></th>
                            <th><?= e(t('documenti.admin_elenco.creato_col')) ?></th>
                            <th class="text-end" style="width:14rem"><?= e(t('documenti.scadenze.col_azioni')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><code class="dc-code"><?= e($item['protocollo'] ?? '—') ?></code></td>
                            <td>
                                <a href="<?= e(route('documenti.show', ['id' => $item['id']])) ?>" class="text-decoration-none fw-semibold">
                                    <?= e($item['titolo']) ?>
                                </a>
                            </td>
                            <td><small><?= e($item['owner_name'] ?? t('documenti.timeline.utente_fallback', ['id' => (int) $item['owner_user_id']])) ?></small></td>
                            <td><?= StatoHelper::badge((string) $item['stato']) ?></td>
                            <td>
                                <small class="text-muted"
                                       data-bs-toggle="tooltip"
                                       title="<?= e(format_date($item['created_at'], 'long')) ?>">
                                    <?= e(format_date($item['created_at'], 'compact')) ?>
                                </small>
                            </td>
                            <td class="text-end">
                                <?php if (!empty($users)): ?>
                                <form method="post"
                                      action="<?= e(route('documenti.admin.riassegna_owner', ['id' => $item['id']])) ?>"
                                      class="d-inline-flex gap-1 align-items-center">
                                    <?= csrf_field() ?>
                                    <label class="visually-hidden" for="dc-owner-<?= (int) $item['id'] ?>"><?= e(t('documenti.admin_elenco.riassegna_owner_label')) ?></label>
                                    <select name="owner_user_id"
                                            id="dc-owner-<?= (int) $item['id'] ?>"
                                            class="form-select form-select-sm dc-select-owner">
                                        <?php foreach ($users as $u): ?>
                                        <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === (int) $item['owner_user_id'] ? 'selected' : '' ?>>
                                            <?= e($u['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= UiHelper::ariaButton(t('documenti.admin_elenco.riassegna_owner_label'), 'fa-user-pen', [
                                'type'  => 'submit',
                                'class' => 'btn btn-sm btn-outline-secondary',
                            ]) ?>
                                </form>
                                <?php endif; ?>
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
