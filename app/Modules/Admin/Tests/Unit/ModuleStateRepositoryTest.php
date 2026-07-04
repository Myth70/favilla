<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Repositories\ModuleStateRepository;
use Tests\ModuleTestCase;

class ModuleStateRepositoryTest extends ModuleTestCase
{
    private ModuleStateRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE module_states (
                name       TEXT PRIMARY KEY,
                enabled    INTEGER NOT NULL DEFAULT 1,
                testing    INTEGER NOT NULL DEFAULT 0,
                updated_by INTEGER NULL,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->repo = new ModuleStateRepository();
    }

    public function testFindByNameReturnsNullWhenAbsent(): void
    {
        $this->assertNull($this->repo->findByName('Inesistente'));
    }

    public function testUpsertInsertsNewRow(): void
    {
        $this->repo->upsert('Contacts', 1, 0, 5);

        $row = $this->repo->findByName('Contacts');
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['enabled']);
        $this->assertSame(0, (int) $row['testing']);
        $this->assertSame(5, (int) $row['updated_by']);
    }

    public function testUpsertUpdatesExistingRowWithoutDuplicating(): void
    {
        $this->repo->upsert('Contacts', 1, 0, 5);
        $this->repo->upsert('Contacts', 0, 1, 9);

        $row = $this->repo->findByName('Contacts');
        $this->assertSame(0, (int) $row['enabled']);
        $this->assertSame(1, (int) $row['testing']);
        $this->assertSame(9, (int) $row['updated_by']);

        // PK = name → una sola riga.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM module_states WHERE name = 'Contacts'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testUpsertAcceptsNullUpdatedBy(): void
    {
        $this->repo->upsert('System', 1, 0, null);

        $row = $this->repo->findByName('System');
        $this->assertNull($row['updated_by']);
    }
}
