<?php
/**
 * HTMX partial — contenuto modale anteprima file.
 * Variabili: $fileRecord, $previewType, $previewUrl
 */
?>
<div class="modal-header">
    <h5 class="modal-title text-truncate fm-preview-modal-title">
        <i class="fa-solid fa-eye"></i><?= e($fileRecord['original_name']) ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
</div>
<div class="modal-body p-0 fm-preview-modal-body">
    <?php if ($previewType === 'image'): ?>
        <div class="text-center p-3">
            <img src="<?= e($previewUrl) ?>"
                 alt="<?= e($fileRecord['original_name']) ?>"
                 class="img-fluid rounded fm-preview-modal-image">
        </div>
    <?php elseif ($previewType === 'pdf'): ?>
        <iframe src="<?= e($previewUrl) ?>"
                class="w-100 d-block fm-preview-modal-frame"
                title="<?= e($fileRecord['original_name']) ?>">
        </iframe>
    <?php else: ?>
        <div class="text-center py-5 px-3">
            <i class="fa-solid fa-file fa-4x text-muted mb-3"></i>
            <p class="text-muted mb-0"><?= e(t('files.modal.preview_unavailable')) ?></p>
        </div>
    <?php endif; ?>
</div>
<div class="modal-footer">
    <a href="<?= e(route('files.show', ['id' => $fileRecord['id']])) ?>"
       class="btn btn-outline-secondary">
        <i class="fa-solid fa-info-circle me-1"></i><?= e(t('files.modal.details')) ?>
    </a>
    <a href="<?= e(route('files.download', ['id' => $fileRecord['id']])) ?>"
       class="btn btn-primary">
        <i class="fa-solid fa-download me-1"></i><?= e(t('files.modal.download')) ?>
    </a>
</div>
