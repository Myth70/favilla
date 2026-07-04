<?php

declare(strict_types=1);

namespace App\Modules\_Template\Tests\Unit;

use App\Modules\_Template\Repositories\ExampleRepository;
use Tests\ModuleTestCase;

/**
 * Test del Repository di esempio.
 *
 * Pattern (vedi stubs/Tests/RepositoryTest.stub, copiato da make:module):
 *   - Estendi ModuleTestCase (SQLite in-memory + Container + NOW())
 *   - Crea lo schema in setUp() con $this->migrate(...)  (TEXT al posto di ENUM/VARCHAR)
 *   - Istanzia il repository via Container: app(ExampleRepository::class)
 */
class ExampleRepositoryTest extends ModuleTestCase
{
    private ExampleRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUsersTable();
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
        $this->repo = app(ExampleRepository::class);
    }

    public function test_create_and_find_for_user(): void
    {
        $id = $this->repo->create(['name' => 'Mario', 'email' => 'm@example.test', 'created_by' => 1]);

        $this->assertNotNull($this->repo->find($id));
        $this->assertSame('Mario', $this->repo->findForUser($id, 1)['name']);
        // Owner-scoping: un altro utente non vede il record.
        $this->assertNull($this->repo->findForUser($id, 2));
    }

    public function test_soft_delete_hides_record(): void
    {
        $id = $this->repo->create(['name' => 'Da cancellare', 'created_by' => 1]);
        $this->repo->delete($id);

        $this->assertNull($this->repo->find($id));
        $this->assertNull($this->repo->findForUser($id, 1));
    }

    public function test_list_paginated_shape_and_owner_scope(): void
    {
        $this->repo->create(['name' => 'A', 'created_by' => 1]);
        $this->repo->create(['name' => 'B', 'created_by' => 1]);
        $this->repo->create(['name' => 'Altrui', 'created_by' => 2]);

        $result = $this->repo->listPaginated([], 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('lastPage', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function test_list_paginated_filters_by_q(): void
    {
        $this->repo->create(['name' => 'Alfa', 'created_by' => 1]);
        $this->repo->create(['name' => 'Beta', 'created_by' => 1]);

        $result = $this->repo->listPaginated(['q' => 'Alfa'], 1);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Alfa', $result['data'][0]['name']);
    }

    public function test_find_with_author_joins_user_name(): void
    {
        $this->insertRow('users', ['name' => 'Amministratore']);
        $id = $this->repo->create(['name' => 'Con autore', 'created_by' => 1]);

        $row = $this->repo->findWithAuthor($id, 1);
        $this->assertSame('Amministratore', $row['author_name']);
    }

    public function test_count_by_status(): void
    {
        $this->repo->create(['name' => 'A', 'status' => 'active',   'created_by' => 1]);
        $this->repo->create(['name' => 'B', 'status' => 'active',   'created_by' => 1]);
        $this->repo->create(['name' => 'C', 'status' => 'archived', 'created_by' => 1]);

        $counts = $this->repo->countByStatus(1);

        $this->assertSame(2, $counts['active'] ?? 0);
        $this->assertSame(1, $counts['archived'] ?? 0);
    }
}
