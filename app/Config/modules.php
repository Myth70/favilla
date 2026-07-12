<?php

/**
 * Module registry — declares enabled modules and route loading order.
 *
 * Each module can declare:
 *  - name:    Directory name under app/Modules/
 *  - enabled: Whether the module is active (default true)
 *  - core:    If true, always active and hidden from Admin module management
 *  - menu:    Legacy. Presente solo per Admin (unica voce radice, senza children)
 *             e usato dal fallback di NavigationRegistry -> surface "sidebar".
 *             Per i nuovi moduli usare invece la sezione "navigation" in module.json
 *             con `surfaces: [...]` (vedi app/Services/NavigationRegistry.php).
 *             Il tooling di sistema/admin NON va in sidebar: si espone via il
 *             catalogo admin (App\Modules\Admin\Services\AdminIndexService).
 *
 * NOTE: Admin must always be the LAST module in this array.
 * Non-core modules with module.json are auto-discovered by ModuleLoader.
 */

return [
    [
        'name'    => 'Auth',
        'enabled' => true,
        'core'    => true,
    ],
    [
        'name'    => 'Home',
        'enabled' => true,
        'core'    => true,
    ],
    [
        'name'    => 'Notifications',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Files',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'HealthCheck',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Backup',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Reports',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Calendar',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Tasks',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Contacts',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'HelpOnline',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Feedback',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Api',
        'enabled' => true,
        'core'    => true,
    ],
    [
        'name'    => 'Webhooks',
        'enabled' => true,
        'core'    => false,
        'permissions_manageable' => true,
    ],
    // ─── Admin must be LAST ───
    [
        'name'    => 'Scheduler',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
    ],
    [
        'name'    => 'Admin',
        'enabled' => true,
        'core'    => true,
        'permissions_manageable' => true,
        // Sola voce radice: la sidebar (app/Views/partials/sidebar.php) la estrae
        // in $adminItem per innescare la sezione "Amministrazione" pinnata in fondo,
        // usandone solo label/icon/route. I link admin quotidiani sono in
        // $adminQuickLinks; il catalogo completo e' in AdminIndexService (admin.index).
        'menu'    => [
            [
                'label'      => 'Amministrazione',
                'icon'       => 'fa-shield-halved',
                'route'      => 'admin.dashboard',
                'permission' => 'admin.users.view',
                'order'      => 90,
            ],
        ],
    ],
];
