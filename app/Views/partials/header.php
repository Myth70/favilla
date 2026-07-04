<?php
/**
 * Header partial.
 * Variables: $user (array), $pageTitle (string), $appName (string)
 */
$view->share('pageTitle', $pageTitle ?? '');
$headerPatternClass = \App\Modules\Home\Helpers\PatternHelper::resolveClass();
?>
<header class="app-header <?= e($headerPatternClass) ?>">
    <div class="d-flex align-items-center">
        <!-- Mobile sidebar toggle -->
        <button class="btn btn-link text-body d-lg-none me-2 p-0" id="sidebar-toggle-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?= e(t('common.chrome.menu')) ?>">
            <i class="fa-solid fa-bars fa-lg"></i>
        </button>

        <!-- Desktop sidebar collapse toggle -->
        <button class="btn btn-link text-body d-none d-lg-inline-block me-2 p-0" id="sidebar-collapse-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?= e(t('common.chrome.sidebar_collapse')) ?>">
            <i class="fa-solid fa-bars fa-lg"></i>
        </button>

    </div>

    <div class="d-flex align-items-center gap-3">
       

        <!-- Global search -->
        <div class="position-relative app-global-search" id="global-search-wrapper">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-magnifying-glass fa-sm text-muted"></i></span>
                <input type="search" class="form-control border-start-0 ps-0"
                       name="q" placeholder="<?= e(t('common.chrome.search_placeholder')) ?>"
                       autocomplete="off"
                       hx-get="<?= e(route('search.quick')) ?>"
                       hx-trigger="keyup changed delay:400ms, search"
                       hx-target="#global-search-results"
                       hx-indicator="#global-search-spinner">
                <span class="htmx-indicator position-absolute end-0 top-50 translate-middle-y me-2" id="global-search-spinner">
                    <span class="spinner-border spinner-border-sm text-muted" role="status"></span>
                </span>
            </div>
            <div id="global-search-results" class="position-absolute top-100 start-0 w-100 mt-1 app-global-search-results"></div>
        </div>
