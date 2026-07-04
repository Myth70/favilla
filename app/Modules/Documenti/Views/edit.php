<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/documenti.css'); ?>
<?php $view->pushScript('js/documenti.js'); ?>

<?php $view->start('content'); ?>
<div class="container-xl">
<div class="row justify-content-center">
<div class="col-xl-9">

    <div class="d-flex align-items-center justify-content-between mb-3 mt-3 flex-wrap gap-2">
        <h2 class="mb-0"><i class="fa-solid fa-pen-to-square me-2 text-primary" aria-hidden="true"></i><?= e(t('documenti.edit.heading')) ?></h2>
        <a href="<?= e(route('documenti.show', ['id' => $doc['id']])) ?>" class="btn btn-link text-muted text-decoration-none">
            <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i><?= e(t('documenti.edit.back_link')) ?>
        </a>
    </div>

    <?php if (!empty($errors['_general'])): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i><?= e($errors['_general']) ?>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= e(route('documenti.update', ['id' => $doc['id']])) ?>" data-dirty-check>
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">

        <div class="card mb-3">
            <div class="card-header"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?= e(t('documenti.create.info_principali')) ?></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="dc-edit-titolo"><?= e(t('documenti.create.titolo_label')) ?> <span class="text-danger" aria-label="<?= e(t('documenti.create.obbligatorio')) ?>">*</span></label>
                    <input type="text" id="dc-edit-titolo" name="titolo"
                           class="form-control <?= !empty($errors['titolo']) ? 'is-invalid' : '' ?>"
                           value="<?= e($old['titolo'] ?? $doc['titolo']) ?>" maxlength="500" required>
                    <?php if (!empty($errors['titolo'])): ?>
                        <div class="invalid-feedback"><?= e($errors['titolo']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="dc-edit-descr"><?= e(t('documenti.create.descrizione_label')) ?></label>
                    <textarea id="dc-edit-descr" name="descrizione" class="form-control" rows="3"><?= e($old['descrizione'] ?? $doc['descrizione'] ?? '') ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="dc-edit-scad"><?= e(t('documenti.create.scadenza_label')) ?></label>
                        <input type="date" id="dc-edit-scad" name="scade_il" class="form-control"
                               value="<?= e(substr((string)($old['scade_il'] ?? $doc['scade_il'] ?? ''), 0, 10)) ?>">
                        <small class="form-text text-muted"><?= e(t('documenti.edit.scadenza_help')) ?></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="dc-edit-tag"><?= e(t('documenti.create.tag_label')) ?></label>
                        <input type="text" id="dc-edit-tag" name="tag" class="form-control" maxlength="255"
                               value="<?= e($old['tag'] ?? $doc['tag'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-check form-switch mb-0">
                    <input type="checkbox" name="approvazione_richiesta" value="1"
                           id="dc-edit-appr" class="form-check-input"
                           <?= !empty($old['approvazione_richiesta'] ?? $doc['approvazione_richiesta']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="dc-edit-appr">
                        <?= e(t('documenti.create.approvazione_richiesta_label')) ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?= e(route('documenti.show', ['id' => $doc['id']])) ?>" class="btn btn-outline-secondary"><?= e(t('documenti.create.annulla_btn')) ?></a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i><?= e(t('documenti.edit.salva_btn')) ?>
            </button>
        </div>
    </form>

</div>
</div>
</div>
<?php $view->end(); ?>
