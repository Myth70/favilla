<?php
/**
 * TOTP MFA challenge — login verification step.
 * Uses auth layout (no sidebar).
 * Variables: $view, $error, $pageTitle
 */
$view->layout('auth');
$view->start('content');
?>

<div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(config('app.name', 'Favilla')) ?></p>
        <p class="auth-brand-sub"><?= e(t('auth.totp.challenge_subtitle')) ?></p>
    </div>

    <hr class="auth-divider">

    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 auth-mfa-icon-wrap">
            <i class="fa-solid fa-shield-halved fa-xl auth-mfa-icon"></i>
        </div>
        <p class="text-muted small mb-0">
            <?= e(t('auth.totp.challenge_desc')) ?>
        </p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-auth-danger mb-3 d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(route('mfa.challenge.verify')) ?>" id="mfa-form" novalidate>
        <?= csrf_field() ?>

        <div class="mb-4">
            <label for="totp_code" class="form-label"><?= e(t('auth.totp.challenge_label')) ?></label>
            <div class="input-icon-group">
                <i class="fa-solid fa-key input-icon-left"></i>
                <input type="text"
                      class="form-control text-center fs-4 fw-bold auth-mfa-code"
                       id="totp_code"
                       name="totp_code"
                       placeholder="000 000"
                       required
                       autofocus
                       autocomplete="one-time-code"
                       inputmode="numeric"
                       maxlength="10"
                       pattern="[\d\s\-A-Za-z]+">
            </div>
        </div>

        <button type="submit" class="btn btn-auth w-100" id="btn-verify">
            <i class="fa-solid fa-check-circle me-2"></i><?= e(t('auth.totp.challenge_submit')) ?>
        </button>
    </form>

    <div class="text-center mt-3">
        <form method="POST" action="<?= e(route('logout')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link btn-sm auth-footer-link p-0">
                <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('auth.totp.challenge_back')) ?>
            </button>
        </form>
    </div>
</div>

<p class="text-center mt-4 auth-footer-meta">
    <?= e(t('auth.totp.challenge_hint_before')) ?> <strong><?= e(t('auth.totp.challenge_backup_word')) ?></strong>.
</p>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';
    var input = document.getElementById('totp_code');
    var form  = document.getElementById('mfa-form');
    var btn   = document.getElementById('btn-verify');

    // Auto-submit when 6 digits entered
    if (input) {
        input.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '');
            if (digits.length === 6) {
                this.value = digits;
                if (form) form.submit();
            }
        });
    }

    // Loading state
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i><?= e(t('auth.totp.challenge_verifying')) ?>';
        });
    }
})();
</script>

<?php $view->end(); ?>
