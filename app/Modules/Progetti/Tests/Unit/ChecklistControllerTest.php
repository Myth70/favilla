<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Tests\Unit;

use App\Modules\Progetti\Controllers\ChecklistController;
use Tests\ControllerTestCase;

class ChecklistControllerTest extends ControllerTestCase
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
                deleted_at           TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_tasks (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id       INTEGER NOT NULL,
                title            TEXT NOT NULL,
                assigned_user_id INTEGER NULL,
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
                PRIMARY KEY (project_id, user_id)
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
                created_by INTEGER NULL,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS project_checklist_templates (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                created_by INTEGER NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_checklist_template_items (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                template_id INTEGER NOT NULL,
                label       TEXT NOT NULL,
                position    INTEGER NOT NULL DEFAULT 0
            );
        ');

        $this->insertRow('users', ['name' => 'Mario']);
        $this->insertRow('projects', ['name' => 'Progetto Test', 'owner_user_id' => 1]);
        $this->insertRow('project_members', ['project_id' => 1, 'user_id' => 1, 'role' => 'owner']);

        // Granted progetti.edit so canManageChecklist()/canCheckItem() pass regardless of assignment.
        $this->actingAs(1, ['progetti.edit']);
    }

    private function createTask(): int
    {
        return $this->insertRow('project_tasks', ['project_id' => 1, 'title' => 'Task', 'status' => 'todo']);
    }

    public function testGetChecklistReturnsItemsForTask(): void
    {
        $taskId = $this->createTask();
        $this->insertRow('project_task_checklist_items', ['task_id' => $taskId, 'label' => 'Voce 1', 'position' => 0]);

        $result = $this->dispatch(ChecklistController::class, 'getChecklist', ['1', (string) $taskId]);

        $this->assertTrue($result->isJson());
        $payload = $result->jsonPayload();
        $this->assertTrue($payload['ok']);
        $this->assertCount(1, $payload['items']);
    }

    public function testGetChecklistReturns403WhenProjectNotFound(): void
    {
        $result = $this->dispatch(ChecklistController::class, 'getChecklist', ['999', '1']);

        $this->assertTrue($result->isJson());
        $this->assertSame(403, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['ok']);
    }

    public function testStoreItemAddsChecklistItem(): void
    {
        $taskId = $this->createTask();

        $result = $this->withPost(['label' => 'Nuova voce'])
            ->dispatch(ChecklistController::class, 'storeItem', ['1', (string) $taskId]);

        $this->assertTrue($result->isJson());
        $this->assertTrue($result->jsonPayload()['ok']);
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM project_task_checklist_items WHERE task_id = {$taskId}")->fetchColumn()
        );
    }

    public function testStoreItemRejectsEmptyLabel(): void
    {
        $taskId = $this->createTask();

        $result = $this->withPost(['label' => '  '])
            ->dispatch(ChecklistController::class, 'storeItem', ['1', (string) $taskId]);

        $this->assertTrue($result->isJson());
        $this->assertSame(422, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['ok']);
    }

    public function testCheckItemMarksItemDone(): void
    {
        $taskId = $this->createTask();
        $itemId = $this->insertRow('project_task_checklist_items', ['task_id' => $taskId, 'label' => 'Voce', 'position' => 0]);

        // Ultima voce rimasta: ChecklistService::checkItem() richiede un commento.
        $result = $this->withPost(['comment' => 'Fatto'])
            ->dispatch(ChecklistController::class, 'checkItem', ['1', (string) $taskId, (string) $itemId]);

        $this->assertTrue($result->isJson());
        $this->assertTrue($result->jsonPayload()['ok']);
        $this->assertTrue((bool) $result->jsonPayload()['allDone']);
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT is_done FROM project_task_checklist_items WHERE id = {$itemId}")->fetchColumn()
        );
    }

    public function testDestroyItemRemovesItem(): void
    {
        $taskId = $this->createTask();
        $itemId = $this->insertRow('project_task_checklist_items', ['task_id' => $taskId, 'label' => 'Voce', 'position' => 0]);

        $result = $this->dispatch(ChecklistController::class, 'destroyItem', ['1', (string) $taskId, (string) $itemId]);

        $this->assertTrue($result->isJson());
        $this->assertTrue($result->jsonPayload()['ok']);
        $this->assertSame(
            0,
            (int) $this->pdo->query("SELECT COUNT(*) FROM project_task_checklist_items WHERE id = {$itemId}")->fetchColumn()
        );
    }

    public function testListTemplatesRendersTemplatesPage(): void
    {
        $this->insertRow('project_checklist_templates', ['name' => 'Onboarding cliente', 'created_by' => 1]);

        $result = $this->dispatch(ChecklistController::class, 'listTemplates');

        $this->assertTrue($result->didRender());
        $this->assertSame('Progetti/Views/checklist_templates', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['templates']);
    }

    public function testStoreTemplateCreatesTemplateAndReturnsJson(): void
    {
        $result = $this->withPost(['name' => 'Nuovo Template', 'labels' => ['Voce A', 'Voce B']])
            ->dispatch(ChecklistController::class, 'storeTemplate');

        $this->assertTrue($result->isJson());
        $this->assertTrue($result->jsonPayload()['ok']);
        $this->assertSame(
            1,
            (int) $this->pdo->query('SELECT COUNT(*) FROM project_checklist_templates')->fetchColumn()
        );
        $this->assertSame(
            2,
            (int) $this->pdo->query('SELECT COUNT(*) FROM project_checklist_template_items')->fetchColumn()
        );
    }

    public function testDestroyTemplateRemovesTemplate(): void
    {
        $tplId = $this->insertRow('project_checklist_templates', ['name' => 'Da eliminare', 'created_by' => 1]);

        $result = $this->dispatch(ChecklistController::class, 'destroyTemplate', [(string) $tplId]);

        $this->assertTrue($result->isJson());
        $this->assertTrue($result->jsonPayload()['ok']);
        $this->assertNotNull(
            $this->pdo->query("SELECT deleted_at FROM project_checklist_templates WHERE id = {$tplId}")->fetchColumn()
        );
    }
}
