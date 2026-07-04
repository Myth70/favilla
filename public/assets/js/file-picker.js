/**
 * FilePicker — modal per selezionare file dalla libreria.
 *
 * API pubblica:
 *   FilePicker.open(targetFieldId, previewId, mimeFilter)
 *   FilePicker.select(value, label, previewUrl)
 *
 * Bottone trigger (HTML):
 *   <button type="button"
 *           onclick="FilePicker.open('field_id', 'preview_id', 'image')">
 *     Dalla libreria
 *   </button>
 *
 * Il valore salvato nel campo nascosto è il path relativo da uploads/
 * (es. "files/stored_name.jpg").
 */
(function () {
    'use strict';

    var _targetFieldId = null;
    var _previewId     = null;
    var _mimeFilter    = '';

    var FilePicker = {

        /**
         * Apre il modal e carica i risultati iniziali.
         * @param {string} targetFieldId  ID del campo hidden da popolare
         * @param {string|null} previewId ID dell'elemento preview (null = nessun preview)
         * @param {string} mimeFilter     Filtro mime opzionale ('image', 'document', ecc.)
         */
        open: function (targetFieldId, previewId, mimeFilter) {
            _targetFieldId = targetFieldId;
            _previewId     = previewId || null;
            _mimeFilter    = mimeFilter || '';

            var modalEl    = document.getElementById('filePicker');
            var searchEl   = document.getElementById('fp-search');
            var mimeEl     = document.getElementById('fp-mime-filter');
            var resultsEl  = document.getElementById('fp-results');

            if (!modalEl || !searchEl) return;

            // Imposta filtro mime
            if (mimeEl) mimeEl.value = _mimeFilter;

            // Reset campo ricerca
            searchEl.value = '';

            // Costruisce URL base con eventuale filtro mime
            var baseUrl = searchEl.getAttribute('data-picker-url') || '';
            var loadUrl = baseUrl + (_mimeFilter ? '?mime=' + encodeURIComponent(_mimeFilter) : '');

            // Aggiorna hx-get sull'input di ricerca (con il filtro mime già embedded)
            searchEl.setAttribute('hx-get', baseUrl);
            if (typeof htmx !== 'undefined') {
                htmx.process(searchEl);
            }

            // Mostra il modal Bootstrap
            var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();

            // Carica risultati iniziali via HTMX
            if (resultsEl && typeof htmx !== 'undefined') {
                resultsEl.innerHTML = '<div class="text-center text-muted py-4">'
                    + '<i class="fa-solid fa-spinner fa-spin me-2"></i>Caricamento...</div>';
                htmx.ajax('GET', loadUrl, { target: '#fp-results', swap: 'innerHTML' });
            }
        },

        /**
         * Seleziona un file dalla griglia picker.
         * Chiamata dal click su ogni item nella picker_grid.php.
         * @param {string} value      Path relativo da uploads/ (es. "files/img.jpg")
         * @param {string} label      Nome originale del file
         * @param {string} previewUrl URL completo per preview
         */
        select: function (value, label, previewUrl) {
            // Popola il campo nascosto
            var field = _targetFieldId ? document.getElementById(_targetFieldId) : null;
            if (field) field.value = value;

            // Mostra preview se richiesto
            if (_previewId) {
                var preview = document.getElementById(_previewId);
                if (preview) {
                    if (previewUrl && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(previewUrl)) {
                        preview.innerHTML = '<img src="' + previewUrl
                            + '" style="max-height:120px;max-width:100%;border-radius:6px;margin-top:.5rem" alt="preview">';
                    } else {
                        preview.innerHTML = '<span class="badge text-bg-secondary mt-1">'
                            + label.replace(/[<>]/g, '') + '</span>';
                    }
                }
            }

            // Chiude il modal
            var modalEl = document.getElementById('filePicker');
            if (modalEl) {
                var bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
            }
        }
    };

    window.FilePicker = FilePicker;

    // Event delegation for picker grid items (CSP-friendly, replaces inline onclick)
    document.addEventListener('click', function (e) {
        var target = e.target.closest('[data-fp-pick]');
        if (!target) return;
        e.preventDefault();
        FilePicker.select(
            target.getAttribute('data-fp-value') || '',
            target.getAttribute('data-fp-label') || '',
            target.getAttribute('data-fp-url') || ''
        );
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var target = e.target.closest('[data-fp-pick]');
        if (!target) return;
        e.preventDefault();
        FilePicker.select(
            target.getAttribute('data-fp-value') || '',
            target.getAttribute('data-fp-label') || '',
            target.getAttribute('data-fp-url') || ''
        );
    });

}());
