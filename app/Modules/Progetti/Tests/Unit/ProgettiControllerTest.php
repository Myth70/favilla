<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Tests\Unit;

use App\Modules\Progetti\Controllers\ProgettiController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for ProgettiController's main routes.
 *
 * show()/kanban()/gantt()/timesheet()/report() all funnel through
 * ProgettiService::getDashboardKpi(), whose repository query uses MySQL-only
 * INTERVAL syntax (ProgettiRepository::getTimesheetTrend()) that SQLite cannot
 * even parse. Per the SQLite/MariaDB portability gotcha, those actions are
 * exercised here only via their DB-free guard branch (project not found);
 * the happy path is covered by manual QA against MariaDB (Gate 3).
 */
class ProgettiControllerTest extends ControllerTestCase
{
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
                code                 TEXT NULL,
                description          TEXT NULL,
                client_name          TEXT NULL,
                owner_user_id        INTEGER NOT NULL DEFAULT 1,
                status               TEXT NOT NULL DEFAULT "planning",
                start_date           TEXT NULL,
                end_date             TEXT NULL,
                estimated_hours      REAL NOT NULL DEFAULT 0,
                budget_planned       REAL NOT NULL DEFAULT 0,
                budget_actual_cached REAL NOT NULL DEFAULT 0,
                progress_cached      REAL NOT NULL DEFAULT 0,
                teams_conversation_id INTEGER NULL,
                created_by           INTEGER NULL,
                created_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at           TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_milestones (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id        INTEGER NOT NULL,
                name              TEXT NOT NULL,
                description       TEXT NULL,
                due_date          TEXT NULL,
                billable          INTEGER NOT NULL DEFAULT 0,
                status            TEXT NOT NULL DEFAULT "pending",
                calendar_event_id INTEGER NULL,
                created_by        INTEGER NULL,
                deleted_at        TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_tasks (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id        INTEGER NOT NULL,
                milestone_id      INTEGER NULL,
                title             TEXT NOT NULL,
                description       TEXT NULL,
                assigned_user_id  INTEGER NULL,
                priority          TEXT NOT NULL DEFAULT "medium",
                status            TEXT NOT NULL DEFAULT "todo",
                position          INTEGER NOT NULL DEFAULT 0,
                start_date        TEXT NULL,
                due_date          TEXT NULL,
                estimated_hours   REAL NOT NULL DEFAULT 0,
                completed_at      TEXT NULL,
                calendar_event_id INTEGER NULL,
                last_reminded_date TEXT NULL,
                created_by        INTEGER NULL,
                deleted_at        TEXT NULL,
                updated_at        TEXT DEFAULT CURRENT_TIMESTAMP
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
                note       TEXT NULL
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
            CREATE TABLE IF NOT EXISTS project_task_checklist_items (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id    INTEGER NOT NULL,
                label      TEXT NOT NULL,
                position   INTEGER NOT NULL DEFAULT 0,
                is_done    INTEGER NOT NULL DEFAULT 0,
                done_at    TEXT NULL,
                done_by    INTEGER NULL,
                comment    TEXT NULL,
                created_by INTEGER NULL
            );
        ');

        $roleId = $this->insertRow('roles', ['name' => 'Project User', 'slug' => 'project-user']);
        $permId = $this->insertRow('permissions', ['slug' => 'progetti.view', 'name' => 'Visualizza Progetti', 'module' => 'Progetti']);
        $this->insertRow('role_permission', ['role_id' => $roleId, 'permission_id' => $permId]);

        $this->insertRow('users', ['name' => 'Mario']);
        $this->insertRow('user_role', ['user_id' => 1, 'role_id' => $roleId]);
        $this->insertRow('users', ['name' => 'Luigi']);
        $this->insertRow('user_role', ['user_id' => 2, 'role_id' => $roleId]);

        // actingAs() default: owner of every fixture project, no elevated permission needed.
        $this->actingAs(1);
    }

    private function createProject(array $overrides = []): int
    {
        $id = $this->insertRow('projects', array_merge([
            'name' => 'Sito Web Aziendale',
            'owner_user_id' => 1,
            'status' => 'active',
        ], $overrides));

        $this->insertRow('project_members', ['project_id' => $id, 'user_id' => 1, 'role' => 'owner']);

        return $id;
    }

    // ── index ────────────────────────────────────────────────────────────────

    public function testIndexRendersProjectListForUser(): void
    {
        $this->createProject(['name' => 'Progetto A']);
        $this->createProject(['name' => 'Progetto B']);

        $result = $this->withGet([])->dispatch(ProgettiController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Progetti/Views/index', $result->renderedTemplate());
        $this->assertCount(2, $result->renderedData()['items']);
    }

    // ── create / store ───────────────────────────────────────────────────────

    public function testCreateRendersFormWithoutTouchingDatabase(): void
    {
        $result = $this->dispatch(ProgettiController::class, 'create');

        $this->assertTrue($result->didRender());
        $this->assertSame('Progetti/Views/form', $result->renderedTemplate());
    }

    public function testStoreRejectsEmptyNameAndRedirectsToCreate(): void
    {
        $result = $this->withPost(['name' => '  ', 'status' => 'planning'])
            ->dispatch(ProgettiController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/projects.create', $result->redirectUrl());
        $this->assertArrayHasKey('name', $_SESSION['_errors'] ?? []);
    }

    public function testStoreCreatesProjectAndRedirectsToShow(): void
    {
        $result = $this->withPost([
            'name' => 'Nuovo Progetto',
            'status' => 'planning',
            'estimated_hours' => '10',
            'budget_planned' => '1000',
        ])->dispatch(ProgettiController::class, 'store');

        $this->assertTrue($result->isRedirect());
        $this->assertStringStartsWith('/projects.show', $result->redirectUrl());
        $this->assertSame('Nuovo Progetto', $this->pdo->query('SELECT name FROM projects ORDER BY id DESC LIMIT 1')->fetchColumn());
    }

    // ── show ─────────────────────────────────────────────────────────────────

    public function testShowRedirectsToIndexWhenProjectNotFound(): void
    {
        $result = $this->dispatch(ProgettiController::class, 'show', ['999']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/projects.index', $result->redirectUrl());
    }

    // ── edit / update ────────────────────────────────────────────────────────

    public function testEditRendersFormForExistingProject(): void
    {
        $id = $this->createProject();

        $result = $this->dispatch(ProgettiController::class, 'edit', [(string) $id]);

        $this->assertTrue($result->didRender());
        $this->assertSame('Progetti/Views/form', $result->renderedTemplate());
        $this->assertTrue($result->renderedData()['isEdit']);
    }

    public function testEditRedirectsWhenProjectNotFound(): void
    {
        $result = $this->dispatch(ProgettiController::class, 'edit', ['999']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/projects.index', $result->redirectUrl());
    }

    public function testUpdateRejectsInvalidDataAndRedirectsToEdit(): void
    {
        $id = $this->createProject();

        $result = $this->withPost(['name' => '', 'status' => 'planning'])
            ->dispatch(ProgettiController::class, 'update', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertStringStartsWith('/projects.edit', $result->redirectUrl());
        $this->assertArrayHasKey('name', $_SESSION['_errors'] ?? []);
    }

    public function testUpdateUpdatesProjectAndRedirectsToShow(): void
    {
        $id = $this->createProject();

        $result = $this->withPost([
            'name' => 'Progetto Rinominato',
            'status' => 'active',
        ])->dispatch(ProgettiController::class, 'update', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertStringStartsWith('/projects.show', $result->redirectUrl());
        $this->assertSame('Progetto Rinominato', $this->pdo->query("SELECT name FROM projects WHERE id = {$id}")->fetchColumn());
    }

    // ── destroy ──────────────────────────────────────────────────────────────

    public function testDestroyDeletesProjectAndRedirectsToIndex(): void
    {
        $id = $this->createProject();

        $result = $this->dispatch(ProgettiController::class, 'destroy', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/projects.index', $result->redirectUrl());
        $this->assertNotNull($this->pdo->query("SELECT deleted_at FROM projects WHERE id = {$id}")->fetchColumn());
    }

    // ── myTasks ──────────────────────────────────────────────────────────────

    public function testMyTasksRendersAssignedTasks(): void
    {
        $projectId = $this->createProject();
        $this->insertRow('project_tasks', [
            'project_id' => $projectId,
            'title' => 'Task assegnato',
            'assigned_user_id' => 1,
            'status' => 'todo',
        ]);

        $result = $this->withGet([])->dispatch(ProgettiController::class, 'myTasks');

        $this->assertTrue($result->didRender());
        $this->assertSame('Progetti/Views/my_tasks', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['tasks']);
    }

    // ── kanban / gantt / timesheet / report guard branches ──────────────────
    // Happy paths call ProgettiService::getDashboardKpi(), which is not
    // SQLite-portable (see class docblock) — covered manually against MariaDB.

    public function testKanbanReturns404WhenProjectNotFound(): void
    {
        $result = $this->dispatch(ProgettiController::class, 'kanban', ['999']);

        $this->assertSame(404, http_response_code());
        $this->assertNotSame('', $result->echoed);
    }

    public function testGanttReturns404WhenProjectNotFound(): void
    {
        $this->dispatch(ProgettiController::class, 'gantt', ['999']);

        $this->assertSame(404, http_response_code());
    }

    public function testTimesheetReturns404WhenProjectNotFound(): void
    {
        $this->dispatch(ProgettiController::class, 'timesheet', ['999']);

        $this->assertSame(404, http_response_code());
    }

    public function testReportRedirectsWhenProjectNotFound(): void
    {
        $result = $this->dispatch(ProgettiController::class, 'report', ['999']);

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/projects.index', $result->redirectUrl());
    }

    // ── members / milestones / tasks (representative sub-resources) ─────────

    public function testStoreMemberAddsMemberAndRedirectsToShow(): void
    {
        $id = $this->createProject();

        $result = $this->withPost(['user_id' => '2', 'role' => 'member'])
            ->dispatch(ProgettiController::class, 'storeMember', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertStringStartsWith('/projects.show', $result->redirectUrl());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM project_members WHERE project_id = {$id} AND user_id = 2")->fetchColumn()
        );
    }

    public function testStoreMilestoneCreatesMilestoneAndRedirects(): void
    {
        $id = $this->createProject();

        $result = $this->withPost(['name' => 'Milestone 1', 'status' => 'pending'])
            ->dispatch(ProgettiController::class, 'storeMilestone', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM project_milestones WHERE project_id = {$id}")->fetchColumn()
        );
    }

    public function testStoreTaskCreatesTaskAndRedirects(): void
    {
        $id = $this->createProject();

        $result = $this->withPost(['title' => 'Nuovo Task', 'priority' => 'medium', 'status' => 'todo'])
            ->dispatch(ProgettiController::class, 'storeTask', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM project_tasks WHERE project_id = {$id}")->fetchColumn()
        );
    }

    public function testQuickStatusTaskUpdatesStatusAndReturnsJson(): void
    {
        $id = $this->createProject();
        $taskId = $this->insertRow('project_tasks', [
            'project_id' => $id,
            'title' => 'Task',
            'status' => 'todo',
            'assigned_user_id' => 1,
        ]);

        $result = $this->withPost(['status' => 'in_progress'])
            ->dispatch(ProgettiController::class, 'quickStatusTask', [(string) $id, (string) $taskId]);

        $this->assertTrue($result->isJson());
        $this->assertTrue($result->jsonPayload()['ok'] ?? false);
        $this->assertSame('in_progress', $this->pdo->query("SELECT status FROM project_tasks WHERE id = {$taskId}")->fetchColumn());
    }
}
