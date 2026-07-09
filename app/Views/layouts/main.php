<?php
/**
 * Main application layout.
 * Variables available: $view (View instance), $cssVars, $user, $pageTitle
 */
$appName = e(config('app.name', 'Proteus'));
$pageTitle = e($pageTitle ?? 'Dashboard');
?>
<!DOCTYPE html>
<?php
$allowedSkins = ['default', 'soft', 'sharp', 'compact'];
$currentSkin  = in_array($currentSkin ?? '', $allowedSkins, true) ? $currentSkin : 'default';
$allowedSidebarStyles = ['default', 'light', 'accent'];
$currentSidebarStyle  = in_array($currentSidebarStyle ?? '', $allowedSidebarStyles, true) ? $currentSidebarStyle : 'default';
?>
<html lang="<?= e($currentLocale ?? locale()) ?>"
    data-bs-theme="<?= e($currentTheme ?? 'light') ?>"
    data-theme-skin="<?= e($currentSkin) ?>"
    data-theme-font="<?= e($currentFont ?? 'system') ?>"
    data-theme-pattern="<?= e($_SESSION['user_preferences']['background_pattern'] ?? 'circles') ?>"
    data-sidebar-style="<?= e($currentSidebarStyle) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php if (!empty($metaDescription)): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
        <meta property="og:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($metaKeywords)): ?>
        <meta name="keywords" content="<?= e($metaKeywords) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= e(($ogTitle ?? $pageTitle) ?: $pageTitle) ?>">
    <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
    <?php if (!empty($ogImage)): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <title><?= $appName ?> - <?= $pageTitle ?></title>
    <?php $publicUrl = rtrim(config('app.url'), '/') . rtrim(config('app.base_path'), '/'); ?>
    <link rel="icon" type="image/svg+xml" href="<?= e($publicUrl) ?>/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="<?= e($publicUrl) ?>/favicon.ico">
    <!-- PWA: manifest + icona iOS + theme color (il SW viene registrato da app.js via data-sw-url sul body) -->
    <link rel="manifest" href="<?= e($publicUrl) ?>/manifest.webmanifest">
    <link rel="apple-touch-icon" href="<?= e($publicUrl) ?>/assets/img/pwa/apple-touch-icon-180.png">
    <meta name="theme-color" content="#f97316">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="<?= e(asset('css/fontawesome.min.css')) ?>">
    <!-- User CSS vars: specificita' html:root per vincere sugli override dei temi
         visivi ([data-theme-skin]), cosi' la scelta di font e accento dell'utente
         resta sovrana rispetto a qualunque skin.
         NB: non applichiamo e() qui perche' dentro <style> le entita' HTML non
         vengono decodificate (&quot; resta letterale e rompe il parsing CSS dei
         font stack come "Inter"). $cssVars e' costruito server-side da valori
         whitelist in Controller::prepareSharedData — niente input utente raw. -->
    <style>html:root { <?= preg_replace('#</style#i', '', (string)($cssVars ?? '')) ?> }</style>
    <!-- App custom styles -->
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if (isModuleEnabled('HelpOnline')): ?>
        <link rel="stylesheet" href="<?= e(asset('css/helponline.css')) ?>">
    <?php endif; ?>
    <!-- Self-hosted web fonts (Inter, EB Garamond — browser downloads only the active one) -->
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <!-- Temi visivi (skin) — tutti gli override scoped via [data-theme-skin="..."].
         Caricati tutti per permettere preview inline senza ricaricare la pagina. -->
    <?php foreach ($allowedSkins as $skin): ?>
        <link rel="stylesheet" href="<?= e(asset('css/themes/' . $skin . '.css')) ?>">
    <?php endforeach; ?>
    <?php foreach ($view->getExtraStyles() as $href): ?>
        <link rel="stylesheet" href="<?= e(asset($href)) ?>">
    <?php endforeach; ?>
    <!-- Radial Context Menu -->
    <link rel="stylesheet" href="<?= e(asset('css/components/radial-menu.css')) ?>">
