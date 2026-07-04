<?php
/**
 * Forgot password page — uses auth layout.
 * Variables: $view, $error, $success, $layout, $authPage
 */
$view->layout($layout ?? 'auth');
$view->start('content');
?>

<div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(t('auth.forgot_pw.title')) ?></p>
        <p class="auth-brand-sub"><?= e(t('auth.forgot_pw.subtitle')) ?></p>
    </div>

    <hr class="auth-divider">

    <?php if (!empty($error)): ?>
        <div class="alert-auth-danger mb-4 d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-auth-success mb-4 d-flex align-items-start gap-2">
            <i class="fa-solid fa-circle-check mt-1"></i>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($success)): ?>
    <form method="POST" action="<?= e(route('password.forgot.post')) ?>" id="forgot-form" novalidate>
        <?= csrf_field() ?>

        <div class="mb-4">
            <label for="email" class="form-label"><?= e(t('auth.forgot_pw.email_label')) ?></label>
            <div class="input-icon-group">
                <i class="fa-solid fa-envelope input-icon-left"></i>
                <input type="email"
                       class="form-control"
                       id="email"
                       name="email"
                       placeholder="<?= e(t('auth.forgot_pw.email_ph')) ?>"
                       required
                       autofocus
                       autocomplete="email"
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="mt-2 auth-field-help">
                <i class="fa-solid fa-circle-info me-1"></i>
                <?= e(t('auth.forgot_pw.email_help')) ?>
            </div>
        </div>

        <button type="submit" class="btn btn-auth w-100 mb-3" id="btn-submit">
            <i class="fa-solid fa-paper-plane me-2"></i><?= e(t('auth.forgot_pw.submit')) ?>
        </button>
    </form>
    <?php else: ?>
        <div class="text-center py-2">
            <i class="fa-solid fa-envelope-circle-check auth-success-icon"></i>
            <p class="mt-3 mb-0 auth-success-copy">
                <?= e(t('auth.forgot_pw.success_message')) ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="text-center mt-3">
        <a href="<?= e(route('login')) ?>" class="auth-footer-link">
            <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('auth.forgot_pw.back_login')) ?>
        </a>
    </div>
</div>

<p class="text-center mt-4 auth-footer-meta">
    <?= e(config('app.name', 'Favilla')) ?> &copy; <?= date('Y') ?>
</p>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';
    var form = document.getElementById('forgot-form');
    var btn  = document.getElementById('btn-submit');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i><?= e(t('auth.forgot_pw.submitting')) ?>';
        });
    }
})();
</script>

<?php $view->end(); ?>
