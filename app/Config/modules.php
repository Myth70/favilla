<?php

/**
 * Module registry — declares enabled modules and route loading order.
 *
 * Each module can declare:
 *  - name:    Directory name under app/Modules/
 *  - enabled: Whether the module is active (default true)
 *  - core:    If true, always active and hidden from Admin module management
 *  - menu:    Legacy. Presente solo per Admin (struttura gerarchica con children)
 *             e usato dal fallback di NavigationRegistry -> surface "sidebar".
 *             Per i nuovi moduli usare invece la sezione "navigation" in module.json
 *             con `surfaces: [...]` (vedi app/Services/NavigationRegistry.php).
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
        'menu'    => [
            [
                'label'      => 'Amministrazione',
                'icon'       => 'fa-shield-halved',
                'route'      => 'admin.dashboard',
                'permission' => 'admin.users.view',
                'order'      => 90,
                'children'   => [
                    // ── Generale ──────────────────────────────────────────
                    [
                        'label'      => 'Generale',
                        'icon'       => 'fa-gauge-high',
                        'route'      => 'admin.dashboard',
                        'permission' => 'admin.users.view',
                        'children'   => [
                            [
                                'label'      => 'Indice',
                                'icon'       => 'fa-table-cells-large',
                                'route'      => 'admin.index',
                                'permission' => 'admin.users.view',
                            ],
                            [
                                'label'      => 'Dashboard',
                                'icon'       => 'fa-gauge-high',
                                'route'      => 'admin.dashboard',
                                'permission' => 'admin.users.view',
                            ],
                            [
                                'label'      => 'Utenti',
                                'icon'       => 'fa-users',
                                'route'      => 'admin.users.index',
                                'permission' => 'admin.users.view',
                            ],
                        ],
                    ],
                    // ── Moduli applicativi ───────────────────────────────
                    [
                        'label'      => 'Applicativi',
                        'icon'       => 'fa-cubes',
                        'route'      => 'admin.modules.index',
                        'permission' => 'admin.modules.manage',
                        'children'   => [
                            [
                                'label'      => 'Moduli',
                                'icon'       => 'fa-puzzle-piece',
                                'route'      => 'admin.modules.index',
                                'permission' => 'admin.modules.manage',
                            ],
                            [
                                'label'      => 'Report',
                                'icon'       => 'fa-file-export',
                                'route'      => 'reports.index',
                                'permission' => 'reports.view',
                            ],
                        ],
                    ],
                    // ── Sistema ───────────────────────────────────────────
                    [
                        'label'      => 'Sistema',
                        'icon'       => 'fa-server',
                        'route'      => 'admin.settings.index',
                        'permission' => 'admin.settings.manage',
                        'children'   => [
                            [
                                'label'      => 'Configurazione',
                                'icon'       => 'fa-sliders',
                                'route'      => 'admin.settings.index',
                                'permission' => 'admin.settings.manage',
                            ],
                            [
                                'label'      => 'Email',
                                'icon'       => 'fa-envelope',
                                'route'      => 'admin.mail.index',
                                'permission' => 'admin.mail.manage',
                            ],
                            [
                                'label'      => 'Notifiche',
                                'icon'       => 'fa-bell',
                                'route'      => 'admin.notifications.settings',
                                'permission' => 'notifications.admin.manage',
                            ],
                            [
                                'label'      => 'Scheduler',
                                'icon'       => 'fa-clock',
                                'route'      => 'scheduler.index',
                                'permission' => 'scheduler.view',
                            ],
                            [
                                'label'      => 'Changelog',
                                'icon'       => 'fa-code-branch',
                                'route'      => 'admin.changelog.index',
                                'permission' => 'admin.changelog.manage',
                            ],
                            [
                                'label'      => 'Log',
                                'icon'       => 'fa-list-check',
                                'route'      => 'admin.logs.index',
                                'permission' => 'admin.logs.view',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
