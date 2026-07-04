<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php
use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Helpers\UiHelper;

$stato     = (string) ($doc['stato'] ?? '');
$userId    = (int) ($user['id'] ?? 0);
$isOwner   = (int) ($doc['owner_user_id'] ?? 0) === $userId;
$isAdmin   = has_permission('documenti.admin');
$canEdit   = $isAdmin || $isOwner;
$canDelete = $canEdit && has_permission('documenti.delete') || $isAdmin;
?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $buttons  = StatoHelper::badge($stato, 'fs-6 me-2');
if ($canEdit && in_array($stato, ['bozza', 'rifiutato'], true)) {
    $buttons .= '<a href="' . e(route('documenti.edit', ['id' => $doc['id']])) . '" class="btn btn-outline-primary btn-sm">'
             . '<i class="fa-solid fa-pen me-1" aria-hidden="true"></i>' . e(t('documenti.show.modifica_btn')) . '</a>';
}
if ($canDelete && $stato === 'bozza') {
    $buttons .= '<form method="post" action="' . e(route('documenti.destroy', ['id' => $doc['id']])) . '" class="d-inline" data-app-confirm="' . e(t('documenti.show.elimina_confirm', ['titolo' => $doc['titolo']])) . '">'
             . csrf_field()
             . '<input type="hidden" name="_method" value="DELETE">'
             . '<button class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>' . e(t('documenti.show.elimina_btn')) . '</button>'
             . '</form>';
}

