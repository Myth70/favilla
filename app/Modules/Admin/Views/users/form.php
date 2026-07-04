<?php
/**
 * Admin user create/edit form.
 * Variables: $view, $profileUser (null = create), $roles, $userRoleIds, $errors, $old
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->start('content');

$isEdit  = $profileUser !== null;
$action  = $isEdit
    ? route('admin.users.update', ['id' => $profileUser['id']])
    : route('admin.users.store');

$errors = $errors ?? [];
$old    = $old    ?? [];

$fv = function (string $k, $default = '') use ($old, $profileUser) {
    if (array_key_exists($k, $old))        return $old[$k];
    if ($profileUser && array_key_exists($k, $profileUser)) return $profileUser[$k];
    return $default;
};
$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => $isEdit ? 'fa-solid fa-user-pen' : 'fa-solid fa-user-plus',
        'adminTitle'    => $isEdit ? t('admin.users.form_title_edit') : t('admin.users.form_title_new'),
        'adminSubtitle' => $isEdit ? e($profileUser['name']) : null,
    ]); ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php $view->include('partials/app-form-errors', [
                'errors' => $errors,
                'summaryTitle' => t('admin.users.fix_errors'),
            ]); ?>

            <form method="post" action="<?= e($action) ?>" novalidate data-app-form>
                <?= csrf_field() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="_method" value="PUT">
                <?php endif; ?>

                <!-- Sezione: Identità -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('admin.users.identity_legend')) ?></legend>
                    <div class="app-form-section-header" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="users-identity-body">
                        <span class="app-card-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="fw-semibold flex-grow-1"><?= e(t('admin.users.identity')) ?></span>
                        <i class="fa-solid fa-chevron-down app-chevron"></i>
                    </div>
                    <div class="app-form-section-body" id="users-identity-body">
                        <div class="mb-3">
                            <label class="form-label" for="f-name"><?= e(t('admin.users.name')) ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f-name"
                                   value="<?= e($fv('name')) ?>"
                                   class="<?= $fc('name') ?>"
                                   autocomplete="name"
                                   required aria-required="true"
                                   maxlength="120"
                                   aria-invalid="<?= $fe('name') ? 'true' : 'false' ?>"
                                   aria-describedby="f-name-feedback">
                            <div id="f-name-feedback" class="invalid-feedback">
                                <?= e($fe('name') ?? t('admin.users.name_invalid')) ?>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="f-email"><?= e(t('admin.users.email')) ?> <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="f-email"
                                       value="<?= e($fv('email')) ?>"
                                       class="<?= $fc('email') ?>"
                                       autocomplete="email"
                                       inputmode="email"
                                       required aria-required="true"
                                       maxlength="255"
                                       aria-invalid="<?= $fe('email') ? 'true' : 'false' ?>"
                                       aria-describedby="f-email-feedback">
                                <div id="f-email-feedback" class="invalid-feedback">
                                    <?= e($fe('email') ?? t('admin.users.email_invalid')) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="f-username"><?= e(t('admin.users.username')) ?> <span class="text-danger">*</span></label>
                                <input type="text" name="username" id="f-username"
                                       value="<?= e($fv('username')) ?>"
                                       class="<?= $fc('username') ?>"
                                       autocomplete="off"
                                       required aria-required="true"
                                       maxlength="64"
                                       aria-invalid="<?= $fe('username') ? 'true' : 'false' ?>"
                                       aria-describedby="f-username-feedback">
                                <div id="f-username-feedback" class="invalid-feedback">
                                    <?= e($fe('username') ?? t('admin.users.username_invalid')) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <?php if (!$isEdit): ?>
                <!-- Sezione: Password (create only) -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('admin.users.pw_legend')) ?></legend>
                    <div class="app-form-section-header" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="users-pw-body">
                        <span class="app-card-icon"><i class="fa-solid fa-key"></i></span>
                        <span class="fw-semibold flex-grow-1"><?= e(t('admin.users.password')) ?></span>
                        <i class="fa-solid fa-chevron-down app-chevron"></i>
                    </div>
                    <div class="app-form-section-body" id="users-pw-body">
                        <div class="mb-3">
                            <label class="form-label" for="field-password"><?= e(t('admin.users.password')) ?> <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="password" name="password" id="field-password"
                                       class="<?= $fc('password') ?>"
                                       autocomplete="new-password"
                                       required aria-required="true"
                                       minlength="8"
                                       aria-invalid="<?= $fe('password') ? 'true' : 'false' ?>"
                                       aria-describedby="field-password-feedback field-password-help">
                                <button type="button" class="btn btn-outline-secondary" id="btn-toggle-pw"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.users.toggle_pw')) ?>">
                                    <i class="fa-solid fa-eye" id="icon-toggle-pw"></i>
                                </button>
                                <button type="button" class="btn btn-outline-warning" id="btn-gen-pw"
                                        data-bs-toggle="tooltip" title="<?= e(t('admin.users.gen_pw_tip')) ?>">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i><?= e(t('admin.users.gen')) ?>
                                </button>
                                <div id="field-password-feedback" class="invalid-feedback">
                                    <?= e($fe('password') ?? t('admin.users.pw_invalid')) ?>
                                </div>
                            </div>
                            <div id="field-password-help" class="form-text"><?= e(t('admin.users.pw_help')) ?></div>
                        </div>

                        <?php if (!empty($roles)): ?>
                        <div class="mb-0">
                            <label class="form-label d-block"><?= e(t('admin.users.roles')) ?></label>
                            <div class="row g-2">
                                <?php foreach ($roles as $role): ?>
                                <div class="col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="role_ids[]"
                                               value="<?= e($role['id']) ?>"
                                               id="role_<?= e($role['id']) ?>"
                                               <?= in_array((int) $role['id'], $userRoleIds, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="role_<?= e($role['id']) ?>">
                                            <?= e($role['name']) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
                (function () {
                    'use strict';
                    var field  = document.getElementById('field-password');
                    var btnGen = document.getElementById('btn-gen-pw');
                    var btnTog = document.getElementById('btn-toggle-pw');
                    var icon   = document.getElementById('icon-toggle-pw');
                    if (!field || !btnGen || !btnTog || !icon) return;

                    function generatePassword(len) {
                        var chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
                        var arr   = new Uint8Array(len);
                        crypto.getRandomValues(arr);
                        return Array.from(arr, function (b) { return chars[b % chars.length]; }).join('');
                    }
                    btnGen.addEventListener('click', function () {
                        field.value = generatePassword(12);
                        field.type  = 'text';
                        icon.className = 'fa-solid fa-eye-slash';
                    });
                    btnTog.addEventListener('click', function () {
                        if (field.type === 'password') { field.type = 'text';     icon.className = 'fa-solid fa-eye-slash'; }
                        else                           { field.type = 'password'; icon.className = 'fa-solid fa-eye'; }
                    });
                })();
                </script>
                <?php endif; ?>

                <?php if ($isEdit): ?>
                <!-- Sezione: Stato account (edit only) -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('admin.users.state_legend')) ?></legend>
                    <div class="app-form-section-header" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="users-state-body">
                        <span class="app-card-icon"><i class="fa-solid fa-toggle-on"></i></span>
                        <span class="fw-semibold flex-grow-1"><?= e(t('admin.users.state')) ?></span>
                        <i class="fa-solid fa-chevron-down app-chevron"></i>
                    </div>
                    <div class="app-form-section-body" id="users-state-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       role="switch"
                                       name="is_active" id="field-is-active"
                                       value="1"
                                       <?= (bool) ($old['is_active'] ?? $profileUser['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="field-is-active">
                                    <?= e(t('admin.users.user_active')) ?>
                                </label>
                            </div>
                            <?php if ($fe('is_active')): ?>
                                <div class="text-danger small mt-1"><?= e($fe('is_active')) ?></div>
                            <?php endif; ?>
                            <div class="form-text"><?= e(t('admin.users.user_active_help')) ?></div>
                        </div>

                        <div class="mb-0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       role="switch"
                                       name="must_change_password" id="field-must-change-pw"
                                       value="1"
                                       <?= (bool) ($old['must_change_password'] ?? $profileUser['must_change_password']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="field-must-change-pw">
                                    <?= e(t('admin.users.force_pw')) ?>
                                </label>
                            </div>
                            <div class="form-text"><?= e(t('admin.users.force_pw_help')) ?></div>
                        </div>
                    </div>
                </fieldset>
                <?php endif; ?>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="<?= $isEdit ? e(route('admin.users.show', ['id' => $profileUser['id']])) : e(route('admin.users.index')) ?>"
                       class="btn btn-outline-secondary"><?= e(t('admin.users.cancel')) ?></a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        <?= $isEdit ? e(t('admin.users.save')) : e(t('admin.users.create')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $view->end(); ?>
