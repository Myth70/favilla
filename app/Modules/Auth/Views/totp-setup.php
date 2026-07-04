<?php
/**
 * TOTP setup page — QR code + manual entry + verification.
 * Can be shown in auth layout (forced) or main layout (profile).
 * Variables: $view, $secret, $qrUri, $forced, $error, $pageTitle
 */
use App\Modules\Auth\Helpers\AvatarHelper;

$view->layout($layout ?? ($forced ? 'auth' : 'main'));
$view->start('content');

$formAction = $forced ? route('mfa.setup.forced.verify') : route('mfa.setup.verify');
?>

<?php if (empty($forced)): ?>
    <?php
    $view->include('partials/pf-hero-user', [
        'userName'     => $_SESSION['user_name'] ?? '',
        'userSubtitle' => t('auth.totp.setup_subtitle'),
        'userAvatar'   => AvatarHelper::url($_SESSION['user_avatar'] ?? null),
        'userInitials' => strtoupper(mb_substr($_SESSION['user_name'] ?? 'U', 0, 1)),
        'userStats'    => [],
        'userButtons'  => '<a href="' . e(route('profile')) . '" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>' . e(t('auth.totp.setup_back_profile')) . '</a>',
    ]);
    ?>
<div class="container py-4">
<div class="row justify-content-center">
<div class="col-lg-7">
<?php endif; ?>

<div class="<?= $forced ? 'auth-card' : 'card shadow-sm' ?>">
    <?php if ($forced): ?>
        <div class="auth-brand">
            <?php $view->include('Auth/Views/partials/logo') ?>
            <p class="auth-brand-name"><?= e(config('app.name', 'Favilla')) ?></p>
            <p class="auth-brand-sub"><?= e(t('auth.totp.setup_subtitle')) ?></p>
        </div>
        <hr class="auth-divider">
    <?php else: ?>
        <div class="card-header d-flex align-items-center gap-2">
            <span class="app-card-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <span class="fw-semibold"><?= e(t('auth.totp.setup_heading')) ?></span>
        </div>
    <?php endif; ?>

    <div class="<?= $forced ? '' : 'card-body' ?>">

        <?php if (!empty($error)): ?>
            <div class="<?= $forced ? 'alert-auth-danger' : 'alert alert-danger' ?> mb-3 d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Step 1: Scan QR -->
        <div class="mb-4">
            <h6 class="fw-bold mb-2">
                <span class="badge bg-primary rounded-pill me-1">1</span>
                <?= e(t('auth.totp.setup_step1')) ?>
            </h6>
            <p class="text-muted small">
                <?= e(t('auth.totp.setup_step1_help')) ?>
            </p>

            <div class="text-center my-3">
                <div class="d-inline-block p-3 bg-white rounded-3 shadow-sm" id="qr-container">
                    <canvas id="qr-canvas" width="200" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Step 2: Manual entry (fallback) -->
        <div class="mb-4">
            <h6 class="fw-bold mb-2">
                <span class="badge bg-secondary rounded-pill me-1">2</span>
                <?= e(t('auth.totp.setup_step2')) ?>
            </h6>
            <div class="input-group">
                <input type="text"
                       class="form-control font-monospace text-center"
                       value="<?= e($secret) ?>"
                       id="totp-secret"
                       readonly>
                <button type="button"
                        class="btn btn-outline-secondary"
                        onclick="navigator.clipboard.writeText(document.getElementById('totp-secret').value)"
                        data-bs-toggle="tooltip"
                        title="<?= e(t('auth.totp.copy_tip')) ?>">
                    <i class="fa-solid fa-copy"></i>
                </button>
            </div>
        </div>

        <!-- Step 3: Verify -->
        <div class="mb-3">
            <h6 class="fw-bold mb-2">
                <span class="badge bg-success rounded-pill me-1">3</span>
                <?= e(t('auth.totp.setup_step3')) ?>
            </h6>
            <p class="text-muted small">
                <?= e(t('auth.totp.setup_step3_help')) ?>
            </p>

            <form method="POST" action="<?= e($formAction) ?>" id="setup-form" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                        <input type="text"
                               class="form-control text-center fs-5 fw-bold auth-mfa-code-setup"
                               name="totp_code"
                               placeholder="<?= e(t('auth.totp.code_ph')) ?>"
                               required
                               autocomplete="one-time-code"
                               inputmode="numeric"
                               maxlength="6"
                               pattern="\d{6}">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-check me-2"></i><?= e(t('auth.totp.setup_activate')) ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php if (empty($forced)): ?>
</div>
</div>
</div>
<?php endif; ?>

<!-- QR Code generation -->
<script src="<?= e(asset('js/qrcode-min.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function() {
    'use strict';
    function initSetupUi() {
        var uri = <?= json_encode($qrUri) ?>;
        var canvas = document.getElementById('qr-canvas');
        if (canvas && uri && typeof QRCode !== 'undefined') {
            QRCode.toCanvas(canvas, uri, 200);
        }
        if (!window.bootstrap) {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSetupUi, { once: true });
    } else {
        initSetupUi();
    }
})();
</script>

<?php $view->end(); ?>
