<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/files.css'); ?>

<?php $view->start('content'); ?>

<?php
$errors = $errors ?? [];
$old    = $old    ?? [];

$fv = function (string $k, $default = '') use ($old, $fileRecord) {
    if (array_key_exists($k, $old))        return $old[$k];
    if (array_key_exists($k, $fileRecord)) return $fileRecord[$k];
    return $default;
};
$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');

$visibility = (string) $fv('visibility', 'private');
?>

<div class="container-fluid">

<?php
$moduleButtonsHtml = '<a href="' . route('files.show', ['id' => $fileRecord['id']]) . '" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('files.edit.cancel_tip')) . '"><i class="fa-solid fa-arrow-left"></i> ' . e(t('common.action.cancel')) . '</a>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('files.edit.title'),
    'moduleIcon'     => 'fa-solid fa-pen-to-square',
    'moduleSubtitle' => e($fileRecord['original_name']),
    'moduleButtons'  => $moduleButtonsHtml,
]);
?>

<div class="row justify-content-center">
  <div class="col-lg-8">

    <?php $view->include('partials/app-form-errors', [
        'errors' => $errors,
        'summaryTitle' => t('files.edit.fix_errors'),
    ]); ?>

    <form method="POST"
          action="<?= route('files.update', ['id' => $fileRecord['id']]) ?>"
          novalidate data-app-form>
      <?= csrf_field() ?>
      <input type="hidden" name="_method" value="PUT">

      <!-- Sezione: Organizzazione -->
      <fieldset class="app-form-section">
        <legend class="visually-hidden"><?= e(t('files.edit.org_legend')) ?></legend>
        <div class="app-form-section-header" role="button" tabindex="0"
             aria-expanded="true" aria-controls="files-org-body">
          <span class="app-card-icon"><i class="fa-solid fa-folder-tree"></i></span>
          <span class="fw-semibold flex-grow-1"><?= e(t('files.edit.org')) ?></span>
          <i class="fa-solid fa-chevron-down app-chevron"></i>
        </div>
        <div class="app-form-section-body" id="files-org-body">

          <div class="mb-3">
            <label for="fm-folder" class="form-label"><?= e(t('files.edit.folder')) ?></label>
            <input type="text"
                   id="fm-folder"
                   name="folder"
                   list="fm-folder-list"
                   class="<?= $fc('folder') ?>"
                   placeholder="<?= e(t('files.edit.folder_ph')) ?>"
                   value="<?= e($fv('folder')) ?>"
                   maxlength="200"
                   autocomplete="off"
                   aria-invalid="<?= $fe('folder') ? 'true' : 'false' ?>"
                   aria-describedby="fm-folder-feedback">
            <datalist id="fm-folder-list">
              <?php foreach ($folders as $f): ?>
                <option value="<?= e($f) ?>">
              <?php endforeach; ?>
            </datalist>
            <div id="fm-folder-feedback" class="invalid-feedback">
              <?= e($fe('folder') ?? t('files.edit.folder_invalid')) ?>
            </div>
          </div>

          <div class="mb-3">
            <label for="fm-description" class="form-label"><?= e(t('files.edit.description')) ?></label>
            <textarea id="fm-description"
                      name="description"
                      class="<?= $fc('description') ?>"
                      rows="3"
                      maxlength="500"
                      data-char-counter="fm-description-counter"
                      aria-invalid="<?= $fe('description') ? 'true' : 'false' ?>"
                      aria-describedby="fm-description-feedback fm-description-help"><?= e($fv('description')) ?></textarea>
            <div id="fm-description-feedback" class="invalid-feedback">
              <?= e($fe('description') ?? t('files.edit.desc_invalid')) ?>
            </div>
            <div class="form-text d-flex justify-content-between" id="fm-description-help">
              <span><?= e(t('files.edit.desc_max')) ?></span>
              <span class="app-char-counter" id="fm-description-counter">0 / 500</span>
            </div>
          </div>

          <div class="mb-0">
            <label for="fm-tags" class="form-label"><?= e(t('files.edit.tags')) ?></label>
            <input type="text"
                   id="fm-tags"
                   name="tags"
                   class="<?= $fc('tags') ?>"
                   placeholder="<?= e(t('files.edit.tags_ph')) ?>"
                   value="<?= e($fv('tags')) ?>"
                   maxlength="500"
                   autocomplete="off"
                   data-tag-preview="fm-tags-preview"
                   aria-invalid="<?= $fe('tags') ? 'true' : 'false' ?>"
                   aria-describedby="fm-tags-feedback fm-tags-help">
            <div id="fm-tags-feedback" class="invalid-feedback">
              <?= e($fe('tags') ?? t('files.edit.tags_invalid')) ?>
            </div>
            <div id="fm-tags-help" class="form-text"><?= e(t('files.edit.tags_help')) ?></div>
            <div id="fm-tags-preview" class="app-tag-preview" aria-hidden="true"></div>
          </div>
        </div>
      </fieldset>

      <!-- Sezione: Visibilità -->
      <fieldset class="app-form-section">
        <legend class="visually-hidden"><?= e(t('files.edit.vis_legend')) ?></legend>
        <div class="app-form-section-header" role="button" tabindex="0"
             aria-expanded="true" aria-controls="files-vis-body">
          <span class="app-card-icon"><i class="fa-solid fa-eye"></i></span>
          <span class="fw-semibold flex-grow-1"><?= e(t('files.edit.visibility')) ?></span>
          <i class="fa-solid fa-chevron-down app-chevron"></i>
        </div>
        <div class="app-form-section-body" id="files-vis-body">
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="visibility" id="vis-private"
                     value="private"
                     <?= $visibility === 'private' ? 'checked' : '' ?>>
              <label class="form-check-label" for="vis-private">
                <i class="fa-solid fa-lock me-1 text-muted"></i><?= e(t('files.edit.vis_private')) ?>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="visibility" id="vis-internal"
                     value="internal"
                     <?= $visibility === 'internal' ? 'checked' : '' ?>>
              <label class="form-check-label" for="vis-internal">
                <i class="fa-solid fa-users me-1 text-success"></i><?= e(t('files.edit.vis_internal')) ?>
              </label>
            </div>
          </div>
        </div>
      </fieldset>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= route('files.show', ['id' => $fileRecord['id']]) ?>"
           class="btn btn-outline-secondary"><?= e(t('common.action.cancel')) ?></a>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('files.edit.save')) ?>
        </button>
      </div>

    </form>

  </div>
</div>
</div>

<?php $view->end(); ?>
