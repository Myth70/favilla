<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\ModuleLoader;
use App\Services\NavigationRegistry;
use Tests\ModuleTestCase;

/**
 * Verifica NavigationRegistry:
 *  - raccolta entry da moduli abilitati
 *  - esclusione moduli disabilitati
 *  - filtro per surface
 *  - filtro per permessi utente (con bypass admin)
 *  - fallback legacy `menu` -> surface `sidebar`
 *  - priorita' di "navigation" su "menu"
 *  - ordinamento stabile per `order`
 */
class NavigationRegistryTest extends ModuleTestCase
{
    private array $savedEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedEnv = $_ENV;
        unset($_ENV['APP_EDITION']);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->savedEnv;
        parent::tearDown();
    }

    /**
     * Crea un loader stub con lista moduli e mapping moduleName => (navigation|menu|null).
     * `$moduleJson` e' una mappa 'ModuleName' => array|null (quello che readModuleJson restituirebbe).
     */
    private function makeLoader(array $modules, array $moduleJson = []): ModuleLoader
    {
        return new class (BASE_PATH, $modules, $moduleJson) extends ModuleLoader {
            public function __construct(
                string $basePath,
                private array $stubModules,
                private array $stubJson,
            ) {
                parent::__construct($basePath);
            }

            public function getModules(): array
            {
                return $this->stubModules;
            }

            public function readModuleJson(string $moduleName): ?array
            {
                return $this->stubJson[$moduleName] ?? null;
            }
        };
    }

    // ------------------------------------------------------------------
    // all(): enabled modules, sorted by order
    // ------------------------------------------------------------------

    public function test_all_returns_entries_from_enabled_modules_sorted_by_order(): void
    {
        $loader = $this->makeLoader(
            modules: [
                ['name' => 'ModA', 'enabled' => true],
                ['name' => 'ModB', 'enabled' => true],
            ],
            moduleJson: [
                'ModA' => ['navigation' => [
                    ['id' => 'a.index', 'label' => 'A', 'route' => 'a.index', 'order' => 30, 'surfaces' => ['sidebar']],
                ]],
                'ModB' => ['navigation' => [
                    ['id' => 'b.index', 'label' => 'B', 'route' => 'b.index', 'order' => 10, 'surfaces' => ['sidebar']],
                ]],
            ]
        );

        $registry = new NavigationRegistry($loader);
        $entries  = $registry->all();

        $this->assertCount(2, $entries);
        $this->assertSame('b.index', $entries[0]['id'], 'order=10 deve venire prima di order=30');
        $this->assertSame('a.index', $entries[1]['id']);
    }

    public function test_all_excludes_disabled_modules(): void
    {
        $loader = $this->makeLoader(
            modules: [
                ['name' => 'Attivo',   'enabled' => true],
                ['name' => 'Spento',   'enabled' => false],
            ],
            moduleJson: [
                'Attivo' => ['navigation' => [
                    ['id' => 'att.index', 'label' => 'Attivo', 'route' => 'att.index', 'surfaces' => ['sidebar']],
                ]],
                'Spento' => ['navigation' => [
                    ['id' => 'spe.index', 'label' => 'Spento', 'route' => 'spe.index', 'surfaces' => ['sidebar']],
                ]],
            ]
        );

        $entries = (new NavigationRegistry($loader))->all();

        $this->assertCount(1, $entries);
        $this->assertSame('att.index', $entries[0]['id']);
    }

    // ------------------------------------------------------------------
    // forSurface(): filtro per surface
    // ------------------------------------------------------------------

    public function test_for_surface_returns_only_entries_declaring_that_surface(): void
    {
        $loader = $this->makeLoader(
            modules: [['name' => 'Mod', 'enabled' => true]],
            moduleJson: [
                'Mod' => ['navigation' => [
                    ['id' => 'sid', 'label' => 'S', 'route' => 's.index', 'surfaces' => ['sidebar']],
                    ['id' => 'usr', 'label' => 'U', 'route' => 'u.index', 'surfaces' => ['user_menu']],
                    ['id' => 'rad', 'label' => 'R', 'route' => 'r.index', 'surfaces' => ['radial']],
                    ['id' => 'both', 'label' => 'B', 'route' => 'b.index', 'surfaces' => ['user_menu', 'radial']],
                ]],
            ]
        );
        $registry = new NavigationRegistry($loader);

        $sidebar = $registry->forSurface(NavigationRegistry::SURFACE_SIDEBAR);
        $this->assertSame(['sid'], array_column($sidebar, 'id'));

        $user = $registry->forSurface(NavigationRegistry::SURFACE_USER_MENU);
        $this->assertSame(['usr', 'both'], array_column($user, 'id'));

        $radial = $registry->forSurface(NavigationRegistry::SURFACE_RADIAL);
        $this->assertSame(['rad', 'both'], array_column($radial, 'id'));
    }

    // ------------------------------------------------------------------
    // forSurface(): filtro per permessi utente
    // ------------------------------------------------------------------

    public function test_for_surface_filters_by_user_permissions(): void
    {
        $loader = $this->makeLoader(
            modules: [['name' => 'Mod', 'enabled' => true]],
            moduleJson: [
                'Mod' => ['navigation' => [
                    ['id' => 'pub',  'label' => 'Pubblico',     'route' => 'pub.index',  'permission' => null,         'surfaces' => ['sidebar']],
                    ['id' => 'priv', 'label' => 'Riservato',    'route' => 'priv.index', 'permission' => 'secret.view', 'surfaces' => ['sidebar']],
                ]],
            ]
        );
        $registry = new NavigationRegistry($loader);

        // Utente senza il permesso: vede solo la voce pubblica
        $entries = $registry->forSurface(NavigationRegistry::SURFACE_SIDEBAR, [], []);
        $this->assertSame(['pub'], array_column($entries, 'id'));

        // Utente con il permesso: vede entrambe
        $entries = $registry->forSurface(NavigationRegistry::SURFACE_SIDEBAR, ['secret.view'], []);
        $this->assertSame(['pub', 'priv'], array_column($entries, 'id'));
    }

    public function test_for_surface_admin_role_bypasses_permission_filter(): void
    {
        $loader = $this->makeLoader(
            modules: [['name' => 'Mod', 'enabled' => true]],
            moduleJson: [
                'Mod' => ['navigation' => [
                    ['id' => 'priv', 'label' => 'X', 'route' => 'x.index', 'permission' => 'secret.view', 'surfaces' => ['sidebar']],
                ]],
            ]
        );
        $registry = new NavigationRegistry($loader);

        // Admin senza permesso esplicito: bypassa
        $entries = $registry->forSurface(NavigationRegistry::SURFACE_SIDEBAR, [], ['admin']);
        $this->assertCount(1, $entries);
    }

    public function test_for_surface_with_null_permissions_disables_permission_filter(): void
    {
        $loader = $this->makeLoader(
            modules: [['name' => 'Mod', 'enabled' => true]],
            moduleJson: [
                'Mod' => ['navigation' => [
                    ['id' => 'priv', 'label' => 'X', 'route' => 'x.index', 'permission' => 'secret.view', 'surfaces' => ['sidebar']],
                ]],
            ]
        );
        $registry = new NavigationRegistry($loader);

        // null = no filter (uso interno o preview admin)
        $entries = $registry->forSurface(NavigationRegistry::SURFACE_SIDEBAR, null, []);
        $this->assertCount(1, $entries);
    }

    // ------------------------------------------------------------------
    // Legacy fallback: menu -> sidebar surface
    // ------------------------------------------------------------------

    public function test_legacy_menu_falls_back_to_sidebar_surface(): void
    {
        // Modulo con solo "menu" (niente "navigation"): deve finire in sidebar.
        $loader = $this->makeLoader(
            modules: [
                [
                    'name'    => 'Legacy',
                    'enabled' => true,
                    'menu'    => [
                        ['label' => 'Legacy', 'icon' => 'fa-cog', 'route' => 'legacy.index', 'order' => 50],
                    ],
                ],
            ],
            moduleJson: []
        );
        $registry = new NavigationRegistry($loader);

        $sidebar = $registry->forSurface(NavigationRegistry::SURFACE_SIDEBAR);
        $this->assertCount(1, $sidebar);
        $this->assertSame('legacy.index', $sidebar[0]['id']);
        $this->assertSame(['sidebar'], $sidebar[0]['surfaces']);

        // Non deve comparire su altre surface
        $this->assertEmpty($registry->forSurface(NavigationRegistry::SURFACE_USER_MENU));
        $this->assertEmpty($registry->forSurface(NavigationRegistry::SURFACE_RADIAL));
    }

    public function test_legacy_fallback_reads_module_menu_not_module_json_menu(): void
    {
        // Se il loader NON ha popolato $module['menu'] (modulo registrato in modules.php
        // senza sezione menu), un menu presente nel module.json NON deve essere ripreso
        // via fallback: e' considerato "legacy / non piu' attivo".
        $loader = $this->makeLoader(
            modules: [
                ['name' => 'Silent', 'enabled' => true],   // niente 'menu' qui
            ],
            moduleJson: [
                'Silent' => [
                    'menu' => [
                        ['label' => 'Dead', 'icon' => 'fa-skull', 'route' => 'silent.index', 'order' => 50],
                    ],
                ],
            ]
        );
        $registry = new NavigationRegistry($loader);

        $this->assertEmpty($registry->all(), 'menu in module.json senza esplicito navigation non deve apparire');
    }

    public function test_navigation_key_takes_priority_over_legacy_menu(): void
    {
        // Modulo con ENTRAMBI menu + navigation: prevale navigation.
        $loader = $this->makeLoader(
            modules: [
                [
                    'name'    => 'Dual',
                    'enabled' => true,
                    'menu'    => [
                        ['label' => 'DaMenu', 'icon' => 'fa-old', 'route' => 'dual.old', 'order' => 50],
                    ],
                ],
            ],
            moduleJson: [
                'Dual' => ['navigation' => [
                    ['id' => 'dual.new', 'label' => 'DaNavigation', 'route' => 'dual.new', 'order' => 10, 'surfaces' => ['user_menu']],
                ]],
            ]
        );
        $registry = new NavigationRegistry($loader);

        $all = $registry->all();
        $this->assertCount(1, $all, 'navigation sostituisce menu, non si sommano');
        $this->assertSame('dual.new', $all[0]['id']);
        $this->assertSame(['user_menu'], $all[0]['surfaces']);
    }

    // ------------------------------------------------------------------
    // Normalizzazione
    // ------------------------------------------------------------------

    public function test_entries_are_normalized_with_defaults(): void
    {
        $loader = $this->makeLoader(
            modules: [['name' => 'Mod', 'enabled' => true]],
            moduleJson: [
                'Mod' => ['navigation' => [
                    // Entry minimale: solo route
                    ['route' => 'min.index'],
                ]],
            ]
        );
        $entries = (new NavigationRegistry($loader))->all();

        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertSame('min.index', $entry['id'], 'id default: route');
        $this->assertSame('fa-circle', $entry['icon']);
        $this->assertSame(100, $entry['order']);
        $this->assertSame(['sidebar'], $entry['surfaces']);
        $this->assertNull($entry['permission']);
        $this->assertSame('Mod', $entry['module']);
    }

    public function test_multiple_modules_entries_are_merged_and_sorted(): void
    {
        $loader = $this->makeLoader(
            modules: [
                ['name' => 'A', 'enabled' => true],
                ['name' => 'B', 'enabled' => true],
                ['name' => 'C', 'enabled' => true],
            ],
            moduleJson: [
                'A' => ['navigation' => [
                    ['id' => 'a1', 'label' => 'A1', 'route' => 'a1', 'order' => 30, 'surfaces' => ['user_menu']],
                ]],
                'B' => ['navigation' => [
                    ['id' => 'b1', 'label' => 'B1', 'route' => 'b1', 'order' => 5,  'surfaces' => ['user_menu']],
                    ['id' => 'b2', 'label' => 'B2', 'route' => 'b2', 'order' => 20, 'surfaces' => ['sidebar']],
                ]],
                'C' => ['navigation' => [
                    ['id' => 'c1', 'label' => 'C1', 'route' => 'c1', 'order' => 15, 'surfaces' => ['user_menu']],
                ]],
            ]
        );
        $registry = new NavigationRegistry($loader);

        $userMenu = $registry->forSurface(NavigationRegistry::SURFACE_USER_MENU);
        $this->assertSame(['b1', 'c1', 'a1'], array_column($userMenu, 'id'));
    }

    // ------------------------------------------------------------------
    // invalidate()
    // ------------------------------------------------------------------

    public function test_invalidate_clears_internal_cache(): void
    {
        $mutable = [
            ['name' => 'Mod', 'enabled' => true],
        ];
        $json = [
            'Mod' => ['navigation' => [
                ['id' => 'one', 'label' => 'One', 'route' => 'one', 'surfaces' => ['sidebar']],
            ]],
        ];

        // Loader che cambia la risposta fra prima e dopo invalidate().
        $loader = new class (BASE_PATH, $mutable, $json) extends ModuleLoader {
            public array $modules2;
            public function __construct(
                string $basePath,
                private array $modulesStub,
                private array $jsonStub,
            ) {
                parent::__construct($basePath);
            }
            public function getModules(): array
            {
                return $this->modulesStub;
            }
            public function readModuleJson(string $moduleName): ?array
            {
                return $this->jsonStub[$moduleName] ?? null;
            }
            public function setJson(array $json): void
            {
                $this->jsonStub = $json;
            }
        };

        $registry = new NavigationRegistry($loader);
        $this->assertCount(1, $registry->all());

        // Cambio la fonte sotto il cofano — senza invalidate la cache non si aggiorna
        $loader->setJson([
            'Mod' => ['navigation' => [
                ['id' => 'one', 'label' => 'One', 'route' => 'one', 'surfaces' => ['sidebar']],
                ['id' => 'two', 'label' => 'Two', 'route' => 'two', 'surfaces' => ['sidebar']],
            ]],
        ]);
        $this->assertCount(1, $registry->all(), 'cache stale senza invalidate');

        $registry->invalidate();
        $this->assertCount(2, $registry->all(), 'invalidate ricostruisce la cache');
    }

    // ------------------------------------------------------------------
    // forSurface(): filtro per edizione (hidden ≠ disabled, solo sidebar)
    // ------------------------------------------------------------------

    private function makeAdminAndTasksLoader(): ModuleLoader
    {
        return $this->makeLoader(
            modules: [
                ['name' => 'Admin', 'enabled' => true],
                ['name' => 'Tasks', 'enabled' => true],
            ],
            moduleJson: [
                'Admin' => ['navigation' => [
                    ['id' => 'admin.dashboard', 'label' => 'Admin', 'route' => 'admin.index', 'surfaces' => ['sidebar']],
                ]],
                'Tasks' => ['navigation' => [
                    ['id' => 'tasks.index', 'label' => 'Tasks', 'route' => 'tasks.index', 'surfaces' => ['sidebar']],
                ]],
            ]
        );
    }

    public function test_for_surface_hides_edition_hidden_modules_from_sidebar_in_personal(): void
    {
        $_ENV['APP_EDITION'] = 'personal';
        $registry = new NavigationRegistry($this->makeAdminAndTasksLoader());

        $sidebar = $registry->forSurface(NavigationRegistry::SURFACE_SIDEBAR);

        $this->assertSame(['tasks.index'], array_column($sidebar, 'id'), 'Admin deve sparire dalla sidebar in personal');
    }

    public function test_for_surface_shows_all_modules_in_developer(): void
    {
        $_ENV['APP_EDITION'] = 'developer';
        $registry = new NavigationRegistry($this->makeAdminAndTasksLoader());

        $sidebar = $registry->forSurface(NavigationRegistry::SURFACE_SIDEBAR);

        $this->assertSame(['admin.dashboard', 'tasks.index'], array_column($sidebar, 'id'));
    }

    public function test_edition_filter_applies_only_to_sidebar_surface(): void
    {
        $_ENV['APP_EDITION'] = 'personal';
        $loader = $this->makeLoader(
            modules: [['name' => 'Admin', 'enabled' => true]],
            moduleJson: [
                'Admin' => ['navigation' => [
                    ['id' => 'admin.usermenu', 'label' => 'Admin', 'route' => 'admin.index', 'surfaces' => ['user_menu']],
                ]],
            ]
        );
        $registry = new NavigationRegistry($loader);

        $userMenu = $registry->forSurface(NavigationRegistry::SURFACE_USER_MENU);
        $this->assertSame(['admin.usermenu'], array_column($userMenu, 'id'), 'il filtro edizione non tocca le altre surface');
    }
}
