<?php
/**
 * VISTA FORM — Usata sia per creazione che per modifica.
 *
 * VARIABILI:
 *   $item   → null (creazione) oppure array (modifica)
 *   $errors → ['campo' => ['messaggio', ...]] da sessione
 *   $old    → dati precedenti per ripopolare dopo errore
 *
 * PATTERN PER OGNI CAMPO:
 *   1. class "is-invalid" se errore presente
 *   2. value da $old (priorità) o $item (fallback) o '' (default)
 *   3. <div class="invalid-feedback"> sotto il campo per messaggio errore
 *
 * i18n: ogni stringa user-facing passa da e(t('example.<chiave>')).
 */

$view->layout('main');
$isEdit = $item !== null;
$errors = $errors ?? [];
$old = $old ?? [];

$action = $isEdit
    ? route('example.update', ['id' => $item['id']])
    : route('example.store');

$heroButtons = '<a href="' . e(route('example.index')) . '" class="btn btn-sm btn-outline-secondary">'
             . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('example.actions.back')) . '</a>';

$fv = function (string $key, $default = '') use ($old, $item) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($item !== null && array_key_exists($key, $item)) {
        return $item[$key];
    }
    return $default;
};

$fe = fn(string $key) => $errors[$key][0] ?? null;
$fc = fn(string $key, string $base = 'form-control') => $base . ($fe($key) ? ' is-invalid' : '');
$currentStatus = (string) ($fv('status', 'active'));
?>

<?php $view->start('content'); ?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-module', [
        'moduleName'     => $isEdit ? t('example.edit_page_title') : t('example.new_page_title'),
        'moduleIcon'     => $isEdit ? 'fa-solid fa-pen-to-square' : 'fa-solid fa-plus',
        'moduleSubtitle' => $isEdit
            ? e((string) ($item['name'] ?? t('example.form.subtitle_edit')))
            : e(t('example.form.subtitle_new')),
        'moduleButtons'  => $heroButtons,
    ]); ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php $view->include('partials/app-form-errors', [
                'errors' => $errors,
                'summaryTitle' => t('example.form.errors_summary'),
            ]); ?>

            <div class="card shadow-sm">
                <div class="card-body">

                    <form method="POST" action="<?= e($action) ?>" novalidate data-app-form>
                        <?= csrf_field() ?>
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="_method" value="PUT">
                        <?php endif; ?>

                        <fieldset class="app-form-section">
                            <legend class="visually-hidden"><?= e(t('example.sections.main')) ?></legend>
                            <div class="app-form-section-header" role="button" tabindex="0"
                                 aria-expanded="true" aria-controls="example-main-body">
                                <span class="app-card-icon"><i class="fa-solid fa-address-card"></i></span>
                                <span class="fw-semibold flex-grow-1"><?= e(t('example.sections.main')) ?></span>
                                <i class="fa-solid fa-chevron-down app-chevron"></i>
                            </div>
                            <div class="app-form-section-body" id="example-main-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label"><?= e(t('example.fields.name')) ?> <span class="text-danger">*</span></label>
                                        <input type="text"
                                               class="<?= $fc('name') ?>"
                                               id="name" name="name"
                                               value="<?= e((string) $fv('name')) ?>"
                                               maxlength="255"
                                               required aria-required="true"
                                               aria-invalid="<?= $fe('name') ? 'true' : 'false' ?>">
                                        <div class="invalid-feedback"><?= e($fe('name') ?? t('example.feedback.name')) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label"><?= e(t('example.fields.email')) ?> <span class="text-danger">*</span></label>
                                        <input type="email"
                                               class="<?= $fc('email') ?>"
                                               id="email" name="email"
                                               value="<?= e((string) $fv('email')) ?>"
                                               maxlength="255"
                                               required aria-required="true"
                                               aria-invalid="<?= $fe('email') ? 'true' : 'false' ?>">
                                        <div class="invalid-feedback"><?= e($fe('email') ?? t('example.feedback.email')) ?></div>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="app-form-section">
                            <legend class="visually-hidden"><?= e(t('example.sections.content')) ?></legend>
                            <div class="app-form-section-header" role="button" tabindex="0"
                                 aria-expanded="true" aria-controls="example-content-body">
                                <span class="app-card-icon"><i class="fa-solid fa-pen-to-square"></i></span>
                                <span class="fw-semibold flex-grow-1"><?= e(t('example.sections.content')) ?></span>
                                <i class="fa-solid fa-chevron-down app-chevron"></i>
                            </div>
                            <div class="app-form-section-body" id="example-content-body">
                                <div class="mb-3">
                                    <label for="description" class="form-label"><?= e(t('example.fields.description')) ?></label>
                                    <textarea class="<?= $fc('description') ?>"
                                              id="description" name="description"
                                              rows="4"
                                              aria-invalid="<?= $fe('description') ? 'true' : 'false' ?>"><?= e((string) $fv('description')) ?></textarea>
                                    <div class="invalid-feedback"><?= e($fe('description') ?? t('example.feedback.description')) ?></div>
                                </div>

                                <div class="mb-0">
                                    <label for="status" class="form-label"><?= e(t('example.fields.status')) ?></label>
                                    <select class="<?= $fc('status', 'form-select') ?>"
                                            id="status" name="status"
                                            aria-invalid="<?= $fe('status') ? 'true' : 'false' ?>">
                                        <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>><?= e(t('example.status.active')) ?></option>
                                        <option value="inactive" <?= $currentStatus === 'inactive' ? 'selected' : '' ?>><?= e(t('example.status.inactive')) ?></option>
                                        <option value="archived" <?= $currentStatus === 'archived' ? 'selected' : '' ?>><?= e(t('example.status.archived')) ?></option>
                                    </select>
                                    <div class="invalid-feedback"><?= e($fe('status') ?? t('example.feedback.status')) ?></div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="d-flex gap-2 justify-content-end flex-wrap mt-4">
                            <a href="<?= e(route('example.index')) ?>" class="btn btn-outline-secondary">
                                <?= e(t('example.actions.cancel')) ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-check me-1"></i>
                                <?= e($isEdit ? t('example.actions.update') : t('example.actions.create')) ?>
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?php $view->end(); ?>
