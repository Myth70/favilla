<?php
/**
 * Backup codes display — shown once after MFA setup or regeneration.
 * Variables: $view, $codes, $pageTitle
 */
use App\Modules\Auth\Helpers\AvatarHelper;

$view->layout($layout ?? 'main');
$view->start('content');
?>

<?php if (!empty($authPage)): ?>
<div class="auth-card">
    <div class="auth-brand">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(config('app.name', 'Favilla')) ?></p>
        <p class="auth-brand-sub"><?= e(t('auth.totp.backup_title')) ?></p>
    </div>
    <hr class="auth-divider">
<?php else: ?>
    <?php
    $view->include('partials/pf-hero-user', [
        'userName'     => $_SESSION['user_name'] ?? '',
        'userSubtitle' => t('auth.totp.backup_title'),
        'userAvatar'   => AvatarHelper::url($_SESSION['user_avatar'] ?? null),
        'userInitials' => strtoupper(mb_substr($_SESSION['user_name'] ?? 'U', 0, 1)),
        'userStats'    => [],
        'userButtons'  => '',
    ]);
    ?>
<div class="container py-4">
<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="app-card-icon"><i class="fa-solid fa-key"></i></span>
        <span class="fw-semibold"><?= e(t('auth.totp.backup_heading')) ?></span>
    </div>
    <div class="card-body">
<?php endif; ?>

    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <div>
            <strong><?= e(t('auth.totp.backup_warning')) ?></strong><br>
            <small><?= e(t('auth.totp.backup_each_once')) ?></small>
        </div>
    </div>

    <div class="row g-2 mb-4" id="backup-codes">
        <?php foreach ($codes as $code): ?>
            <div class="col-6">
                <div class="font-monospace text-center py-2 px-3 rounded-2 border bg-light">
                    <?= e($code) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="button"
                class="btn btn-outline-secondary btn-sm"
                onclick="copyBackupCodes()"
                data-bs-toggle="tooltip"
                title="<?= e(t('auth.totp.copy_all_tip')) ?>">
            <i class="fa-solid fa-copy me-1"></i><?= e(t('auth.totp.backup_copy_btn')) ?>
        </button>
        <button type="button"
                class="btn btn-outline-secondary btn-sm"
                onclick="downloadBackupCodes()"
                data-bs-toggle="tooltip"
                title="<?= e(t('auth.totp.download_tip')) ?>">
            <i class="fa-solid fa-download me-1"></i><?= e(t('auth.totp.backup_download_btn')) ?>
        </button>
        <button type="button"
                class="btn btn-outline-secondary btn-sm"
                onclick="window.print()"
                data-bs-toggle="tooltip"
                title="<?= e(t('auth.totp.print_tip')) ?>">
            <i class="fa-solid fa-print me-1"></i><?= e(t('auth.totp.backup_print_btn')) ?>
        </button>
    </div>

    <hr class="my-3">

    <div class="text-center">
        <?php if (!empty($authPage)): ?>
            <a href="<?= e(route('home')) ?>" class="btn btn-auth w-100">
                <i class="fa-solid fa-check me-2"></i><?= e(t('auth.totp.backup_done_continue')) ?>
            </a>
        <?php else: ?>
            <a href="<?= e(route('profile')) ?>" class="btn btn-primary">
                <i class="fa-solid fa-check me-2"></i><?= e(t('auth.totp.backup_done_profile')) ?>
            </a>
        <?php endif; ?>
    </div>

<?php if (!empty($authPage)): ?>
</div>
<?php else: ?>
    </div>
</div>
</div>
</div>
</div>
<?php endif; ?>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function() {
    'use strict';

    var codes = <?= json_encode($codes) ?>;

    var i18nBackupHeader    = <?= json_encode(t('auth.totp.backup_file_header', ['name' => config('app.name', 'Favilla')]), JSON_UNESCAPED_UNICODE) ?>;
    var i18nBackupGenerated = <?= json_encode(t('auth.totp.backup_file_generated'), JSON_UNESCAPED_UNICODE) ?>;
    var i18nBackupWarning   = <?= json_encode(t('auth.totp.backup_file_warning'), JSON_UNESCAPED_UNICODE) ?>;
    var i18nBackupCopied    = <?= json_encode(t('auth.totp.backup_copied'), JSON_UNESCAPED_UNICODE) ?>;

    window.copyBackupCodes = function() {
        var text = i18nBackupHeader + '\n'
                 + '====================================\n'
                 + codes.join('\n')
                 + '\n====================================\n'
                 + i18nBackupGenerated + ' ' + new Date().toLocaleDateString();
        navigator.clipboard.writeText(text).then(function() {
            var btn = event.currentTarget;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>' + i18nBackupCopied;
            setTimeout(function() { btn.innerHTML = origHtml; }, 2000);
        });
    };

    window.downloadBackupCodes = function() {
        var text = i18nBackupHeader + '\r\n'
                 + '====================================\r\n'
                 + codes.join('\r\n')
                 + '\r\n====================================\r\n'
                 + i18nBackupGenerated + ' ' + new Date().toLocaleDateString() + '\r\n'
                 + i18nBackupWarning + '\r\n';
        var blob = new Blob([text], {type: 'text/plain'});
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'favilla-backup-codes.txt';
        a.click();
        URL.revokeObjectURL(a.href);
    };

    function initBackupCodeTooltips() {
        if (!window.bootstrap) {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBackupCodeTooltips, { once: true });
    } else {
        initBackupCodeTooltips();
    }
})();
</script>

<?php $view->end(); ?>