</head>
<body<?= !empty($_SESSION['user_preferences']['sidebar_collapsed']) ? ' class="sidebar-collapsed"' : '' ?>
      data-theme-url="<?= e(route('preferences.theme')) ?>"
      data-sidebar-url="<?= e(route('preferences.sidebar')) ?>"
      data-color-url="<?= e(route('preferences.color')) ?>"
      data-skin-url="<?= e(route('preferences.skin')) ?>"
      data-font-url="<?= e(route('preferences.font')) ?>"
      data-sidebar-style-url="<?= e(route('preferences.sidebar_style')) ?>"
      data-sw-url="<?= e($publicUrl) ?>/sw.js">
    <?php if (isModuleEnabled('Feedback') && !empty($user)): ?>
    <!-- Segnalazioni: early error collector (cattura errori JS precoci, prima di app.js) -->
    <script nonce="<?= e(csp_nonce()) ?>">
    (function(){var b=(window.__sgBuffer=window.__sgBuffer||{errors:[],breadcrumb:[]});b.earlyInstalled=true;function c(a){if(a.length>30)a.splice(0,a.length-30);}window.addEventListener('error',function(e){try{b.errors.push({type:'js',message:String((e&&e.message)||'Errore'),source:String((e&&e.filename)||''),line:(e&&e.lineno)||null,stack:(e&&e.error&&e.error.stack)?String(e.error.stack).slice(0,2000):'',ts:new Date().toISOString()});c(b.errors);}catch(x){}});window.addEventListener('unhandledrejection',function(e){try{var r=e&&e.reason;b.errors.push({type:'js',message:'Promise non gestita: '+((r&&r.message)?r.message:String(r)),source:'',stack:(r&&r.stack)?String(r.stack).slice(0,2000):'',ts:new Date().toISOString()});c(b.errors);}catch(x){}});})();
    </script>
    <?php endif; ?>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <?php $view->include('partials/sidebar', compact('user', 'appName')); ?>

        <!-- Main content area -->
        <div class="app-main" id="app-main">
            <!-- Header -->
            <?php $view->include('partials/header', compact('user', 'pageTitle', 'appName')); ?>

            <!-- Breadcrumb -->
            <?php if (!empty($breadcrumbs)): ?>
                <div class="px-3 border-bottom app-breadcrumb-strip">
                    <?php $view->include('partials/breadcrumb', compact('breadcrumbs')); ?>
                </div>
            <?php endif; ?>

            <!-- Page content -->
            <main class="app-content">
                <?php $view->yield('content'); ?>
            </main>

            <!-- Footer -->
            <?php $view->include('partials/footer', compact('user', 'pageTitle')); ?>
        </div>
    </div>

    <!-- Toast container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>

    <!-- Bootstrap 5 JS -->
    <script src="<?= e(asset('js/bootstrap.bundle.min.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <!-- HTMX -->
    <script src="<?= e(asset('js/htmx.min.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <!-- JS i18n: window.__I18N dict + global t(key, fallback) helper — must load
         before module-specific scripts AND app.js, since both call t(). -->
    <script nonce="<?= e(csp_nonce()) ?>">window.__I18N = <?= json_encode(js_i18n_dict(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.t = function (key, fallback) { return (window.__I18N && window.__I18N[key]) || fallback; };</script>
    <!-- Module-specific scripts -->
    <?php foreach ($view->getExtraScripts() as $src): ?>
        <script src="<?= e(asset($src)) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <?php endforeach; ?>
    <!-- App JS (last — initializes everything) -->
    <script src="<?= e(asset('js/app.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <?php if (isModuleEnabled('HelpOnline')): ?>
        <script src="<?= e(asset('js/helponline.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <?php endif; ?>
    <!-- Form sections: accordion per form entity (create/edit) -->
    <script src="<?= e(asset('js/components/form-sections.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <!-- Form validation & UX (data-app-form, data-char-counter, data-tag-preview) -->
    <script src="<?= e(asset('js/components/form-validation.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
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

    <?php if (isModuleEnabled('Files')): ?>
    <!-- File Picker Modal — globale, usato da qualsiasi modulo -->
    <div class="modal fade" id="filePicker" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-folder-open me-2"></i><?= e(t('common.chrome.library_choose')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="text"
                           id="fp-search"
                           name="q"
                           class="form-control mb-3"
                           placeholder="<?= e(t('common.chrome.library_search')) ?>"
                           autocomplete="off"
                           data-picker-url="<?= e(route('files.picker')) ?>"
                           hx-get="<?= e(route('files.picker')) ?>"
                           hx-trigger="keyup changed delay:400ms"
                           hx-target="#fp-results"
                           hx-swap="innerHTML"
                           hx-include="#fp-mime-filter">
                    <input type="hidden" id="fp-mime-filter" name="mime" value="">
                    <div id="fp-results">
                        <div class="text-center text-muted py-4">
                            <i class="fa-solid fa-spinner fa-spin me-2"></i><?= e(t('common.state.loading')) ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(t('common.action.cancel')) ?></button>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= e(asset('js/file-picker.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
    <?php endif; ?>
    <!-- Radial Context Menu: voci dinamiche da NavigationRegistry (surface=radial) -->
    <?php
    $rmEntries = [];
        $rmTrailingEntries = [];
    foreach (navigation('radial') as $rmNav) {
            $entry = [
            'label' => $rmNav['label'],
            'icon'  => 'fa-solid ' . $rmNav['icon'],
            'url'   => route($rmNav['route']),
        ];

            if (($rmNav['route'] ?? '') === 'home.today') {
                $rmTrailingEntries[] = $entry;
                continue;
            }

            $rmEntries[] = $entry;
    }
        $rmEntries = array_merge($rmEntries, $rmTrailingEntries);
    $rmItems = json_encode(
        $rmEntries,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
    );
    ?>
    <div id="rm-root" data-rm-items="<?= e($rmItems) ?>">
        <div class="rm-origin"></div>
        <div class="rm-close"><i class="fa-solid fa-xmark"></i></div>
    </div>
    <?php if (isModuleEnabled('HelpOnline') && !empty($user)): ?>
        <?php $view->include('HelpOnline/Views/partials/launcher', ['pageTitle' => $pageTitle ?? '']); ?>
    <?php endif; ?>
    <?php if (isModuleEnabled('Feedback') && !empty($user)): ?>
        <?php $view->include('Feedback/Views/partials/launcher', ['pageTitle' => $pageTitle ?? '']); ?>
    <?php endif; ?>
    <script src="<?= e(asset('js/components/radial-menu.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
</body>
</html>
