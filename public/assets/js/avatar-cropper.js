/**
 * avatar-cropper.js — Shared Cropper.js avatar module.
 * Exposes window.AvatarCropper for use by profile.js, teams.js, etc.
 * Requires: Cropper.js (vendor/cropper.min.js), Bootstrap 5 modal.
 */
(function () {
    'use strict';

    var cropper     = null;
    var bsModal     = null;
    var opts        = {};
    var initialized = false;

    /**
     * Resolve DOM elements lazily (ensures they exist even if init is called early).
     */
    function getModal()   { return document.getElementById('pf-cropper-modal'); }
    function getImgEl()   { return document.getElementById('pf-cropper-image'); }
    function getSaveBtn() { return document.getElementById('pf-cropper-save'); }

    /**
     * Initialize the avatar cropper.
     * @param {Object} options
     * @param {string} options.context     'profile' or 'team'
     * @param {number} options.contextId   userId or conversationId
     * @param {string} options.cropUrl     Route to POST /api/avatar/crop
     * @param {string} options.csrfToken   CSRF token value
     * @param {Function} options.onSuccess callback(data) — data: { success, filename, url }
     * @param {Function} [options.onError] callback(errorMsg)
     */
    function init(options) {
        opts = options || {};

        if (initialized) return;

        var modal   = getModal();
        var saveBtn = getSaveBtn();
        if (!modal || !saveBtn) return;

        initialized = true;

        // Toolbar actions
        modal.querySelectorAll('[data-cropper-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!cropper) return;
                var action = btn.getAttribute('data-cropper-action');
                switch (action) {
                    case 'rotate-left':  cropper.rotate(-90); break;
                    case 'rotate-right': cropper.rotate(90);  break;
                    case 'zoom-in':      cropper.zoom(0.1);   break;
                    case 'zoom-out':     cropper.zoom(-0.1);  break;
                    case 'reset':        cropper.reset();      break;
                }
            });
        });

        // Save button
        saveBtn.addEventListener('click', handleSave);

        // Cleanup cropper when modal is fully hidden
        modal.addEventListener('hidden.bs.modal', function () {
            destroyCropper();
        });
    }

    /**
     * Update the context (useful when switching conversations in Teams).
     * @param {string} context    'profile' or 'team'
     * @param {number} contextId  userId or conversationId
     */
    function setContext(context, contextId) {
        opts.context   = context;
        opts.contextId = contextId;
    }

    /**
     * Open the cropper modal with a File object.
     * @param {File} file
     */
    function openFile(file) {
        var reader = new FileReader();
        reader.onload = function (e) {
            openWithSrc(e.target.result);
        };
        reader.readAsDataURL(file);
    }

    /**
     * Open the cropper modal with an image URL (e.g. from FilePicker).
     * @param {string} src
     */
    function openWithSrc(src) {
        var modal = getModal();
        var imgEl = getImgEl();
        if (!modal || !imgEl) return;

        // Destroy previous instance
        destroyCropper();

        // Ensure Bootstrap modal instance
        bsModal = bootstrap.Modal.getOrCreateInstance(modal);

        imgEl.src = src;

        // Attach listener BEFORE show to avoid race condition
        modal.addEventListener('shown.bs.modal', function onShown() {
            modal.removeEventListener('shown.bs.modal', onShown);
            initCropper();
        });

        bsModal.show();
    }

    /**
     * Initialize Cropper.js on the image element.
     */
    function initCropper() {
        var imgEl = getImgEl();
        if (!imgEl) return;

        if (cropper) cropper.destroy();

        cropper = new Cropper(imgEl, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.9,
            restore: false,
            guides: false,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            minCropBoxWidth: 64,
            minCropBoxHeight: 64
        });
    }

    /**
     * Handle save button click — crop, upload, callback.
     */
    function handleSave() {
        // Fallback: read cropper from img element if closure var not set
        if (!cropper) {
            var imgEl = getImgEl();
            cropper = imgEl ? imgEl.cropper : null;
        }
        if (!cropper) return;

        var saveBtn = getSaveBtn();
        if (!saveBtn) return;

        saveBtn.classList.add('is-loading');
        var originalHtml = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + t('js.avatar_cropper.saving', 'Salvataggio...');

        var canvas = cropper.getCroppedCanvas({
            width: 256,
            height: 256,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });

        canvas.toBlob(function (blob) {
            if (!blob) {
                restoreButton(saveBtn, originalHtml);
                if (opts.onError) opts.onError(t('js.avatar_cropper.generate_error', 'Errore nella generazione dell\'immagine.'));
                return;
            }

            var formData = new FormData();
            formData.append('cropped_image', blob, 'avatar.png');
            formData.append('context', opts.context || 'profile');
            formData.append('context_id', String(opts.contextId || 0));

            fetch(opts.cropUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': opts.csrfToken || ''
                },
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                restoreButton(saveBtn, originalHtml);
                if (data.success) {
                    if (bsModal) bsModal.hide();
                    if (opts.onSuccess) opts.onSuccess(data);
                } else {
                    if (opts.onError) opts.onError(data.error || t('js.avatar_cropper.save_error', 'Errore durante il salvataggio.'));
                }
            })
            .catch(function () {
                restoreButton(saveBtn, originalHtml);
                if (opts.onError) opts.onError(t('js.avatar_cropper.network_error', 'Errore di rete. Riprova.'));
            });
        }, 'image/png');
    }

    function restoreButton(btn, html) {
        btn.classList.remove('is-loading');
        btn.innerHTML = html;
    }

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        var imgEl = getImgEl();
        if (imgEl) {
            imgEl.src = '';
        }
    }

    // Public API
    window.AvatarCropper = {
        init: init,
        setContext: setContext,
        open: openFile,
        openWithSrc: openWithSrc,
        destroy: destroyCropper
    };

})();
