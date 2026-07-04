<?php
$view->layout('main');
$view->pushStyle('css/progetti.css');

$templates = $templates ?? [];
?>

<?php $view->start('content'); ?>
<div class="container-fluid prj-page">

    <?php
    $adminButtons = '';
    $adminButtons .= '<button type="button" class="btn btn-primary btn-sm" id="prj-tpl-create-btn" data-bs-toggle="tooltip" title="' . e(t('progetti.checklist_templates.new_tip')) . '">';
    $adminButtons .= '<i class="fa-solid fa-plus me-1"></i>' . e(t('progetti.checklist_templates.new_btn'));
    $adminButtons .= '</button>';
    $adminButtons .= ' <a href="' . e(route('projects.my_tasks')) . '" class="btn btn-outline-secondary btn-sm">';
    $adminButtons .= '<i class="fa-solid fa-clipboard-check me-1"></i>' . e(t('progetti.checklist_templates.assigned_tasks_btn'));
    $adminButtons .= '</a>';

    $view->include('partials/pf-hero-module', [
        'moduleName'     => t('progetti.checklist_templates.title'),
        'moduleSubtitle' => t('progetti.checklist_templates.subtitle'),
        'moduleIcon'     => 'fa-solid fa-list-check',
        'moduleButtons'  => $adminButtons,
    ]);
    ?>

    <!-- Tabella modelli -->
    <div class="card shadow-sm">
        <?php if (empty($templates)): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-list-check fa-2x d-block mb-2 opacity-50"></i>
            <p class="mb-0"><?= e(t('progetti.checklist_templates.no_templates')) ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="prj-tpl-table">
                <thead class="table-light">
                <tr>
                    <th><?= e(t('progetti.checklist_templates.col_name')) ?></th>
                    <th class="text-center"><?= e(t('progetti.checklist_templates.col_items')) ?></th>
                    <th><?= e(t('progetti.checklist_templates.col_creator')) ?></th>
                    <th class="text-end"><?= e(t('progetti.checklist_templates.col_actions')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($templates as $tpl): ?>
                <tr id="prj-tpl-row-<?= (int) $tpl['id'] ?>">
                    <td class="fw-semibold"><?= e($tpl['name']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary bg-opacity-25 text-dark"><?= (int) $tpl['item_count'] ?></span>
                    </td>
                    <td class="small text-muted"><?= e($tpl['created_by_name'] ?? '—') ?></td>
                    <td class="text-end">
                        <button type="button"
                                class="btn btn-sm btn-outline-warning border-0 prj-tpl-edit-btn"
                                data-tpl-id="<?= (int) $tpl['id'] ?>"
                                data-tpl-name="<?= e($tpl['name']) ?>"
                                data-bs-toggle="tooltip"
                                title="<?= e(t('progetti.checklist_templates.edit_tip')) ?>">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger border-0 prj-tpl-delete-btn"
                                data-tpl-id="<?= (int) $tpl['id'] ?>"
                                data-tpl-name="<?= e($tpl['name']) ?>"
                                data-bs-toggle="tooltip"
                                title="<?= e(t('progetti.checklist_templates.delete_tip')) ?>">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- ── Modal crea/modifica modello ──────────────────────────────────── -->
<div class="modal fade" id="prjTplModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prjTplModalTitle">
                    <i class="fa-solid fa-list-check me-2"></i><?= e(t('progetti.checklist_templates.modal_new_title')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('progetti.show.close_modal_aria')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= e(t('progetti.checklist_templates.name_label')) ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="prj-tpl-name" maxlength="255" placeholder="<?= e(t('progetti.checklist_templates.name_placeholder')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><?= e(t('progetti.checklist_templates.items_label')) ?></label>
                    <div id="prj-tpl-items-list" class="mb-2">
                        <!-- Voci dinamiche -->
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="prj-tpl-new-item" placeholder="<?= e(t('progetti.checklist_templates.add_item_placeholder')) ?>" maxlength="500">
                        <button type="button" class="btn btn-outline-primary" id="prj-tpl-add-item-btn" data-bs-toggle="tooltip" title="<?= e(t('progetti.checklist_templates.add_item_tip')) ?>">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    <div class="form-text"><?= e(t('progetti.checklist_templates.items_hint')) ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('progetti.checklist_templates.cancel')) ?></button>
                <button type="button" class="btn btn-primary" id="prj-tpl-save-btn">
                    <i class="fa-solid fa-check me-1"></i><?= e(t('progetti.checklist_templates.save')) ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal conferma eliminazione ───────────────────────────────────── -->
<div class="modal fade" id="prjTplDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i><?= e(t('progetti.checklist_templates.delete_title')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('progetti.show.close_modal_aria')) ?>"></button>
            </div>
            <div class="modal-body">
                <p><?= t('progetti.checklist_templates.delete_message', ['name' => '<strong id="prj-tpl-delete-name"></strong>']) ?></p>
                <p class="mb-0 text-muted small"><?= e(t('progetti.checklist_templates.delete_hint')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('progetti.checklist_templates.cancel')) ?></button>
                <button type="button" class="btn btn-danger" id="prj-tpl-confirm-delete-btn">
                    <i class="fa-solid fa-trash me-1"></i><?= e(t('progetti.checklist_templates.delete_confirm')) ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    'use strict';

    var csrfToken    = '<?= e(csrf_token()) ?>';
    var storeUrl      = '<?= e(route('projects.checklist_templates.store')) ?>';
    var showUrlTpl    = '<?= e(route('projects.checklist_templates.show', ['tplId' => '__TID__'])) ?>';
    var updateUrlTpl  = '<?= e(route('projects.checklist_templates.update', ['tplId' => '__TID__'])) ?>';
    var destroyUrlTpl = '<?= e(route('projects.checklist_templates.destroy', ['tplId' => '__TID__'])) ?>';

    var currentTplId    = null;
    var pendingDeleteId = null;

    // Bootstrap non è ancora caricato quando il blocco content viene renderizzato;
    // i modal vengono inizializzati lazily al momento del click.
    function getTplModal() {
        var el = document.getElementById('prjTplModal');
        return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }
    function getTplDeleteModal() {
        var el = document.getElementById('prjTplDeleteModal');
        return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }

    // ── Toast helper ────────────────────────────────────────────────────
    function toast(msg, type) {
        if (window.toast) { window.toast(msg, type); return; }
        var evt = new CustomEvent('notify', { detail: { message: msg, type: type || 'info' } });
        document.body.dispatchEvent(evt);
    }

    // ── Render items list nel modal ──────────────────────────────────────
    function renderItemsList(labels) {
        var listEl = document.getElementById('prj-tpl-items-list');
        if (!listEl) return;
        if (!labels || labels.length === 0) {
            listEl.innerHTML = '<p class="text-muted small mb-0">' + t('js.progetti.no_items_added', 'Nessuna voce aggiunta.') + '</p>';
            return;
        }
        var html = '<ul class="list-unstyled mb-0">';
        labels.forEach(function (label, i) {
            html += '<li class="d-flex align-items-center gap-2 py-1 border-bottom prj-tpl-item-row">';
            html += '<span class="flex-grow-1 small">' + escHtml(label) + '</span>';
            html += '<button type="button" class="btn btn-outline-danger btn-sm border-0 prj-tpl-remove-item prj-tpl-remove-btn" data-index="' + i + '"><i class="fa-solid fa-xmark"></i></button>';
            html += '</li>';
        });
        html += '</ul>';
        listEl.innerHTML = html;
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getItemLabels() {
        var rows = document.querySelectorAll('.prj-tpl-item-row');
        var labels = [];
        rows.forEach(function (row) {
            var span = row.querySelector('span');
            if (span) labels.push(span.textContent.trim());
        });
        return labels;
    }

    // ── Apri modal creazione ─────────────────────────────────────────────
    var createBtn = document.getElementById('prj-tpl-create-btn');
    if (createBtn) {
        createBtn.addEventListener('click', function () {
            currentTplId = null;
            document.getElementById('prjTplModalTitle').textContent = t('js.progetti.new_template', ' Nuovo modello');
            document.getElementById('prj-tpl-name').value = '';
            renderItemsList([]);
            getTplModal().show();
        });
    }

    // ── Apri modal modifica ──────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.prj-tpl-edit-btn');
        if (!editBtn) return;
        currentTplId = parseInt(editBtn.dataset.tplId, 10);
        document.getElementById('prjTplModalTitle').textContent = t('js.progetti.edit_template', ' Modifica modello');
        document.getElementById('prj-tpl-name').value = editBtn.dataset.tplName || '';
        renderItemsList([]);
        getTplModal().show();
        // Carica voci esistenti dal server
        var getUrl = showUrlTpl.replace('__TID__', currentTplId);
        fetch(getUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok && data.template) {
                renderItemsList(data.template.labels || []);
            }
        })
        .catch(function () { /* lascia la lista vuota se il fetch fallisce */ });
    });

    // ── Add item nel modal ──────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#prj-tpl-add-item-btn')) return;
        var input = document.getElementById('prj-tpl-new-item');
        var label = input ? input.value.trim() : '';
        if (!label) { input && input.focus(); return; }
        var current = getItemLabels();
        current.push(label);
        renderItemsList(current);
        input.value = '';
        input.focus();
    });

    // Enter nel campo nuovo item
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        if (!e.target.matches('#prj-tpl-new-item')) return;
        e.preventDefault();
        var btn = document.getElementById('prj-tpl-add-item-btn');
        if (btn) btn.click();
    });

    // ── Rimuovi item nel modal ───────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('.prj-tpl-remove-item');
        if (!removeBtn) return;
        var idx = parseInt(removeBtn.dataset.index, 10);
        var current = getItemLabels();
        current.splice(idx, 1);
        renderItemsList(current);
    });

    // ── Salva modello ───────────────────────────────────────────────────
    var saveBtn = document.getElementById('prj-tpl-save-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var name = (document.getElementById('prj-tpl-name').value || '').trim();
            var labels = getItemLabels();
            if (!name) {
                toast(t('js.progetti.template_name_required', 'Il nome del modello e obbligatorio.'), 'danger');
                document.getElementById('prj-tpl-name').focus();
                return;
            }
            if (labels.length === 0) {
                toast(t('js.progetti.template_item_required', 'Aggiungi almeno una voce al modello.'), 'danger');
                return;
            }

            var url = currentTplId
                ? updateUrlTpl.replace('__TID__', currentTplId)
                : storeUrl;

            var fd = new FormData();
            fd.append('_token', csrfToken);
            fd.append('name', name);
            labels.forEach(function (l) { fd.append('labels[]', l); });
            if (currentTplId) fd.append('_method', 'PUT');

            saveBtn.disabled = true;
            fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || t('js.progetti.save_error', 'Errore durante il salvataggio.'));
                toast(data.message || t('js.progetti.template_saved', 'Modello salvato.'), 'success');
                getTplModal().hide();
                setTimeout(function () { window.location.reload(); }, 600);
            })
            .catch(function (err) {
                toast(err.message || t('js.progetti.generic_error_dot', 'Errore.'), 'danger');
            })
            .finally(function () { saveBtn.disabled = false; });
        });
    }

    // ── Elimina modello ─────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var deleteBtn = e.target.closest('.prj-tpl-delete-btn');
        if (!deleteBtn) return;
        pendingDeleteId = parseInt(deleteBtn.dataset.tplId, 10);
        var nameEl = document.getElementById('prj-tpl-delete-name');
        if (nameEl) nameEl.textContent = deleteBtn.dataset.tplName || '';
        getTplDeleteModal().show();
    });

    var confirmDeleteBtn = document.getElementById('prj-tpl-confirm-delete-btn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (!pendingDeleteId) return;
            var url = destroyUrlTpl.replace('__TID__', pendingDeleteId);
            var fd = new FormData();
            fd.append('_token', csrfToken);
            fd.append('_method', 'DELETE');
            confirmDeleteBtn.disabled = true;
            fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || t('js.progetti.delete_template_error', "Errore durante l'eliminazione."));
                toast(data.message || t('js.progetti.template_deleted', 'Modello eliminato.'), 'success');
                getTplDeleteModal().hide();
                var row = document.getElementById('prj-tpl-row-' + pendingDeleteId);
                if (row) row.remove();
            })
            .catch(function (err) {
                toast(err.message || t('js.progetti.generic_error_dot', 'Errore.'), 'danger');
            })
            .finally(function () { confirmDeleteBtn.disabled = false; });
        });
    }

}());
</script>

<?php $view->end(); ?>
