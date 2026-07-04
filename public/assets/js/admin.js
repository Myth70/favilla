/**
 * Admin Module — Shared JavaScript
 * Handles: lazy-load tabs, tooltip reinit after HTMX swap, CSV export
 */
(function () {
    'use strict';

    // ── Lazy-load tabs ──────────────────────────────────────────
    // Attach to any tablist with [data-adm-lazy] attribute.
    // Each tab pane must contain a child with hx-trigger="load".
    function initLazyTabs(tablistId) {
        var tabEls = document.querySelectorAll('#' + tablistId + ' button[data-bs-toggle="tab"]');
        tabEls.forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function (e) {
                var pane = document.querySelector(e.target.getAttribute('data-bs-target'));
                if (!pane) return;
                var tbl = pane.querySelector('[hx-trigger="load"]');
                if (tbl && !tbl.dataset.admLoaded) {
                    htmx.trigger(tbl, 'load');
                    tbl.dataset.admLoaded = '1';
                }
            });
        });

        // Mark the currently active pane as already loaded
        var activePane = document.querySelector('#' + tablistId + ' ~ .tab-content .tab-pane.active, #' + tablistId + ' + .tab-content .tab-pane.active');
        if (!activePane) {
            // Try within same card structure
            var card = document.getElementById(tablistId);
            if (card) {
                activePane = card.closest('.card, .tab-content, div')
                    ? document.querySelector('.tab-pane.active') : null;
            }
        }
        // Broader search within parent
        var tablist = document.getElementById(tablistId);
        if (tablist) {
            var container = tablist.closest('.card') || tablist.parentElement;
            var active = container ? container.querySelector('.tab-pane.active') : null;
            if (active) {
                var tbl = active.querySelector('[hx-trigger="load"]');
                if (tbl) tbl.dataset.admLoaded = '1';
            }
        }
    }

    // Auto-init all tab lists with data-adm-lazy
    document.querySelectorAll('[data-adm-lazy]').forEach(function (el) {
        initLazyTabs(el.id);
    });

    // Expose for manual init
    window.admInitLazyTabs = initLazyTabs;

    // ── Tooltip reinit after HTMX swap ──────────────────────────
    function initTooltips(root) {
        (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (!bootstrap.Tooltip.getInstance(el)) {
                new bootstrap.Tooltip(el);
            }
        });
    }

    document.body.addEventListener('htmx:afterSwap', function (e) {
        initTooltips(e.detail.target);
    });

    // Init on page load
    initTooltips();

    // ── Clickable table rows ─────────────────────────────────────
    document.addEventListener('click', function (e) {
        var row = e.target.closest('tr.adm-clickable-row[data-href]');
        if (!row) return;
        // Ignore clicks on interactive elements
        if (e.target.closest('a, button, input, select, form, [data-bs-toggle]')) return;
        window.location.href = row.dataset.href;
    });

    // ── CSV export handler ──────────────────────────────────────
    // Attach to buttons with .adm-export-btn
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.adm-export-btn');
        if (!btn) return;
        var form = document.getElementById(btn.dataset.form);
        if (!form) return;
        var params = new URLSearchParams(new FormData(form));
        params.set('type', btn.dataset.type);
        window.location.href = btn.dataset.url + '?' + params.toString();
    });

    // ── Auto-submit controls ─────────────────────────────────────
    document.addEventListener('change', function (e) {
        var control = e.target.closest('[data-adm-autosubmit="change"]');
        if (!control) return;
        var form = control.form || control.closest('form');
        if (!form) return;
        if (form.requestSubmit) {
            form.requestSubmit();
            return;
        }
        form.submit();
    });

    // ── Admin helper actions (bulk permissions, reset forms) ────
    document.addEventListener('click', function (e) {
        var resetBtn = e.target.closest('[data-adm-reset-form="1"]');
        if (resetBtn) {
            var resetForm = resetBtn.form || resetBtn.closest('form');
            if (resetForm) resetForm.reset();
            return;
        }

        var permBtn = e.target.closest('[data-adm-perm-bulk]');
        if (!permBtn) return;

        var formId = permBtn.getAttribute('data-adm-perm-form');
        var mode = permBtn.getAttribute('data-adm-perm-bulk');
        var form = formId ? document.getElementById(formId) : null;
        if (!form) return;

        var shouldCheck = mode === 'all';
        form.querySelectorAll('input[type="checkbox"][name="permission_ids[]"]').forEach(function (checkbox) {
            checkbox.checked = shouldCheck;
        });
    });

    // ── Role permissions: "Salvato" indicator after save ────────
    // Replaces a per-form hx-on::after-request, which a strict CSP
    // (no 'unsafe-eval') blocks because htmx evaluates it via new Function().
    document.body.addEventListener('htmx:afterRequest', function (e) {
        var elt = e.detail && e.detail.elt;
        if (!elt || elt.id !== 'perm-form') return;
        if (!(e.detail && e.detail.successful)) return;
        var saved = document.getElementById('perm-saved');
        if (!saved) return;
        saved.classList.remove('d-none');
        setTimeout(function () { saved.classList.add('d-none'); }, 3000);
    });

    // ── Audit detail modal handler ──────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.adm-detail-btn');
        if (!btn) return;
        var modal = document.getElementById('adm-detail-modal');
        if (!modal) return;
        var oldEl = document.getElementById('adm-modal-old');
        var newEl = document.getElementById('adm-modal-new');
        if (oldEl) oldEl.textContent = btn.dataset.old || '\u2014';
        if (newEl) newEl.textContent = btn.dataset.new || '\u2014';
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

})();