<!-- Help Online launcher (solo se il modulo è abilitato e l'utente è loggato) -->
        <?php if (isModuleEnabled('HelpOnline') && !empty($user)): ?>
        <button type="button"
                id="ho-launcher-header-btn"
                class="btn btn-link text-body p-0 app-header-help-btn"
                data-bs-toggle="tooltip"
                data-bs-placement="bottom"
                aria-controls="ho-offcanvas"
                aria-label="<?= e(t('common.tooltip.open_help_aria')) ?>"
                title="<?= e(t('common.tooltip.open_help')) ?>">
            <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
        </button>
        <?php endif; ?>

        <!-- Language switcher (i18n) -->
        <?php $view->include('partials/language_switcher', ['currentLocale' => $currentLocale ?? locale()]); ?>

        <!-- Dark/Light mode toggle -->
        <button class="btn btn-link text-body p-0" id="theme-toggle-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?= e(t('common.theme.toggle')) ?>">
            <i class="fa-solid fa-moon" id="theme-icon"></i>
        </button>
        <!-- Accent color palette dropdown -->
        <?php
        $accentPalette = ['#3b82f6','#8b5cf6','#ec4899','#ef4444','#f97316','#22c55e','#14b8a6','#64748b'];
        $accentNames   = ['#3b82f6'=>'Blu','#8b5cf6'=>'Viola','#ec4899'=>'Rosa','#ef4444'=>'Rosso','#f97316'=>'Arancione','#22c55e'=>'Verde','#14b8a6'=>'Turchese','#64748b'=>'Grigio'];
        $currentAccent = $_SESSION['user_preferences']['primary_color'] ?? '#3b82f6';
        ?>
        <div class="dropdown">
            <button class="btn btn-link text-body p-0 d-flex align-items-center" type="button"
                    data-bs-toggle="dropdown"
                    data-bs-placement="bottom"
                    title="<?= e(t('common.theme.color')) ?>"
                    aria-label="<?= e(t('common.theme.color')) ?>"
                    aria-expanded="false">
                <span id="accent-preview-dot"
                        class="app-accent-preview-dot"></span>
            </button>
            <div class="dropdown-menu accent-dropdown-menu">
                <div class="accent-dropdown-hdr px-3 py-2">
                    <i class="fa-solid fa-palette small me-1 app-accent-palette-icon"></i>
                    <span class="small fw-semibold"><?= e(t('common.theme.color')) ?></span>
                </div>
                <div class="px-3 pt-2 pb-3">
                    <div class="d-flex flex-wrap gap-2 app-accent-swatch-wrap">
                        <?php foreach ($accentPalette as $swatchColor): ?>
                        <button type="button"
                                class="accent-swatch app-accent-swatch <?= $swatchColor === $currentAccent ? 'active' : '' ?>"
                                data-color="<?= e($swatchColor) ?>"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e($accentNames[$swatchColor] ?? $swatchColor) ?>"
                            style="--swatch-color:<?= e($swatchColor) ?>;"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification bell (solo se il modulo Notifications è abilitato) -->
        <?php if (isModuleEnabled('Notifications')): ?>
        <?php $view->include('Notifications/Views/partials/bell', get_defined_vars()); ?>
        <?php endif; ?>

        <!-- User dropdown -->
        <div class="dropdown">
            <button class="btn btn-link text-body dropdown-toggle text-decoration-none p-0" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <?php
                    $nameParts = explode(' ', $user['name'] ?? 'U');
                    $initials  = mb_strtoupper(mb_substr($nameParts[0], 0, 1));
                    if (isset($nameParts[1]) && $nameParts[1] !== '') {
                        $initials .= mb_strtoupper(mb_substr($nameParts[1], 0, 1));
                    }
                    $hdrAvatarUrl = \App\Modules\Auth\Helpers\AvatarHelper::url($_SESSION['user_avatar'] ?? null);
                ?>
                <?php if ($hdrAvatarUrl): ?>
                <img src="<?= e($hdrAvatarUrl) ?>" alt=""
                       class="rounded-circle me-1 app-header-avatar-sm">
                <?php else: ?>
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white me-1 app-header-avatar-fallback">
                    <?= e($initials) ?>
                </span>
                <?php endif; ?>
                <span class="d-none d-md-inline"><small><?= e($user['name'] ?? t('common.user.fallback_name')) ?></small></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
                <!-- Header "user card" -->
                <li class="user-dropdown-header px-3 py-2">
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($hdrAvatarUrl): ?>
                        <img src="<?= e($hdrAvatarUrl) ?>" alt=""
                                class="rounded-circle app-header-avatar-md">
                        <?php else: ?>
                        <span class="user-dropdown-avatar"><?= e($initials) ?></span>
                        <?php endif; ?>
                        <div class="overflow-hidden">
                            <div class="fw-semibold text-body lh-sm"><?= e($user['name'] ?? t('common.user.fallback_name')) ?></div>
                        </div>
                    </div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>

                <!-- Voci menu: rendering dinamico via NavigationRegistry -->
                <?php foreach (navigation('user_menu') as $navItem): ?>
                <li>
                    <a href="<?= e(route($navItem['route'])) ?>" class="dropdown-item user-dropdown-item">
                        <span class="user-dropdown-item-icon"><i class="fa-solid <?= e($navItem['icon']) ?>"></i></span>
                        <?= e($navItem['label']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
                <?php if (is_single_user() && has_permission('admin.settings.manage')): ?>
                <li>
                    <a href="<?= e(route('admin.index')) ?>" class="dropdown-item user-dropdown-item">
                        <span class="user-dropdown-item-icon"><i class="fa-solid fa-sliders"></i></span>
                        <?= e(t('nav.edition_settings')) ?>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?= e(route('profile')) ?>#cambia-password" class="dropdown-item user-dropdown-item">
                        <span class="user-dropdown-item-icon"><i class="fa-solid fa-key"></i></span>
                        <?= e(t('common.user.password')) ?>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>

                <!-- Logout -->
                <li>
                    <form method="POST" action="<?= e(route('logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item user-dropdown-item user-dropdown-item-btn">
                            <span class="user-dropdown-item-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                            <?= e(t('common.user.logout')) ?>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
document.addEventListener('click',function(e){var w=document.getElementById('global-search-wrapper');if(w&&!w.contains(e.target)){document.getElementById('global-search-results').innerHTML='';}});
(function(){var input=document.querySelector('#global-search-wrapper input[name="q"]');if(input){input.addEventListener('keydown',function(e){if(e.key==='Escape'){document.getElementById('global-search-results').innerHTML='';input.blur();}});}})();
</script>
