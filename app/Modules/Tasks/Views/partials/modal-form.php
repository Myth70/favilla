<?php
/**
 * Modal form per creare/modificare attività via HTMX.
 *
 * Variabili: $task, $isEdit, $tags, $statuses, $priorities, $errors, $old
 */
$errors = $errors ?? [];
$old    = $old ?? [];

$curStatus    = $old['status']   ?? $task['status']   ?? ($status ?? 'todo');
$curPriority  = $old['priority'] ?? $task['priority'] ?? 'medium';
$selectedTags = $old['tag_ids'] ?? array_column($task['tags'] ?? [], 'id');

$fv = function (string $k, $default = '') use ($old, $task) {
    if (array_key_exists($k, $old))    return $old[$k];
    if (is_array($task) && array_key_exists($k, $task)) return $task[$k];
    return $default;
};
$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');
?>
<div class="modal-header">
    <h5 class="modal-title" id="att-modal-label">
        <i class="fa-solid fa-<?= $isEdit ? 'pen' : 'plus' ?>"></i>
        <?= $isEdit ? e(t('tasks.edit_page_title')) : e(t('tasks.new_page_title')) ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
</div>
<form id="att-modal-form" method="POST"
      action="<?= $isEdit
          ? e(route('tasks.update', ['id' => $task['id']]))
          : e(route('tasks.store')) ?>"
      novalidate data-app-form>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <div class="modal-body">
        <?php $view->include('partials/app-form-errors', [
            'errors' => $errors,
            'summaryTitle' => t('tasks.form.fix_errors'),
            'summaryBodyClass' => 'small',
        ]); ?>

        <!-- Titolo -->
        <div class="mb-3">
            <label for="modal-title" class="form-label"><?= e(t('tasks.fields.title')) ?> <span class="text-danger">*</span></label>
            <input type="text" class="<?= $fc('title') ?>" id="modal-title" name="title"
                   value="<?= e($fv('title')) ?>"
                   placeholder="<?= e(t('tasks.form.title_placeholder')) ?>"
                   required aria-required="true" maxlength="255"
                   autofocus
                   aria-invalid="<?= $fe('title') ? 'true' : 'false' ?>"
                   aria-describedby="modal-title-feedback">
            <div id="modal-title-feedback" class="invalid-feedback">
                <?= e($fe('title') ?? t('tasks.form.title_hint')) ?>
            </div>
        </div>

        <!-- Descrizione -->
        <div class="mb-3">
            <label for="modal-description" class="form-label"><?= e(t('tasks.fields.description')) ?></label>
            <textarea class="<?= $fc('description') ?>" id="modal-description" name="description"
                      rows="3"
                      maxlength="5000"
                      placeholder="<?= e(t('tasks.form.description_placeholder')) ?>"
                      aria-invalid="<?= $fe('description') ? 'true' : 'false' ?>"
                      aria-describedby="modal-description-feedback"><?= e($fv('description')) ?></textarea>
            <div id="modal-description-feedback" class="invalid-feedback">
                <?= e($fe('description') ?? t('tasks.form.description_invalid')) ?>
            </div>
        </div>

        <div class="row g-3">
            <!-- Status -->
            <div class="col-md-6">
                <label for="modal-status" class="form-label"><?= e(t('tasks.fields.status')) ?></label>
                <select class="<?= $fc('status', 'form-select') ?>" id="modal-status" name="status"
                        aria-invalid="<?= $fe('status') ? 'true' : 'false' ?>">
                    <?php foreach ($statuses as $key => $s): ?>
                    <option value="<?= e($key) ?>" <?= $curStatus === $key ? 'selected' : '' ?>>
                        <?= e($s['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fe('status')): ?>
                    <div class="invalid-feedback"><?= e($fe('status')) ?></div>
                <?php endif; ?>
            </div>
            <!-- Priorità -->
            <div class="col-md-6">
                <label for="modal-priority" class="form-label"><?= e(t('tasks.fields.priority')) ?></label>
                <select class="<?= $fc('priority', 'form-select') ?>" id="modal-priority" name="priority"
                        aria-invalid="<?= $fe('priority') ? 'true' : 'false' ?>">
                    <?php foreach ($priorities as $key => $p): ?>
                    <option value="<?= e($key) ?>" <?= $curPriority === $key ? 'selected' : '' ?>>
                        <?= e($p['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fe('priority')): ?>
                    <div class="invalid-feedback"><?= e($fe('priority')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mt-0">
            <!-- Data scadenza -->
            <div class="col-md-4">
                <label for="modal-due-date" class="form-label"><?= e(t('tasks.fields.due_date')) ?></label>
                <input type="date" class="<?= $fc('due_date') ?>" id="modal-due-date" name="due_date"
                       value="<?= e($fv('due_date')) ?>"
                       aria-invalid="<?= $fe('due_date') ? 'true' : 'false' ?>">
                <?php if ($fe('due_date')): ?>
                    <div class="invalid-feedback"><?= e($fe('due_date')) ?></div>
                <?php endif; ?>
            </div>
            <!-- Ora scadenza -->
            <div class="col-md-4">
                <?php $dueTime = $old['due_time'] ?? (isset($task['due_time']) ? substr((string) $task['due_time'], 0, 5) : ''); ?>
                <label for="modal-due-time" class="form-label"><?= e(t('tasks.fields.due_time')) ?></label>
                <input type="time" class="<?= $fc('due_time') ?>" id="modal-due-time" name="due_time"
                       value="<?= e($dueTime) ?>"
                       aria-invalid="<?= $fe('due_time') ? 'true' : 'false' ?>">
                <?php if ($fe('due_time')): ?>
                    <div class="invalid-feedback"><?= e($fe('due_time')) ?></div>
                <?php endif; ?>
            </div>
            <!-- Colore -->
            <div class="col-md-4">
                <label for="modal-color" class="form-label"><?= e(t('tasks.fields.color')) ?></label>
                <input type="color" class="form-control form-control-color w-100<?= $fe('color') ? ' is-invalid' : '' ?>"
                       id="modal-color" name="color"
                       value="<?= e($fv('color', '#0d6efd')) ?>"
                       aria-invalid="<?= $fe('color') ? 'true' : 'false' ?>">
            </div>
        </div>

        <!-- Tag -->
        <?php if (!empty($tags)): ?>
        <div class="mt-3">
            <label class="form-label"><?= e(t('tasks.fields.tags')) ?></label>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($tags as $tag):
                    $isChecked = in_array($tag['id'], $selectedTags);
                ?>
                <label class="att-tag-check">
                    <input type="checkbox" name="tag_ids[]"
                           value="<?= e((string) $tag['id']) ?>"
                           <?= $isChecked ? 'checked' : '' ?> class="d-none">
                    <span class="badge att-tag-badge" style="--att-tag-color: <?= e($tag['color']) ?>;">
                        <?= e($tag['name']) ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.action.cancel')) ?></button>
        <button type="submit" class="btn btn-primary" id="att-modal-save">
            <i class="fa-solid fa-check me-1"></i><?= $isEdit ? e(t('common.action.update')) : e(t('common.action.create')) ?>
        </button>
    </div>
</form>
