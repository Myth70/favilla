<?php
/**
 * Partial: dropzone upload file (drag-and-drop con progress bar).
 *
 * Variabili:
 *   $docId       (int)
 *   $maxSizeMb   (int, default 20)
 *   $async       (bool, default false) — submit XHR con progress
 *   $accept      (string|null) — attributo accept del file input
 *   $acceptLabel (string|null) — testo descrittivo dei tipi accettati
 */
$maxSizeMb   = $maxSizeMb   ?? 20;
$async       = $async       ?? false;
$accept      = $accept      ?? '.pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.ppt,.pptx,.txt,.csv,.zip,.rar';
$acceptLabel = $acceptLabel ?? t('documenti.dropzone.accept_label_default');
$inputId     = 'dc-file-input-' . (int) $docId;
?>
<div class="dc-dropzone" data-max-mb="<?= (int) $maxSizeMb ?>">
    <form method="post"
          action="<?= e(route('documenti.versioni.store', ['id' => $docId])) ?>"
          enctype="multipart/form-data"
          <?= $async ? 'data-async="1"' : '' ?>>
        <?= csrf_field() ?>
        <div class="dc-drop-area text-center p-4">
            <i class="fa-solid fa-cloud-arrow-up fa-3x text-primary mb-2" aria-hidden="true"></i>
            <p class="mb-1 fw-semibold">
                <?= e(t('documenti.dropzone.trascina_qui')) ?>
                <label for="<?= e($inputId) ?>" class="text-primary dc-clickable"><?= e(t('documenti.dropzone.seleziona_dal_computer')) ?></label>
            </p>
            <p class="text-muted small mb-0">
                <?= e(t('documenti.dropzone.max_size', ['size' => $maxSizeMb])) ?> — <?= e($acceptLabel) ?>
            </p>
            <input type="file"
                   name="file"
                   id="<?= e($inputId) ?>"
                   class="visually-hidden"
                   required
                   accept="<?= e($accept) ?>"
                   aria-label="<?= e(t('documenti.dropzone.scegli_file')) ?>">
        </div>
        <div class="dc-drop-preview d-none mt-2">
            <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0">
                <i class="fa-solid fa-file-circle-check" aria-hidden="true"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="dc-drop-filename text-truncate fw-semibold"></div>
                    <small class="dc-drop-filesize text-muted"></small>
                </div>
                <button type="button" class="btn-close dc-drop-clear" aria-label="<?= e(t('documenti.dropzone.rimuovi_file')) ?>"></button>
            </div>
        </div>
        <div class="dc-progress-wrap d-none mt-2">
            <div class="dc-progress-bar" role="progressbar" aria-label="<?= e(t('documenti.dropzone.avanzamento_upload')) ?>"></div>
        </div>
        <div class="mt-2">
            <label for="dc-note-<?= (int) $docId ?>" class="form-label small mb-1"><?= e(t('documenti.dropzone.note_label')) ?></label>
            <input type="text"
                   id="dc-note-<?= (int) $docId ?>"
                   name="note"
                   class="form-control form-control-sm"
                   placeholder="<?= e(t('documenti.dropzone.note_placeholder')) ?>"
                   maxlength="500">
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-2">
            <i class="fa-solid fa-upload me-1" aria-hidden="true"></i><?= e(t('documenti.show.carica_versione_btn')) ?>
        </button>
    </form>
</div>
