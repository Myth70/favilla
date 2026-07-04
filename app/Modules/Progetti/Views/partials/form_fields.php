<?php
$isEdit = !empty($isEdit);
$project = $project ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$statusOptions = $statusOptions ?? [];
$isModal = !empty($isModal);
$cancelUrl = route('projects.index');
$curStatus = $old['status'] ?? $project['status'] ?? 'planning';
?>

<?php if (!empty($errors['generic'][0] ?? null)): ?>
<div class="alert alert-danger"><?= e($errors['generic'][0]) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label"><?= e(t('progetti.form.name')) ?> <span class="text-danger">*</span></label>
        <input type="text" name="name"
               class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
               value="<?= e((string) ($old['name'] ?? $project['name'] ?? '')) ?>"
               required autofocus placeholder="<?= e(t('progetti.form.name_placeholder')) ?>">
        <?php if (isset($errors['name'])): ?>
        <div class="invalid-feedback"><?= e($errors['name'][0]) ?></div>
        <?php endif; ?>
    </div>
    <div class="col-md-4">
        <label class="form-label"><?= e(t('progetti.form.code')) ?></label>
        <input type="text" name="code" class="form-control"
               value="<?= e((string) ($old['code'] ?? $project['code'] ?? '')) ?>"
               placeholder="<?= e(t('progetti.form.code_placeholder')) ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label"><?= e(t('progetti.form.client')) ?></label>
        <input type="text" name="client_name" class="form-control"
               value="<?= e((string) ($old['client_name'] ?? $project['client_name'] ?? '')) ?>"
               placeholder="<?= e(t('progetti.form.client_placeholder')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label"><?= e(t('progetti.form.status')) ?></label>
        <select name="status" class="form-select">
            <?php foreach ($statusOptions as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= ($curStatus === $k) ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label"><?= e(t('progetti.form.budget')) ?></label>
        <input type="number" step="0.01" min="0" name="budget_planned" class="form-control"
               value="<?= e((string) ($old['budget_planned'] ?? $project['budget_planned'] ?? '0')) ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label"><?= e(t('progetti.form.start_date')) ?></label>
        <input type="date" name="start_date" class="form-control"
               value="<?= e((string) ($old['start_date'] ?? $project['start_date'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label"><?= e(t('progetti.form.end_date')) ?></label>
        <input type="date" name="end_date" class="form-control"
               value="<?= e((string) ($old['end_date'] ?? $project['end_date'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label"><?= e(t('progetti.form.estimated_hours')) ?></label>
        <input type="number" step="0.25" min="0" name="estimated_hours" class="form-control"
               value="<?= e((string) ($old['estimated_hours'] ?? $project['estimated_hours'] ?? '0')) ?>">
    </div>

    <div class="col-12">
        <label class="form-label"><?= e(t('progetti.form.description')) ?></label>
        <textarea name="description" rows="4" class="form-control"
                  placeholder="<?= e(t('progetti.form.description_placeholder')) ?>"><?= e((string) ($old['description'] ?? $project['description'] ?? '')) ?></textarea>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <?php if ($isModal): ?>
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('progetti.form.cancel')) ?></button>
    <?php else: ?>
    <a href="<?= e($cancelUrl) ?>" class="btn btn-outline-secondary"><?= e(t('progetti.form.cancel')) ?></a>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-check me-1"></i><?= e($isEdit ? t('progetti.form.submit_update') : t('progetti.form.submit_create')) ?>
    </button>
</div>
