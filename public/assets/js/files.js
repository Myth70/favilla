(function () {
    'use strict';

    var LS_VIEW_KEY = 'fm_view_mode';

    // Pending form for folder delete modal
    var _pendingDeleteForm = null;

    // ── 1. Init on DOMContentLoaded (or immediately if already parsed) ────
    function init() {
        initViewToggle();
        initUploadZone();
        initBulkSelect();
        initBulkZipDownload();
        initFolderDeleteModal();  // register confirm-button listener once
        initFolderSidebar();
        initFolderChips();
        initMobileFolderSelect();
        initTooltips();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-init after HTMX swaps
    // initFolderSidebar only when #fm-folder-nav itself was swapped (create/rename/delete folder)
    // initBulkSelect on every swap (file list grid/list refresh)
    document.body.addEventListener('htmx:afterSwap', function (e) {
        initBulkSelect();
        if (e.detail && e.detail.target && e.detail.target.id === 'fm-folder-nav') {
            initFolderSidebar();
        }
        // Open the preview modal once its content has been swapped in.
        // Replaces a per-button hx-on::after-swap, which a strict CSP
        // (no 'unsafe-eval') blocks because htmx evaluates it via new Function().
        if (e.detail && e.detail.target && e.detail.target.id === 'fm-preview-modal-content' && window.bootstrap) {
            var modal = document.getElementById('fm-preview-modal');
            if (modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        }
        initTooltips();
    });

    // ── 2. View toggle (grid / list) ──────────────────────────────────────
    function initViewToggle() {
        var viewInput = document.getElementById('fm-view-input');
        if (!viewInput) return;

        // Restore saved preference on page load
        var saved = localStorage.getItem(LS_VIEW_KEY);
        if (saved && saved !== viewInput.value) {
            setViewMode(saved, false); // false = no HTMX trigger on load
        }

        document.querySelectorAll('[data-fm-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setViewMode(btn.dataset.fmView, true);
            });
        });
    }

    function setViewMode(mode, triggerHtmx) {
        var viewInput = document.getElementById('fm-view-input');
        if (!viewInput) return;

        viewInput.value = mode;
        localStorage.setItem(LS_VIEW_KEY, mode);

        // Update active state on toggle buttons
        document.querySelectorAll('[data-fm-view]').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.fmView === mode);
        });

        if (triggerHtmx) {
            // Manually trigger HTMX request with updated view value
            var container = document.getElementById('files-container');
            if (container && window.htmx) {
                var url = new URL(window.location.href);
                url.searchParams.set('view', mode);
                htmx.ajax('GET', url.toString(), {target: '#files-container', pushUrl: true});
            }
        }
    }

    // ── 3. FileDropzone init (upload.php) ─────────────────────────────────
    function initUploadZone() {
        var zone = document.getElementById('fm-upload-zone');
        if (!zone || typeof FileDropzone === 'undefined') return;

        var maxBytes    = parseInt(zone.dataset.maxBytes || '20971520', 10);
        var acceptMimes = [];
        try {
            acceptMimes = JSON.parse(zone.dataset.acceptMimes || '[]');
        } catch (e) { /* ignore parse errors */ }

        FileDropzone.init('#fm-upload-zone', {
            inputId  : 'fm-file-input',
            maxBytes : maxBytes,
            accept   : acceptMimes,
        });
    }

    // ── 4. BulkSelect init ────────────────────────────────────────────────
    function initBulkSelect() {
        if (typeof BulkSelect === 'undefined') return;

        // User file list (list_table.php)
        if (document.getElementById('fm-check-all')) {
            BulkSelect.init({
                checkboxSelector : '.fm-row-check',
                selectAllId      : 'fm-check-all',
                barId            : 'fm-bulk-bar',
                countId          : 'fm-selected-count',
                formId           : 'fm-bulk-form',
                confirmMsg       : 'Eliminare {n} file selezionati?',
            });
        }

        // Admin file table (admin/partials/table.php)
        if (document.getElementById('fm-admin-check-all')) {
            BulkSelect.init({
                checkboxSelector : '.fm-admin-row-check',
                selectAllId      : 'fm-admin-check-all',
                barId            : 'fm-admin-bulk-bar',
                countId          : 'fm-admin-selected-count',
                formId           : 'fm-admin-bulk-form',
                confirmMsg       : 'Spostare nel cestino {n} file selezionati?',
            });
        }

        // Trash table
        if (document.getElementById('fm-trash-check-all')) {
            BulkSelect.init({
                checkboxSelector : '.fm-trash-row-check',
                selectAllId      : 'fm-trash-check-all',
                barId            : 'fm-trash-bulk-bar',
                countId          : 'fm-trash-selected-count',
                formId           : 'fm-trash-bulk-form',
                confirmMsg       : 'Eliminare definitivamente {n} file? Impossibile annullare.',
            });
        }
    }

    // ── 4b. Bulk ZIP download ─────────────────────────────────────────────
    function initBulkZipDownload() {
        var zipBtn = document.querySelector('[data-fm-bulk-zip="1"]');
        if (!zipBtn) return;

        zipBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var zipUrl = zipBtn.getAttribute('data-fm-zip-url') || '';
            if (!zipUrl) return;

            var ids = [];
            document.querySelectorAll('#fm-bulk-form input[name="ids[]"]:checked').forEach(function (el) {
                ids.push(el.value);
            });

            if (ids.length === 0) return;
            window.location = zipUrl + '?ids=' + encodeURIComponent(ids.join(','));
        });
    }

    // ── 5a. Folder delete modal (setup once — modal lives outside HTMX swap zone) ──
    function initFolderDeleteModal() {
        var confirmBtn = document.getElementById('fm-delete-confirm-btn');
        var modal      = document.getElementById('fm-delete-confirm-modal');
        if (!confirmBtn || !modal) return;

        confirmBtn.addEventListener('click', function () {
            if (window.bootstrap) {
                var bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }
            if (_pendingDeleteForm && window.htmx) {
                htmx.trigger(_pendingDeleteForm, 'submit');
            }
            _pendingDeleteForm = null;
        });
    }

    // ── 5. Folder sidebar: create + rename + delete ───────────────────────
    function initFolderSidebar() {
        // "+ new folder" button toggle
        var newBtn    = document.getElementById('fm-new-folder-btn');
        var newForm   = document.getElementById('fm-new-folder-form');
        var cancelBtn = document.getElementById('fm-cancel-folder');
        var newInput  = document.getElementById('fm-new-folder-input');

        if (newBtn && newForm) {
            newBtn.addEventListener('click', function () {
                newForm.classList.toggle('d-none');
                if (!newForm.classList.contains('d-none') && newInput) {
                    newInput.focus();
                }
            });
        }
        if (cancelBtn && newForm) {
            cancelBtn.addEventListener('click', function () {
                newForm.classList.add('d-none');
                if (newInput) newInput.value = '';
            });
        }

        // Rename buttons
        document.querySelectorAll('.fm-rename-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li       = btn.closest('.fm-folder-item');
                var row      = li && li.querySelector('.fm-folder-row');
                var renForm  = li && li.querySelector('.fm-rename-form');
                if (!row || !renForm) return;
                row.classList.add('d-none');
                renForm.classList.remove('d-none');
                var inp = renForm.querySelector('input[name="new_folder"]');
                if (inp) { inp.focus(); inp.select(); }
            });
        });

        // Cancel rename
        document.querySelectorAll('.fm-rename-cancel').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var li      = btn.closest('.fm-folder-item');
                var row     = li && li.querySelector('.fm-folder-row');
                var renForm = li && li.querySelector('.fm-rename-form');
                if (!row || !renForm) return;
                renForm.classList.add('d-none');
                row.classList.remove('d-none');
            });
        });

        // Delete folder buttons — open Bootstrap modal instead of native confirm
        document.querySelectorAll('.fm-delete-folder-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal  = document.getElementById('fm-delete-confirm-modal');
                var nameEl = document.getElementById('fm-delete-folder-name');
                if (nameEl) {
                    nameEl.textContent = '\u00ab' + (btn.dataset.folderLabel || '') + '\u00bb';
                }
                _pendingDeleteForm = btn.closest('form');
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        });
    }

    // ── 6. Folder chip picker (upload.php) ────────────────────────────────
    function initFolderChips() {
        var folderInput = document.getElementById('fm-folder');
        if (!folderInput) return;

        document.querySelectorAll('.fm-folder-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var val = chip.dataset.folder || '';
                // Toggle: click again on active chip = clear
                if (folderInput.value === val) {
                    folderInput.value = '';
                    chip.classList.remove('active');
                } else {
                    folderInput.value = val;
                    document.querySelectorAll('.fm-folder-chip').forEach(function (c) {
                        c.classList.remove('active');
                    });
                    chip.classList.add('active');
                }
            });
        });

        // Deactivate chips if user types manually
        folderInput.addEventListener('input', function () {
            var val = folderInput.value;
            document.querySelectorAll('.fm-folder-chip').forEach(function (chip) {
                chip.classList.toggle('active', chip.dataset.folder === val);
            });
        });
    }

    // ── 6b. Mobile folder selector sync ───────────────────────────────────
    function initMobileFolderSelect() {
        var mobileSelect = document.querySelector('[data-fm-folder-mobile="1"]');
        var folderInput = document.getElementById('fm-folder-input');
        var searchInput = document.querySelector('[data-filter][name="search"]');

        if (!mobileSelect || !folderInput || !searchInput) return;

        mobileSelect.addEventListener('change', function () {
            folderInput.value = mobileSelect.value;
            if (window.htmx) {
                htmx.trigger(searchInput, 'search');
            }
        });
    }

    // ── 7. Bootstrap tooltip reinit ───────────────────────────────────────
    function initTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            var existing = bootstrap.Tooltip.getInstance(el);
            if (existing) existing.dispose();
            new bootstrap.Tooltip(el);
        });
    }

})();
