<?php
$view->layout('main');
$view->pushStyle('css/progetti.css');
$view->pushScript('js/progetti.js');

$tasks        = $tasks ?? [];
$filters      = $filters ?? [];
$taskStatuses = \App\Modules\Progetti\Services\ProgettiService::getTaskStatuses();
$priorityCfg  = \App\Modules\Progetti\Services\ProgettiService::getPriorityConfig();
$totalTasks   = count($tasks);
$doneTasks    = count(array_filter($tasks, fn($t) => $t['status'] === 'done'));
?>

<?php $view->start('content'); ?>
<div class="container-fluid prj-page">

    <?php
    $view->include('partials/pf-hero-module', [
        'moduleName'     => t('progetti.my_tasks.title'),
        'moduleIcon'     => 'fa-solid fa-list-check',
        'moduleSubtitle' => t('progetti.my_tasks.subtitle', ['total' => $totalTasks]) . ($doneTasks > 0 ? t('progetti.my_tasks.subtitle_done', ['done' => $doneTasks]) : ''),
        'moduleButtons'  => (has_permission('progetti.edit') ? '<a href="' . e(route('projects.checklist_templates.index')) . '" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-list-check me-1"></i>' . e(t('progetti.my_tasks.checklist_templates_btn')) . '</a> ' : '') .
                              '<a href="' . e(route('projects.index')) . '" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-diagram-project me-1"></i>' . e(t('progetti.my_tasks.all_projects_btn')) . '</a>',
    ]);
    ?>

    <!-- Filtri -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" action="<?= e(route('projects.my_tasks')) ?>" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value=""><?= e(t('progetti.my_tasks.all_statuses')) ?></option>
                        <?php foreach ($taskStatuses as $k => $cfg): ?>
                        <option value="<?= e($k) ?>" <?= (($filters['status'] ?? '') === $k) ? 'selected' : '' ?>>
                            <?= e($cfg['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="priority" class="form-select form-select-sm">
                        <option value=""><?= e(t('progetti.my_tasks.all_priorities')) ?></option>
                        <?php foreach ($priorityCfg as $k => $cfg): ?>
                        <option value="<?= e($k) ?>" <?= (($filters['priority'] ?? '') === $k) ? 'selected' : '' ?>>
                            <?= e($cfg['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="sort" class="form-select form-select-sm">
                        <option value="due_date" <?= (($filters['sort'] ?? 'due_date') === 'due_date') ? 'selected' : '' ?>><?= e(t('progetti.my_tasks.sort_due')) ?></option>
                        <option value="priority" <?= (($filters['sort'] ?? '') === 'priority') ? 'selected' : '' ?>><?= e(t('progetti.my_tasks.sort_priority')) ?></option>
                        <option value="status" <?= (($filters['sort'] ?? '') === 'status') ? 'selected' : '' ?>><?= e(t('progetti.my_tasks.sort_status')) ?></option>
                        <option value="project_name" <?= (($filters['sort'] ?? '') === 'project_name') ? 'selected' : '' ?>><?= e(t('progetti.my_tasks.sort_project')) ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="dir" class="form-select form-select-sm">
                        <option value="ASC" <?= (($filters['dir'] ?? 'ASC') === 'ASC') ? 'selected' : '' ?>><?= e(t('progetti.my_tasks.sort_asc')) ?></option>
                        <option value="DESC" <?= (($filters['dir'] ?? '') === 'DESC') ? 'selected' : '' ?>><?= e(t('progetti.my_tasks.sort_desc')) ?></option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100" data-bs-toggle="tooltip" title="<?= e(t('progetti.my_tasks.filter_tip')) ?>">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="<?= e(route('projects.my_tasks')) ?>" class="btn btn-sm btn-outline-secondary w-100" data-bs-toggle="tooltip" title="<?= e(t('progetti.my_tasks.reset_filter_tip')) ?>">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista attivita -->
    <?php if (empty($tasks)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fa-solid fa-list-check fa-2x d-block mb-2 opacity-50"></i>
            <p class="mb-0"><?= e(t('progetti.my_tasks.no_tasks')) ?><?= !empty($filters['status']) || !empty($filters['priority']) ? e(t('progetti.my_tasks.no_tasks_filtered')) : '' ?>.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 prj-myt-table">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3"><?= e(t('progetti.my_tasks.col_task')) ?></th>
                        <th class="d-none d-sm-table-cell"><?= e(t('progetti.my_tasks.col_project')) ?></th>
                        <th class="d-none d-md-table-cell text-center"><?= e(t('progetti.my_tasks.col_checklist')) ?></th>
                        <th class="text-center"><?= e(t('progetti.my_tasks.col_status')) ?></th>
                        <th class="d-none d-sm-table-cell text-center"><?= e(t('progetti.my_tasks.col_priority')) ?></th>
                        <th class="d-none d-lg-table-cell text-end"><?= e(t('progetti.my_tasks.col_hours')) ?></th>
                        <th class="d-none d-md-table-cell text-end pe-3"><?= e(t('progetti.my_tasks.col_due')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task):
                        $taskId    = (int) $task['id'];
                        $projectId = (int) $task['project_id'];
                        $statusCfg = $taskStatuses[$task['status']] ?? ['label' => $task['status'], 'color' => 'secondary', 'icon' => 'fa-circle'];
                        $priCfg    = $priorityCfg[$task['priority']] ?? ['label' => $task['priority'], 'color' => 'secondary'];
                        $isOverdue = !empty($task['due_date']) && $task['due_date'] < date('Y-m-d') && $task['status'] !== 'done';
                        $clTotal   = (int) ($task['checklist_total'] ?? 0);
                        $clDone    = (int) ($task['checklist_done']  ?? 0);
                        $clPct     = $clTotal > 0 ? round($clDone / $clTotal * 100) : 0;
                    ?>
                    <tr class="prj-myt-row" role="button"
                        id="prj-mytask-<?= $taskId ?>"
                        data-myt-edit="1"
                        data-myt-task-id="<?= $taskId ?>"
                        data-myt-project-id="<?= $projectId ?>"
                        data-myt-project-name="<?= e($task['project_name']) ?>"
                        data-myt-title="<?= e($task['title']) ?>"
                        data-myt-priority="<?= e($task['priority'] ?? 'medium') ?>"
                        data-myt-status="<?= e($task['status'] ?? 'todo') ?>"
                        data-myt-start-date="<?= e($task['start_date'] ?? '') ?>"
                        data-myt-due-date="<?= e($task['due_date'] ?? '') ?>"
                        data-myt-hours="<?= e($task['estimated_hours'] ?? 0) ?>"
                        data-myt-description="<?= e($task['description'] ?? '') ?>"
                        data-myt-milestone-id="<?= e($task['milestone_id'] ?? '') ?>"
                        data-myt-milestone-name="<?= e($task['milestone_name'] ?? '') ?>"
                        data-myt-assigned-user-id="<?= e($task['assigned_user_id'] ?? '') ?>"
                        data-myt-checklist-url="<?= e(route('projects.tasks.checklist.index', ['id' => $projectId, 'taskId' => '__TID__'])) ?>"
                        data-myt-quick-status-url="<?= e(route('projects.tasks.quick_status', ['id' => $projectId, 'taskId' => '__TID__'])) ?>">
                        <td class="ps-3 fw-semibold text-truncate prj-mytask-title-col">
                            <?= e($task['title']) ?>
                        </td>
                        <td class="d-none d-sm-table-cell">
                            <span class="badge text-bg-secondary fw-normal text-truncate prj-badge-truncate"><?= e($task['project_name']) ?></span>
                        </td>
                        <td class="d-none d-md-table-cell text-center">
                            <?php if ($clTotal > 0): ?>
                            <div class="d-inline-flex align-items-center gap-1">
                                <div class="progress prj-progress-thin prj-mytask-cl-progress-wrap">
                                    <div class="progress-bar bg-<?= $clDone === $clTotal ? 'success' : 'primary' ?>" style="--prj-pct:<?= $clPct ?>%"></div>
                                </div>
                                <small class="text-muted prj-mytask-cl-stat prj-cl-stat-small"><?= $clDone ?>/<?= $clTotal ?></small>
                            </div>
                            <?php else: ?>
                            <small class="text-muted">—</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= e($statusCfg['color']) ?> prj-myt-status-badge">
                                <i class="fa-solid <?= e($statusCfg['icon']) ?> me-1"></i><?= e($statusCfg['label']) ?>
                            </span>
                        </td>
                        <td class="d-none d-sm-table-cell text-center">
                            <span class="badge bg-<?= e($priCfg['color']) ?> prj-myt-priority-badge"><?= e($priCfg['label']) ?></span>
                        </td>
                        <td class="d-none d-lg-table-cell text-end small text-muted">
                            <?= (float) ($task['estimated_hours'] ?? 0) > 0 ? e(number_format((float) $task['estimated_hours'], 1)) . ' h' : '—' ?>
                        </td>
                        <td class="d-none d-md-table-cell text-end pe-3">
                            <?php if (!empty($task['due_date'])): ?>
                            <small class="text-<?= $isOverdue ? 'danger fw-semibold' : 'muted' ?> prj-myt-due-label">
                                <?php if ($isOverdue): ?><i class="fa-solid fa-circle-exclamation me-1"></i><?php endif; ?>
                                <?= e(format_date((string)$task['due_date'], 'short')) ?>
                            </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ═══ Modal attivita ═══════════════════════════════════════════ -->
<div class="modal fade" id="prjMyTaskEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div id="prj-task-edit-form">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

                <!-- Header con info attivita -->
                <div class="modal-header py-2 border-bottom-0">
                    <div class="d-flex flex-column gap-1 flex-grow-1 overflow-hidden">
                        <h6 class="modal-title mb-0 text-truncate" id="prj-myt-modal-title"></h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap" id="prj-myt-modal-meta">
                            <small class="text-muted" id="prj-myt-modal-project"></small>
                            <span class="badge bg-secondary" id="prj-myt-modal-status-badge"></span>
                            <span class="badge bg-secondary" id="prj-myt-modal-priority-badge"></span>
                            <small class="text-muted" id="prj-myt-modal-due"></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button>
                </div>

                <!-- Tab nav -->
                <div class="px-3 border-bottom">
                    <ul class="nav nav-tabs border-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="prjTaskChecklistTabBtn" type="button"
                                    data-bs-toggle="tab" data-bs-target="#prjMyTaskTabChecklist"
                                    role="tab" aria-selected="false">
                                <i class="fa-solid fa-list-check me-1"></i><?= e(t('progetti.my_tasks.tab_checklist')) ?>
                                <span id="prj-checklist-badge" class="badge bg-secondary ms-1 d-none">0/0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="prjMyTaskTabDetailsBtn" type="button"
                                    data-bs-toggle="tab" data-bs-target="#prjMyTaskTabDetails"
                                    role="tab" aria-selected="false">
                                <i class="fa-solid fa-pen-to-square me-1"></i><?= e(t('progetti.my_tasks.tab_details')) ?>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="modal-body">
                    <div class="tab-content">

                        <!-- ── Tab Checklist (prima) ───────────────── -->
                        <div class="tab-pane fade" id="prjMyTaskTabChecklist" role="tabpanel">
                            <div id="prj-checklist-container"
                                 data-checklist-url=""
                                 data-quick-status-url=""
                                 data-csrf="<?= e(csrf_token()) ?>">
                                <div class="text-center text-muted py-5">
                                    <i class="fa-solid fa-spinner fa-spin fa-lg"></i>
                                </div>
                            </div>
                        </div>

                        <!-- ── Tab Dettagli ──────────────────────────── -->
                        <div class="tab-pane fade" id="prjMyTaskTabDetails" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label"><?= e(t('progetti.my_tasks.field_title')) ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="prj-myt-title" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><?= e(t('progetti.my_tasks.field_priority')) ?></label>
                                    <select class="form-select" id="prj-myt-priority">
                                        <option value="low"><?= e(t('progetti.priority.low')) ?></option>
                                        <option value="medium"><?= e(t('progetti.priority.medium')) ?></option>
                                        <option value="high"><?= e(t('progetti.priority.high')) ?></option>
                                        <option value="urgent"><?= e(t('progetti.priority.urgent')) ?></option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><?= e(t('progetti.my_tasks.field_status')) ?></label>
                                    <select class="form-select" id="prj-myt-status">
                                        <?php foreach ($taskStatuses as $k => $cfg): ?>
                                        <option value="<?= e($k) ?>"><?= e($cfg['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><?= e(t('progetti.my_tasks.field_hours')) ?></label>
                                    <input type="number" min="0" step="0.25" class="form-control" id="prj-myt-hours">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= e(t('progetti.my_tasks.field_start')) ?></label>
                                    <input type="date" class="form-control" id="prj-myt-start-date">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= e(t('progetti.my_tasks.field_due')) ?></label>
                                    <input type="date" class="form-control" id="prj-myt-due-date">
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?= e(t('progetti.my_tasks.field_description')) ?></label>
                                    <textarea class="form-control" id="prj-myt-description" rows="3" placeholder="<?= e(t('progetti.my_tasks.description_placeholder')) ?>"></textarea>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" id="prj-task-mark-done-btn"
                            class="btn btn-success btn-sm d-none me-auto"
                            data-task-id=""
                            data-bs-toggle="tooltip"
                            title="<?= e(t('progetti.my_tasks.mark_done_tip')) ?>">
                        <i class="fa-solid fa-circle-check me-1"></i><?= e(t('progetti.my_tasks.mark_done_btn')) ?>
                    </button>
                    <a id="prj-myt-open-project-link" href="#" class="btn btn-outline-secondary btn-sm"
                       data-bs-toggle="tooltip" title="<?= e(t('progetti.my_tasks.open_project_tip')) ?>">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= e(t('progetti.my_tasks.open_project')) ?>
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= e(t('progetti.my_tasks.close')) ?></button>
                    <button type="button" class="btn btn-primary btn-sm" id="prj-myt-save-btn">
                        <i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('progetti.my_tasks.save')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';

    var modalEl  = document.getElementById('prjMyTaskEditModal');
    if (!modalEl) return;
    var _bsModal = null;
    function getModal() {
        if (!_bsModal) _bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        return _bsModal;
    }
    var saveBtn  = document.getElementById('prj-myt-save-btn');
    var openLink = document.getElementById('prj-myt-open-project-link');

    var _taskId = null, _projectId = null, _editRow = null;

    var STATUS_CFG   = <?= json_encode($taskStatuses) ?>;
    var PRIORITY_CFG = <?= json_encode($priorityCfg) ?>;
    var CSRF_TOKEN   = <?= json_encode(csrf_token()) ?>;

    function _fmtDate(d) {
        if (!d) return '';
        var p = d.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    // ── Click sulla riga → apri modal ─────────────────────
    document.addEventListener('click', function (e) {
        var row = e.target.closest('[data-myt-edit="1"]');
        if (!row) return;
        e.preventDefault();

        _editRow   = row;
        _taskId    = row.dataset.mytTaskId;
        _projectId = row.dataset.mytProjectId;

        // Popola form dettagli
        document.getElementById('prj-myt-title').value       = row.dataset.mytTitle || '';
        document.getElementById('prj-myt-priority').value    = row.dataset.mytPriority || 'medium';
        document.getElementById('prj-myt-status').value      = row.dataset.mytStatus || 'todo';
        document.getElementById('prj-myt-hours').value       = row.dataset.mytHours || '0';
        document.getElementById('prj-myt-start-date').value  = row.dataset.mytStartDate || '';
        document.getElementById('prj-myt-due-date').value    = row.dataset.mytDueDate || '';
        document.getElementById('prj-myt-description').value = row.dataset.mytDescription || '';

        // Header modal
        var sCfg = STATUS_CFG[row.dataset.mytStatus] || { label: row.dataset.mytStatus, color: 'secondary', icon: 'fa-circle' };
        var pCfg = PRIORITY_CFG[row.dataset.mytPriority] || { label: row.dataset.mytPriority, color: 'secondary' };
        document.getElementById('prj-myt-modal-title').textContent = row.dataset.mytTitle || '';
        document.getElementById('prj-myt-modal-project').innerHTML = '<i class="fa-solid fa-diagram-project me-1"></i>' + (row.dataset.mytProjectName || '');
        var sBadge = document.getElementById('prj-myt-modal-status-badge');
        sBadge.className = 'badge bg-' + sCfg.color;
        sBadge.innerHTML = '<i class="fa-solid ' + sCfg.icon + ' me-1"></i>' + sCfg.label;
        var pBadge = document.getElementById('prj-myt-modal-priority-badge');
        pBadge.className = 'badge bg-' + pCfg.color;
        pBadge.textContent = pCfg.label;
        var dueEl = document.getElementById('prj-myt-modal-due');
        var dueStr = _fmtDate(row.dataset.mytDueDate);
        dueEl.innerHTML = dueStr ? '<i class="fa-regular fa-calendar me-1"></i>' + dueStr : '';

        // Link progetto
        if (openLink) openLink.href = row.dataset.mytProjectId
            ? '<?= e(rtrim(route('projects.show', ['id' => 0]), '0')) ?>' + row.dataset.mytProjectId
            : '#';

        // Reset e prepara checklist
        var clCont = document.getElementById('prj-checklist-container');
        if (clCont) {
            clCont.dataset.checklistUrl   = row.dataset.mytChecklistUrl   || '';
            clCont.dataset.quickStatusUrl = row.dataset.mytQuickStatusUrl || '';
            clCont.innerHTML = '<div class="text-center text-muted py-5"><i class="fa-solid fa-spinner fa-spin fa-lg"></i></div>';
        }
        var clBadge = document.getElementById('prj-checklist-badge');
        if (clBadge) { clBadge.textContent = '0/0'; clBadge.classList.add('d-none'); }
        var clDoneBtn = document.getElementById('prj-task-mark-done-btn');
        if (clDoneBtn) clDoneBtn.classList.add('d-none');

        if (typeof window._prjSetCurrentTask === 'function') {
            window._prjSetCurrentTask(_taskId);
        }

        // Apri su tab Checklist
        var clTabBtn = document.getElementById('prjTaskChecklistTabBtn');
        if (clTabBtn) bootstrap.Tab.getOrCreateInstance(clTabBtn).show();

        getModal().show();

        // Trigger caricamento checklist dopo che il tab è visibile
        if (clTabBtn) clTabBtn.click();
    });

    // ── Salva ────────────────────────────────────────────────────
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!_taskId || !_projectId) return;
            var titleEl = document.getElementById('prj-myt-title');
            if (!titleEl.value.trim()) {
                titleEl.classList.add('is-invalid');
                titleEl.focus();
                return;
            }
            titleEl.classList.remove('is-invalid');

            var fd = new FormData();
            fd.append('_token',           CSRF_TOKEN);
            fd.append('_method',          'PUT');
            fd.append('title',            titleEl.value.trim());
            fd.append('priority',         document.getElementById('prj-myt-priority').value);
            fd.append('status',           document.getElementById('prj-myt-status').value);
            fd.append('estimated_hours',  document.getElementById('prj-myt-hours').value || '0');
            fd.append('start_date',       document.getElementById('prj-myt-start-date').value || '');
            fd.append('due_date',         document.getElementById('prj-myt-due-date').value || '');
            fd.append('description',      document.getElementById('prj-myt-description').value || '');
            if (_editRow) {
                fd.append('milestone_id',      _editRow.dataset.mytMilestoneId      || '');
                fd.append('assigned_user_id',  _editRow.dataset.mytAssignedUserId   || '');
            }

            saveBtn.disabled = true;

            fetch('<?= e(rtrim(route('projects.show', ['id' => 0]), '0')) ?>' + _projectId + '/tasks/' + _taskId, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    getModal().hide();
                    if (window.notify) window.notify(data.message || t('js.progetti.task_updated', 'Attivita aggiornata.'), 'success');
                    _syncRow();
                } else {
                    if (window.notify) window.notify(data.message || t('js.progetti.task_update_error', "Errore durante l'aggiornamento."), 'danger');
                }
            })
            .catch(function () {
                if (window.notify) window.notify(t('js.progetti.network_error_dot', 'Errore di rete.'), 'danger');
            })
            .finally(function () { saveBtn.disabled = false; });
        });
    }

    // ── Sync riga tabella dopo salvataggio ─────────────────────
    function _syncRow() {
        if (!_editRow) return;

        var newTitle    = document.getElementById('prj-myt-title').value.trim();
        var newPriority = document.getElementById('prj-myt-priority').value;
        var newStatus   = document.getElementById('prj-myt-status').value;
        var newDue      = document.getElementById('prj-myt-due-date').value;

        // Aggiorna data-attributes per future aperture
        _editRow.dataset.mytTitle       = newTitle;
        _editRow.dataset.mytPriority    = newPriority;
        _editRow.dataset.mytStatus      = newStatus;
        _editRow.dataset.mytDueDate     = newDue;
        _editRow.dataset.mytHours       = document.getElementById('prj-myt-hours').value;
        _editRow.dataset.mytDescription = document.getElementById('prj-myt-description').value;
        _editRow.dataset.mytStartDate   = document.getElementById('prj-myt-start-date').value;

        // Titolo cella
        var titleTd = _editRow.querySelector('td:first-child');
        if (titleTd) titleTd.textContent = newTitle;

        // Badge stato
        var sCfg = STATUS_CFG[newStatus] || { label: newStatus, color: 'secondary', icon: 'fa-circle' };
        var sBadge = _editRow.querySelector('.prj-myt-status-badge');
        if (sBadge) {
            sBadge.className = 'badge bg-' + sCfg.color + ' prj-myt-status-badge';
            sBadge.innerHTML = '<i class="fa-solid ' + sCfg.icon + ' me-1"></i>' + sCfg.label;
        }

        // Badge priorità
        var pCfg = PRIORITY_CFG[newPriority] || { label: newPriority, color: 'secondary' };
        var pBadge = _editRow.querySelector('.prj-myt-priority-badge');
        if (pBadge) {
            pBadge.className = 'badge bg-' + pCfg.color + ' prj-myt-priority-badge';
            pBadge.textContent = pCfg.label;
        }

        // Scadenza
        var dueEl = _editRow.querySelector('.prj-myt-due-label');
        if (dueEl) {
            dueEl.textContent = _fmtDate(newDue);
            dueEl.className = 'text-' + (newDue && newDue < new Date().toISOString().slice(0, 10) && newStatus !== 'done' ? 'danger fw-semibold' : 'muted') + ' prj-myt-due-label';
        }
    }
})();
</script>
<?php $view->end(); ?>
