<?php
/**
 * Forced password change page — uses auth layout.
 * Variables: $view, $error, $success, $layout, $authPage
 */
$view->layout($layout ?? 'auth');
$view->start('content');
?>

<div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(t('auth.change_pw.title')) ?></p>
        <p class="auth-brand-sub"><?= e(t('auth.change_pw.subtitle')) ?></p>
    </div>

    <hr class="auth-divider">

    <?php if (!empty($error)): ?>
        <div class="alert-auth-danger mb-4 d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-auth-success mb-4 d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(route('password.change.post')) ?>" id="change-pw-form" novalidate>
        <?= csrf_field() ?>

        <!-- New password -->
        <div class="mb-3">
            <label for="password" class="form-label"><?= e(t('auth.change_pw.new_label')) ?></label>
            <div class="input-icon-group">
                <i class="fa-solid fa-lock input-icon-left"></i>
                <input type="password"
                      class="form-control auth-password-input"
                       id="password"
                       name="password"
                       placeholder="<?= e(t('auth.change_pw.pw_ph')) ?>"
                       required
                       minlength="8"
                       autocomplete="new-password"
                       autofocus>
                <button type="button" class="toggle-pw" id="toggle-pw-1" aria-label="<?= e(t('auth.change_pw.toggle_aria')) ?>" tabindex="-1">
                    <i class="fa-solid fa-eye" id="icon-pw-1"></i>
                </button>
            </div>
            <!-- Strength meter -->
            <div class="pw-strength-wrap is-hidden" id="strength-wrap">
                <div class="pw-strength-bar">
                    <div class="pw-strength-bar-fill" id="strength-bar-fill"></div>
                </div>
                <span class="pw-strength-label" id="strength-label"></span>
            </div>
        </div>

        <!-- Confirm password -->
        <div class="mb-4">
            <label for="password_confirmation" class="form-label">
                <?= e(t('auth.change_pw.confirm_label')) ?>
                <span id="match-icon" class="pw-match-icon"></span>
            </label>
            <div class="input-icon-group">
                <i class="fa-solid fa-lock-open input-icon-left"></i>
                <input type="password"
                      class="form-control auth-password-input"
                       id="password_confirmation"
                       name="password_confirmation"
                       placeholder="<?= e(t('auth.change_pw.pw_confirm_ph')) ?>"
                       required
                       autocomplete="new-password"
                      >
                <button type="button" class="toggle-pw" id="toggle-pw-2" aria-label="<?= e(t('auth.change_pw.toggle_confirm_aria')) ?>" tabindex="-1">
                    <i class="fa-solid fa-eye" id="icon-pw-2"></i>
                </button>
            </div>
        </div>

        <!-- Requirements hint -->
        <div class="mb-4 p-3 auth-req-box">
            <p class="mb-2 auth-req-title"><?= e(t('auth.change_pw.req_title')) ?></p>
            <ul class="mb-0 ps-3 auth-req-list">
                <li id="req-len" class="auth-req-item"><i class="fa-solid fa-circle me-1 auth-req-dot"></i><?= e(t('auth.change_pw.req_len')) ?></li>
                <li id="req-upper" class="auth-req-item"><i class="fa-solid fa-circle me-1 auth-req-dot"></i><?= e(t('auth.change_pw.req_upper')) ?></li>
                <li id="req-num" class="auth-req-item"><i class="fa-solid fa-circle me-1 auth-req-dot"></i><?= e(t('auth.change_pw.req_num')) ?></li>
            </ul>
        </div>

        <button type="submit" class="btn btn-auth w-100" id="btn-submit">
            <i class="fa-solid fa-floppy-disk me-2"></i><?= e(t('auth.change_pw.submit')) ?>
        </button>
    </form>
</div>

<p class="text-center mt-4 auth-footer-meta">
    <?= e(config('app.name', 'Favilla')) ?> &copy; <?= date('Y') ?>
</p>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';

    var pwInput   = document.getElementById('password');
    var pwConfirm = document.getElementById('password_confirmation');
    var strengthWrap = document.getElementById('strength-wrap');
    var barFill   = document.getElementById('strength-bar-fill');
    var strengthLbl = document.getElementById('strength-label');
    var matchIcon = document.getElementById('match-icon');

    // Toggle show/hide
    function makeToggle(btnId, iconId, inputId) {
        var btn  = document.getElementById(btnId);
        var icon = document.getElementById(iconId);
        var inp  = document.getElementById(inputId);
        if (btn) {
            btn.addEventListener('click', function () {
                var hidden = inp.type === 'password';
                inp.type = hidden ? 'text' : 'password';
                icon.className = hidden ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            });
        }
    }
    makeToggle('toggle-pw-1', 'icon-pw-1', 'password');
    makeToggle('toggle-pw-2', 'icon-pw-2', 'password_confirmation');

    // Strength meter
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
        { pct: 20,  color: '#ef4444', label: <?= json_encode(t('auth.pw_strength.very_weak'), JSON_UNESCAPED_UNICODE) ?> },
        { pct: 40,  color: '#f97316', label: <?= json_encode(t('auth.pw_strength.weak'), JSON_UNESCAPED_UNICODE) ?> },
        { pct: 60,  color: '#eab308', label: <?= json_encode(t('auth.pw_strength.fair'), JSON_UNESCAPED_UNICODE) ?> },
        { pct: 80,  color: '#22c55e', label: <?= json_encode(t('auth.pw_strength.good'), JSON_UNESCAPED_UNICODE) ?> },
        { pct: 100, color: '#15803d', label: <?= json_encode(t('auth.pw_strength.strong'), JSON_UNESCAPED_UNICODE) ?> },
    ];

    // Requirement indicators
    function updateReqs(pw) {
        updateReq('req-len',   pw.length >= 8);
        updateReq('req-upper', /[A-Z]/.test(pw));
        updateReq('req-num',   /[0-9]/.test(pw));
    }
    function updateReq(id, ok) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('is-valid', ok);
    }

    if (pwInput) {
        pwInput.addEventListener('input', function () {
            var pw = pwInput.value;
            updateReqs(pw);
            if (pw.length === 0) {
                strengthWrap.classList.add('is-hidden');
                return;
            }
            strengthWrap.classList.remove('is-hidden');
            var s = Math.min(calcStrength(pw), 5);
            var lvl = levels[s - 1] || levels[0];
            barFill.style.width   = lvl.pct + '%';
            barFill.style.background = lvl.color;
            strengthLbl.textContent = lvl.label;
            strengthLbl.style.color = lvl.color;
            checkMatch();
        });
    }

    // Match indicator
    function checkMatch() {
        if (!pwConfirm.value) {
            matchIcon.textContent = '';
            return;
        }
        if (pwInput.value === pwConfirm.value) {
            matchIcon.innerHTML = '<i class="fa-solid fa-circle-check auth-match-success"></i>';
        } else {
            matchIcon.innerHTML = '<i class="fa-solid fa-circle-xmark auth-match-error"></i>';
        }
    }
    if (pwConfirm) {
        pwConfirm.addEventListener('input', checkMatch);
    }

    // Loading state
    var form = document.getElementById('change-pw-form');
    var btn  = document.getElementById('btn-submit');
    if (form && btn) {
        form.addEventListener('submit', function (e) {
            if (pwInput.value !== pwConfirm.value) {
                e.preventDefault();
                pwConfirm.classList.add('is-invalid');
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>' + <?= json_encode(t('auth.change_pw.saving'), JSON_UNESCAPED_UNICODE) ?>;
        });
    }
})();
</script>

<?php $view->end(); ?>
