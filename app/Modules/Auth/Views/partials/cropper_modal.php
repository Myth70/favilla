<?php
/**
 * Cropper modal — reusable Bootstrap 5 modal for avatar cropping with Cropper.js.
 * Include this partial in any page that needs avatar cropping.
 * Requires: cropper.min.css, cropper.min.js, avatar-cropper.js, avatar-cropper.css
 */
?>
<div class="modal fade" id="pf-cropper-modal" tabindex="-1" aria-labelledby="pf-cropper-modal-label"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pf-cropper-modal-label">
                    <i class="fa-solid fa-crop-simple"></i><?= e(t('auth.cropper.title')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
            </div>
            <div class="modal-body p-0">
                <div class="pf-cropper-container">
                    <img id="pf-cropper-image" src="" alt="<?= e(t('auth.cropper.image_alt')) ?>">
                </div>
                <div class="pf-cropper-toolbar">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cropper-action="rotate-left"
                            title="<?= e(t('auth.cropper.rotate_left')) ?>">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cropper-action="rotate-right"
                            title="<?= e(t('auth.cropper.rotate_right')) ?>">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cropper-action="zoom-in"
                            title="<?= e(t('auth.cropper.zoom_in')) ?>">
                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cropper-action="zoom-out"
                            title="<?= e(t('auth.cropper.zoom_out')) ?>">
                        <i class="fa-solid fa-magnifying-glass-minus"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cropper-action="reset"
                            title="<?= e(t('auth.cropper.reset')) ?>">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= e(t('common.action.cancel')) ?>
                </button>
                <button type="button" class="btn btn-primary" id="pf-cropper-save">
                    <i class="fa-solid fa-check me-1"></i><?= e(t('common.action.save')) ?>
                </button>
            </div>
        </div>
    </div>
</div>
