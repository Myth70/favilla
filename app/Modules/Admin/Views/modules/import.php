<?php
/**
 * Admin module import form + result display.
 * Variables: $view, $result (ImportResult|null)
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->start('content');
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'  => 'fa-solid fa-file-import',
        'adminTitle' => t('admin.modules.import.title'),
    ]); ?>

<?php if ($result !== null): ?>
    <!-- ── Import Result ────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header <?= $result->success ? 'bg-success text-white' : 'bg-danger text-white' ?>">
            <i class="fa-solid <?= $result->success ? 'fa-circle-check' : 'fa-circle-xmark' ?> me-2"></i>
            <?= e($result->success ? t('admin.modules.import.result_success') : t('admin.modules.import.result_fail')) ?>
            <?php if ($result->moduleName): ?>
                — <?= e($result->moduleName) ?>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($result->error): ?>
            <div class="alert alert-danger mb-3">
                <i class="fa-solid fa-circle-exclamation me-1"></i>
                <?= e($result->error) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($result->log)): ?>
            <h6 class="mb-2"><?= e(t('admin.modules.import.log_heading')) ?></h6>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($result->log as $entry): ?>
                <li class="list-group-item py-1 px-2 small">
                    <?php if (str_starts_with($entry, '[ROLLBACK')): ?>
                        <i class="fa-solid fa-rotate-left text-warning me-1"></i>
                    <?php elseif (str_contains($entry, 'Errore') || str_contains($entry, 'fallito')): ?>
                        <i class="fa-solid fa-xmark text-danger me-1"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-check text-success me-1"></i>
                    <?php endif; ?>
                    <?= e($entry) ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if (!empty($result->warnings)): ?>
            <h6 class="mb-2"><?= e(t('admin.modules.import.warnings_heading')) ?></h6>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($result->warnings as $warn): ?>
                <li class="list-group-item py-1 px-2 small list-group-item-warning">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <?= e($warn) ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if ($result->success): ?>
            <div class="alert alert-info mb-0">
                <i class="fa-solid fa-circle-info me-1"></i>
                <strong><?= e(t('admin.modules.import.important')) ?></strong> <?= e(t('admin.modules.import.relogin')) ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <a href="<?= e(route('admin.modules.index')) ?>" class="btn btn-primary">
                <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('admin.modules.import.back')) ?>
            </a>
            <?php if (!$result->success): ?>
            <a href="<?= e(route('admin.modules.import')) ?>" class="btn btn-outline-secondary ms-2">
                <i class="fa-solid fa-rotate-right me-1"></i><?= e(t('admin.modules.import.retry')) ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ── Upload Form ──────────────────────────────────────────── -->
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-file-import me-2"></i><?= e(t('admin.modules.import.form_title')) ?>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <?= e(t('admin.modules.import.intro')) ?>
                    </p>

                    <form method="post"
                          action="<?= e(route('admin.modules.import.store')) ?>"
                          enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="module_zip" class="form-label"><?= e(t('admin.modules.import.file_label')) ?></label>
                            <div class="input-group">
                                <input type="text"
                                       id="module_zip_name"
                                       class="form-control"
                                       value="<?= e(t('admin.modules.import.no_file')) ?>"
                                       readonly
                                       aria-label="<?= e(t('admin.modules.import.file_label')) ?>">
                                <label class="btn btn-outline-secondary text-nowrap" for="module_zip">
                                    <i class="fa-solid fa-paperclip me-1"></i><?= e(t('admin.modules.import.choose_file')) ?>
                                </label>
                                <input type="file"
                                       class="visually-hidden"
                                       id="module_zip"
                                       name="module_zip"
                                       accept=".zip"
                                       required
                                       data-app-file-target="module_zip_name"
                                       data-app-file-placeholder="<?= e(t('admin.modules.import.no_file')) ?>">
                            </div>
                            <div class="form-text">
                                <?= t('admin.modules.import.format_hint') ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="import_data"
                                       name="import_data" value="1">
                                <label class="form-check-label" for="import_data">
                                    <?= e(t('admin.modules.import.import_data')) ?>
                                    <small class="text-muted d-block">
                                        <?= t('admin.modules.import.import_data_hint') ?>
                                    </small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="db_name_override" class="form-label">
                                <?= e(t('admin.modules.import.db_name_label')) ?> <small class="text-muted">(<?= t('admin.modules.import.db_name_only_independent') ?>)</small>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="db_name_override"
                                   name="db_name_override"
                                   placeholder="<?= e(t('admin.modules.import.db_name_placeholder')) ?>"
                                   pattern="[a-z][a-z0-9_]{0,63}"
                                   maxlength="64">
                            <div class="form-text">
                                <?= t('admin.modules.import.db_name_hint') ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="reuse_existing"
                                       name="reuse_existing" value="1">
                                <label class="form-check-label" for="reuse_existing">
                                    <?= e(t('admin.modules.import.reuse')) ?>
                                    <small class="text-muted d-block">
                                        <?= e(t('admin.modules.import.reuse_hint')) ?>
                                    </small>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-upload me-1"></i><?= e(t('admin.modules.import.submit')) ?>
                            </button>
                            <a href="<?= e(route('admin.modules.index')) ?>" class="btn btn-outline-secondary">
                                <?= e(t('admin.modules.import.cancel')) ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body small text-muted">
                    <h6 class="card-title mb-2"><?= e(t('admin.modules.import.how_title')) ?></h6>
                    <ol class="mb-0 ps-3">
                        <li><?= t('admin.modules.import.how_1') ?></li>
                        <li><?= t('admin.modules.import.how_2') ?></li>
                        <li><?= t('admin.modules.import.how_3') ?></li>
                        <li><?= t('admin.modules.import.how_4') ?></li>
                        <li><?= t('admin.modules.import.how_5') ?></li>
                        <li><?= t('admin.modules.import.how_6') ?></li>
                        <li><?= t('admin.modules.import.how_7') ?></li>
                    </ol>
                    <p class="mt-2 mb-0">
                        <?= t('admin.modules.import.how_note') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<?php $view->end(); ?>
