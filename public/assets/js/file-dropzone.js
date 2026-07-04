/**
 * FileDropzone — drag-and-drop + click-to-browse file upload widget.
 *
 * Replaces plain <input type="file"> with a styled drop area that shows
 * an image preview (for images) or filename badge.
 * Validates type and size on the client before the form is submitted.
 *
 * Usage:
 *   FileDropzone.init('#my-dropzone', {
 *     inputId    : 'my-file-input',      // id of the hidden <input type="file">
 *     accept     : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
 *     maxBytes   : 2097152,              // 2 MB default
 *     previewId  : 'my-preview',        // optional <img> element for preview
 *     onFile     : function(file) {},   // optional callback with File object
 *   });
 *
 * Required HTML structure:
 *   <div id="my-dropzone" class="fd-zone">
 *     <input type="file" id="my-file-input" name="avatar" class="fd-input" accept="image/*">
 *     <div class="fd-label">
 *       <i class="fa-solid fa-cloud-arrow-up"></i>
 *       <span class="fd-text">Trascina un file qui o <u>clicca per sfogliare</u></span>
 *       <small class="fd-hint">JPG, PNG, GIF, WebP — max 2 MB</small>
 *     </div>
 *     <div class="fd-preview d-none">
 *       <img id="my-preview" src="" alt="Preview">
 *       <span class="fd-filename"></span>
 *     </div>
 *   </div>
 *
 * Loaded via $view->pushScript('js/file-dropzone.js') on pages with file upload forms.
 */
(function (global) {
    'use strict';

    function notifyDropzone(message, type, options) {
        if (typeof window.notify === 'function') {
            window.notify(Object.assign({
                message: message,
                type: type || 'info',
                source: 'file-dropzone'
            }, options || {}));
            return;
        }

        console.warn('[file-dropzone]', message);
    }

    var FileDropzone = {

        /**
         * @param {string}  zoneSelector  CSS selector for the drop zone container.
         * @param {Object}  options
         */
        init: function (zoneSelector, options) {
            var zone = document.querySelector(zoneSelector);
            if (!zone) return;

            var cfg = Object.assign({
                inputId  : null,
                accept   : ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                maxBytes : 2097152,
                previewId: null,
                onFile   : null,
            }, options || {});

            var input = cfg.inputId
                ? document.getElementById(cfg.inputId)
                : zone.querySelector('input[type="file"]');
            if (!input) return;

            // Click on zone → open file picker
            zone.addEventListener('click', function (e) {
                if (e.target !== input) input.click();
            });

            // Drag events
            zone.addEventListener('dragover', function (e) {
                e.preventDefault();
                zone.classList.add('fd-dragover');
            });
            ['dragleave', 'dragend'].forEach(function (ev) {
                zone.addEventListener(ev, function () {
                    zone.classList.remove('fd-dragover');
                });
            });
            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                zone.classList.remove('fd-dragover');
                var file = e.dataTransfer.files[0];
                if (file) FileDropzone._handleFile(file, input, zone, cfg);
            });

            // Input change (click-to-browse path)
            input.addEventListener('change', function () {
                var file = input.files[0];
                if (file) FileDropzone._handleFile(file, input, zone, cfg);
            });
        },

        // ── Private ─────────────────────────────────────────────────────

        _handleFile: function (file, input, zone, cfg) {
            // Client-side validation
            if (cfg.accept.length > 0 && !cfg.accept.includes(file.type)) {
                notifyDropzone('Formato non supportato: ' + file.type + '. Usa: ' + cfg.accept.join(', '), 'warning', {
                    title: 'File non supportato',
                    channel: 'banner',
                    duration: 10000
                });
                return;
            }
            if (file.size > cfg.maxBytes) {
                var maxMb = (cfg.maxBytes / 1048576).toFixed(1);
                notifyDropzone('Il file supera il limite di ' + maxMb + ' MB.', 'warning', {
                    title: 'File troppo grande',
                    channel: 'banner',
                    duration: 10000
                });
                return;
            }

            // Assign file to the hidden input via DataTransfer (works in modern browsers)
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;

            // Show preview for images
            if (file.type.startsWith('image/')) {
                var previewEl = cfg.previewId ? document.getElementById(cfg.previewId) : zone.querySelector('.fd-preview img');
                if (previewEl) {
                    var reader = new FileReader();
                    reader.onload = function (ev) {
                        previewEl.src = ev.target.result;
                        previewEl.classList.remove('d-none');
                    };
                    reader.readAsDataURL(file);
                }
            }

            // Show filename badge
            var nameEl = zone.querySelector('.fd-filename');
            if (nameEl) nameEl.textContent = file.name;

            // Toggle label / preview sections
            var label   = zone.querySelector('.fd-label');
            var preview = zone.querySelector('.fd-preview');
            if (label)   label.classList.add('d-none');
            if (preview) preview.classList.remove('d-none');

            if (typeof cfg.onFile === 'function') {
                cfg.onFile(file);
            }
        },
    };

    global.FileDropzone = FileDropzone;

})(window);