$subtitleParts = [];
if (!empty($categoria['nome'])) {
    $subtitleParts[] = e($categoria['nome']);
}
if (!empty($doc['protocollo'])) {
    $subtitleParts[] = '<code class="dc-code">' . e($doc['protocollo']) . '</code>';
}
$view->include('partials/pf-hero-module', [
    'moduleName'     => $doc['titolo'],
    'moduleIcon'     => 'fa-solid fa-file-alt',
    'moduleSubtitle' => implode(' &middot; ', $subtitleParts),
    'moduleButtons'  => $buttons,
]);
?>
    </div>

    <div class="col-lg-8">

        <?php if (!empty($doc['descrizione'])): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-align-left me-1" aria-hidden="true"></i><?= e(t('documenti.show.descrizione')) ?></div>
            <div class="card-body">
                <p class="mb-0"><?= nl2br(e($doc['descrizione'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i><?= e(t('documenti.show.versioni')) ?></span>
                <?php if ($canEdit && in_array($stato, ['bozza', 'rifiutato', 'pubblicato'], true)): ?>
                <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#modalNuovaVersione">
                    <i class="fa-solid fa-upload me-1" aria-hidden="true"></i><?= e(t('documenti.show.carica_versione_btn')) ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body" id="dc-versioni-container">
                <?php $view->include('Documenti/Views/partials/timeline_versioni', [
            'versioni'           => $versioni,
            'docId'              => $doc['id'],
            'versioneCorrenteId' => $doc['versione_corrente_id'] ?? null,
        ]); ?>
            </div>
        </div>

        <div id="dc-approvazione-container">
            <?php $view->include('Documenti/Views/partials/pannello_approvazione', [
        'doc'          => $doc,
        'approvazioni' => $approvazioni,
        'user'         => $user,
    ]); ?>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-link me-1" aria-hidden="true"></i><?= e(t('documenti.show.correlati')) ?></span>
                <?php if (has_permission('documenti.manage_collegamenti')): ?>
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#modalCollegamento">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i><?= e(t('documenti.show.aggiungi_btn')) ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0" id="dc-collegamenti-container">
                <?php $view->include('Documenti/Views/partials/pannello_collegamenti', [
            'collegamenti' => $collegamenti,
            'docId'        => $doc['id'],
        ]); ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">

        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?= e(t('documenti.show.dettagli')) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item">
                    <div class="row gx-2">
                        <div class="col-5 text-muted"><?= e(t('documenti.create.categoria_label')) ?></div>
                        <div class="col-7"><?= e($categoria['nome'] ?? '—') ?></div>
                    </div>
                </li>
                <li class="list-group-item">
                    <div class="row gx-2">
                        <div class="col-5 text-muted"><?= e(t('documenti.create.scadenza_label')) ?></div>
                        <div class="col-7">
                            <?php if (!empty($doc['scade_il'])):
                                $g = (int) ceil((strtotime($doc['scade_il']) - time()) / 86400);
                                $cls = StatoHelper::urgencyClass($g);
                                ?>
                                <span class="<?= $cls ? 'dc-urgent-chip ' . e($cls) : '' ?>" data-bs-toggle="tooltip" title="<?= e(format_date($doc['scade_il'], 'long')) ?>">
                                    <?= e(format_date($doc['scade_il'], 'short')) ?>
                                </span>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                </li>
                <li class="list-group-item">
                    <div class="row gx-2">
                        <div class="col-5 text-muted"><?= e(t('documenti.create.tag_label')) ?></div>
                        <div class="col-7"><?= !empty($doc['tag']) ? e($doc['tag']) : '—' ?></div>
                    </div>
                </li>
                <li class="list-group-item">
                    <div class="row gx-2">
                        <div class="col-5 text-muted"><?= e(t('documenti.show.approvazione_col')) ?></div>
                        <div class="col-7">
                            <?php if (!empty($doc['approvazione_richiesta'])): ?>
                                <i class="fa-solid fa-circle-check text-success me-1" aria-hidden="true"></i><?= e(t('documenti.show.richiesta')) ?>
                            <?php else: ?>
                                <i class="fa-solid fa-circle-minus text-muted me-1" aria-hidden="true"></i><?= e(t('documenti.show.non_richiesta')) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <li class="list-group-item">
                    <div class="row gx-2">
                        <div class="col-5 text-muted"><?= e(t('documenti.show.creato_il')) ?></div>
                        <div class="col-7" data-bs-toggle="tooltip" title="<?= e(format_date($doc['created_at'], 'long')) ?>">
                            <?= e(format_date($doc['created_at'], 'short')) ?>
                        </div>
                    </div>
                </li>
                <li class="list-group-item">
                    <div class="row gx-2">
                        <div class="col-5 text-muted"><?= e(t('documenti.show.aggiornato')) ?></div>
                        <div class="col-7" data-bs-toggle="tooltip" title="<?= e(format_date($doc['updated_at'] ?? '', 'long')) ?>">
                            <?= e(UiHelper::timeAgo($doc['updated_at'] ?? null)) ?>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

    </div>

</div>
</div>

<!-- Modal: nuova versione -->
<?php if ($canEdit && in_array($stato, ['bozza', 'rifiutato', 'pubblicato'], true)): ?>
<div class="modal fade" id="modalNuovaVersione" tabindex="-1" aria-labelledby="modalNuovaVersioneTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNuovaVersioneTitle"><i class="fa-solid fa-upload me-2" aria-hidden="true"></i><?= e(t('documenti.show.carica_nuova_versione_title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('documenti.create.chiudi')) ?>"></button>
            </div>
            <div class="modal-body">
                <?php $view->include('Documenti/Views/partials/dropzone', [
                    'docId'     => (int) $doc['id'],
                    'maxSizeMb' => 20,
                ]); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: nuovo collegamento -->
<?php if (has_permission('documenti.manage_collegamenti')): ?>
<div class="modal fade" id="modalCollegamento" tabindex="-1" aria-labelledby="modalCollegamentoTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= e(route('documenti.collegamenti.store', ['id' => $doc['id']])) ?>" data-dirty-check>
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCollegamentoTitle"><i class="fa-solid fa-link me-2" aria-hidden="true"></i><?= e(t('documenti.show.aggiungi_collegamento_title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('documenti.create.chiudi')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="dc-coll-dest"><?= e(t('documenti.show.dest_id_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                        <input type="number" id="dc-coll-dest" name="destinazione_id" class="form-control" min="1" required>
                        <small class="form-text text-muted"><?= e(t('documenti.show.dest_id_help')) ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dc-coll-tipo"><?= e(t('documenti.show.tipo_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                        <select id="dc-coll-tipo" name="tipo" class="form-select" required>
                            <option value=""><?= e(t('documenti.show.tipo_placeholder')) ?></option>
                            <option value="sostituisce"><?= e(t('documenti.collegamento_tipo.sostituisce')) ?></option>
                            <option value="allegato"><?= e(t('documenti.collegamento_tipo.allegato')) ?></option>
                            <option value="correlato"><?= e(t('documenti.collegamento_tipo.correlato')) ?></option>
                            <option value="riferimento"><?= e(t('documenti.collegamento_tipo.riferimento')) ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dc-coll-note"><?= e(t('documenti.show.note_label')) ?></label>
                        <input type="text" id="dc-coll-note" name="note" class="form-control" maxlength="500" placeholder="<?= e(t('documenti.create.opzionale')) ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('documenti.create.annulla_btn')) ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-link me-1" aria-hidden="true"></i><?= e(t('documenti.show.collega_btn')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php $view->end(); ?>
