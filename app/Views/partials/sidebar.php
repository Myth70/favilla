<?php
/**
 * Sidebar partial.
 * Variables: $user (array), $appName (string)
 */
$currentRoute = $_SERVER['REQUEST_URI'] ?? '/';

$sidebarStyle = $_SESSION['user_preferences']['sidebar_style'] ?? 'default';
$sidebarPatternClass = $sidebarStyle === 'accent'
    ? ' ' . \App\Modules\Home\Helpers\PatternHelper::resolveClass()
    : '';
?>
<aside class="app-sidebar<?= e($sidebarPatternClass) ?>" id="app-sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <a href="<?= e(route('home.index')) ?>" class="sidebar-brand-link">
            <svg class="sidebar-brand-icon" xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 32 32" width="22" height="22" aria-hidden="true">
                <path d="M16 2 C13 7 5 10 5 18 C5 24.8 9.8 29.5 16 30 C22.2 29.5 27 24.8 27 18 C27 10 19 7 16 2Z" fill="#f97316"/>
                <path d="M16 9 C14 13 10 16 10 20 C10 23.9 12.7 27 16 27 C19.3 27 22 23.9 22 20 C22 16 18 13 16 9Z" fill="#ea580c"/>
                <path d="M16 15 C15 17 13 19 14 22 C14.7 24 16 25 16 25 C16 25 17.3 24 18 22 C19 19 17 17 16 15Z" fill="#fbbf24"/>
            </svg>
            <span class="sidebar-brand-text"><?= $appName ?? 'Favilla' ?></span>
        </a>
        <button class="sidebar-toggle-btn d-lg-none" id="sidebar-close-btn" data-bs-toggle="tooltip" data-bs-placement="right" title="<?= e(t('common.chrome.sidebar_close')) ?>">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <?php
            // Compute once: current path and app base path.
            $currentPath = rtrim(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), '/');
            $appBasePath = rtrim(parse_url(route('home.index'), PHP_URL_PATH) ?? '', '/');

            // Helper: is a given path "active" relative to $currentPath?
            $pathMatches = function (string $path) use ($currentPath, $appBasePath): bool {
                return $currentPath === $path
                    || ($path !== $appBasePath && str_starts_with($currentPath, $path . '/'));
            };

            // Separate the admin item (pinned at bottom) from regular sidebar items.
            // Fonte unica: NavigationRegistry. I moduli user-facing (contatti, attivita, calendario, files)
            // dichiarano surfaces=[user_menu, radial, quick_access] quindi non compaiono qui.
            $adminItem    = null;
            $regularItems = [];
            foreach (navigation('sidebar') as $item) {
                if (($item['route'] ?? '') === 'admin.dashboard') {
                    $adminItem = $item;
                } else {
                    $regularItems[] = $item;
                }
            }
            ?>
            <?php foreach ($regularItems as $item): ?>
                <?php
                    $routeUrl  = route($item['route']);
                    $routePath = rtrim(parse_url($routeUrl, PHP_URL_PATH) ?? '', '/');

                    $isActive = $pathMatches($routePath);

                    $hasChildren = !empty($item['children']);
                    if (!$isActive && $hasChildren) {
                        foreach ($item['children'] as $child) {
                            $childPathTmp = rtrim(parse_url(route($child['route']), PHP_URL_PATH) ?? '', '/');
                            if ($pathMatches($childPathTmp)) {
                                $isActive = true;
                                break;
                            }
                        }
                    }

                    $collapseId = $hasChildren
                        ? 'submenu-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($item['route']))
                        : '';
                ?>
                <li class="sidebar-menu-item <?= $isActive ? 'active' : '' ?>">
                    <?php if ($hasChildren): ?>
                        <a href="#"
                           class="sidebar-menu-link <?= !$isActive ? 'collapsed' : '' ?>"
                           data-bs-toggle="collapse"
                           data-bs-target="#<?= $collapseId ?>"
                           data-sidebar-route="<?= e($routeUrl) ?>"
                           data-sidebar-label="<?= e($item['label']) ?>"
                           aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                            <i class="fa-solid <?= e($item['icon']) ?> sidebar-menu-icon"></i>
                            <span class="sidebar-menu-text"><?= e($item['label']) ?></span>
                            <i class="fa-solid fa-chevron-down sidebar-menu-chevron"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?= e($routeUrl) ?>" class="sidebar-menu-link"
                           data-sidebar-label="<?= e($item['label']) ?>">
                            <i class="fa-solid <?= e($item['icon']) ?> sidebar-menu-icon"></i>
                            <span class="sidebar-menu-text"><?= e($item['label']) ?></span>
                        </a>
                    <?php endif; ?>

                    <?php if ($hasChildren): ?>
                        <ul class="sidebar-submenu collapse <?= $isActive ? 'show' : '' ?>" id="<?= $collapseId ?>">
                            <?php
                                // Se un child corrisponde esattamente al path corrente,
                                // disabilita il prefix-match per gli altri child.
                                $anyChildExact = false;
                                foreach ($item['children'] as $ch) {
                                    $chPath = rtrim(parse_url(route($ch['route']), PHP_URL_PATH) ?? '', '/');
                                    if ($currentPath === $chPath) { $anyChildExact = true; break; }
                                }
                            ?>
                            <?php foreach ($item['children'] as $child): ?>
                                <?php
                                    $childUrl  = route($child['route']);
                                    $childPath = rtrim(parse_url($childUrl, PHP_URL_PATH) ?? '', '/');
                                    $isChildActive = $anyChildExact
                                        ? ($currentPath === $childPath)
                                        : $pathMatches($childPath);
                                ?>
                                <li class="sidebar-submenu-item <?= $isChildActive ? 'active' : '' ?>">
                                    <a href="<?= e($childUrl) ?>" class="sidebar-submenu-link">
                                        <i class="fa-solid <?= e($child['icon'] ?? 'fa-circle') ?> fa-xs"></i>
                                        <span><?= e($child['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <?php if ($adminItem): ?>
    <?php
        // Voci admin quotidiane
        $adminQuickLinks = [
            ['label' => t('common.admin.catalog'),   'icon' => 'fa-table-cells-large', 'route' => 'admin.index',      'permission' => 'admin.users.view'],
            ['label' => t('common.admin.dashboard'), 'icon' => 'fa-gauge-high',   'route' => 'admin.dashboard',      'permission' => 'admin.users.view'],
            ['label' => t('common.admin.users'),     'icon' => 'fa-users',        'route' => 'admin.users.index',    'permission' => 'admin.users.view'],
            ['label' => t('common.admin.roles'),     'icon' => 'fa-user-tag',     'route' => 'admin.roles.index',    'permission' => 'admin.roles.manage'],
            ['label' => t('common.admin.modules'),   'icon' => 'fa-cubes',        'route' => 'admin.modules.index',  'permission' => 'admin.modules.manage'],
            ['label' => t('common.admin.settings'),  'icon' => 'fa-sliders',      'route' => 'admin.settings.index', 'permission' => 'admin.settings.manage'],
            ['label' => t('common.admin.logs'),      'icon' => 'fa-list-check',   'route' => 'admin.logs.index',     'permission' => 'admin.logs.view'],
        ];

        // Verifica se una qualsiasi voce admin è attiva (per espandere il menu)
        $adminCollapseId = 'sidebar-admin-collapse';
        $isAnyAdminActive = false;
        foreach ($adminQuickLinks as $ql) {
            if (!has_permission($ql['permission'])) continue;
            try {
                $qlPath = rtrim(parse_url(route($ql['route']), PHP_URL_PATH) ?? '', '/');
                $match  = $pathMatches($qlPath);
                if ($match) { $isAnyAdminActive = true; break; }
            } catch (\Throwable $e) {
                app_log('error', 'sidebar.php admin quick link resolve failed: ' . $e->getMessage());
            }
        }
        // Controlla anche se siamo in area admin in generale
        if (!$isAnyAdminActive) {
            $adminBasePath = rtrim(parse_url(route('admin.dashboard'), PHP_URL_PATH) ?? '', '/');
            $adminBasePath = preg_replace('#/dashboard$#', '', $adminBasePath);
            if ($adminBasePath !== '' && str_starts_with($currentPath, $adminBasePath)) {
                $isAnyAdminActive = true;
            }
        }
    ?>
    <!-- Admin — sezione collassabile + Command Palette -->
    <div class="sidebar-admin-section">
        <button type="button"
                class="sidebar-admin-header sidebar-admin-toggle <?= $isAnyAdminActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#<?= $adminCollapseId ?>"
            data-sidebar-route="<?= e(route('admin.dashboard')) ?>"
            data-sidebar-label="<?= e($adminItem['label']) ?>"
                aria-expanded="<?= $isAnyAdminActive ? 'true' : 'false' ?>"
                aria-controls="<?= $adminCollapseId ?>"
                data-bs-toggle-tooltip data-bs-placement="right"
                title="<?= e(t('common.admin.area_toggle')) ?>">
            <i class="fa-solid <?= e($adminItem['icon']) ?> sidebar-menu-icon"></i>
            <span class="sidebar-menu-text"><?= e($adminItem['label']) ?></span>
            <i class="fa-solid fa-chevron-down sidebar-admin-chevron ms-auto"></i>
        </button>
        <div class="collapse <?= $isAnyAdminActive ? 'show' : '' ?>" id="<?= $adminCollapseId ?>">
            <ul class="sidebar-menu sidebar-admin-menu">
                <?php foreach ($adminQuickLinks as $qlink): ?>
                    <?php if (!has_permission($qlink['permission'])) continue; ?>
                    <?php
                        $qlUrl    = route($qlink['route']);
                        $qlPath   = rtrim(parse_url($qlUrl, PHP_URL_PATH) ?? '', '/');
                        $qlActive = $pathMatches($qlPath);
                    ?>
                    <li class="sidebar-menu-item <?= $qlActive ? 'active' : '' ?>">
                        <a href="<?= e($qlUrl) ?>" class="sidebar-menu-link">
                            <i class="fa-solid <?= e($qlink['icon']) ?> sidebar-menu-icon"></i>
                            <span class="sidebar-menu-text"><?= e($qlink['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <!-- Cerca funzione — apre la Command Palette -->
                <li class="sidebar-menu-item">
                    <a href="#" class="sidebar-menu-link sidebar-palette-trigger" id="palette-trigger-btn">
                        <i class="fa-solid fa-magnifying-glass sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text"><?= e(t('common.admin.search_function')) ?></span>
                        <kbd class="sidebar-palette-kbd" title="<?= e(t('common.admin.search_hint')) ?>">Ctrl+K</kbd>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Logout rapido -->
    <div class="sidebar-footer">
        <form method="POST" action="<?= e(route('logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="sidebar-logout-btn" data-bs-toggle="tooltip" data-bs-placement="right" title="<?= e(t('common.user.logout')) ?>">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="sidebar-menu-text"><?= e(t('common.user.logout')) ?></span>
            </button>
        </form>
    </div>
</aside>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<?php if (has_permission('admin.users.view')): ?>
    <?php $view->include('Admin/Views/partials/command-palette'); ?>
<?php endif; ?>
