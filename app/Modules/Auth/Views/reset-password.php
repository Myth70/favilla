<?php
$view->layout($layout ?? 'auth');
$view->start('content');
?>

<div class="auth-card">
    <div class="auth-brand">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(t('auth.reset_pw.title')) ?></p>
        <p class="auth-brand-sub"><?= e(t('auth.reset_pw.subtitle')) ?></p>
    </div>

    <hr class="auth-divider">

    <?php if (!empty($error)): ?>
        <div class="alert-auth-danger mb-4 d-flex align-items-start gap-2">
            <i class="fa-solid fa-circle-exclamation mt-1"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-auth-success mb-4 d-flex align-items-start gap-2">
            <i class="fa-solid fa-circle-check mt-1"></i>
            <span><?= e($success) ?></span>
        </div>
        <div class="text-center mt-3">
            <a href="<?= e(route('login')) ?>" class="auth-footer-link">
                <i class="fa-solid fa-arrow-right-to-bracket me-1"></i><?= e(t('auth.reset_pw.login_link')) ?>
            </a>
        </div>
    <?php else: ?>
        <?php if (!empty($maskedAccount)): ?>
            <div class="mb-3 p-3 auth-req-box">
                <p class="mb-0 small"><?= e(t('auth.reset_pw.account_label')) ?> <strong><?= e($maskedAccount) ?></strong></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= e(route('password.reset.post', ['token' => $token])) ?>" id="reset-pw-form" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="password" class="form-label"><?= e(t('auth.reset_pw.new_label')) ?></label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-lock input-icon-left"></i>
                    <input type="password"
                           class="form-control auth-password-input"
                           id="password"
                           name="password"
                           placeholder="<?= e(t('auth.reset_pw.pw_ph')) ?>"
                           required
                           autocomplete="new-password"
                           minlength="8"
                           autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label"><?= e(t('auth.reset_pw.confirm_label')) ?></label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-lock-open input-icon-left"></i>
                    <input type="password"
                           class="form-control auth-password-input"
                           id="password_confirmation"
                           name="password_confirmation"
                           placeholder="<?= e(t('auth.reset_pw.pw_confirm_ph')) ?>"
                           required
                           autocomplete="new-password"
                           minlength="8">
                </div>
            </div>

            <?php if (!empty($rules) && is_array($rules)): ?>
                <div class="mb-4 p-3 auth-req-box">
                    <p class="mb-2 auth-req-title"><?= e(t('auth.reset_pw.req_title')) ?></p>
                    <ul class="mb-0 ps-3 auth-req-list">
                        <?php foreach ($rules as $rule): ?>
                            <li class="auth-req-item">
                                <i class="fa-solid fa-circle me-1 auth-req-dot"></i><?= e($rule) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-auth w-100" id="btn-submit">
                <i class="fa-solid fa-floppy-disk me-2"></i><?= e(t('auth.reset_pw.submit')) ?>
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= e(route('login')) ?>" class="auth-footer-link">
                <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('auth.reset_pw.back_login')) ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<p class="text-center mt-4 auth-footer-meta">
    <?= e(config('app.name', 'Favilla')) ?> &copy; <?= date('Y') ?>
</p>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';
    var form = document.getElementById('reset-pw-form');
    var btn  = document.getElementById('btn-submit');
    var pw   = document.getElementById('password');
    var pwc  = document.getElementById('password_confirmation');

    if (!form || !btn || !pw || !pwc) {
        return;
    }

    form.addEventListener('submit', function (e) {
        if (pw.value !== pwc.value) {
            e.preventDefault();
            pwc.classList.add('is-invalid');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i><?= e(t('auth.reset_pw.saving')) ?>';
    });
})();
</script>

<?php $view->end(); ?>
