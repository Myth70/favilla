<?php

namespace App\Modules\Progetti\Tests\Unit;

use App\Modules\Progetti\Repositories\ProgettiRepository;
use Tests\ModuleTestCase;

/**
 * Test del repository Progetti su SQLite in-memory.
 *
 * Copre:
 * - dependencyExists() / createDependency() / deleteDependency()
 * - getDependencyEdges()
 * - countOpenPredecessors()
 * - createTimesheet() / deleteTimesheet()
 * - updateProgressCache() / updateBudgetCache()
 */
class ProgettiRepositoryTest extends ModuleTestCase
{
    private ProgettiRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                is_active  INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS roles (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS permissions (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                slug   TEXT NOT NULL,
                name   TEXT NOT NULL,
                module TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS user_role (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, role_id)
            );
            CREATE TABLE IF NOT EXISTS role_permission (
                role_id       INTEGER NOT NULL,
                permission_id INTEGER NOT NULL,
                PRIMARY KEY (role_id, permission_id)
            );
            CREATE TABLE IF NOT EXISTS projects (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                name                 TEXT NOT NULL,
                owner_user_id        INTEGER NOT NULL DEFAULT 1,
                status               TEXT NOT NULL DEFAULT "planning",
                estimated_hours      REAL NOT NULL DEFAULT 0,
                budget_planned       REAL NOT NULL DEFAULT 0,
                budget_actual_cached REAL NOT NULL DEFAULT 0,
                progress_cached      REAL NOT NULL DEFAULT 0,
                created_by           INTEGER NULL,
                created_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at           TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_milestones (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                name       TEXT NOT NULL,
                status     TEXT NOT NULL DEFAULT "pending",
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_tasks (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id       INTEGER NOT NULL,
                title            TEXT NOT NULL,
                status           TEXT NOT NULL DEFAULT "todo",
                priority         TEXT NOT NULL DEFAULT "medium",
                position         INTEGER NOT NULL DEFAULT 0,
                completed_at     TEXT NULL,
                created_by       INTEGER NULL,
                deleted_at       TEXT NULL,
                updated_at       TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS project_task_dependencies (
                predecessor_task_id INTEGER NOT NULL,
                successor_task_id   INTEGER NOT NULL,
                dependency_type     TEXT NOT NULL DEFAULT "FS",
                PRIMARY KEY (predecessor_task_id, successor_task_id)
            );
            CREATE TABLE IF NOT EXISTS project_members (
                project_id           INTEGER NOT NULL,
                user_id              INTEGER NOT NULL,
                role                 TEXT NOT NULL DEFAULT "member",
                hourly_rate_override REAL NULL,
                joined_at            TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (project_id, user_id)
            );
            CREATE TABLE IF NOT EXISTS project_timesheets (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                task_id    INTEGER NOT NULL,
                user_id    INTEGER NOT NULL,
                work_date  TEXT NOT NULL,
                hours      REAL NOT NULL,
                note       TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS project_files (
                project_id INTEGER NOT NULL,
                file_id    INTEGER NOT NULL,
                linked_by  INTEGER NULL,
                linked_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (project_id, file_id)
            );
            CREATE TABLE IF NOT EXISTS files (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                original_name TEXT NOT NULL,
                stored_name   TEXT NOT NULL,
                directory     TEXT NOT NULL DEFAULT "files",
                mime_type     TEXT NOT NULL,
                extension     TEXT NOT NULL,
                size_bytes    INTEGER NOT NULL DEFAULT 0,
                deleted_at    TEXT NULL
            );
        ');

        $this->repo = new ProgettiRepository();

        // Dati di base
        $roleId = $this->insertRow('roles', ['name' => 'Project User', 'slug' => 'project-user']);
        $permId = $this->insertRow('permissions', ['slug' => 'progetti.view', 'name' => 'Visualizza Progetti', 'module' => 'Progetti']);
        $this->insertRow('role_permission', ['role_id' => $roleId, 'permission_id' => $permId]);

        $this->insertRow('users', ['name' => 'Mario', 'is_active' => 1, 'deleted_at' => null]);
        $this->insertRow('user_role', ['user_id' => 1, 'role_id' => $roleId]);
        $this->insertRow('projects', ['name' => 'Test Project', 'owner_user_id' => 1]);
        $this->insertRow('project_members', ['project_id' => 1, 'user_id' => 1, 'role' => 'owner', 'hourly_rate_override' => 50.0]);
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function insertTask(int $projectId, string $title, string $status = 'todo'): int
    {
        return $this->insertRow('project_tasks', [
            'project_id' => $projectId,
            'title'      => $title,
            'status'     => $status,
            'priority'   => 'medium',
            'position'   => 0,
        ]);
    }

    // ── Dependencies ─────────────────────────────────────────────────────────

    public function testDependencyDoesNotExistInitially(): void
    {
        $t1 = $this->insertTask(1, 'Task A');
        $t2 = $this->insertTask(1, 'Task B');

        $this->assertFalse($this->repo->dependencyExists($t1, $t2));
    }

    public function testCreateDependencyMakesItExist(): void
    {
        $t1 = $this->insertTask(1, 'Task A');
        $t2 = $this->insertTask(1, 'Task B');

        $this->repo->createDependency($t1, $t2);

        $this->assertTrue($this->repo->dependencyExists($t1, $t2));
    }

    public function testDeleteDependencyRemovesIt(): void
    {
        $t1 = $this->insertTask(1, 'Task A');
        $t2 = $this->insertTask(1, 'Task B');

        $this->repo->createDependency($t1, $t2);
        $this->repo->deleteDependency($t1, $t2);

        $this->assertFalse($this->repo->dependencyExists($t1, $t2));
    }

    public function testGetDependencyEdgesReturnsAllProjectEdges(): void
    {
        $t1 = $this->insertTask(1, 'Task A');
        $t2 = $this->insertTask(1, 'Task B');
        $t3 = $this->insertTask(1, 'Task C');

        $this->repo->createDependency($t1, $t2);
        $this->repo->createDependency($t2, $t3);

        $edges = $this->repo->getDependencyEdges(1);

        $this->assertCount(2, $edges);

        $pairs = array_map(fn ($e) => [(int)$e['predecessor_task_id'], (int)$e['successor_task_id']], $edges);
        $this->assertContains([$t1, $t2], $pairs);
        $this->assertContains([$t2, $t3], $pairs);
    }

    public function testGetDependencyEdgesDoesNotReturnOtherProjectEdges(): void
    {
        $this->insertRow('projects', ['name' => 'Other Project', 'owner_user_id' => 1]);

        $t1 = $this->insertTask(1, 'Project 1 Task');
        $t2 = $this->insertTask(2, 'Project 2 Task');

        $this->repo->createDependency($t1, $t2);

        $edges = $this->repo->getDependencyEdges(1);

        // L'edge attraversa due progetti diversi, quindi non deve essere
        // restituito (entrambi gli INNER JOIN devono matchare lo stesso project_id)
        $this->assertCount(0, $edges);
    }

    // ── countOpenPredecessors ────────────────────────────────────────────────

    public function testCountOpenPredecessorsReturnsZeroWithNoDependencies(): void
    {
        $t1 = $this->insertTask(1, 'Task A');
        $this->assertSame(0, $this->repo->countOpenPredecessors(1, $t1));
    }

    public function testCountOpenPredecessorsReturnsZeroWhenPredecessorIsDone(): void
    {
        $t1 = $this->insertTask(1, 'Task A (done)', 'done');
        $t2 = $this->insertTask(1, 'Task B');

        $this->repo->createDependency($t1, $t2);

        $this->assertSame(0, $this->repo->countOpenPredecessors(1, $t2));
    }

    public function testCountOpenPredecessorsCountsOpenPredecessors(): void
    {
        $t1 = $this->insertTask(1, 'Task A (todo)', 'todo');
        $t2 = $this->insertTask(1, 'Task B (in_progress)', 'in_progress');
        $t3 = $this->insertTask(1, 'Task C');

        $this->repo->createDependency($t1, $t3);
        $this->repo->createDependency($t2, $t3);

        $this->assertSame(2, $this->repo->countOpenPredecessors(1, $t3));
    }

    public function testCountOpenPredecessorsIgnoresSoftDeletedTasks(): void
    {
        $t1 = $this->insertTask(1, 'Task A (soft-deleted)', 'todo');
        $t2 = $this->insertTask(1, 'Task B');

        $this->repo->createDependency($t1, $t2);

        // Soft-delete t1
        $this->pdo->prepare('UPDATE project_tasks SET deleted_at = datetime("now") WHERE id = ?')
                  ->execute([$t1]);

        $this->assertSame(0, $this->repo->countOpenPredecessors(1, $t2));
    }

    public function testGetMembersReturnsProjectMembershipWithNames(): void
    {
        $this->insertRow('users', ['name' => 'Luigi']);
        $this->repo->addProjectMember(1, 2, 'member', 75.5);

        $members = $this->repo->getMembers(1);

        $this->assertCount(2, $members);
        $names = array_column($members, 'name');
        $this->assertContains('Mario', $names);
        $this->assertContains('Luigi', $names);
    }

    public function testGetAvailableUsersForProjectExcludesExistingMembers(): void
    {
        $roleId = 1;

        $this->insertRow('users', ['name' => 'Luigi', 'is_active' => 1, 'deleted_at' => null]);
        $this->insertRow('user_role', ['user_id' => 2, 'role_id' => $roleId]);

        $this->insertRow('users', ['name' => 'Anna', 'is_active' => 1, 'deleted_at' => null]);
        $this->insertRow('user_role', ['user_id' => 3, 'role_id' => $roleId]);

        $this->insertRow('users', ['name' => 'Paolo', 'is_active' => 0, 'deleted_at' => null]);
        $this->insertRow('user_role', ['user_id' => 4, 'role_id' => $roleId]);

        $this->insertRow('users', ['name' => 'Sara', 'is_active' => 1, 'deleted_at' => null]);
        $this->repo->addProjectMember(1, 2, 'member', null);

        $available = $this->repo->getAvailableUsersForProject(1);

        $this->assertCount(1, $available);
        $this->assertSame('Anna', $available[0]['name']);
    }

    public function testUpdateAndRemoveProjectMember(): void
    {
        $this->insertRow('users', ['name' => 'Luigi']);
        $this->repo->addProjectMember(1, 2, 'viewer', null);

        $this->repo->updateProjectMember(1, 2, 'member', 60.0);
        $member = $this->repo->findProjectMember(1, 2);

        $this->assertSame('member', $member['role']);
        $this->assertSame(60.0, (float) $member['hourly_rate_override']);

        $this->repo->removeProjectMember(1, 2);
        $this->assertNull($this->repo->findProjectMember(1, 2));
    }

    // ── createTimesheet / deleteTimesheet ────────────────────────────────────

    public function testCreateTimesheetInsertsRow(): void
    {
        $taskId = $this->insertTask(1, 'Task A');

        $id = $this->repo->createTimesheet([
            'project_id' => 1,
            'task_id'    => $taskId,
            'user_id'    => 1,
            'work_date'  => '2026-03-25',
            'hours'      => 4.0,
            'note'       => 'Prima sessione',
        ]);

        $this->assertGreaterThan(0, $id);

        $row = $this->pdo
            ->query("SELECT * FROM project_timesheets WHERE id = {$id}")
            ->fetch();

        $this->assertSame(4.0, (float) $row['hours']);
        $this->assertSame('Prima sessione', $row['note']);
    }

    public function testDeleteTimesheetRemovesOwnRow(): void
    {
        $taskId = $this->insertTask(1, 'Task A');

        $id = $this->repo->createTimesheet([
            'project_id' => 1,
            'task_id'    => $taskId,
            'user_id'    => 1,
            'work_date'  => '2026-03-25',
            'hours'      => 2.0,
            'note'       => null,
        ]);

        $result = $this->repo->deleteTimesheet(1, $id, 1);

        $this->assertTrue($result);

        $count = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM project_timesheets WHERE id = {$id}")
            ->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testDeleteTimesheetDoesNotRemoveOtherUsersRow(): void
    {
        $taskId = $this->insertTask(1, 'Task A');

        $id = $this->repo->createTimesheet([
            'project_id' => 1,
            'task_id'    => $taskId,
            'user_id'    => 1,
            'work_date'  => '2026-03-25',
            'hours'      => 3.0,
            'note'       => null,
        ]);

        // Utente 2 NON riesce a cancellare la riga dell'utente 1
        $result = $this->repo->deleteTimesheet(1, $id, 2);

        // Il metodo ritorna "true" perché lo statement è eseguito senza errore,
        // ma le righe affected sono 0; verifichiamo che il record sia ancora presente
        $count = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM project_timesheets WHERE id = {$id}")
            ->fetchColumn();
        $this->assertSame(1, $count);
    }

    // ── updateProgressCache / updateBudgetCache ──────────────────────────────

    public function testUpdateProgressCacheComputesCorrectly(): void
    {
        $this->insertTask(1, 'T1 done', 'done');
        $this->insertTask(1, 'T2 done', 'done');
        $this->insertTask(1, 'T3 todo', 'todo');
        $this->insertTask(1, 'T4 todo', 'todo');

        $this->repo->updateProgressCache(1);

        $cached = (float) $this->pdo
            ->query('SELECT progress_cached FROM projects WHERE id = 1')
            ->fetchColumn();

        $this->assertEqualsWithDelta(50.0, $cached, 0.01);
    }

    public function testUpdateProgressCacheIsZeroWithNoTasks(): void
    {
        $this->repo->updateProgressCache(1);

        $cached = (float) $this->pdo
            ->query('SELECT progress_cached FROM projects WHERE id = 1')
            ->fetchColumn();

        $this->assertEqualsWithDelta(0.0, $cached, 0.01);
    }

    public function testUpdateBudgetCacheReflectsTimesheetHours(): void
    {
        $taskId = $this->insertTask(1, 'Task A');

        // Utente 1 ha hourly_rate_override = 50.00 (inserito in setUp)
        $this->repo->createTimesheet([
            'project_id' => 1,
            'task_id'    => $taskId,
            'user_id'    => 1,
            'work_date'  => '2026-03-25',
            'hours'      => 8.0,
            'note'       => null,
        ]);

        $this->repo->updateBudgetCache(1);

        $cached = (float) $this->pdo
            ->query('SELECT budget_actual_cached FROM projects WHERE id = 1')
            ->fetchColumn();

        // 8h × €50 = €400
        $this->assertEqualsWithDelta(400.0, $cached, 0.01);
    }
}
