<?php
/**
 * Login page — uses auth layout.
 * Variables: $view, $error, $layout, $authPage
 */
$view->layout($layout ?? 'auth');
$view->start('content');
?>

<div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(config('app.name', 'Favilla')) ?></p>
        <p class="auth-brand-sub"><?= e(t('auth.login.welcome')) ?></p>
    </div>

    <hr class="auth-divider">

    <?php if (!empty($error)): ?>
        <div class="alert-auth-danger mb-4 d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($ssoEnabled)): ?>
        <?php $view->include('Auth/Views/partials/sso-button', ['ssoButtonLabel' => $ssoButtonLabel, 'showLocalForm' => $showLocalForm]) ?>
        <?php if (empty($showLocalForm)): ?>
            <p class="text-center small text-muted mt-3 mb-0"><?= e(t('auth.login.sso_only_hint')) ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($showLocalForm)): ?>
    <form method="POST" action="<?= e(route('login.post')) ?>" id="login-form" novalidate>
        <?= csrf_field() ?>

        <!-- Username / Email -->
        <div class="mb-3">
            <label for="login" class="form-label"><?= e(t('auth.login.username_label')) ?></label>
            <div class="input-icon-group">
                <i class="fa-solid fa-user input-icon-left"></i>
                <input type="text"
                       class="form-control"
                       id="login"
                       name="login"
                       placeholder="<?= e(t('auth.login.username_placeholder')) ?>"
                       required
                       autofocus
                       autocomplete="username"
                       value="<?= e($_POST['login'] ?? '') ?>">
            </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label"><?= e(t('auth.login.password_label')) ?></label>
            <div class="input-icon-group">
                <i class="fa-solid fa-lock input-icon-left"></i>
                <input type="password"
                      class="form-control auth-password-input"
                       id="password"
                       name="password"
                       placeholder="<?= e(t('auth.login.password_placeholder')) ?>"
                       required
                       autocomplete="current-password"
                      >
                <button type="button" class="toggle-pw" id="toggle-pw" aria-label="<?= e(t('auth.login.toggle_password')) ?>" tabindex="-1">
                    <i class="fa-solid fa-eye" id="toggle-pw-icon"></i>
                </button>
            </div>
        </div>

        <!-- Remember me + forgot -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                <label class="form-check-label" for="remember"><?= e(t('auth.login.remember')) ?></label>
            </div>
            <a href="<?= e(route('password.forgot')) ?>" class="auth-footer-link">
                <?= e(t('auth.login.forgot')) ?>
            </a>
        </div>

        <button type="submit" class="btn btn-auth w-100" id="btn-submit">
            <i class="fa-solid fa-right-to-bracket me-2"></i><?= e(t('auth.login.submit')) ?>
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (!is_single_user() && empty($ssoOnly)): ?>
<p class="text-center mt-4 auth-footer-meta">
    <?= e(t('auth.login.no_account')) ?>
    <a href="<?= e(route('registrazione')) ?>" class="auth-footer-ext-link"><?= e(t('auth.login.register')) ?></a>
</p>
<?php endif; ?>
<p class="text-center auth-footer-meta">
    <?= e(config('app.name', 'Favilla')) ?> &copy; <?= date('Y') ?>
</p>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';
    var pwInput  = document.getElementById('password');
    var toggleBtn = document.getElementById('toggle-pw');
    var toggleIcon = document.getElementById('toggle-pw-icon');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var isHidden = pwInput.type === 'password';
            pwInput.type = isHidden ? 'text' : 'password';
            toggleIcon.className = isHidden ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    }

    // Loading state on submit
    var form = document.getElementById('login-form');
    var btn  = document.getElementById('btn-submit');
    if (form && btn) {
        var submittingText = <?= json_encode(t('auth.login.submitting'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>' + submittingText;
        });
    }
})();
</script>

<?php $view->end(); ?>
