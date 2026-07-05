<?php
/**
 * Interstitial post-callback OIDC: risposta 200 same-origin che naviga via
 * client. Necessaria perché il cookie di sessione è SameSite=Strict e non
 * viaggerebbe su un redirect HTTP originato dalla navigazione cross-site
 * di ritorno dall'Identity Provider.
 *
 * Variables: $view, $layout, $targetUrl
 */
$view->layout($layout ?? 'auth');
$view->start('content');
?>

<meta http-equiv="refresh" content="2;url=<?= e($targetUrl) ?>">

<div class="auth-card">
    <div class="auth-brand">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(config('app.name', 'Favilla')) ?></p>
    </div>

    <hr class="auth-divider">

    <div class="text-center py-4">
        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
        <p class="mb-2"><?= e(t('auth.login.sso_redirecting')) ?></p>
        <p class="small text-muted mb-0">
            <a href="<?= e($targetUrl) ?>" class="auth-footer-link"><?= e(t('auth.login.sso_redirect_link')) ?></a>
        </p>
    </div>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
    // location.replace: l'interstitial non finisce nella history.
    window.location.replace(<?= json_encode($targetUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
</script>

<?php $view->end(); ?>
