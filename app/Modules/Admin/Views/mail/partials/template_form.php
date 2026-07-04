<?php $view->layout('main'); ?>

<?php $view->start('content'); ?>
<?php $view->pushStyle('css/admin.css'); ?>

<?php
$isEdit = !empty($template);
$action = $isEdit
    ? route('admin.mail.templates.update', ['id' => $template['id']])
    : route('admin.mail.templates.store');
$errors = $_SESSION['_errors'] ?? [];
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_errors'], $_SESSION['_old']);
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => $isEdit ? 'fa-solid fa-pen' : 'fa-solid fa-plus',
        'adminTitle'    => $isEdit ? t('admin.mail.form.edit_title') : t('admin.mail.form.new_title'),
        'adminSubtitle' => $isEdit ? e($template['name']) : null,
        'adminButtons'  => '<a href="' . e(route('admin.mail.index')) . '" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>' . e(t('admin.mail.form.back')) . '</a>',
    ]); ?>

    <div class="card adm-card">
        <div class="card-body">
            <form method="POST" action="<?= e($action) ?>">
                <?= csrf_field() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="_method" value="PUT">
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label"><?= e(t('admin.mail.form.name')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               id="name" name="name"
                               value="<?= e($old['name'] ?? $template['name'] ?? '') ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="slug" class="form-label"><?= e(t('admin.mail.form.slug')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['slug']) ? 'is-invalid' : '' ?>"
                               id="slug" name="slug"
                               value="<?= e($old['slug'] ?? $template['slug'] ?? '') ?>"
                               pattern="[a-z0-9\-]+" required>
                        <?php if (isset($errors['slug'])): ?>
                            <div class="invalid-feedback"><?= e($errors['slug']) ?></div>
                        <?php endif; ?>
                        <div class="form-text"><?= e(t('admin.mail.form.slug_hint')) ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="subject" class="form-label"><?= e(t('admin.mail.form.subject')) ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['subject']) ? 'is-invalid' : '' ?>"
                           id="subject" name="subject"
                           value="<?= e($old['subject'] ?? $template['subject'] ?? '') ?>" required>
                    <?php if (isset($errors['subject'])): ?>
                        <div class="invalid-feedback"><?= e($errors['subject']) ?></div>
                    <?php endif; ?>
                    <div class="form-text"><?= t('admin.mail.form.subject_hint') ?></div>
                </div>

                <div class="mb-3">
                    <label for="body_html" class="form-label"><?= e(t('admin.mail.form.body')) ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control font-monospace <?= isset($errors['body_html']) ? 'is-invalid' : '' ?>"
                              id="body_html" name="body_html" rows="12"
                              required><?= e($old['body_html'] ?? $template['body_html'] ?? '') ?></textarea>
                    <?php if (isset($errors['body_html'])): ?>
                        <div class="invalid-feedback"><?= e($errors['body_html']) ?></div>
                    <?php endif; ?>
                    <div class="form-text"><?= t('admin.mail.form.body_hint') ?></div>
                </div>

                <div class="mb-3">
                    <label for="variables" class="form-label"><?= e(t('admin.mail.form.variables')) ?></label>
                    <input type="text" class="form-control" id="variables" name="variables"
                           value="<?= e($old['variables'] ?? $template['variables'] ?? '') ?>"
                           placeholder="{{name}},{{link}},{{app_name}}">
                    <div class="form-text"><?= e(t('admin.mail.form.variables_hint')) ?></div>
                </div>

                <div class="text-end">
                    <a href="<?= e(route('admin.mail.index')) ?>" class="btn btn-secondary me-2"><?= e(t('admin.mail.form.cancel')) ?></a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i><?= e($isEdit ? t('admin.mail.form.update') : t('admin.mail.form.create')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $view->end(); ?>
