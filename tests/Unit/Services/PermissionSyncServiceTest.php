<?php

namespace Tests\Unit\Services;

use App\Core\ModuleLoader;
use App\Services\PermissionSyncService;
use Tests\ModuleTestCase;

class PermissionSyncServiceTest extends ModuleTestCase
{
    private PermissionSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Tabelle necessarie: permissions (obbligatoria) + app_settings (opzionale)
        $this->migrate("
            CREATE TABLE permissions (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                slug       TEXT NOT NULL UNIQUE,
                module     TEXT DEFAULT NULL,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now'))
            )
        ");
        $this->migrate("
            CREATE TABLE app_settings (
                key        TEXT NOT NULL PRIMARY KEY,
                value      TEXT DEFAULT NULL,
                type       TEXT NOT NULL DEFAULT 'string',
                'group'    TEXT NOT NULL DEFAULT 'general',
                label      TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT (datetime('now'))
            )
        ");

        // Il loader reale viene gia' injettato dal parent; e' sufficiente per i test
        // che usano syncFromDeclarations(). Per collectDeclarations() usiamo loader stub.
        $this->service = new PermissionSyncService(
            $this->pdo,
            app(ModuleLoader::class)
        );
    }

    // ------------------------------------------------------------------
    // sync: insert di nuovi slug
    // ------------------------------------------------------------------

    public function test_sync_inserts_new_permissions(): void
    {
        $report = $this->service->syncFromDeclarations([
            'contacts.view'   => ['name' => 'Visualizza', 'module' => 'Contatti', 'declared_by' => ['Contatti']],
            'contacts.create' => ['name' => 'Crea',       'module' => 'Contatti', 'declared_by' => ['Contatti']],
        ]);

        $this->assertCount(2, $report['added']);
        $this->assertEmpty($report['renamed']);
        $this->assertEmpty($report['collisions']);
        $this->assertEmpty($report['orphaned']);

        $rows = $this->pdo->query('SELECT slug, name, module FROM permissions ORDER BY slug')->fetchAll();
        $this->assertCount(2, $rows);
        $this->assertSame('contacts.create', $rows[0]['slug']);
        $this->assertSame('Crea', $rows[0]['name']);
        $this->assertSame('Contatti', $rows[0]['module']);
    }

    // ------------------------------------------------------------------
    // sync: no-op su permessi gia' esistenti e invariati
    // ------------------------------------------------------------------

    public function test_sync_is_idempotent_when_state_matches(): void
    {
        $declared = [
            'contacts.view' => ['name' => 'Visualizza', 'module' => 'Contatti', 'declared_by' => ['Contatti']],
        ];

        $this->service->syncFromDeclarations($declared);
        $report = $this->service->syncFromDeclarations($declared);

        $this->assertEmpty($report['added']);
        $this->assertEmpty($report['renamed']);
        $this->assertSame(1, $report['existing']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
        $this->assertSame(1, $count);
    }

    // ------------------------------------------------------------------
    // sync: UPDATE se il nome dichiarato cambia
    // ------------------------------------------------------------------

    public function test_sync_updates_name_when_declaration_changes(): void
    {
        $this->service->syncFromDeclarations([
            'contacts.view' => ['name' => 'Old', 'module' => 'Contatti', 'declared_by' => ['Contatti']],
        ]);

        $report = $this->service->syncFromDeclarations([
            'contacts.view' => ['name' => 'New', 'module' => 'Contatti', 'declared_by' => ['Contatti']],
        ]);

        $this->assertCount(1, $report['renamed']);
        $this->assertSame('contacts.view', $report['renamed'][0]['slug']);
        $this->assertSame('Old', $report['renamed'][0]['old_name']);
        $this->assertSame('New', $report['renamed'][0]['new_name']);

        $name = $this->pdo->query("SELECT name FROM permissions WHERE slug = 'contacts.view'")->fetchColumn();
        $this->assertSame('New', $name);
    }

    public function test_sync_updates_module_when_reassigned(): void
    {
        $this->service->syncFromDeclarations([
            'x.view' => ['name' => 'X', 'module' => 'ModA', 'declared_by' => ['ModA']],
        ]);

        $report = $this->service->syncFromDeclarations([
            'x.view' => ['name' => 'X', 'module' => 'ModB', 'declared_by' => ['ModB']],
        ]);

        $this->assertCount(1, $report['renamed']);
        $this->assertSame('ModA', $report['renamed'][0]['old_module']);
        $this->assertSame('ModB', $report['renamed'][0]['new_module']);
    }

    // ------------------------------------------------------------------
    // sync: detection di collisioni cross-module
    // ------------------------------------------------------------------

    public function test_sync_detects_collisions_when_slug_declared_by_multiple_modules(): void
    {
        $report = $this->service->syncFromDeclarations([
            'shared.slug' => [
                'name'        => 'Label',
                'module'      => 'ModA',
                'declared_by' => ['ModA', 'ModB'],
            ],
        ]);

        $this->assertCount(1, $report['collisions']);
        $this->assertSame('shared.slug', $report['collisions'][0]['slug']);
        $this->assertSame('ModA', $report['collisions'][0]['winner_module']);
        $this->assertSame(['ModA', 'ModB'], $report['collisions'][0]['declared_by']);

        // La collisione non blocca l'insert: il primo dichiarante entra in DB
        $module = $this->pdo->query("SELECT module FROM permissions WHERE slug = 'shared.slug'")->fetchColumn();
        $this->assertSame('ModA', $module);
    }

    // ------------------------------------------------------------------
    // sync: orphan detection (senza cancellazione)
    // ------------------------------------------------------------------

    public function test_sync_reports_orphans_without_deleting_them(): void
    {
        $this->pdo->exec("INSERT INTO permissions (name, slug, module) VALUES ('Vecchio', 'legacy.slug', 'ModX')");

        $report = $this->service->syncFromDeclarations([
            'nuovo.slug' => ['name' => 'Nuovo', 'module' => 'ModY', 'declared_by' => ['ModY']],
        ]);

        $this->assertCount(1, $report['orphaned']);
        $this->assertSame('legacy.slug', $report['orphaned'][0]['slug']);
        $this->assertSame('ModX', $report['orphaned'][0]['module']);

        // IMPORTANTE: orphan NON cancellato
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM permissions WHERE slug = 'legacy.slug'")->fetchColumn();
        $this->assertSame(1, $count, 'Orphan permission must NOT be deleted (safe-by-default).');
    }

    // ------------------------------------------------------------------
    // sync: marca timestamp in app_settings
    // ------------------------------------------------------------------

    public function test_sync_marks_last_sync_timestamp_in_app_settings(): void
    {
        $this->service->syncFromDeclarations([
            'x.view' => ['name' => 'X', 'module' => 'ModA', 'declared_by' => ['ModA']],
        ]);

        $value = $this->pdo->query(
            "SELECT value FROM app_settings WHERE `key` = 'permissions_last_sync_at'"
        )->fetchColumn();

        $this->assertNotFalse($value);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }

    public function test_sync_overwrites_previous_timestamp(): void
    {
        $this->service->syncFromDeclarations([]);
        $first = $this->pdo->query(
            "SELECT value FROM app_settings WHERE `key` = 'permissions_last_sync_at'"
        )->fetchColumn();

        sleep(1);
        $this->service->syncFromDeclarations([]);
        $second = $this->pdo->query(
            "SELECT value FROM app_settings WHERE `key` = 'permissions_last_sync_at'"
        )->fetchColumn();

        $this->assertNotFalse($first);
        $this->assertNotFalse($second);

        // Un solo record con la chiave (no duplicati)
        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM app_settings WHERE `key` = 'permissions_last_sync_at'"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }

    // ------------------------------------------------------------------
    // collectDeclarations: integrazione con ModuleLoader (stub)
    // ------------------------------------------------------------------

    public function test_collect_declarations_reads_permissions_php_via_loader(): void
    {
        $loader = new class (BASE_PATH) extends ModuleLoader {
            public function getModules(): array
            {
                return [
                    ['name' => 'FakeMod',     'enabled' => true],
                    ['name' => 'Disabled',    'enabled' => false],
                    ['name' => '_Template',   'enabled' => true],
                ];
            }
            public function scanPermissions(string $moduleName): array
            {
                if ($moduleName === 'FakeMod') {
                    return [
                        ['slug' => 'fake.view',   'name' => 'Vedi Fake'],
                        ['slug' => 'fake.create', 'name' => 'Crea Fake'],
                    ];
                }
                return [];
            }
            public function readModuleJson(string $moduleName): ?array
            {
                return null;
            }
        };

        $service = new PermissionSyncService($this->pdo, $loader);
        $declared = $service->collectDeclarations();

        $this->assertArrayHasKey('fake.view', $declared);
        $this->assertArrayHasKey('fake.create', $declared);
        $this->assertSame('FakeMod', $declared['fake.view']['module']);
        $this->assertSame(['FakeMod'], $declared['fake.view']['declared_by']);

        // Disabilitati e _Template esclusi
        $this->assertCount(2, $declared);
    }

    public function test_collect_declarations_prefers_module_json_over_permissions_php(): void
    {
        $loader = new class (BASE_PATH) extends ModuleLoader {
            public function getModules(): array
            {
                return [['name' => 'Hybrid', 'enabled' => true]];
            }
            public function scanPermissions(string $moduleName): array
            {
                // Questi NON devono vincere
                return [['slug' => 'from.php', 'name' => 'Da php']];
            }
            public function readModuleJson(string $moduleName): ?array
            {
                return [
                    'permissions' => [
                        ['slug' => 'from.json', 'name' => 'Da json'],
                    ],
                ];
            }
        };

        $service = new PermissionSyncService($this->pdo, $loader);
        $declared = $service->collectDeclarations();

        $this->assertArrayHasKey('from.json', $declared);
        $this->assertArrayNotHasKey('from.php', $declared);
    }

    public function test_collect_declarations_falls_back_to_permissions_php_when_json_has_no_permissions_key(): void
    {
        $loader = new class (BASE_PATH) extends ModuleLoader {
            public function getModules(): array
            {
                return [['name' => 'Legacy', 'enabled' => true]];
            }
            public function scanPermissions(string $moduleName): array
            {
                return [['slug' => 'legacy.view', 'name' => 'Legacy view']];
            }
            public function readModuleJson(string $moduleName): ?array
            {
                // module.json esiste ma senza sezione "permissions"
                return ['name' => 'Legacy', 'version' => '1.0.0'];
            }
        };

        $service = new PermissionSyncService($this->pdo, $loader);
        $declared = $service->collectDeclarations();

        $this->assertArrayHasKey('legacy.view', $declared);
    }

    public function test_collect_declarations_detects_cross_module_collision(): void
    {
        $loader = new class (BASE_PATH) extends ModuleLoader {
            public function getModules(): array
            {
                return [
                    ['name' => 'ModA', 'enabled' => true],
                    ['name' => 'ModB', 'enabled' => true],
                ];
            }
            public function scanPermissions(string $moduleName): array
            {
                return [['slug' => 'shared.perm', 'name' => "Dichiarato da {$moduleName}"]];
            }
            public function readModuleJson(string $moduleName): ?array
            {
                return null;
            }
        };

        $service = new PermissionSyncService($this->pdo, $loader);
        $declared = $service->collectDeclarations();

        $this->assertArrayHasKey('shared.perm', $declared);
        $this->assertSame('ModA', $declared['shared.perm']['module'], 'Il primo dichiarante vince');
        $this->assertSame(['ModA', 'ModB'], $declared['shared.perm']['declared_by']);
    }

    public function test_sync_end_to_end_through_loader(): void
    {
        $loader = new class (BASE_PATH) extends ModuleLoader {
            public function getModules(): array
            {
                return [['name' => 'E2E', 'enabled' => true]];
            }
            public function scanPermissions(string $moduleName): array
            {
                return [
                    ['slug' => 'e2e.a', 'name' => 'A'],
                    ['slug' => 'e2e.b', 'name' => 'B'],
                ];
            }
            public function readModuleJson(string $moduleName): ?array
            {
                return null;
            }
        };

        $service = new PermissionSyncService($this->pdo, $loader);
        $report = $service->sync();

        $this->assertCount(2, $report['added']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
        $this->assertSame(2, $count);
    }
}
