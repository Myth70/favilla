<?php
/**
 * Auth layout — minimal standalone layout for login/password pages.
 * No sidebar, no navbar, no footer. Just centered card content.
 * Variables: $view (View instance), $pageTitle
 */
$appName   = e(config('app.name', 'Favilla'));
$pageTitle = e($pageTitle ?? $appName);
?>
<?php
// Dark mode su pagine auth: usa $currentTheme se iniettato, altrimenti legge cookie "theme" se presente,
// altrimenti default 'light' (il browser può comunque passare a dark via prefers-color-scheme grazie a <meta name="color-scheme">).
$authTheme = $currentTheme ?? null;
if ($authTheme === null) {
    $cookieTheme = $_COOKIE['theme'] ?? null;
    $authTheme = (in_array($cookieTheme, ['light', 'dark'], true)) ? $cookieTheme : 'light';
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLocale ?? locale()) ?>" data-bs-theme="<?= e($authTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= $pageTitle ?> — <?= $appName ?></title>
    <?php $publicUrl = rtrim(config('app.url'), '/') . rtrim(config('app.base_path'), '/'); ?>
    <link rel="icon" type="image/svg+xml" href="<?= e($publicUrl) ?>/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="<?= e($publicUrl) ?>/favicon.ico">
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="<?= e(asset('css/fontawesome.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
    <?php foreach ($view->getExtraStyles() as $href): ?>
        <link rel="stylesheet" href="<?= e(asset($href)) ?>">
    <?php endforeach; ?>
</head>
<body>
    <div class="auth-blob auth-blob-1"></div>
    <div class="auth-blob auth-blob-2"></div>
    <div class="auth-blob auth-blob-3"></div>

    <!-- Language switcher (i18n) -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1050;">
        <?php $view->include('partials/language_switcher', ['currentLocale' => $currentLocale ?? locale()]); ?>
    </div>

    <div class="auth-wrapper">
        <?php $view->yield('content'); ?>
    </div>

    <!-- Toast container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>

    <!-- Bootstrap 5 JS -->
    <script src="<?= e(asset('js/bootstrap.bundle.min.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <!-- JS i18n: window.__I18N dict + global t(key, fallback) helper — must load
         before module-specific scripts AND app.js, since both call t(). -->
    <script nonce="<?= e(csp_nonce()) ?>">window.__I18N = <?= json_encode(js_i18n_dict(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.t = function (key, fallback) { return (window.__I18N && window.__I18N[key]) || fallback; };</script>

    <?php foreach ($view->getExtraScripts() as $src): ?>
        <script src="<?= e(asset($src)) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <?php endforeach; ?>
    <script src="<?= e(asset('js/app.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <?php
    $flashSuccess = $_SESSION['_flash_success'] ?? null;
    $flashError = $_SESSION['_flash_error'] ?? null;
    ?>
    <?php if ($flashSuccess !== null && $flashSuccess !== ''): ?>
        <?php
        $flashSuccessPayload = is_array($flashSuccess)
            ? array_merge(['type' => 'success', 'channel' => 'toast', 'source' => 'session-flash'], $flashSuccess)
            : ['message' => (string) $flashSuccess, 'type' => 'success', 'channel' => 'toast', 'source' => 'session-flash'];
        ?>
    <script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">window.notify(<?= json_encode($flashSuccessPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);</script>
    <?php unset($_SESSION['_flash_success']); ?>
    <?php endif; ?>

    <?php if ($flashError !== null && $flashError !== ''): ?>
        <?php
        $flashErrorPayload = is_array($flashError)
            ? array_merge(['type' => 'danger', 'channel' => 'toast', 'source' => 'session-flash'], $flashError)
            : ['message' => (string) $flashError, 'type' => 'danger', 'channel' => 'toast', 'source' => 'session-flash'];
        ?>
    <script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">window.notify(<?= json_encode($flashErrorPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);</script>
    <?php unset($_SESSION['_flash_error']); ?>
    <?php endif; ?>
</body>
</html>
