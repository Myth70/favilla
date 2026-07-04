<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php $view->start('content'); ?>
<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
        <?php
        $buttons = '';
if (has_permission('documenti.manage_categorie')) {
    $buttons = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuovaCategoria">'
             . '<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>' . e(t('documenti.categorie.nuova_btn')) . '</button>';
}
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('documenti.categorie.title'),
    'moduleIcon'     => 'fa-solid fa-folder-tree',
    'moduleSubtitle' => t('documenti.categorie.subtitle'),
    'moduleButtons'  => $buttons,
]); ?>
    </div>

    <div class="col-12">
        <?php if (empty($categorie)): ?>
            <?php $view->include('Documenti/Views/partials/empty_state', [
        'icon'      => 'fa-folder-tree',
        'titolo'    => t('documenti.categorie.empty_titolo'),
        'messaggio' => t('documenti.categorie.empty_messaggio'),
    ]); ?>
        <?php else: ?>
        <div class="card">
            <div class="card-body">
                <?php $view->include('Documenti/Views/partials/albero_categorie', ['categorie' => $categorie]); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<?php if (has_permission('documenti.manage_categorie')): ?>
<!-- Modal: nuova categoria -->
<div class="modal fade" id="modalNuovaCategoria" tabindex="-1" aria-labelledby="modalNuovaCategoriaTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= e(route('documenti.categorie.store')) ?>" data-dirty-check>
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuovaCategoriaTitle"><i class="fa-solid fa-folder-plus me-2" aria-hidden="true"></i><?= e(t('documenti.categorie.nuova_btn')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('documenti.create.chiudi')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="dc-cat-nome"><?= e(t('documenti.create.qcat_nome_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                        <input type="text" id="dc-cat-nome" name="nome" class="form-control" required maxlength="255"
                               placeholder="<?= e(t('documenti.create.qcat_nome_placeholder')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dc-cat-codice"><?= e(t('documenti.create.qcat_codice_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                        <input type="text" id="dc-cat-codice" name="codice" class="form-control" required maxlength="20"
                               style="text-transform:uppercase"
                               placeholder="<?= e(t('documenti.create.qcat_codice_placeholder')) ?>">
                        <small class="form-text text-muted"><?= e(t('documenti.categorie.codice_help')) ?> <code class="dc-code">DOC-{CODICE}-{YYYY}-{NNNN}</code>.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dc-cat-parent"><?= e(t('documenti.create.qcat_parent_label')) ?></label>
                        <?php $view->include('Documenti/Views/partials/selettore_categoria', [
                    'categorie' => $categorie,
                    'selected'  => '',
                    'fieldName' => 'parent_id',
                    'fieldId'   => 'dc-cat-parent',
                    'required'  => false,
                    'cssClass'  => 'form-select',
                ]); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dc-cat-descr"><?= e(t('documenti.create.descrizione_label')) ?></label>
                        <textarea id="dc-cat-descr" name="descrizione" class="form-control" rows="2" placeholder="<?= e(t('documenti.create.opzionale')) ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('documenti.create.annulla_btn')) ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i><?= e(t('documenti.create.crea_categoria_btn')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: sposta categoria -->
<div class="modal fade" id="modalSpostaCategoria" tabindex="-1" aria-labelledby="modalSpostaCategoriaTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="dc-form-sposta-categoria" data-dirty-check>
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSpostaCategoriaTitle"><i class="fa-solid fa-arrows-up-down-left-right me-2" aria-hidden="true"></i><?= e(t('documenti.categorie.sposta_title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('documenti.create.chiudi')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3"><?= e(t('documenti.categorie.sposta_intro')) ?> &laquo;<strong id="dc-sposta-nome">—</strong>&raquo;.</p>
                    <div class="mb-3">
                        <label class="form-label" for="dc-sposta-parent"><?= e(t('documenti.categorie.nuovo_parent_label')) ?></label>
                        <?php $view->include('Documenti/Views/partials/selettore_categoria', [
                    'categorie' => $categorie,
                    'selected'  => '',
                    'fieldName' => 'parent_id',
                    'fieldId'   => 'dc-sposta-parent',
                    'required'  => false,
                    'cssClass'  => 'form-select',
                ]); ?>
                        <small class="form-text text-muted"><?= e(t('documenti.categorie.sposta_help')) ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('documenti.create.annulla_btn')) ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check me-1" aria-hidden="true"></i><?= e(t('documenti.categorie.sposta_btn')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    'use strict';
    const modal   = document.getElementById('modalSpostaCategoria');
    if (!modal) return;
    const form    = document.getElementById('dc-form-sposta-categoria');
    const nameLbl = document.getElementById('dc-sposta-nome');
    modal.addEventListener('show.bs.modal', function (e) {
        const trigger = e.relatedTarget;
        if (!trigger) return;
        const id   = trigger.getAttribute('data-cat-id');
        const nome = trigger.getAttribute('data-cat-nome') || '—';
        if (nameLbl) nameLbl.textContent = nome;
        const template = <?= json_encode(route('documenti.categorie.sposta', ['id' => '__ID__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
        form.action = template.replace('__ID__', encodeURIComponent(id));
    });
})();
</script>
<?php endif; ?>

<?php $view->end(); ?>
