<?php

namespace Tests\Unit\Services;

use App\Services\RoleConstraintService;
use Tests\ModuleTestCase;

class RoleConstraintServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
            )
        ');
        $this->migrate('
            CREATE TABLE role_constraints (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id_1 INTEGER NOT NULL,
                role_id_2 INTEGER NOT NULL,
                reason TEXT DEFAULT NULL,
                enabled INTEGER NOT NULL DEFAULT 1
            )
        ');
        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT DEFAULT NULL,
                deleted_at TEXT DEFAULT NULL
            )
        ');
        $this->migrate('
            CREATE TABLE user_role (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL
            )
        ');

        $this->insertRow('roles', ['name' => 'Admin', 'slug' => 'admin']);
        $this->insertRow('roles', ['name' => 'Auditor', 'slug' => 'auditor']);
        $this->insertRow('roles', ['name' => 'Editor', 'slug' => 'editor']);
    }

    public function test_create_constraint_normalizes_order(): void
    {
        $svc = new RoleConstraintService();
        $id = $svc->createConstraint(3, 1, 'SoD admin vs editor');
        $row = $this->pdo->query("SELECT role_id_1, role_id_2 FROM role_constraints WHERE id = {$id}")->fetch();
        $this->assertSame(1, (int) $row['role_id_1']);
        $this->assertSame(3, (int) $row['role_id_2']);
    }

    public function test_find_constraint_returns_null_for_missing(): void
    {
        $svc = new RoleConstraintService();
        $this->assertNull($svc->findConstraint(999));
    }

    public function test_find_constraint_joins_role_names(): void
    {
        $svc = new RoleConstraintService();
        $id = $svc->createConstraint(1, 2, 'conflict');
        $row = $svc->findConstraint($id);
        $this->assertSame('Admin', $row['role1_name']);
        $this->assertSame('Auditor', $row['role2_name']);
    }

    public function test_update_constraint_changes_reason(): void
    {
        $svc = new RoleConstraintService();
        $id = $svc->createConstraint(1, 2, 'old reason');
        $svc->updateConstraint($id, 'new reason');
        $row = $svc->findConstraint($id);
        $this->assertSame('new reason', $row['reason']);
    }

    public function test_toggle_constraint_flips_enabled(): void
    {
        $svc = new RoleConstraintService();
        $id = $svc->createConstraint(1, 2, 'r');
        $svc->toggleConstraint($id);
        $enabled = (int) $this->pdo->query("SELECT enabled FROM role_constraints WHERE id = {$id}")->fetchColumn();
        $this->assertSame(0, $enabled);
        $svc->toggleConstraint($id);
        $enabled = (int) $this->pdo->query("SELECT enabled FROM role_constraints WHERE id = {$id}")->fetchColumn();
        $this->assertSame(1, $enabled);
    }

    public function test_delete_constraint_removes_row(): void
    {
        $svc = new RoleConstraintService();
        $id = $svc->createConstraint(1, 2, 'r');
        $svc->deleteConstraint($id);
        $this->assertNull($svc->findConstraint($id));
    }

    public function test_all_constraints_lists_with_role_names(): void
    {
        $svc = new RoleConstraintService();
        $svc->createConstraint(1, 2, 'a');
        $svc->createConstraint(2, 3, 'b');
        $rows = $svc->allConstraints();
        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('role1_name', $rows[0]);
        $this->assertArrayHasKey('role2_name', $rows[0]);
    }

    public function test_validate_roles_returns_empty_for_single(): void
    {
        $svc = new RoleConstraintService();
        $this->assertSame([], $svc->validateRoles([1]));
        $this->assertSame([], $svc->validateRoles([]));
    }

    public function test_validate_roles_returns_empty_when_no_conflicts(): void
    {
        $svc = new RoleConstraintService();
        $svc->createConstraint(1, 2, 'admin+auditor forbidden');
        $this->assertSame([], $svc->validateRoles([2, 3]));
    }

    public function test_validate_roles_detects_conflict(): void
    {
        $svc = new RoleConstraintService();
        $svc->createConstraint(1, 2, 'admin+auditor forbidden');
        $violations = $svc->validateRoles([1, 2]);
        $this->assertCount(1, $violations);
        $this->assertSame('admin+auditor forbidden', $violations[0]['reason']);
    }

    public function test_validate_roles_ignores_disabled_constraints(): void
    {
        $svc = new RoleConstraintService();
        $id = $svc->createConstraint(1, 2, 'r');
        $svc->toggleConstraint($id); // disable
        $this->assertSame([], $svc->validateRoles([1, 2]));
    }

    public function test_find_violations_detects_conflicting_assignments(): void
    {
        $svc = new RoleConstraintService();
        $svc->createConstraint(1, 2, 'conflict');
        $userId = $this->insertRow('users', ['name' => 'Mario', 'email' => 'm@x.com']);
        $this->insertRow('user_role', ['user_id' => $userId, 'role_id' => 1]);
        $this->insertRow('user_role', ['user_id' => $userId, 'role_id' => 2]);

        $violations = $svc->findViolations();
        $this->assertCount(1, $violations);
        $this->assertSame('Mario', $violations[0]['user_name']);
    }

    public function test_stats_counts(): void
    {
        $svc = new RoleConstraintService();
        $id1 = $svc->createConstraint(1, 2, 'a');
        $svc->createConstraint(2, 3, 'b');
        $svc->toggleConstraint($id1); // one disabled

        $stats = $svc->getStats();
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['active']);
        $this->assertSame(0, $stats['violations']);
    }

    public function test_get_roles_list_sorted_by_name(): void
    {
        $svc = new RoleConstraintService();
        $rows = $svc->getRolesList();
        $this->assertCount(3, $rows);
        $this->assertSame('Admin', $rows[0]['name']);
        $this->assertSame('Auditor', $rows[1]['name']);
        $this->assertSame('Editor', $rows[2]['name']);
    }
}
