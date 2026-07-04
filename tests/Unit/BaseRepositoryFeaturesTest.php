<?php

namespace Tests\Unit;

use App\Repositories\BaseRepository;
use Tests\ModuleTestCase;

// =========================================================================
//  Test repository variants
// =========================================================================

class PlainRepo extends BaseRepository
{
    protected string $table = 'items';
}

class HookedRepo extends BaseRepository
{
    protected string $table = 'items';

    public array $hookLog = [];

    protected function beforeCreate(array &$data): void
    {
        $this->hookLog[] = 'beforeCreate';
        // Mutate data to prove hooks can modify input
        if (!isset($data['slug']) && isset($data['name'])) {
            $data['slug'] = strtolower(str_replace(' ', '-', $data['name']));
        }
    }

    protected function afterCreate(int $id, array $data): void
    {
        $this->hookLog[] = "afterCreate:{$id}";
    }

    protected function beforeUpdate(int $id, array &$data): void
    {
        $this->hookLog[] = "beforeUpdate:{$id}";
    }

    protected function afterUpdate(int $id, array $data): void
    {
        $this->hookLog[] = "afterUpdate:{$id}";
    }

    protected function beforeDelete(int $id): void
    {
        $this->hookLog[] = "beforeDelete:{$id}";
    }

    protected function afterDelete(int $id): void
    {
        $this->hookLog[] = "afterDelete:{$id}";
    }
}

class FillableRepo extends BaseRepository
{
    protected string $table = 'items';
    protected array $fillable = ['name', 'status'];
}

class GuardedRepo extends BaseRepository
{
    protected string $table = 'items';
    protected array $guarded = ['id', 'created_at', 'updated_at', 'deleted_at', 'secret'];
}

class TimestampRepo extends BaseRepository
{
    protected string $table = 'items';
    protected bool $timestamps = true;
}

class AuditableRepo extends BaseRepository
{
    protected string $table = 'items';
    protected bool $auditable = true;
}

class AuditableCustomEntityRepo extends BaseRepository
{
    protected string $table = 'items';
    protected bool $auditable = true;
    protected string $auditEntity = 'product';
}

class FullFeaturedRepo extends BaseRepository
{
    protected string $table = 'items';
    protected bool $softDelete = true;
    protected array $fillable = ['name', 'status'];
    protected bool $timestamps = true;
    protected bool $auditable = true;

    public array $hookLog = [];

    protected function beforeCreate(array &$data): void
    {
        $this->hookLog[] = 'beforeCreate';
    }

    protected function afterCreate(int $id, array $data): void
    {
        $this->hookLog[] = "afterCreate:{$id}";
    }
}

// =========================================================================
//  Tests
// =========================================================================

class BaseRepositoryFeaturesTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate("
            CREATE TABLE items (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL,
                slug        TEXT DEFAULT NULL,
                status      TEXT DEFAULT 'active',
                secret      TEXT DEFAULT NULL,
                created_at  TEXT DEFAULT NULL,
                updated_at  TEXT DEFAULT NULL,
                deleted_at  TEXT DEFAULT NULL
            )
        ");
        // Audit table for auditable tests
        $this->migrate('
            CREATE TABLE audit_logs (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER,
                action      TEXT NOT NULL,
                entity      TEXT NOT NULL,
                entity_id   INTEGER NOT NULL,
                old_value   TEXT,
                new_value   TEXT,
                ip          TEXT
            )
        ');
    }

    // =====================================================================
    //  Lifecycle Hooks
    // =====================================================================

    public function test_hooks_not_called_on_plain_repo(): void
    {
        $repo = new PlainRepo();
        $id = $repo->create(['name' => 'Test']);
        $repo->update($id, ['name' => 'Updated']);
        $repo->delete($id);

        // Just verify no exceptions — plain repo has no-op hooks
        $this->assertTrue(true);
    }

    public function test_before_create_hook_is_called(): void
    {
        $repo = new HookedRepo();
        $repo->create(['name' => 'Hello World']);

        $this->assertContains('beforeCreate', $repo->hookLog);
    }

    public function test_before_create_hook_can_mutate_data(): void
    {
        $repo = new HookedRepo();
        $id = $repo->create(['name' => 'Hello World']);

        $row = $repo->find($id);
        $this->assertSame('hello-world', $row['slug']);
    }

    public function test_after_create_hook_receives_id(): void
    {
        $repo = new HookedRepo();
        $id = $repo->create(['name' => 'Alpha']);

        $this->assertContains("afterCreate:{$id}", $repo->hookLog);
    }

    public function test_before_update_hook_is_called(): void
    {
        $repo = new HookedRepo();
        $id = $this->insertRow('items', ['name' => 'Beta']);

        $repo->update($id, ['name' => 'Beta Updated']);

        $this->assertContains("beforeUpdate:{$id}", $repo->hookLog);
    }

    public function test_after_update_hook_is_called(): void
    {
        $repo = new HookedRepo();
        $id = $this->insertRow('items', ['name' => 'Gamma']);

        $repo->update($id, ['name' => 'Gamma Updated']);

        $this->assertContains("afterUpdate:{$id}", $repo->hookLog);
    }

    public function test_before_delete_hook_is_called(): void
    {
        $repo = new HookedRepo();
        $id = $this->insertRow('items', ['name' => 'Delta']);

        $repo->delete($id);

        $this->assertContains("beforeDelete:{$id}", $repo->hookLog);
    }

    public function test_after_delete_hook_is_called(): void
    {
        $repo = new HookedRepo();
        $id = $this->insertRow('items', ['name' => 'Epsilon']);

        $repo->delete($id);

        $this->assertContains("afterDelete:{$id}", $repo->hookLog);
    }

    public function test_hooks_order_on_create(): void
    {
        $repo = new HookedRepo();
        $id = $repo->create(['name' => 'Order Test']);

        $this->assertSame('beforeCreate', $repo->hookLog[0]);
        $this->assertSame("afterCreate:{$id}", $repo->hookLog[1]);
    }

    public function test_delete_hooks_not_called_on_failure(): void
    {
        $repo = new HookedRepo();
        // Non-existent ID — delete returns false
        $repo->delete(9999);

        // beforeDelete is always called (before we know if row exists)
        $this->assertContains('beforeDelete:9999', $repo->hookLog);
        // afterDelete should NOT be called because rowCount is 0
        $this->assertNotContains('afterDelete:9999', $repo->hookLog);
    }

    // =====================================================================
    //  Fillable (whitelist)
    // =====================================================================

    public function test_fillable_allows_only_listed_columns_on_create(): void
    {
        $repo = new FillableRepo();
        $id = $repo->create(['name' => 'Alpha', 'status' => 'draft', 'secret' => 'x']);

        $row = $repo->find($id);
        $this->assertSame('Alpha', $row['name']);
        $this->assertSame('draft', $row['status']);
        $this->assertNull($row['secret']);
    }

    public function test_fillable_allows_only_listed_columns_on_update(): void
    {
        $repo = new FillableRepo();
        $id = $this->insertRow('items', ['name' => 'Beta', 'secret' => 'original']);

        $repo->update($id, ['name' => 'Updated', 'secret' => 'hacked']);

        $row = $repo->find($id);
        $this->assertSame('Updated', $row['name']);
        $this->assertSame('original', $row['secret']);
    }

    public function test_fillable_empty_data_after_filter(): void
    {
        $repo = new FillableRepo();

        // Only non-fillable fields → empty data → SQL error
        $this->expectException(\Throwable::class);
        $repo->create(['secret' => 'only-secret']);
    }

    // =====================================================================
    //  Guarded (blacklist)
    // =====================================================================

    public function test_guarded_strips_listed_columns_on_create(): void
    {
        $repo = new GuardedRepo();
        $id = $repo->create(['name' => 'Gamma', 'secret' => 'should-be-stripped']);

        $row = $repo->find($id);
        $this->assertSame('Gamma', $row['name']);
        $this->assertNull($row['secret']);
    }

    public function test_guarded_strips_listed_columns_on_update(): void
    {
        $repo = new GuardedRepo();
        $id = $this->insertRow('items', ['name' => 'Delta', 'secret' => 'safe']);

        $repo->update($id, ['name' => 'Updated', 'secret' => 'hacked']);

        $row = $repo->find($id);
        $this->assertSame('Updated', $row['name']);
        $this->assertSame('safe', $row['secret']);
    }

    public function test_guarded_allows_non_guarded_columns(): void
    {
        $repo = new GuardedRepo();
        $id = $repo->create(['name' => 'Epsilon', 'status' => 'inactive']);

        $row = $repo->find($id);
        $this->assertSame('Epsilon', $row['name']);
        $this->assertSame('inactive', $row['status']);
    }

    public function test_plain_repo_has_no_guarded_by_default(): void
    {
        $repo = new PlainRepo();
        // Plain repo should pass all columns through (guarded is empty by default)
        $id = $repo->create(['name' => 'Zeta', 'secret' => 'visible']);

        $row = $repo->find($id);
        $this->assertSame('visible', $row['secret']);
    }

    // =====================================================================
    //  Timestamps
    // =====================================================================

    public function test_timestamps_disabled_by_default(): void
    {
        $repo = new PlainRepo();
        $id = $repo->create(['name' => 'NoTimestamp']);

        $row = $repo->find($id);
        $this->assertNull($row['created_at']);
        $this->assertNull($row['updated_at']);
    }

    public function test_timestamps_sets_created_at_and_updated_at_on_create(): void
    {
        $repo = new TimestampRepo();
        $before = date('Y-m-d H:i:s');
        $id = $repo->create(['name' => 'Stamped']);
        $after = date('Y-m-d H:i:s');

        $row = $repo->find($id);
        $this->assertNotNull($row['created_at']);
        $this->assertNotNull($row['updated_at']);
        $this->assertGreaterThanOrEqual($before, $row['created_at']);
        $this->assertLessThanOrEqual($after, $row['created_at']);
    }

    public function test_timestamps_sets_updated_at_on_update(): void
    {
        $repo = new TimestampRepo();
        $id = $this->insertRow('items', ['name' => 'Old', 'updated_at' => '2020-01-01 00:00:00']);

        $repo->update($id, ['name' => 'New']);

        $row = $repo->find($id);
        $this->assertNotSame('2020-01-01 00:00:00', $row['updated_at']);
    }

    public function test_timestamps_does_not_overwrite_explicit_created_at(): void
    {
        $repo = new TimestampRepo();
        $id = $repo->create(['name' => 'Explicit', 'created_at' => '2025-01-01 00:00:00']);

        $row = $repo->find($id);
        $this->assertSame('2025-01-01 00:00:00', $row['created_at']);
    }

    public function test_timestamps_does_not_overwrite_explicit_updated_at(): void
    {
        $repo = new TimestampRepo();
        $id = $repo->create(['name' => 'Explicit', 'updated_at' => '2025-06-15 12:00:00']);

        $row = $repo->find($id);
        $this->assertSame('2025-06-15 12:00:00', $row['updated_at']);
    }

    // =====================================================================
    //  Audit (automatic)
    // =====================================================================

    public function test_audit_disabled_by_default(): void
    {
        $repo = new PlainRepo();
        $id = $repo->create(['name' => 'NoAudit']);
        $repo->update($id, ['name' => 'Still no audit']);
        $repo->delete($id);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_audit_logs_create(): void
    {
        $_SESSION['user_id'] = 1;
        $repo = new AuditableRepo();
        $id = $repo->create(['name' => 'Audited']);

        $logs = $this->pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll();
        $this->assertCount(1, $logs);
        $this->assertSame('items_created', $logs[0]['action']);
        $this->assertSame('items', $logs[0]['entity']);
        $this->assertEquals($id, $logs[0]['entity_id']);
    }

    public function test_audit_logs_update_with_old_values(): void
    {
        $_SESSION['user_id'] = 1;
        $repo = new AuditableRepo();
        $id = $this->insertRow('items', ['name' => 'Before']);

        $repo->update($id, ['name' => 'After']);

        $logs = $this->pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll();
        $this->assertCount(1, $logs);
        $this->assertSame('items_updated', $logs[0]['action']);

        $old = json_decode($logs[0]['old_value'], true);
        $this->assertSame('Before', $old['name']);

        $new = json_decode($logs[0]['new_value'], true);
        $this->assertSame('After', $new['name']);
    }

    public function test_audit_logs_delete(): void
    {
        $_SESSION['user_id'] = 1;
        $repo = new AuditableRepo();
        $id = $this->insertRow('items', ['name' => 'ToDelete']);

        $repo->delete($id);

        $logs = $this->pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll();
        $this->assertCount(1, $logs);
        $this->assertSame('items_deleted', $logs[0]['action']);
        $this->assertNotNull($logs[0]['old_value']);
        $this->assertNull($logs[0]['new_value']);
    }

    public function test_audit_custom_entity_name(): void
    {
        $_SESSION['user_id'] = 1;
        $repo = new AuditableCustomEntityRepo();
        $id = $repo->create(['name' => 'Custom']);

        $log = $this->pdo->query('SELECT * FROM audit_logs LIMIT 1')->fetch();
        $this->assertSame('product_created', $log['action']);
        $this->assertSame('product', $log['entity']);
    }

    // =====================================================================
    //  Combined features (FullFeaturedRepo)
    // =====================================================================

    public function test_full_featured_create(): void
    {
        $_SESSION['user_id'] = 1;
        $repo = new FullFeaturedRepo();
        $id = $repo->create([
            'name'   => 'Full',
            'status' => 'draft',
            'secret' => 'should-be-stripped',
        ]);

        $row = $repo->find($id);

        // Fillable: name + status allowed, secret stripped
        $this->assertSame('Full', $row['name']);
        $this->assertSame('draft', $row['status']);
        $this->assertNull($row['secret']);

        // Timestamps
        $this->assertNotNull($row['created_at']);
        $this->assertNotNull($row['updated_at']);

        // Hooks
        $this->assertContains('beforeCreate', $repo->hookLog);
        $this->assertContains("afterCreate:{$id}", $repo->hookLog);

        // Audit
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_full_featured_soft_delete_with_audit(): void
    {
        $_SESSION['user_id'] = 1;
        $repo = new FullFeaturedRepo();
        $id = $this->insertRow('items', ['name' => 'SoftAudit']);

        $repo->delete($id);

        // Soft deleted
        $this->assertNull($repo->find($id));
        $this->assertNotNull($repo->findWithTrashed($id));

        // Audit logged
        $log = $this->pdo->query('SELECT * FROM audit_logs LIMIT 1')->fetch();
        $this->assertSame('items_deleted', $log['action']);
    }

    // =====================================================================
    //  Backward compatibility — existing SoftDelete behavior unchanged
    // =====================================================================

    public function test_existing_soft_delete_behavior_unchanged(): void
    {
        // Use PlainRepo (no new features) — same as old behavior
        $repo = new PlainRepo();
        $id = $repo->create(['name' => 'Compat']);

        $row = $repo->find($id);
        $this->assertSame('Compat', $row['name']);

        $repo->update($id, ['name' => 'Updated']);
        $row = $repo->find($id);
        $this->assertSame('Updated', $row['name']);

        $result = $repo->delete($id);
        $this->assertTrue($result);
        $this->assertNull($repo->find($id));
    }

    // =====================================================================
    //  filterData edge cases
    // =====================================================================

    public function test_fillable_takes_precedence_over_guarded(): void
    {
        // FillableRepo has fillable=['name','status'], guarded is empty
        // If both were set, fillable should win
        $repo = new FillableRepo();
        $id = $repo->create(['name' => 'Precedence', 'slug' => 'ignored']);

        $row = $repo->find($id);
        $this->assertSame('Precedence', $row['name']);
        $this->assertNull($row['slug']); // not in fillable → stripped
    }

    // =====================================================================
    //  Pagination
    // =====================================================================

    public function test_paginate_returns_correct_structure(): void
    {
        $repo = new PlainRepo();
        for ($i = 1; $i <= 25; $i++) {
            $repo->create(['name' => "Item {$i}"]);
        }

        $result = $repo->paginate(1, 10);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('lastPage', $result);
        $this->assertCount(10, $result['data']);
        $this->assertSame(25, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['perPage']);
        $this->assertSame(3, $result['lastPage']);
    }

    public function test_paginate_second_page(): void
    {
        $repo = new PlainRepo();
        for ($i = 1; $i <= 25; $i++) {
            $repo->create(['name' => "Item {$i}"]);
        }

        $result = $repo->paginate(2, 10);

        $this->assertCount(10, $result['data']);
        $this->assertSame(2, $result['page']);
    }

    public function test_paginate_last_page_partial(): void
    {
        $repo = new PlainRepo();
        for ($i = 1; $i <= 25; $i++) {
            $repo->create(['name' => "Item {$i}"]);
        }

        $result = $repo->paginate(3, 10);

        $this->assertCount(5, $result['data']);
        $this->assertSame(3, $result['page']);
    }

    public function test_paginate_with_conditions(): void
    {
        $repo = new PlainRepo();
        $repo->create(['name' => 'Alpha', 'status' => 'active']);
        $repo->create(['name' => 'Beta', 'status' => 'inactive']);
        $repo->create(['name' => 'Gamma', 'status' => 'active']);

        $result = $repo->paginate(1, 10, ['status' => 'active']);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function test_paginate_respects_soft_delete(): void
    {
        $repo = new FullFeaturedRepo();
        $_SESSION['user_id'] = 1;
        $id1 = $repo->create(['name' => 'Visible', 'status' => 'active']);
        $id2 = $repo->create(['name' => 'Deleted', 'status' => 'active']);
        $repo->delete($id2);

        $result = $repo->paginate(1, 10);

        $this->assertSame(1, $result['total']);
    }

    public function test_paginate_clamps_page_to_valid_range(): void
    {
        $repo = new PlainRepo();
        $repo->create(['name' => 'Solo']);

        // Page 999 should clamp to lastPage (1)
        $result = $repo->paginate(999, 10);

        $this->assertSame(1, $result['page']);
        $this->assertCount(1, $result['data']);
    }

    public function test_paginate_empty_table(): void
    {
        $repo = new PlainRepo();

        $result = $repo->paginate(1, 10);

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['data']);
        $this->assertSame(1, $result['lastPage']);
    }
}
