<?php

declare(strict_types=1);

namespace App\Modules\_Template\Tests\Unit;

use App\Modules\_Template\Repositories\ExampleRepository;
use App\Modules\_Template\Services\ExampleService;
use Tests\ModuleTestCase;

/**
 * Test del Service di esempio (orchestrazione + owner-scoping).
 *
 * Pattern (vedi stubs/Tests/ServiceTest.stub, copiato da make:module):
 *   - Estendi ModuleTestCase (SQLite in-memory + Container + NOW())
 *   - Crea lo schema in setUp() con $this->migrate(...)
 *   - Istanzia il service iniettando il repository risolto dal Container
 */
class ExampleServiceTest extends ModuleTestCase
{
    private ExampleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate("
            CREATE TABLE examples (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL,
                email       TEXT DEFAULT NULL,
                description TEXT DEFAULT NULL,
                status      TEXT NOT NULL DEFAULT 'active',
                created_by  INTEGER DEFAULT NULL,
                created_at  TEXT DEFAULT (datetime('now')),
                updated_at  TEXT DEFAULT (datetime('now')),
                deleted_at  TEXT DEFAULT NULL
            )
        ");
        $this->service = new ExampleService(app(ExampleRepository::class));
    }

    public function test_create_sets_author_and_persists(): void
    {
        $id = $this->service->create(['name' => 'Primo', 'email' => 'p@example.test', 'status' => 'active'], 42);

        $this->assertGreaterThan(0, $id);
        $item = $this->service->find($id, 42);
        $this->assertSame('Primo', $item['name']);
        $this->assertSame(42, (int) $item['created_by']);
    }

    public function test_list_returns_paginated_shape(): void
    {
        $this->service->create(['name' => 'A'], 1);
        $this->service->create(['name' => 'B'], 1);

        $result = $this->service->list(['page' => 1], 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('lastPage', $result);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function test_update_is_owner_scoped(): void
    {
        $id = $this->service->create(['name' => 'Originale'], 1);

        // L'utente 2 non possiede il record: l'update non avviene.
        $this->assertFalse($this->service->update($id, ['name' => 'Hacked'], 2));
        $this->assertSame('Originale', $this->service->find($id, 1)['name']);

        $this->assertTrue($this->service->update($id, ['name' => 'Aggiornato'], 1));
        $this->assertSame('Aggiornato', $this->service->find($id, 1)['name']);
    }

    public function test_delete_is_owner_scoped(): void
    {
        $id = $this->service->create(['name' => 'Da cancellare'], 1);

        $this->assertFalse($this->service->delete($id, 2));
        $this->assertNotNull($this->service->find($id, 1));

        $this->assertTrue($this->service->delete($id, 1));
        $this->assertNull($this->service->find($id, 1));
    }

    public function test_find_returns_null_for_other_user(): void
    {
        $id = $this->service->create(['name' => 'Mio'], 1);
        $this->assertNull($this->service->find($id, 2));
        $this->assertNotNull($this->service->find($id, 1));
    }
}
