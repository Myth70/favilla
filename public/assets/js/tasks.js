/**
 * Attività — JavaScript modulo Kanban + CRUD
 * Usa SortableJS per drag & drop tra colonne.
 */
(function () {
    'use strict';

    /* ── Refs & config ────────────────────────────────────────────── */
    var wrapper  = document.getElementById('att-kanban-wrapper');
    if (!wrapper) return; // Non siamo nella vista kanban

    var cfg = {
        boardUrl   : wrapper.dataset.boardUrl,
        createUrl  : wrapper.dataset.createUrl,
        showUrl    : wrapper.dataset.showUrl,
        editUrl    : wrapper.dataset.editUrl,
        moveUrl    : wrapper.dataset.moveUrl,
        toggleUrl  : wrapper.dataset.toggleUrl,
        destroyUrl : wrapper.dataset.destroyUrl,
        storeUrl   : wrapper.dataset.storeUrl,
        csrf       : wrapper.dataset.csrf,
        canCreate  : wrapper.dataset.canCreate === '1',
        canEdit    : wrapper.dataset.canEdit === '1',
        canDelete  : wrapper.dataset.canDelete === '1'
    };

    var $modalEl  = document.getElementById('att-modal');
    var $modalContent = document.getElementById('att-modal-content');
    var bsModal;

    function getModal() {
        if (!bsModal) bsModal = new bootstrap.Modal($modalEl);
        return bsModal;
    }

    /* ── Utility ──────────────────────────────────────────────────── */

    function urlFor(template, id) {
        return template.replace('__ID__', id);
    }

    function jsonFetch(url, opts) {
        var headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
        return fetch(url, Object.assign({}, opts, { headers: headers }))
            .then(function (r) {
                if (r.headers.get('content-type')?.includes('application/json')) {
                    return r.json().then(function (d) { return { ok: r.ok, status: r.status, data: d }; });
                }
                return r.text().then(function (t) { return { ok: r.ok, status: r.status, html: t }; });
            });
    }

    function htmlFetch(url) {
        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'HX-Request': 'true' }
        }).then(function (r) { return r.text(); });
    }

    function toast(message, type) {
        type = type || 'success';
        if (typeof window.notify === 'function') {
            window.notify({
                message: message,
                type: type,
                source: 'tasks'
            });
            return;
        }

        document.body.dispatchEvent(new CustomEvent('notify', {
            detail: { message: message, type: type, source: 'tasks' }
        }));
    }

    function updateColumnCount(statusEl) {
        var col = statusEl.closest('.att-kanban-column');
        if (!col) return;
        var count = statusEl.querySelectorAll('.att-kanban-item').length;
        var badge = col.querySelector('.att-kanban-header .badge');
        if (badge) badge.textContent = count;
    }

    /* ── SortableJS — Drag & Drop ─────────────────────────────────── */

    var sortables = [];
    var mobileStackMedia = window.matchMedia('(max-width: 576px)');
    var isStackMode = mobileStackMedia.matches;

    function getSortableOptions() {
        var stackMode = mobileStackMedia.matches;
        return {
            group: 'att-kanban',
            animation: stackMode ? 120 : 180,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            handle: '.att-kanban-item',
            draggable: '.att-kanban-item',
            filter: '.att-kanban-add-btn',
            easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
            delayOnTouchOnly: true,
            delay: stackMode ? 140 : 0,
            touchStartThreshold: 4,
            fallbackOnBody: true,
            onEnd: onCardDrop
        };
    }

    function initSortables() {
        // Distruggi se precedenti
        sortables.forEach(function (s) { s.destroy(); });
        sortables = [];

        var columns = document.querySelectorAll('.att-kanban-items');
        var sortableOptions = getSortableOptions();
        columns.forEach(function (el) {
            var s = Sortable.create(el, sortableOptions);
            sortables.push(s);
        });
    }

    function onViewportModeChange() {
        var nextMode = mobileStackMedia.matches;
        if (nextMode === isStackMode) return;
        isStackMode = nextMode;
        initSortables();
    }

    function onCardDrop(evt) {
        var item   = evt.item;
        var target = evt.to;
        var taskId = item.dataset.eid;
        var newStatus = target.dataset.status;
        var newPos = Array.prototype.indexOf.call(target.children, item);

        // Aggiorna contatori colonne
        updateColumnCount(evt.from);
        updateColumnCount(evt.to);

        // Invia al server
        var body = new FormData();
        body.append('_method', 'PUT');
        body.append('_token', cfg.csrf);
        body.append('status', newStatus);
        body.append('position', newPos);

        jsonFetch(urlFor(cfg.moveUrl, taskId), { method: 'POST', body: body })
            .then(function (r) {
                if (!r.ok) {
                    toast(r.data?.error || t('js.tasks.move_error', 'Errore nello spostamento'), 'danger');
                    refreshBoard();
                }
            })
            .catch(function () {
                toast(t('js.tasks.network_error', 'Errore di rete'), 'danger');
                refreshBoard();
            });
    }

    /* ── Board refresh ────────────────────────────────────────────── */

    function refreshBoard() {
        htmlFetch(cfg.boardUrl).then(function (html) {
            var boardEl = document.getElementById('att-kanban-board');
            if (boardEl) {
                boardEl.innerHTML = html;
                initSortables();
                reinitTooltips(boardEl);
            }
        });
    }

    /* ── Card click → Show detail ─────────────────────────────────── */

    function onCardClick(e) {
        var card = e.target.closest('.att-kanban-item');
        if (!card) return;
        // Ignora se si sta draggando
        if (card.classList.contains('sortable-chosen') || card.classList.contains('sortable-drag')) return;

        var taskId = card.dataset.eid;
        if (!taskId) return;

        htmlFetch(urlFor(cfg.showUrl, taskId)).then(function (html) {
            $modalContent.innerHTML = html;
            getModal().show();
            bindModalActions();
        });
    }

    /* ── Add task per column ──────────────────────────────────────── */

    function onAddClick(e) {
        var btn = e.target.closest('.att-kanban-add-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var status = btn.dataset.status || 'todo';
        openCreateModal(status);
    }

    function openCreateModal(status) {
        status = status || 'todo';
        var url = cfg.createUrl + '?status=' + encodeURIComponent(status);

        htmlFetch(url).then(function (html) {
            $modalContent.innerHTML = html;
            getModal().show();
            bindModalForm();
        });
    }

    /* ── Modal form (create/edit) ─────────────────────────────────── */

    function bindModalForm() {
        var form = document.getElementById('att-modal-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var saveBtn = document.getElementById('att-modal-save');
            var defaultLabel = saveBtn ? (saveBtn.dataset.defaultLabel || saveBtn.innerHTML) : '';
            if (saveBtn && !saveBtn.dataset.defaultLabel) {
                saveBtn.dataset.defaultLabel = defaultLabel;
            }
            if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>' + t('js.tasks.saving', 'Salvo...'); }

            var fd = new FormData(form);
            fd.append('_token', cfg.csrf);

            jsonFetch(form.action, { method: 'POST', body: fd })
                .then(function (r) {
                    if (r.ok && r.data?.success) {
                        getModal().hide();
                        toast(t('js.tasks.saved', 'Attività salvata!'));
                        refreshBoard();
                    } else if (r.data?.errors) {
                        showFormErrors(r.data.errors);
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = defaultLabel; }
                    } else {
                        toast(r.data?.error || t('js.tasks.save_error', 'Errore nel salvataggio'), 'danger');
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = defaultLabel; }
                    }
                })
                .catch(function () {
                    toast(t('js.tasks.network_error', 'Errore di rete'), 'danger');
                    if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = defaultLabel; }
                });
        });
    }

    function showFormErrors(errors) {
        // Rimuovi errori precedenti
        $modalContent.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        $modalContent.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });

        Object.keys(errors).forEach(function (field) {
            var input = $modalContent.querySelector('[name="' + field + '"]');
            if (!input) return;
            input.classList.add('is-invalid');
            var fb = document.createElement('div');
            fb.className = 'invalid-feedback';
            fb.textContent = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
            input.parentNode.appendChild(fb);
        });
    }

    /* ── Modal actions (detail view) ──────────────────────────────── */

    function bindModalActions() {
        // Edit button
        var editBtn = $modalContent.querySelector('.att-modal-edit-btn');
        if (editBtn) {
            editBtn.addEventListener('click', function () {
                var taskId = this.dataset.taskId;
                htmlFetch(urlFor(cfg.editUrl, taskId)).then(function (html) {
                    $modalContent.innerHTML = html;
                    bindModalForm();
                });
            });
        }

        // Delete button
        var deleteBtn = $modalContent.querySelector('.att-modal-delete-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                var taskId = this.dataset.taskId;
                confirmDelete(taskId);
            });
        }
    }

    /* ── Delete ────────────────────────────────────────────────────── */

    function confirmDelete(taskId) {
        var activeDeleteButton = $modalContent.querySelector('.att-modal-delete-btn[data-task-id="' + taskId + '"]');
        var confirmMessage = activeDeleteButton && activeDeleteButton.dataset.confirmMessage
            ? activeDeleteButton.dataset.confirmMessage
            : t('js.tasks.delete_confirm', 'Eliminare questa attività?');

        var confirmDeletePromise = typeof window.appConfirm === 'function'
            ? window.appConfirm({
                title: t('js.tasks.delete_title', 'Elimina attività'),
                body: confirmMessage,
                confirmLabel: t('js.common.delete', 'Elimina'),
                confirmClass: 'btn-danger'
            })
            : Promise.resolve(window.confirm(confirmMessage));

        confirmDeletePromise.then(function (ok) {
            if (!ok) return;

            var fd = new FormData();
            fd.append('_method', 'DELETE');
            fd.append('_token', cfg.csrf);

            jsonFetch(urlFor(cfg.destroyUrl, taskId), { method: 'POST', body: fd })
                .then(function (r) {
                    if (r.ok) {
                        getModal().hide();
                        toast(t('js.tasks.deleted', 'Attività eliminata.'));
                        // Rimuovi card dal DOM
                        var card = document.querySelector('.att-kanban-item[data-eid="' + taskId + '"]');
                        if (card) {
                            var col = card.closest('.att-kanban-items');
                            card.remove();
                            if (col) updateColumnCount(col);
                        } else {
                            refreshBoard();
                        }
                    } else {
                        toast(r.data?.error || t('js.tasks.delete_error', 'Errore nella cancellazione'), 'danger');
                    }
                })
                .catch(function () { toast(t('js.tasks.network_error', 'Errore di rete'), 'danger'); });
        });
    }

    /* ── Toggle complete (double-click) ───────────────────────────── */

    function onCardDblClick(e) {
        var card = e.target.closest('.att-kanban-item');
        if (!card) return;
        if (!cfg.canEdit) return;

        var taskId = card.dataset.eid;
        var fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('_token', cfg.csrf);

        jsonFetch(urlFor(cfg.toggleUrl, taskId), { method: 'POST', body: fd })
            .then(function (r) {
                if (r.ok) {
                    toast(r.data.status === 'done' ? t('js.tasks.completed', 'Completata!') : t('js.tasks.reopened', 'Riaperta'));
                    refreshBoard();
                } else {
                    toast(r.data?.error || t('js.tasks.generic_error', 'Errore'), 'danger');
                }
            });
    }

    /* ── New task button (header) ─────────────────────────────────── */

    var btnNew = document.getElementById('att-btn-new');
    if (btnNew) {
        btnNew.addEventListener('click', function () {
            openCreateModal('todo');
        });
    }

    /* ── Tooltip reinit ───────────────────────────────────────────── */

    function reinitTooltips(root) {
        root = root || document;
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    /* ── Event delegation ─────────────────────────────────────────── */

    var boardEl = document.getElementById('att-kanban-board');
    if (boardEl) {
        boardEl.addEventListener('click', function (e) {
            // Add button
            if (e.target.closest('.att-kanban-add-btn')) {
                onAddClick(e);
                return;
            }
            // Card click → show detail
            if (e.target.closest('.att-kanban-item')) {
                onCardClick(e);
            }
        });
        boardEl.addEventListener('dblclick', onCardDblClick);
    }

    /* ── HTMX afterSwap → reinit tooltips ─────────────────────────── */

    document.body.addEventListener('htmx:afterSwap', function (e) {
        reinitTooltips(e.detail.target);
    });

    if (typeof mobileStackMedia.addEventListener === 'function') {
        mobileStackMedia.addEventListener('change', onViewportModeChange);
    } else if (typeof mobileStackMedia.addListener === 'function') {
        mobileStackMedia.addListener(onViewportModeChange);
    }

    /* ── Keyboard shortcuts ───────────────────────────────────────── */

    document.addEventListener('keydown', function (e) {
        // N → New task (se modal non aperta)
        if (e.key === 'n' && !e.ctrlKey && !e.metaKey && !e.altKey
            && document.activeElement.tagName !== 'INPUT'
            && document.activeElement.tagName !== 'TEXTAREA'
            && document.activeElement.tagName !== 'SELECT'
            && !$modalEl.classList.contains('show')
            && cfg.canCreate) {
            e.preventDefault();
            openCreateModal('todo');
        }
    });

    /* ── Modal cleanup on hide ────────────────────────────────────── */

    $modalEl.addEventListener('hidden.bs.modal', function () {
        $modalContent.innerHTML = '';
    });

    /* ── Init ─────────────────────────────────────────────────────── */

    initSortables();
    reinitTooltips();

})();