// ── Bulk user selection ──────────────────────────────────────
(function () {
    'use strict';

    function getChecked() {
        return Array.from(document.querySelectorAll('.adm-bulk-check:checked:not(:disabled)'));
    }

    function getToolbar() {
        return document.getElementById('adm-bulk-toolbar');
    }

    function updateToolbar() {
        var toolbar = getToolbar();
        if (!toolbar) return;
        var checked = getChecked();
        var countEl = document.getElementById('adm-bulk-count');
        toolbar.classList.toggle('d-none', checked.length === 0);
        if (countEl) {
            countEl.textContent = checked.length + ' selezionat' + (checked.length === 1 ? 'o' : 'i');
        }
        var selectAll = document.getElementById('bulk-select-all');
        if (selectAll) {
            var all = document.querySelectorAll('.adm-bulk-check:not(:disabled)');
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            selectAll.checked = all.length > 0 && checked.length === all.length;
        }
    }

    // Listener globali — registrati UNA SOLA VOLTA (non dentro initBulk)
    document.addEventListener('change', function (e) {
        if (e.target.id === 'bulk-select-all') {
            document.querySelectorAll('.adm-bulk-check:not(:disabled)').forEach(function (cb) {
                cb.checked = e.target.checked;
            });
        }
        if (e.target.classList.contains('adm-bulk-check') || e.target.id === 'bulk-select-all') {
            updateToolbar();
        }
    });

    document.addEventListener('click', function (e) {
        // Cancel button — delegazione via closest (sopravvive allo swap HTMX)
        if (e.target.closest('#adm-bulk-cancel')) {
            document.querySelectorAll('.adm-bulk-check, #bulk-select-all').forEach(function (cb) { cb.checked = false; });
            updateToolbar();
            return;
        }

        var toolbar = getToolbar();
        var btn = e.target.closest('[data-bulk-action]');
        if (!btn || !toolbar || toolbar.classList.contains('d-none')) return;
        e.preventDefault();

        var bulkUrl   = toolbar.dataset.bulkUrl || '';
        var csrfToken = (document.querySelector('[name="_token"]') || {}).value || '';
        var action    = btn.dataset.bulkAction;
        var roleId    = btn.dataset.roleId || '';
        var ids       = getChecked().map(function (cb) { return cb.value; });
        if (!ids.length) return;

        var formData = new FormData();
        formData.append('action', action);
        formData.append('_token', csrfToken);
        if (roleId) formData.append('role_id', roleId);
        ids.forEach(function (id) { formData.append('user_ids[]', id); });

        fetch(bulkUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var type = data.success ? 'success' : 'danger';
                document.dispatchEvent(new CustomEvent('notify', { detail: { message: data.message, type: type } }));
                if (data.success) {
                    var table = document.getElementById('users-table');
                    if (table && typeof htmx !== 'undefined') {
                        htmx.ajax('GET', table.getAttribute('hx-get') || window.location.href, { target: '#users-table', swap: 'innerHTML' });
                    } else if (table) {
                        table.dispatchEvent(new Event('htmx:load'));
                    }
                }
            })
            .catch(function () {
                document.dispatchEvent(new CustomEvent('notify', { detail: { message: 'Errore di rete.', type: 'danger' } }));
            });
    });

    // initBulk: solo reset stato toolbar — nessun addEventListener
    function initBulk() {
        if (!getToolbar()) return;
        updateToolbar();
    }

    initBulk();
    document.body.addEventListener('htmx:afterSwap', function (e) {
        if (e.detail && e.detail.target && e.detail.target.id === 'users-table') {
            initBulk();
        }
    });
})();
