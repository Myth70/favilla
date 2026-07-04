<?php
/**
 * Changelog — form create/edit.
 * Variables: $release (null per create, array per edit)
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->start('content');

$isEdit  = $release !== null;
$old     = $_SESSION['_old']    ?? [];
$errors  = $_SESSION['_errors'] ?? [];
unset($_SESSION['_old'], $_SESSION['_errors']);

$translations = $translations ?? [];

$val = function (string $key, string $default = '') use ($release, $old): string {
    if (!empty($old[$key])) return $old[$key];
    if ($release && isset($release[$key])) return (string) $release[$key];
    return $default;
};
$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-code-branch',
        'adminTitle'    => $isEdit ? t('admin.changelog.form_edit_title') : t('admin.changelog.new_release'),
        'adminSubtitle' => $isEdit ? 'v' . e($release['version']) : null,
    ]); ?>

<div class="row justify-content-center">
<div class="col-lg-8">

    <?php $view->include('partials/app-form-errors', [
        'errors' => $errors,
        'summaryTitle' => t('admin.changelog.fix_errors'),
    ]); ?>

    <form method="POST"
          action="<?= $isEdit
              ? e(route('admin.changelog.update', ['id' => $release['id']]))
              : e(route('admin.changelog.store')) ?>"
          novalidate data-app-form>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
        <?php endif; ?>

        <!-- Sezione: Metadati -->
        <fieldset class="app-form-section">
            <legend class="visually-hidden"><?= e(t('admin.changelog.meta_legend')) ?></legend>
            <div class="app-form-section-header" role="button" tabindex="0"
                 aria-expanded="true" aria-controls="ch-meta-body">
                <span class="app-card-icon"><i class="fa-solid fa-tag"></i></span>
                <span class="fw-semibold flex-grow-1"><?= e(t('admin.changelog.meta_title')) ?></span>
                <i class="fa-solid fa-chevron-down app-chevron"></i>
            </div>
            <div class="app-form-section-body" id="ch-meta-body">
                <div class="row g-3">
                    <div class="col-sm-5">
                        <label for="ch-version" class="form-label fw-semibold">
                            <?= e(t('admin.changelog.field_version')) ?> <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               id="ch-version"
                               name="version"
                               class="<?= $fc('version') ?>"
                               value="<?= e($val('version')) ?>"
                               placeholder="<?= e(t('admin.changelog.version_ph')) ?>"
                               pattern="\d+\.\d+\.\d+"
                               inputmode="decimal"
                               autocomplete="off"
                               required aria-required="true"
                               aria-invalid="<?= $fe('version') ? 'true' : 'false' ?>"
                               aria-describedby="ch-version-help ch-version-feedback">
                        <div id="ch-version-help" class="form-text"><?= e(t('admin.changelog.version_help')) ?></div>
                        <div id="ch-version-feedback" class="invalid-feedback"><?= e($fe('version') ?? t('admin.changelog.version_invalid')) ?></div>
                    </div>
                    <div class="col-sm-5">
                        <label for="ch-release-date" class="form-label fw-semibold">
                            <?= e(t('admin.changelog.field_date')) ?> <span class="text-danger">*</span>
                        </label>
                        <input type="date"
                               id="ch-release-date"
                               name="release_date"
                               class="<?= $fc('release_date') ?>"
                               value="<?= e($val('release_date', date('Y-m-d'))) ?>"
                               required aria-required="true"
                               aria-invalid="<?= $fe('release_date') ? 'true' : 'false' ?>"
                               aria-describedby="ch-release-date-feedback">
                        <div id="ch-release-date-feedback" class="invalid-feedback"><?= e($fe('release_date') ?? t('admin.changelog.date_invalid')) ?></div>
                    </div>
                    <div class="col-sm-2 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox"
                                   role="switch"
                                   id="ch-published" name="is_published" value="1"
                                   <?= ($val('is_published') || (!empty($old) && isset($old['is_published']))) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ch-published">
                                <?= e(t('admin.changelog.publish_label')) ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <!-- Sezione: Contenuto -->
        <fieldset class="app-form-section">
            <legend class="visually-hidden"><?= e(t('admin.changelog.content_legend')) ?></legend>
            <div class="app-form-section-header" role="button" tabindex="0"
                 aria-expanded="true" aria-controls="ch-content-body">
                <span class="app-card-icon"><i class="fa-solid fa-file-lines"></i></span>
                <span class="fw-semibold flex-grow-1"><?= e(t('admin.changelog.content_title')) ?></span>
                <i class="fa-solid fa-chevron-down app-chevron"></i>
            </div>
            <div class="app-form-section-body" id="ch-content-body">
                <div class="mb-3">
                    <label for="ch-title" class="form-label fw-semibold">
                        <?= e(t('admin.changelog.field_title')) ?> <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="ch-title"
                           name="title"
                           class="<?= $fc('title') ?>"
                           value="<?= e($val('title')) ?>"
                           maxlength="255"
                           placeholder="<?= e(t('admin.changelog.title_ph')) ?>"
                           required aria-required="true"
                           aria-invalid="<?= $fe('title') ? 'true' : 'false' ?>"
                           aria-describedby="ch-title-feedback">
                    <div id="ch-title-feedback" class="invalid-feedback"><?= e($fe('title') ?? t('admin.changelog.title_invalid')) ?></div>
                </div>

                <div class="mb-0">
                    <label for="ch-notes" class="form-label fw-semibold">
                        <?= e(t('admin.changelog.field_notes')) ?> <span class="text-danger">*</span>
                    </label>
                    <textarea id="ch-notes"
                              name="notes"
                              class="<?= $fc('notes', 'form-control adm-notes-area') ?>"
                              rows="10"
                              placeholder="<?= e(t('admin.changelog.notes_ph')) ?>"
                              required aria-required="true"
                              aria-invalid="<?= $fe('notes') ? 'true' : 'false' ?>"
                              aria-describedby="ch-notes-feedback"><?= e($val('notes')) ?></textarea>
                    <div id="ch-notes-feedback" class="invalid-feedback"><?= e($fe('notes') ?? t('admin.changelog.notes_invalid')) ?></div>
                </div>
            </div>
        </fieldset>

        <?php
        $trFallback  = (string) config('localization.fallback', 'it');
        $trSupported = array_values(array_filter(
            (array) config('localization.supported', []),
            static fn ($l) => (string) $l !== $trFallback
        ));
        $trNames = (array) config('localization.names', []);
        $trFlags = (array) config('localization.flags', []);
        $trOld   = (isset($old['tr']) && is_array($old['tr'])) ? $old['tr'] : [];
        $trVal   = function (string $locale, string $field) use ($trOld, $translations): string {
            if (isset($trOld[$locale][$field]) && $trOld[$locale][$field] !== '') {
                return (string) $trOld[$locale][$field];
            }
            return (string) ($translations[$locale][$field] ?? '');
        };
        ?>
        <?php if ($trSupported): ?>
        <!-- Sezione: Traduzioni -->
        <fieldset class="app-form-section">
            <legend class="visually-hidden"><?= e(t('admin.changelog.tr_legend')) ?></legend>
            <div class="app-form-section-header" role="button" tabindex="0"
                 aria-expanded="false" aria-controls="ch-tr-body">
                <span class="app-card-icon"><i class="fa-solid fa-language"></i></span>
                <span class="fw-semibold flex-grow-1"><?= e(t('admin.changelog.tr_title')) ?></span>
                <i class="fa-solid fa-chevron-down app-chevron"></i>
            </div>
            <div class="app-form-section-body" id="ch-tr-body">
                <p class="form-text mb-3"><?= e(t('admin.changelog.tr_hint')) ?></p>

                <ul class="nav nav-tabs" role="tablist">
                    <?php foreach ($trSupported as $i => $loc): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                                    id="ch-tr-tab-<?= e($loc) ?>"
                                    data-bs-toggle="tab" data-bs-target="#ch-tr-pane-<?= e($loc) ?>"
                                    type="button" role="tab"
                                    aria-controls="ch-tr-pane-<?= e($loc) ?>"
                                    aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                                <?php if (!empty($trFlags[$loc])): ?><span class="me-1"><?= $trFlags[$loc] ?></span><?php endif; ?>
                                <?= e($trNames[$loc] ?? strtoupper((string) $loc)) ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="tab-content pt-3">
                    <?php foreach ($trSupported as $i => $loc): ?>
                        <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
                             id="ch-tr-pane-<?= e($loc) ?>" role="tabpanel"
                             aria-labelledby="ch-tr-tab-<?= e($loc) ?>">
                            <div class="mb-3">
                                <label for="ch-tr-title-<?= e($loc) ?>" class="form-label fw-semibold">
                                    <?= e(t('admin.changelog.field_title')) ?>
                                </label>
                                <input type="text"
                                       id="ch-tr-title-<?= e($loc) ?>"
                                       name="tr[<?= e($loc) ?>][title]"
                                       class="form-control"
                                       maxlength="255"
                                       value="<?= e($trVal($loc, 'title')) ?>"
                                       placeholder="<?= e(t('admin.changelog.title_ph')) ?>">
                            </div>
                            <div class="mb-0">
                                <label for="ch-tr-notes-<?= e($loc) ?>" class="form-label fw-semibold">
                                    <?= e(t('admin.changelog.field_notes')) ?>
                                </label>
                                <textarea id="ch-tr-notes-<?= e($loc) ?>"
                                          name="tr[<?= e($loc) ?>][notes]"
                                          class="form-control adm-notes-area"
                                          rows="8"
                                          placeholder="<?= e(t('admin.changelog.notes_ph')) ?>"><?= e($trVal($loc, 'notes')) ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </fieldset>
        <?php endif; ?>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="<?= $isEdit
                ? e(route('admin.changelog.show', ['id' => $release['id']]))
                : e(route('admin.changelog.index')) ?>"
               class="btn btn-outline-secondary"><?= e(t('admin.changelog.cancel')) ?></a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-1"></i>
                <?= e($isEdit ? t('admin.changelog.save_edit') : t('admin.changelog.save_new')) ?>
            </button>
        </div>
    </form>

</div>
</div>
</div>

<?php $view->end(); ?>
