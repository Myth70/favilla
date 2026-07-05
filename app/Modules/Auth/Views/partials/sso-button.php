<?php
/**
 * Bottone di accesso SSO per la pagina di login.
 * Variables: $ssoButtonLabel, $showLocalForm (per il divisore)
 */
?>
<a href="<?= e(route('oidc.start')) ?>" class="btn btn-auth w-100" id="btn-sso">
    <i class="fa-solid fa-building-lock me-2"></i><?= e($ssoButtonLabel) ?>
</a>

<?php if (!empty($showLocalForm)): ?>
<div class="d-flex align-items-center my-3" aria-hidden="true">
    <hr class="flex-grow-1 auth-divider my-0">
    <span class="px-2 small text-muted"><?= e(t('auth.login.sso_or')) ?></span>
    <hr class="flex-grow-1 auth-divider my-0">
</div>
<?php endif; ?>
