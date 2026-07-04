<?php
$rows        = $rows ?? [];
$hoursTotal  = (float) ($hours_total ?? 0);
$costTotal   = isset($cost_total) && $cost_total !== null ? (float) $cost_total : null;
$taskOptions = $task_options ?? [];
$pid         = (int) ($project['id'] ?? 0);
$canLogTime  = has_permission('progetti.log_time');
$hasCost     = false;
foreach ($rows as $r) { if (($r['cost'] ?? null) !== null) { $hasCost = true; break; } }
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0"><i class="fa-solid fa-clock me-1"></i><?= e(t('progetti.timesheet.title')) ?></h6>
    <div class="d-flex gap-2">
        <span class="badge bg-primary"><?= e(t('progetti.timesheet.total', ['hours' => number_format($hoursTotal, 2, ',', '.')])) ?></span>
        <?php if ($costTotal !== null): ?>
        <span class="badge bg-success"><?= e(t('progetti.timesheet.cost', ['amount' => number_format($costTotal, 2, ',', '.')])) ?></span>
        <?php endif; ?>
    </div>
</div>

<?php if ($canLogTime): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="POST"
              action="<?= e(route('projects.timesheet.store', ['id' => $pid])) ?>"
              hx-post="<?= e(route('projects.timesheet.store', ['id' => $pid])) ?>"
              hx-swap="none"
              class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label small mb-1"><?= e(t('progetti.timesheet.field_task')) ?></label>
                <select class="form-select form-select-sm" name="task_id" required>
                    <option value=""><?= e(t('progetti.timesheet.select_task')) ?></option>
                    <?php foreach ($taskOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>"><?= e($opt['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1"><?= e(t('progetti.timesheet.field_date')) ?></label>
                <input type="date" class="form-control form-control-sm" name="work_date"
                       value="<?= e(date('Y-m-d')) ?>"
                       <?= !empty($project['start_date']) ? 'min="' . e($project['start_date']) . '"' : '' ?>
                       <?= !empty($project['end_date'])   ? 'max="' . e($project['end_date'])   . '"' : '' ?>
                       required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1"><?= e(t('progetti.timesheet.field_hours')) ?></label>
                <input type="number" min="0.25" max="24" step="0.25" class="form-control form-control-sm" name="hours" placeholder="0.00" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1"><?= e(t('progetti.timesheet.field_note')) ?></label>
                <input type="text" class="form-control form-control-sm" name="note" placeholder="<?= e(t('progetti.timesheet.note_optional')) ?>" maxlength="500">
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100" type="submit">
                    <i class="fa-solid fa-plus me-1"></i><?= e(t('progetti.timesheet.log_btn')) ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
<div class="text-center text-muted py-4">
    <i class="fa-solid fa-clock fa-2x d-block mb-2 opacity-50"></i>
    <p class="small"><?= e(t('progetti.timesheet.no_entries')) ?></p>
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
        <tr>
            <th><?= e(t('progetti.timesheet.col_date')) ?></th>
            <th><?= e(t('progetti.timesheet.col_user')) ?></th>
            <th><?= e(t('progetti.timesheet.col_task')) ?></th>
            <th class="text-end"><?= e(t('progetti.timesheet.col_hours')) ?></th>
            <?php if ($hasCost): ?><th class="text-end"><?= e(t('progetti.timesheet.col_cost')) ?></th><?php endif; ?>
            <th><?= e(t('progetti.timesheet.col_note')) ?></th>
            <?php if ($canLogTime): ?><th class="text-end"><?= e(t('progetti.timesheet.col_actions')) ?></th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php
        $currentUserId = (int) auth()['id'];
        $canManageAll  = has_permission('progetti.manage_all');
        foreach ($rows as $row):
            $canEditRow = $canLogTime && ($canManageAll || (int) $row['user_id'] === $currentUserId);
            $editRowId  = 'prj-ts-edit-' . (int) $row['id'];
        ?>
        <tr>
            <td class="small"><?= e(format_date((string)$row['work_date'], 'short')) ?></td>
            <td class="small"><?= e($row['user_name']) ?></td>
            <td class="small"><?= e($row['task_title']) ?></td>
            <td class="text-end fw-semibold small"><?= e(number_format((float) $row['hours'], 2, ',', '.')) ?></td>
            <?php if ($hasCost): ?>
            <td class="text-end small text-success">
                <?= ($row['cost'] ?? null) !== null ? '€ ' . e(number_format((float) $row['cost'], 2, ',', '.')) : '<span class="text-muted">—</span>' ?>
            </td>
            <?php endif; ?>
            <td class="small text-muted"><?= e((string) ($row['note'] ?? '')) ?></td>
            <?php if ($canLogTime): ?>
            <td class="text-end">
                <?php if ($canEditRow): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary border-0"
                        data-prj-ts-edit-id="<?= e($editRowId) ?>"
                        data-bs-toggle="tooltip"
                        title="<?= e(t('progetti.timesheet.edit_tip')) ?>">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <?php endif; ?>
                <button type="button"
                        class="btn btn-sm btn-outline-danger border-0"
                        data-prj-confirm-action="1"
                        data-prj-confirm-title="<?= e(t('progetti.timesheet.remove_title')) ?>"
                        data-prj-confirm-message="<?= e(t('progetti.timesheet.remove_message', ['hours' => number_format((float) $row['hours'], 2, ',', '.'), 'date' => format_date((string)$row['work_date'], 'short')])) ?>"
                        data-prj-confirm-action-url="<?= e(route('projects.timesheet.destroy', ['id' => $pid, 'timesheetId' => (int) $row['id']])) ?>"
                        data-prj-confirm-submit="<?= e(t('progetti.timesheet.remove_submit')) ?>"
                        data-prj-confirm-icon="fa-clock"
                        data-bs-toggle="tooltip"
                        title="<?= e(t('progetti.timesheet.remove_tip')) ?>">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
            <?php endif; ?>
        </tr>
        <?php if ($canEditRow): ?>
        <tr id="<?= e($editRowId) ?>" class="d-none table-warning">
            <td colspan="<?= ($hasCost ? 6 : 5) + ($canLogTime ? 1 : 0) ?>">
                <form method="POST"
                      action="<?= e(route('projects.timesheet.update', ['id' => $pid, 'timesheetId' => (int) $row['id']])) ?>"
                      hx-post="<?= e(route('projects.timesheet.update', ['id' => $pid, 'timesheetId' => (int) $row['id']])) ?>"
                      hx-swap="none"
                      class="d-flex gap-2 align-items-center flex-wrap py-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="PUT">
                    <label class="small text-muted mb-0"><?= e(t('progetti.timesheet.col_hours')) ?>:</label>
                    <input type="number" min="0.25" max="24" step="0.25" name="hours"
                           value="<?= e(number_format((float) $row['hours'], 2, '.', '')) ?>"
                           class="form-control form-control-sm prj-ts-input-hours" required>
                    <label class="small text-muted mb-0"><?= e(t('progetti.timesheet.col_note')) ?>:</label>
                    <input type="text" name="note"
                           value="<?= e((string) ($row['note'] ?? '')) ?>"
                           class="form-control form-control-sm prj-ts-input-note" maxlength="500">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-check me-1"></i><?= e(t('progetti.timesheet.save')) ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="document.getElementById('<?= e($editRowId) ?>').classList.add('d-none')">
                        <?= e(t('progetti.timesheet.cancel')) ?>
                    </button>
                </form>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
        <tr>
            <td colspan="3" class="fw-semibold"><?= e(t('progetti.timesheet.total_row')) ?></td>
            <td class="text-end fw-bold"><?= e(number_format($hoursTotal, 2, ',', '.')) ?></td>
            <?php if ($hasCost): ?>
            <td class="text-end fw-bold text-success">
                <?= $costTotal !== null ? '€ ' . e(number_format($costTotal, 2, ',', '.')) : '—' ?>
            </td>
            <?php endif; ?>
            <td colspan="<?= $canLogTime ? 2 : 1 ?>"></td>
        </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
