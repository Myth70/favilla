<?php
/**
 * Registration page — uses auth layout, two-column layout.
 * Variables: $view, $errors, $old, $layout, $authPage
 */
$view->layout($layout ?? 'auth');
$view->start('content');
?>

<!-- Widen wrapper + enable body scroll for this page -->
<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    document.body.classList.add('auth-body-scroll');
    var wrapper = document.querySelector('.auth-wrapper');
    if (wrapper) { wrapper.classList.add('auth-wrapper-wide'); }
})();
</script>

<div class="auth-card auth-card-wide">

    <!-- Brand -->
    <div class="auth-brand mb-0">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(t('auth.register.title')) ?></p>
        <p class="auth-brand-sub"><?= e(t('auth.register.subtitle')) ?></p>
    </div>

    <hr class="auth-divider">

    <?php if (!empty($errors)): ?>
        <div class="alert-auth-danger mb-4 d-flex align-items-start gap-2">
            <i class="fa-solid fa-circle-exclamation mt-1 flex-shrink-0"></i>
            <div>
                <strong><?= e(t('auth.register.errors_title')) ?></strong>
                <ul class="mb-0 mt-1 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(route('registrazione.post')) ?>" id="reg-form" novalidate>
        <?= csrf_field() ?>

        <div class="auth-reg-grid">

            <!-- ====== LEFT COLUMN: dati personali ====== -->
            <div>
                <p class="auth-reg-col-label">
                    <i class="fa-solid fa-id-card me-1"></i><?= e(t('auth.register.col_left_title')) ?>
                </p>

                <!-- Nome -->
                <div class="mb-3">
                    <label for="name" class="form-label"><?= e(t('auth.register.full_name_label')) ?></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-user input-icon-left"></i>
                        <input type="text"
                               class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                               id="name"
                               name="name"
                               placeholder="<?= e(t('auth.register.name_ph')) ?>"
                               required
                               autofocus
                               autocomplete="name"
                               maxlength="100"
                               value="<?= e($old['name'] ?? '') ?>">
                    </div>
                    <?php if (isset($errors['name'])): ?>
                        <div class="auth-field-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Username -->
                <div class="mb-3">
                    <label for="username" class="form-label"><?= e(t('auth.register.username_label')) ?></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-at input-icon-left"></i>
                        <input type="text"
                               class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
                               id="username"
                               name="username"
                               placeholder="<?= e(t('auth.register.username_ph')) ?>"
                               required
                               autocomplete="username"
                               maxlength="50"
                               value="<?= e($old['username'] ?? '') ?>">
                    </div>
                    <?php if (isset($errors['username'])): ?>
                        <div class="auth-field-error"><?= e($errors['username']) ?></div>
                    <?php else: ?>
                        <div class="auth-field-help">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            <?= e(t('auth.register.username_help')) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label"><?= e(t('auth.register.email_label')) ?></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-envelope input-icon-left"></i>
                        <input type="email"
                               class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                               id="email"
                               name="email"
                               placeholder="<?= e(t('auth.register.email_ph')) ?>"
                               required
                               autocomplete="email"
                               maxlength="255"
                               value="<?= e($old['email'] ?? '') ?>">
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <div class="auth-field-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Conferma email -->
                <div class="mb-3">
                    <label for="email_confirm" class="form-label">
                        <?= e(t('auth.register.email_confirm_label')) ?>
                        <span id="email-match-icon" class="pw-match-icon"></span>
                    </label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-envelope-circle-check input-icon-left"></i>
                        <input type="email"
                               class="form-control<?= isset($errors['email_confirm']) ? ' is-invalid' : '' ?>"
                               id="email_confirm"
                               name="email_confirm"
                               placeholder="<?= e(t('auth.register.email_ph')) ?>"
                               required
                               autocomplete="off"
                               maxlength="255"
                               value="<?= e($old['email_confirm'] ?? '') ?>">
                    </div>
                    <?php if (isset($errors['email_confirm'])): ?>
                        <div class="auth-field-error"><?= e($errors['email_confirm']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ====== RIGHT COLUMN: credenziali ====== -->
            <div>
                <p class="auth-reg-col-label">
                    <i class="fa-solid fa-lock me-1"></i><?= e(t('auth.register.col_right_title')) ?>
                </p>

                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label"><?= e(t('auth.register.pw_label')) ?></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-lock input-icon-left"></i>
                        <input type="password"
                               class="form-control auth-password-input<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                               id="password"
                               name="password"
                               placeholder="<?= e(t('auth.register.pw_ph')) ?>"
                               required
                               autocomplete="new-password">
                        <button type="button" class="toggle-pw" id="toggle-pw" aria-label="<?= e(t('auth.register.toggle_pw_aria')) ?>" tabindex="-1">
                            <i class="fa-solid fa-eye" id="toggle-pw-icon"></i>
                        </button>
                    </div>
                    <div class="pw-strength-wrap" id="pw-strength-wrap">
                        <div class="pw-strength-bar">
                            <div class="pw-strength-bar-fill" id="pw-strength-fill"></div>
                        </div>
                        <span class="pw-strength-label" id="pw-strength-label"></span>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="auth-field-error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Conferma password -->
                <div class="mb-3">
                    <label for="password_confirm" class="form-label">
                        <?= e(t('auth.register.pw_confirm_label')) ?>
                        <span id="pw-match-icon" class="pw-match-icon"></span>
                    </label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-lock input-icon-left"></i>
                        <input type="password"
                               class="form-control auth-password-input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                               id="password_confirm"
                               name="password_confirm"
                               placeholder="<?= e(t('auth.register.pw_confirm_ph')) ?>"
                               required
                               autocomplete="new-password">
                        <button type="button" class="toggle-pw" id="toggle-pw2" aria-label="<?= e(t('auth.register.toggle_confirm_aria')) ?>" tabindex="-1">
                            <i class="fa-solid fa-eye" id="toggle-pw2-icon"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password_confirm'])): ?>
                        <div class="auth-field-error"><?= e($errors['password_confirm']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Requisiti password -->
                <div class="auth-req-box p-3 mb-4">
                    <p class="auth-req-title mb-2"><?= e(t('auth.register.pw_req_title')) ?></p>
                    <ul class="list-unstyled auth-req-list mb-0">
                        <li class="auth-req-item" id="req-length">
                            <i class="fa-solid fa-circle-dot auth-req-dot me-2"></i><?= e(t('auth.register.pw_req_min')) ?>
                        </li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-auth w-100" id="btn-submit">
                    <i class="fa-solid fa-user-plus me-2"></i><?= e(t('auth.register.submit')) ?>
                </button>
            </div>

        </div><!-- /.auth-reg-grid -->
    </form>
</div>

<p class="text-center mt-4 auth-footer-meta">
    <?= e(t('auth.register.already_account')) ?>
    <a href="<?= e(route('login')) ?>" class="auth-footer-ext-link"><?= e(t('auth.register.login_link')) ?></a>
</p>
<p class="text-center auth-footer-meta">
    <?= e(config('app.name', 'Favilla')) ?> &copy; <?= date('Y') ?>
</p>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';

    // --- Password toggle (campo 1) ---
    var pwInput   = document.getElementById('password');
    var toggleBtn = document.getElementById('toggle-pw');
    var toggleIcon = document.getElementById('toggle-pw-icon');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            var hidden = pwInput.type === 'password';
            pwInput.type = hidden ? 'text' : 'password';
            toggleIcon.className = hidden ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    }

    // --- Password toggle (campo 2) ---
    var pwInput2   = document.getElementById('password_confirm');
    var toggleBtn2 = document.getElementById('toggle-pw2');
    var toggleIcon2 = document.getElementById('toggle-pw2-icon');
    if (toggleBtn2) {
        toggleBtn2.addEventListener('click', function () {
            var hidden = pwInput2.type === 'password';
            pwInput2.type = hidden ? 'text' : 'password';
            toggleIcon2.className = hidden ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    }

    // --- Password strength ---
    var strengthFill  = document.getElementById('pw-strength-fill');
    var strengthLabel = document.getElementById('pw-strength-label');
    var reqLength     = document.getElementById('req-length');

    function calcStrength(pw) {
        var score = 0;
        if (pw.length >= 8)  score++;
        if (pw.length >= 12) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        return score;
    }

    var levels = [
        { pct: 0,   color: '#e2e8f0', label: '' },
        { pct: 20,  color: '#ef4444', label: <?= json_encode(t('auth.pw_strength.very_weak'), JSON_UNESCAPED_UNICODE) ?> },
        { pct: 40,  color: '#f97316', label: <?= json_encode(t('auth.pw_strength.weak'), JSON_UNESCAPED_UNICODE) ?> },
        { pct: 60,  color: '#eab308', label: <?= json_encode(t('auth.pw_strength.fair'), JSON_UNESCAPED_UNICODE) ?> },
        { pct: 80,  color: '#22c55e', label: <?= json_encode(t('auth.pw_strength.good'), JSON_UNESCAPED_UNICODE) ?> },
        { pct: 100, color: '#16a34a', label: <?= json_encode(t('auth.pw_strength.strong'), JSON_UNESCAPED_UNICODE) ?> },
    ];

    if (pwInput) {
        pwInput.addEventListener('input', function () {
            var val = pwInput.value;
            var score = val.length === 0 ? 0 : Math.min(5, calcStrength(val));
            var lvl = levels[score];
            strengthFill.style.width      = lvl.pct + '%';
            strengthFill.style.background = lvl.color;
            strengthLabel.textContent     = lvl.label;
            strengthLabel.style.color     = lvl.color;
            if (reqLength) {
                reqLength.style.color = val.length >= 8 ? '#22c55e' : '';
                reqLength.querySelector('.auth-req-dot').style.color = val.length >= 8 ? '#22c55e' : '';
            }
            checkPwMatch();
        });
    }

    // --- Password match ---
    var pwMatchIcon = document.getElementById('pw-match-icon');
    function checkPwMatch() {
        if (!pwInput || !pwInput2 || !pwMatchIcon) return;
        if (pwInput2.value.length === 0) { pwMatchIcon.innerHTML = ''; return; }
        if (pwInput.value === pwInput2.value) {
            pwMatchIcon.innerHTML = '<i class="fa-solid fa-check auth-match-success"></i>';
        } else {
            pwMatchIcon.innerHTML = '<i class="fa-solid fa-xmark auth-match-error"></i>';
        }
    }
    if (pwInput2) { pwInput2.addEventListener('input', checkPwMatch); }

    // --- Email match ---
    var emailInput     = document.getElementById('email');
    var emailConfirm   = document.getElementById('email_confirm');
    var emailMatchIcon = document.getElementById('email-match-icon');
    function checkEmailMatch() {
        if (!emailInput || !emailConfirm || !emailMatchIcon) return;
        if (emailConfirm.value.length === 0) { emailMatchIcon.innerHTML = ''; return; }
        if (emailInput.value === emailConfirm.value) {
            emailMatchIcon.innerHTML = '<i class="fa-solid fa-check auth-match-success"></i>';
        } else {
            emailMatchIcon.innerHTML = '<i class="fa-solid fa-xmark auth-match-error"></i>';
        }
    }
    if (emailInput)   { emailInput.addEventListener('input', checkEmailMatch); }
    if (emailConfirm) { emailConfirm.addEventListener('input', checkEmailMatch); }

    // --- Loading state on submit ---
    var form = document.getElementById('reg-form');
    var btn  = document.getElementById('btn-submit');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i><?= e(t('auth.register.submitting')) ?>';
        });
    }
})();
</script>

<?php $view->end(); ?>
