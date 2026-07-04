<?php

namespace Tests\Unit;

use App\Repositories\BaseRepository;
use Tests\ModuleTestCase;

/**
 * Concrete repository senza soft delete (default)
 */
class HardDeleteRepo extends BaseRepository
{
    protected string $table = 'items';
}

/**
 * Concrete repository con soft delete attivato
 */
class SoftDeleteRepo extends BaseRepository
{
    protected string $table = 'items';
    protected bool $softDelete = true;
}

class SoftDeleteTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE items (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                deleted_at TEXT DEFAULT NULL
            )
        ');
    }

    // -----------------------------------------------------------------
    // Hard delete (softDelete = false, default)
    // -----------------------------------------------------------------

    public function test_hard_delete_removes_record(): void
    {
        $repo = new HardDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Alpha']);

        $result = $repo->delete($id);

        $this->assertTrue($result);
        $this->assertNull($repo->find($id));
    }

    public function test_hard_delete_find_is_not_filtered(): void
    {
        $repo = new HardDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Beta', 'deleted_at' => '2024-01-01 00:00:00']);

        // Senza softDelete, find() non applica alcun filtro su deleted_at
        $this->assertNotNull($repo->find($id));
    }

    // -----------------------------------------------------------------
    // Soft delete (softDelete = true)
    // -----------------------------------------------------------------

    public function test_soft_delete_sets_deleted_at(): void
    {
        $repo = new SoftDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Gamma']);

        $result = $repo->delete($id);

        $this->assertTrue($result);
        $row = $this->pdo->query("SELECT deleted_at FROM items WHERE id = {$id}")->fetch();
        $this->assertNotNull($row['deleted_at']);
    }

    public function test_find_excludes_soft_deleted(): void
    {
        $repo = new SoftDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Delta', 'deleted_at' => '2024-01-01 00:00:00']);

        $this->assertNull($repo->find($id));
    }

    public function test_find_returns_non_deleted(): void
    {
        $repo = new SoftDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Epsilon']);

        $this->assertNotNull($repo->find($id));
    }

    public function test_all_excludes_soft_deleted(): void
    {
        $repo = new SoftDeleteRepo();
        $this->insertRow('items', ['name' => 'Visible']);
        $this->insertRow('items', ['name' => 'Hidden', 'deleted_at' => '2024-01-01 00:00:00']);

        $rows = $repo->all();

        $this->assertCount(1, $rows);
        $this->assertSame('Visible', $rows[0]['name']);
    }

    public function test_where_excludes_soft_deleted(): void
    {
        $repo = new SoftDeleteRepo();
        $this->insertRow('items', ['name' => 'Zeta']);
        $this->insertRow('items', ['name' => 'Zeta', 'deleted_at' => '2024-01-01 00:00:00']);

        $rows = $repo->where(['name' => 'Zeta']);

        $this->assertCount(1, $rows);
    }

    public function test_find_by_excludes_soft_deleted(): void
    {
        $repo = new SoftDeleteRepo();
        $this->insertRow('items', ['name' => 'Eta', 'deleted_at' => '2024-01-01 00:00:00']);

        $this->assertNull($repo->findBy('name', 'Eta'));
    }

    public function test_count_excludes_soft_deleted(): void
    {
        $repo = new SoftDeleteRepo();
        $this->insertRow('items', ['name' => 'Theta']);
        $this->insertRow('items', ['name' => 'Iota', 'deleted_at' => '2024-01-01 00:00:00']);

        $this->assertSame(1, $repo->count());
    }

    public function test_all_with_trashed_returns_all(): void
    {
        $repo = new SoftDeleteRepo();
        $this->insertRow('items', ['name' => 'Kappa']);
        $this->insertRow('items', ['name' => 'Lambda', 'deleted_at' => '2024-01-01 00:00:00']);

        $rows = $repo->allWithTrashed();

        $this->assertCount(2, $rows);
    }

    public function test_only_trashed_returns_only_deleted(): void
    {
        $repo = new SoftDeleteRepo();
        $this->insertRow('items', ['name' => 'Mu']);
        $this->insertRow('items', ['name' => 'Nu', 'deleted_at' => '2024-01-01 00:00:00']);

        $rows = $repo->onlyTrashed();

        $this->assertCount(1, $rows);
        $this->assertSame('Nu', $rows[0]['name']);
    }

    public function test_only_trashed_returns_empty_when_soft_delete_disabled(): void
    {
        $repo = new HardDeleteRepo();
        $this->insertRow('items', ['name' => 'Xi', 'deleted_at' => '2024-01-01 00:00:00']);

        $this->assertSame([], $repo->onlyTrashed());
    }

    public function test_restore_clears_deleted_at(): void
    {
        $repo = new SoftDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Omicron', 'deleted_at' => '2024-01-01 00:00:00']);

        $result = $repo->restore($id);

        $this->assertTrue($result);
        $this->assertNotNull($repo->find($id));
    }

    public function test_restore_returns_false_when_soft_delete_disabled(): void
    {
        $repo = new HardDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Pi']);

        $this->assertFalse($repo->restore($id));
    }

    public function test_force_delete_removes_soft_deleted_record(): void
    {
        $repo = new SoftDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Rho', 'deleted_at' => '2024-01-01 00:00:00']);

        $result = $repo->forceDelete($id);

        $this->assertTrue($result);
        $this->assertNull($repo->findWithTrashed($id));
    }

    public function test_find_with_trashed_returns_soft_deleted(): void
    {
        $repo = new SoftDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Sigma', 'deleted_at' => '2024-01-01 00:00:00']);

        $row = $repo->findWithTrashed($id);

        $this->assertNotNull($row);
        $this->assertSame('Sigma', $row['name']);
    }

    public function test_double_soft_delete_returns_false(): void
    {
        $repo = new SoftDeleteRepo();
        $id = $this->insertRow('items', ['name' => 'Tau']);

        $repo->delete($id);
        $second = $repo->delete($id);

        // Il secondo delete fallisce perché deleted_at IS NULL non è più vero
        $this->assertFalse($second);
    }
}
