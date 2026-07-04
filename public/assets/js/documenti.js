/**
 * Documenti module JS — IIFE wrapped.
 * Handles: dropzone (drag/drop + progress), searchable selects,
 * sortable table headers, bulk selection, dirty form guard,
 * tooltips, clipboard copy.
 */
(function () {
    'use strict';

    /* ── Notify shim ──────────────────────────────────────── */
    function notify(type, message) {
        if (window.notify) {
            window.notify({ type: type, message: message, channel: 'toast' });
        } else {
            console[type === 'error' || type === 'danger' ? 'error' : 'log'](message);
        }
    }

    /* ── Dropzone drag-and-drop + progress ────────────────── */
    function initDropzone(root) {
        root = root || document;
        const zones = root.querySelectorAll('.dc-dropzone');
        zones.forEach(initSingleDropzone);
    }

    function initSingleDropzone(zone) {
        if (zone.dataset.dcInited === '1') return;
        zone.dataset.dcInited = '1';

        const area     = zone.querySelector('.dc-drop-area');
        const input    = zone.querySelector('input[type="file"]');
        const preview  = zone.querySelector('.dc-drop-preview');
        const nameLbl  = zone.querySelector('.dc-drop-filename');
        const sizeLbl  = zone.querySelector('.dc-drop-filesize');
        const clearBtn = zone.querySelector('.dc-drop-clear');
        const form     = zone.querySelector('form');
        const progress = zone.querySelector('.dc-progress-wrap');
        const progBar  = zone.querySelector('.dc-progress-bar');
        const maxMb    = parseInt(zone.dataset.maxMb || '20', 10);

        if (!area || !input) return;

        ['dragenter', 'dragover'].forEach(evt => {
            area.addEventListener(evt, e => {
                e.preventDefault();
                area.classList.add('dc-dragging');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            area.addEventListener(evt, e => {
                e.preventDefault();
                area.classList.remove('dc-dragging');
            });
        });
        area.addEventListener('drop', e => {
            const files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length > 0) setFile(files[0]);
        });

        input.addEventListener('change', () => {
            if (input.files && input.files[0]) setFile(input.files[0]);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                input.value = '';
                if (preview) preview.classList.add('d-none');
            });
        }

        // Async upload via XHR if data-async="1"
        if (form && form.dataset.async === '1') {
            form.addEventListener('submit', function (e) {
                if (!input.files || !input.files[0]) return;
                e.preventDefault();
                const fd = new FormData(form);
                const xhr = new XMLHttpRequest();
                xhr.open(form.method.toUpperCase() || 'POST', form.action);
                xhr.upload.addEventListener('progress', function (ev) {
                    if (ev.lengthComputable && progress && progBar) {
                        progress.classList.remove('d-none');
                        progBar.style.width = ((ev.loaded / ev.total) * 100).toFixed(1) + '%';
                    }
                });
                xhr.addEventListener('load', function () {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        notify('success', t('js.documenti.caricamento_completato', 'Caricamento completato.'));
                        if (form.dataset.reloadAfter !== '0') {
                            setTimeout(() => window.location.reload(), 600);
                        }
                    } else {
                        notify('danger', t('js.documenti.errore_caricamento', 'Errore caricamento ({status}).').replace('{status}', xhr.status));
                    }
                });
                xhr.addEventListener('error', () => notify('danger', t('js.documenti.errore_rete', 'Errore di rete.')));
                xhr.send(fd);
            });
        }

        function setFile(file) {
            const sizeMb = file.size / (1024 * 1024);
            if (sizeMb > maxMb) {
                notify('danger', t('js.documenti.file_troppo_grande', 'File troppo grande. Max {max} MB.').replace('{max}', maxMb));
                return;
            }
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;

            if (nameLbl) nameLbl.textContent = file.name;
            if (sizeLbl) sizeLbl.textContent = sizeMb.toFixed(2) + ' MB';
            if (preview) preview.classList.remove('d-none');
        }
    }

    /* ── Bootstrap tooltips ──────────────────────────────── */
    function initTooltips(root) {
        root = root || document;
        if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Tooltip) return;
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (!window.bootstrap.Tooltip.getInstance(el)) {
                try { new window.bootstrap.Tooltip(el); } catch (e) {}
            }
        });
    }

    /* ── Searchable select (sostituisce Choices.js) ──────── */
    function initSearchable(root) {
        root = root || document;
        root.querySelectorAll('select.dc-searchable').forEach(initSingleSearchable);
    }

    function initSingleSearchable(select) {
        if (select.dataset.dcInited === '1') return;
        select.dataset.dcInited = '1';

        const placeholder = select.dataset.placeholder || t('js.documenti.cerca_placeholder', 'Cerca…');
        const wrap = document.createElement('div');
        wrap.className = 'dc-searchable-wrap';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm dc-searchable-input';
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.placeholder = placeholder;

        const dropdown = document.createElement('div');
        dropdown.className = 'dc-searchable-dropdown';
        dropdown.setAttribute('role', 'listbox');

        // Build option list from the native <select>
        const options = Array.from(select.options).filter(o => o.value !== '');
        function renderList(filterText) {
            dropdown.innerHTML = '';
            const filt = (filterText || '').toLowerCase().trim();
            let count = 0;
            options.forEach(opt => {
                const label = opt.textContent || '';
                if (filt && !label.toLowerCase().includes(filt)) return;
                const item = document.createElement('div');
                item.className = 'dc-searchable-option';
                item.setAttribute('role', 'option');
                item.dataset.value = opt.value;
                item.textContent = label;
                if (opt.value === select.value) item.classList.add('dc-active');
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    select.value = opt.value;
                    input.value = label.trim();
                    dropdown.classList.remove('dc-open');
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                dropdown.appendChild(item);
                count++;
            });
            if (count === 0) {
                const empty = document.createElement('div');
                empty.className = 'dc-searchable-option text-muted';
                empty.textContent = t('js.documenti.nessun_risultato', 'Nessun risultato');
                dropdown.appendChild(empty);
            }
        }

        input.addEventListener('focus', () => { renderList(input.value); dropdown.classList.add('dc-open'); });
        input.addEventListener('input', () => { renderList(input.value); dropdown.classList.add('dc-open'); });
        input.addEventListener('blur',  () => { setTimeout(() => dropdown.classList.remove('dc-open'), 150); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { dropdown.classList.remove('dc-open'); input.blur(); }
        });

        // Initial label from current selection
        const current = Array.from(select.options).find(o => o.value === select.value && o.value !== '');
        if (current) input.value = (current.textContent || '').trim();

        // Hide native select; place wrap before it
        select.classList.add('d-none');
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(input);
        wrap.appendChild(dropdown);
        wrap.appendChild(select);
    }

    /* ── Sortable table headers ──────────────────────────── */
    function initSortable(root) {
        root = root || document;
        root.querySelectorAll('th.dc-sort[data-sort]').forEach(function (th) {
            if (th.dataset.dcInited === '1') return;
            th.dataset.dcInited = '1';
            th.addEventListener('click', function () {
                const field = th.dataset.sort;
                const url = new URL(window.location.href);
                const currentSort = url.searchParams.get('sort');
                const currentDir  = (url.searchParams.get('dir') || 'DESC').toUpperCase();
                let newDir = 'ASC';
                if (currentSort === field) newDir = currentDir === 'ASC' ? 'DESC' : 'ASC';
                url.searchParams.set('sort', field);
                url.searchParams.set('dir', newDir);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });
        });
    }

    /* ── Bulk selection bar ──────────────────────────────── */
    function initBulk(root) {
        root = root || document;
        root.querySelectorAll('[data-bulk-scope]').forEach(function (scope) {
            if (scope.dataset.dcInited === '1') return;
            scope.dataset.dcInited = '1';
            const all = scope.querySelector('[data-bulk-toggle-all]');
            const items = () => scope.querySelectorAll('[data-bulk-item]');
            const bar = document.querySelector(scope.dataset.bulkBar || '');
            const countEl = bar ? bar.querySelector('.dc-bulk-count') : null;
            function refresh() {
                const checked = Array.from(items()).filter(c => c.checked);
                if (countEl) countEl.textContent = checked.length;
                if (bar) bar.classList.toggle('dc-visible', checked.length > 0);
                if (all) {
                    all.checked = checked.length === items().length && items().length > 0;
                    all.indeterminate = checked.length > 0 && checked.length < items().length;
                }
                // Push selected IDs into bulk-action forms
                const ids = checked.map(c => c.value).join(',');
                document.querySelectorAll('[data-bulk-ids-input]').forEach(inp => { inp.value = ids; });
            }
            if (all) all.addEventListener('change', () => {
                items().forEach(c => { c.checked = all.checked; });
                refresh();
            });
            scope.addEventListener('change', e => {
                if (e.target.matches('[data-bulk-item]')) refresh();
            });
            refresh();
        });
    }

    /* ── Dirty form guard ────────────────────────────────── */
    function initDirtyGuard(root) {
        root = root || document;
        root.querySelectorAll('form[data-dirty-check]').forEach(function (form) {
            if (form.dataset.dcInited === '1') return;
            form.dataset.dcInited = '1';
            const initial = new FormData(form);
            const snapshot = {};
            initial.forEach((v, k) => { snapshot[k] = v; });
            let dirty = false;
            form.addEventListener('input', () => { dirty = true; });
            form.addEventListener('submit', () => { dirty = false; });
            window.addEventListener('beforeunload', function (e) {
                if (dirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        });
    }

    /* ── Row filter (client-side search) ─────────────────── */
    function initRowFilter(root) {
        root = root || document;
        root.querySelectorAll('input[data-filter-rows]').forEach(function (inp) {
            if (inp.dataset.dcInited === '1') return;
            inp.dataset.dcInited = '1';
            const selector = inp.dataset.filterRows;
            const attr = inp.dataset.filterAttr || 'data-mime';
            inp.addEventListener('input', function () {
                const q = inp.value.toLowerCase().trim();
                document.querySelectorAll(selector).forEach(function (row) {
                    const val = (row.getAttribute(attr) || '').toLowerCase();
                    row.style.display = (q === '' || val.indexOf(q) >= 0) ? '' : 'none';
                });
            });
        });
    }

    /* ── Copy to clipboard ───────────────────────────────── */
    function initClipboard(root) {
        root = root || document;
        root.querySelectorAll('[data-copy-target]').forEach(function (btn) {
            if (btn.dataset.dcInited === '1') return;
            btn.dataset.dcInited = '1';
            btn.addEventListener('click', function () {
                const sel = btn.dataset.copyTarget;
                const target = document.querySelector(sel);
                if (!target) return;
                const text = target.value !== undefined ? target.value : target.textContent;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(
                        () => notify('success', t('js.documenti.copiato_appunti', 'Copiato negli appunti.')),
                        () => notify('danger',  t('js.documenti.impossibile_copiare', 'Impossibile copiare.'))
                    );
                } else {
                    notify('danger', t('js.documenti.clipboard_non_disponibile', 'Clipboard non disponibile.'));
                }
            });
        });
    }

    /* ── Quick-create categoria al volo ──────────────────── */
    function initQuickCategoria(root) {
        root = root || document;
        var form = root.querySelector('#dc-quick-cat-form');
        if (!form || form.dataset.dcInited === '1') return;
        form.dataset.dcInited = '1';

        // Reset native selects AND searchable display inputs when the modal closes
        var modalEl = document.getElementById('dc-modal-quick-cat');
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                form.reset();
                form.querySelectorAll('.dc-searchable-wrap .dc-searchable-input').forEach(function (inp) {
                    inp.value = '';
                });
                var errEl = document.getElementById('dc-quick-cat-error');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var url       = form.dataset.quickUrl;
            var errEl     = document.getElementById('dc-quick-cat-error');
            var submitBtn = document.getElementById('dc-quick-cat-submit');
            if (!url) return;

            if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
            if (submitBtn) { submitBtn.disabled = true; }

            var fd = new FormData(form);
            // Uppercase the codice value to match server-side strtoupper
            var codiceInput = form.querySelector('[name="codice"]');
            if (codiceInput) fd.set('codice', codiceInput.value.toUpperCase());

            fetch(url, { method: 'POST', body: fd })
                .then(function (res) {
                    return res.json().then(function (data) {
                        if (!res.ok && data.error) {
                            throw new Error(data.error);
                        }
                        if (!res.ok) {
                            throw new Error(t('js.documenti.errore_server', 'Errore server ({status}).').replace('{status}', res.status));
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.error || t('js.documenti.errore_creazione_categoria', 'Errore nella creazione della categoria.'));
                    }

                    // Find the main category native select
                    var mainSelect = document.getElementById('dc-create-cat');
                    if (mainSelect) {
                        // Unwrap the dc-searchable-wrap to reset the widget
                        var wrap = mainSelect.closest('.dc-searchable-wrap');
                        if (wrap) {
                            wrap.parentNode.insertBefore(mainSelect, wrap);
                            wrap.parentNode.removeChild(wrap);
                        }
                        // Clear inited flag and make visible again
                        delete mainSelect.dataset.dcInited;
                        mainSelect.classList.remove('d-none');

                        // Add the new option and select it
                        var opt = document.createElement('option');
                        opt.value       = data.id;
                        opt.textContent = data.nome;
                        mainSelect.appendChild(opt);
                        mainSelect.value = data.id;

                        // Re-init the searchable widget on the wrapping container
                        var container = document.getElementById('dc-create-cat-wrap');
                        if (container && window.documenti && window.documenti.init) {
                            window.documenti.init(container);
                        }
                    }

                    // Re-enable the main form submit button if it was disabled (no-categories state)
                    var mainSubmit = document.querySelector('form[data-dirty-check] [type="submit"]');
                    if (mainSubmit) mainSubmit.disabled = false;

                    // Close modal (reset will fire via hidden.bs.modal listener above)
                    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                        var bsModal = window.bootstrap.Modal.getInstance(modalEl);
                        if (bsModal) bsModal.hide();
                    }

                    notify('success', t('js.documenti.categoria_creata_selezionata', 'Categoria «{nome}» creata e selezionata.').replace('{nome}', data.nome));
                })
                .catch(function (err) {
                    var msg = (err && err.message) ? err.message : t('js.documenti.errore_imprevisto', 'Errore imprevisto.');
                    if (errEl) {
                        errEl.textContent = msg;
                        errEl.classList.remove('d-none');
                    }
                })
                .finally(function () {
                    if (submitBtn) submitBtn.disabled = false;
                });
        });
    }

    /* ── Toast su HX-Trigger ──────────────────────────────── */
    document.body && document.body.addEventListener('htmx:afterRequest', function (e) {
        // Hook is delegated to layout — nothing to do here.
    });

    /* ── Init ─────────────────────────────────────────────── */
    function init(root) {
        initDropzone(root);
        initTooltips(root);
        initSearchable(root);
        initSortable(root);
        initBulk(root);
        initDirtyGuard(root);
        initClipboard(root);
        initRowFilter(root);
        initQuickCategoria(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init(document));
    } else {
        init(document);
    }

    /* Re-init after HTMX swaps */
    document.addEventListener('htmx:afterSettle', function (e) {
        const target = e && e.detail && e.detail.elt ? e.detail.elt : document;
        init(target);
    });

    /* Public namespace (per uso da view inline) */
    window.documenti = {
        init: init,
        notify: notify,
    };
})();
