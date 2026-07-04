<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php $view->start('content'); ?>
<div class="container-xl">
<div class="row justify-content-center">
<div class="col-xl-9">

    <div class="d-flex align-items-center justify-content-between mb-3 mt-3 flex-wrap gap-2">
        <h2 class="mb-0"><i class="fa-solid fa-file-circle-plus me-2 text-primary" aria-hidden="true"></i><?= e(t('documenti.create.heading')) ?></h2>
        <a href="<?= e(route('documenti.index')) ?>" class="btn btn-link text-muted text-decoration-none">
            <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= e(t('documenti.create.back_link')) ?>
        </a>
    </div>

    <?php if (!empty($errors['_general'])): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i><?= e($errors['_general']) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($categorie)): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="fa-solid fa-folder-plus mt-1" aria-hidden="true"></i>
        <div>
            <?= e(t('documenti.create.no_categorie')) ?>
            <?php if (has_permission('documenti.manage_categorie')): ?>
                <a href="<?= e(route('documenti.categorie.index')) ?>" class="alert-link"><?= e(t('documenti.create.crea_categoria_link')) ?></a>.
            <?php else: ?>
                <?= e(t('documenti.create.contatta_admin')) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= e(route('documenti.store')) ?>" enctype="multipart/form-data" data-dirty-check>
        <?= csrf_field() ?>

        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?= e(t('documenti.create.info_principali')) ?></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="dc-create-titolo"><?= e(t('documenti.create.titolo_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                    <input type="text" id="dc-create-titolo" name="titolo"
                           class="form-control <?= !empty($errors['titolo']) ? 'is-invalid' : '' ?>"
                           value="<?= e($old['titolo'] ?? '') ?>" maxlength="500" required autofocus>
                    <?php if (!empty($errors['titolo'])): ?>
                        <div class="invalid-feedback"><?= e($errors['titolo']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="dc-create-descr"><?= e(t('documenti.create.descrizione_label')) ?></label>
                    <textarea id="dc-create-descr" name="descrizione" class="form-control" rows="3"
                              placeholder="<?= e(t('documenti.create.descrizione_placeholder')) ?>"><?= e($old['descrizione'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="dc-create-cat"><?= e(t('documenti.create.categoria_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                    <div class="d-flex gap-2 align-items-start" id="dc-create-cat-wrap">
                        <div class="flex-grow-1">
                            <?php $view->include('Documenti/Views/partials/selettore_categoria', [
                                'categorie'   => $categorie,
                                'selected'    => $old['categoria_id'] ?? '',
                                'fieldName'   => 'categoria_id',
                                'fieldId'     => 'dc-create-cat',
                                'required'    => true,
                                'cssClass'    => !empty($errors['categoria_id']) ? 'form-select is-invalid' : 'form-select',
                            ]); ?>
                        </div>
                        <?php if (has_permission('documenti.manage_categorie')): ?>
                        <button type="button"
                                class="btn btn-outline-secondary flex-shrink-0"
                                data-bs-toggle="modal"
                                data-bs-target="#dc-modal-quick-cat"
                                title="<?= e(t('documenti.create.nuova_categoria_tip')) ?>"
                                aria-label="<?= e(t('documenti.create.nuova_categoria_tip')) ?>">
                            <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($errors['categoria_id'])): ?>
                        <div class="invalid-feedback d-block"><?= e($errors['categoria_id']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="dc-create-scad"><?= e(t('documenti.create.scadenza_label')) ?></label>
                        <input type="date" id="dc-create-scad" name="scade_il" class="form-control"
                               value="<?= e($old['scade_il'] ?? '') ?>"
                               min="<?= date('Y-m-d') ?>">
                        <small class="form-text text-muted"><?= e(t('documenti.create.scadenza_help')) ?></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="dc-create-tag"><?= e(t('documenti.create.tag_label')) ?></label>
                        <input type="text" id="dc-create-tag" name="tag" class="form-control" maxlength="255"
                               value="<?= e($old['tag'] ?? '') ?>" placeholder="<?= e(t('documenti.create.tag_placeholder')) ?>">
                        <small class="form-text text-muted"><?= e(t('documenti.create.tag_help')) ?></small>
                    </div>
                </div>

                <div class="form-check form-switch mb-0">
                    <input type="checkbox" name="approvazione_richiesta" value="1"
                           id="dc-create-appr" class="form-check-input"
                           <?= !empty($old['approvazione_richiesta']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="dc-create-appr">
                        <?= e(t('documenti.create.approvazione_richiesta_label')) ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-paperclip me-1" aria-hidden="true"></i><?= e(t('documenti.create.file_allegato')) ?></div>
            <div class="card-body">
                <label class="form-label" for="dc-create-file"><?= e(t('documenti.create.scegli_file')) ?></label>
                <input type="file" id="dc-create-file" name="file" class="form-control"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.ppt,.pptx,.txt,.csv,.zip,.rar">
                <small class="form-text text-muted">
                    <?= e(t('documenti.create.file_help')) ?>
                </small>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?= e(route('documenti.index')) ?>" class="btn btn-outline-secondary"><?= e(t('documenti.create.annulla_btn')) ?></a>
            <button type="submit" class="btn btn-primary" <?= empty($categorie) ? 'disabled' : '' ?>>
                <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i><?= e(t('documenti.create.crea_btn')) ?>
            </button>
        </div>
    </form>

</div>
</div>
</div>

<?php if (has_permission('documenti.manage_categorie')): ?>
<!-- Modal: nuova categoria al volo -->
<div class="modal fade" id="dc-modal-quick-cat" tabindex="-1"
     aria-labelledby="dc-modal-quick-cat-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="dc-quick-cat-form"
                  data-quick-url="<?= e(route('documenti.categorie.quickStore')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="dc-modal-quick-cat-title">
                        <i class="fa-solid fa-folder-plus me-2" aria-hidden="true"></i><?= e(t('documenti.create.nuova_categoria_tip')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('documenti.create.chiudi')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div id="dc-quick-cat-error" class="alert alert-danger d-none" role="alert"></div>
                    <div class="mb-3">
                        <label class="form-label" for="dc-qcat-nome"><?= e(t('documenti.create.qcat_nome_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                        <input type="text" id="dc-qcat-nome" name="nome"
                               class="form-control" required maxlength="255"
                               placeholder="<?= e(t('documenti.create.qcat_nome_placeholder')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dc-qcat-codice"><?= e(t('documenti.create.qcat_codice_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                        <input type="text" id="dc-qcat-codice" name="codice"
                               class="form-control" required maxlength="20"
                               style="text-transform:uppercase"
                               placeholder="<?= e(t('documenti.create.qcat_codice_placeholder')) ?>">
                        <small class="form-text text-muted"><?= e(t('documenti.create.qcat_codice_help')) ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dc-qcat-parent"><?= e(t('documenti.create.qcat_parent_label')) ?> <span class="text-muted fw-normal">(<?= e(t('documenti.create.opzionale')) ?>)</span></label>
                        <?php $view->include('Documenti/Views/partials/selettore_categoria', [
                            'categorie' => $categorie,
                            'selected'  => '',
                            'fieldName' => 'parent_id',
                            'fieldId'   => 'dc-qcat-parent',
                            'required'  => false,
                            'cssClass'  => 'form-select',
                        ]); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('documenti.create.annulla_btn')) ?></button>
                    <button type="submit" class="btn btn-primary" id="dc-quick-cat-submit">
                        <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i><?= e(t('documenti.create.crea_categoria_btn')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php $view->end(); ?>
