<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Tests\Unit;

use App\Modules\Progetti\Controllers\AdminProgettiController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for AdminProgettiController's main routes.
 */
class AdminProgettiControllerTest extends ControllerTestCase
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
            CREATE TABLE IF NOT EXISTS project_tasks (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id       INTEGER NOT NULL,
                title            TEXT NOT NULL,
                status           TEXT NOT NULL DEFAULT "todo",
                priority         TEXT NOT NULL DEFAULT "medium",
                position         INTEGER NOT NULL DEFAULT 0,
                deleted_at       TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_members (
                project_id           INTEGER NOT NULL,
                user_id              INTEGER NOT NULL,
                role                 TEXT NOT NULL DEFAULT "member",
                hourly_rate_override REAL NULL,
                joined_at            TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (project_id, user_id)
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

        $this->insertRow('users', ['name' => 'Mario']);

        // AdminProgettiController is guarded by progetti.manage_all in routes.php.
        $this->actingAs(1, ['progetti.manage_all', 'progetti.view_all']);
    }

    private function createProject(array $overrides = []): int
    {
        return $this->insertRow('projects', array_merge([
            'name' => 'Progetto Test',
            'owner_user_id' => 1,
            'status' => 'active',
        ], $overrides));
    }

    public function testIndexRendersActiveProjects(): void
    {
        $this->createProject(['name' => 'Attivo']);
        $this->createProject(['name' => 'Cestinato', 'deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->withGet([])->dispatch(AdminProgettiController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Progetti/Views/admin/index', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['items']);
        $this->assertSame('active', $result->renderedData()['filters']['scope']);
    }

    public function testTrashRendersTrashedProjects(): void
    {
        $this->createProject(['name' => 'Attivo']);
        $this->createProject(['name' => 'Cestinato', 'deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->withGet([])->dispatch(AdminProgettiController::class, 'trash');

        $this->assertTrue($result->didRender());
        $this->assertSame('trash', $result->renderedData()['filters']['scope']);
        $this->assertCount(1, $result->renderedData()['items']);
        $this->assertSame('Cestinato', $result->renderedData()['items'][0]['name']);
    }

    public function testTableReturnsPartialWithFilteredItems(): void
    {
        $this->createProject(['name' => 'Sito Web', 'status' => 'active']);
        $this->createProject(['name' => 'App Mobile', 'status' => 'on_hold']);

        $result = $this->withGet(['status' => 'on_hold'])
            ->dispatch(AdminProgettiController::class, 'table');

        $this->assertSame('Progetti/Views/admin/partials/table', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['items']);
        $this->assertSame('App Mobile', $result->renderedData()['items'][0]['name']);
    }

    public function testMoveToTrashSoftDeletesAndRedirects(): void
    {
        $id = $this->createProject();

        $result = $this->dispatch(AdminProgettiController::class, 'moveToTrash', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertNotNull($this->pdo->query("SELECT deleted_at FROM projects WHERE id = {$id}")->fetchColumn());
    }

    public function testRestoreRestoresProjectAndRedirects(): void
    {
        $id = $this->createProject(['deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->dispatch(AdminProgettiController::class, 'restore', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertNull($this->pdo->query("SELECT deleted_at FROM projects WHERE id = {$id}")->fetchColumn());
    }

    public function testPurgeRemovesProjectPermanentlyAndRedirects(): void
    {
        $id = $this->createProject(['deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->dispatch(AdminProgettiController::class, 'purge', [(string) $id]);

        $this->assertTrue($result->isRedirect());
        $this->assertSame(
            0,
            (int) $this->pdo->query("SELECT COUNT(*) FROM projects WHERE id = {$id}")->fetchColumn()
        );
    }
}
