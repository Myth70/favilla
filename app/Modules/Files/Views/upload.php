<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/files.css'); ?>
<?php $view->pushScript('js/file-dropzone.js'); ?>
<?php $view->pushScript('js/files.js'); ?>

<?php
$errors = $errors ?? [];
$old    = $old    ?? [];

$fv = fn(string $k, $default = '') => $old[$k] ?? $default;
$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');
?>

<?php $view->start('content'); ?>

<div class="container-fluid">
<div class="row g-4">

    <div class="col-12">
    <?php
    $uploadButtons = '<a href="' . e(route('files.index')) . '" class="btn btn-outline-secondary btn-sm text-nowrap">' .
                     '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('files.my_files')) . '</a>';
    $view->include('partials/pf-hero-module', [
        'moduleName'     => t('files.upload_title'),
        'moduleIcon'     => 'fa-solid fa-cloud-arrow-up',
        'moduleSubtitle' => t('files.upload.subtitle'),
        'moduleButtons'  => $uploadButtons,
    ]);
    ?>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center">
                <span class="app-card-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                <?= e(t('files.upload.card_header')) ?>
            </div>
            <div class="card-body">

                <?php $view->include('partials/app-form-errors', [
                    'errors' => $errors,
                    'summaryTitle' => t('files.upload.fix_errors'),
                    'summaryBodyClass' => 'small',
                ]); ?>

                <form method="POST"
                      action="<?= e(route('files.store')) ?>"
                      enctype="multipart/form-data"
                      novalidate data-app-form>
                    <?= csrf_field() ?>

                    <!-- File dropzone -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="fm-file-input">
                            <?= e(t('files.upload.file_label')) ?> <span class="text-danger">*</span>
                        </label>
                        <div id="fm-upload-zone"
                             class="fm-upload-zone fd-zone<?= $fe('file') ? ' is-invalid' : '' ?>"
                             data-max-bytes="<?= e((string) $maxBytes) ?>"
                             data-accept-mimes="<?= e(json_encode($allowedMimes)) ?>">
                            <input type="file"
                                   id="fm-file-input"
                                   name="file"
                                   accept="<?= e($acceptAttr) ?>"
                                   class="fd-input d-none"
                                   aria-required="true"
                                   aria-invalid="<?= $fe('file') ? 'true' : 'false' ?>"
                                   aria-describedby="fm-file-feedback fm-file-help">
                            <div class="fd-label fm-upload-zone-inner text-center py-5 px-3">
                                <i class="fa-solid fa-cloud-arrow-up fa-3x mb-3 fm-drop-accent-icon"></i>
                                <p class="mb-1 fw-semibold"><?= e(t('files.upload.dropzone_main')) ?> <span class="fm-drop-accent-text"><?= e(t('files.upload.dropzone_browse')) ?></span></p>
                                <p id="fm-file-help" class="text-muted small mb-0">
                                    <?= e(t('files.upload.dropzone_types')) ?><br>
                                    <?= e(t('files.upload.dropzone_max', ['mb' => round($maxBytes / 1048576)])) ?>
                                </p>
                            </div>
                            <div class="fd-preview d-none text-center py-4 px-3">
                                <i class="fa-solid fa-file-circle-check fa-3x text-success mb-3"></i>
                                <p class="fd-filename fw-semibold mb-1 text-truncate"></p>
                                <p class="text-muted small"><?= e(t('files.upload.file_ready')) ?></p>
                            </div>
                        </div>
                        <?php if ($fe('file')): ?>
                            <div id="fm-file-feedback" class="invalid-feedback d-block"><?= e($fe('file')) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Folder -->
                    <div class="mb-3">
                        <label for="fm-folder" class="form-label"><?= e(t('files.upload.folder_label')) ?></label>
                        <?php if (!empty($folders)): ?>
                        <div class="fm-folder-chips mb-2 d-flex flex-wrap gap-1">
                            <?php foreach ($folders as $f): ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary fm-folder-chip <?= (($old['folder'] ?? '') === $f) ? 'active' : '' ?>"
                                    data-folder="<?= e($f) ?>"
                                    data-bs-toggle="tooltip" data-bs-title="<?= e(t('files.upload.folder_use', ['name' => $f])) ?>">
                                <i class="fa-solid fa-folder fa-xs me-1"></i><?= e(basename($f) ?: $f) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="input-group">
                            <span class="input-group-text app-input-icon">
                                <i class="fa-solid fa-folder fa-sm"></i>
                            </span>
                            <input type="text"
                                   id="fm-folder"
                                   name="folder"
                                   class="<?= $fc('folder') ?>"
                                   placeholder="<?= e(t('files.upload.folder_ph')) ?>"
                                   value="<?= e($fv('folder')) ?>"
                                   maxlength="200"
                                   autocomplete="off"
                                   aria-invalid="<?= $fe('folder') ? 'true' : 'false' ?>"
                                   aria-describedby="fm-folder-feedback fm-folder-help">
                            <?php if ($fe('folder')): ?>
                                <div id="fm-folder-feedback" class="invalid-feedback"><?= e($fe('folder')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div id="fm-folder-help" class="form-text"><?= t('files.upload.folder_help') ?></div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="fm-description" class="form-label"><?= e(t('files.upload.desc_label')) ?></label>
                        <textarea id="fm-description"
                                  name="description"
                                  class="<?= $fc('description') ?>"
                                  rows="3"
                                  maxlength="500"
                                  placeholder="<?= e(t('files.upload.desc_ph')) ?>"
                                  data-char-counter="fm-description-counter"
                                  aria-invalid="<?= $fe('description') ? 'true' : 'false' ?>"
                                  aria-describedby="fm-description-feedback fm-description-help"><?= e($fv('description')) ?></textarea>
                        <?php if ($fe('description')): ?>
                            <div id="fm-description-feedback" class="invalid-feedback"><?= e($fe('description')) ?></div>
                        <?php endif; ?>
                        <div id="fm-description-help" class="form-text d-flex justify-content-between">
                            <span><?= e(t('files.upload.desc_max')) ?></span>
                            <span id="fm-description-counter" class="app-char-counter">0 / 500</span>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="mb-3">
                        <label for="fm-tags" class="form-label"><?= e(t('files.upload.tags_label')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text app-input-icon">
                                <i class="fa-solid fa-tags fa-sm"></i>
                            </span>
                            <input type="text"
                                   id="fm-tags"
                                   name="tags"
                                   class="form-control"
                                   placeholder="<?= e(t('files.upload.tags_ph')) ?>"
                                   value="<?= e($fv('tags')) ?>"
                                   maxlength="500"
                                   aria-describedby="fm-tags-help"
                                   data-tag-preview="fm-tags-preview">
                        </div>
                        <div id="fm-tags-help" class="form-text"><?= e(t('files.upload.tags_help')) ?></div>
                        <div id="fm-tags-preview" class="app-tag-preview mt-1"></div>
                    </div>

                    <!-- Visibility -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold"><?= e(t('files.upload.visibility')) ?></label>
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="visibility" id="vis-private"
                                       value="private" <?= (($old['visibility'] ?? 'private') === 'private') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="vis-private">
                                    <i class="fa-solid fa-lock me-1 text-muted"></i><?= e(t('files.upload.vis_private')) ?>
                                    <span class="text-muted small d-block"><?= e(t('files.upload.vis_private_hint')) ?></span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="visibility" id="vis-internal"
                                       value="internal" <?= (($old['visibility'] ?? '') === 'internal') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="vis-internal">
                                    <i class="fa-solid fa-users me-1 text-success"></i><?= e(t('files.upload.vis_internal')) ?>
                                    <span class="text-muted small d-block"><?= e(t('files.upload.vis_internal_hint')) ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= e(route('files.index')) ?>" class="btn btn-outline-secondary"
                           data-bs-toggle="tooltip" data-bs-title="<?= e(t('files.upload.cancel_tip')) ?>">
                            <?= e(t('common.action.cancel')) ?>
                        </a>
                        <button type="submit" class="btn btn-primary"
                                data-bs-toggle="tooltip" data-bs-title="<?= e(t('files.upload.submit_tip')) ?>">
                            <i class="fa-solid fa-cloud-arrow-up me-1"></i><?= e(t('files.upload.submit')) ?>
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

</div>
</div>

<?php $view->end(); ?>
