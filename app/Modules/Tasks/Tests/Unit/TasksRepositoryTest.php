<?php

namespace App\Modules\Tasks\Tests\Unit;

use App\Modules\Tasks\Repositories\TasksRepository;
use Tests\ModuleTestCase;

class TasksRepositoryTest extends ModuleTestCase
{
    private TasksRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NULL,
                status TEXT NOT NULL,
                priority TEXT NOT NULL,
                due_date TEXT NULL,
                due_time TEXT NULL,
                color TEXT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                completed_at TEXT NULL,
                calendar_event_id INTEGER NULL,
                user_id INTEGER NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE task_checklist (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                text TEXT NOT NULL,
                is_done INTEGER NOT NULL DEFAULT 0,
                position INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE task_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                color TEXT NOT NULL,
                user_id INTEGER NOT NULL
            );
            CREATE TABLE task_tag_map (
                task_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL
            );
        ');

        $this->repo = new TasksRepository();
    }

    public function testGetBoardForUserGroupsTasksAndLoadsChecklistAndTags(): void
    {
        $taskTodo = $this->insertRow('tasks', [
            'title' => 'Todo task',
            'description' => 'desc',
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 1,
            'user_id' => 7,
            'created_at' => '2026-04-24 10:00:00',
            'updated_at' => '2026-04-24 10:00:00',
            'deleted_at' => null,
        ]);

        $taskDone = $this->insertRow('tasks', [
            'title' => 'Done task',
            'description' => null,
            'status' => 'done',
            'priority' => 'high',
            'position' => 2,
            'user_id' => 7,
            'created_at' => '2026-04-24 10:05:00',
            'updated_at' => '2026-04-24 10:05:00',
            'deleted_at' => null,
        ]);

        $this->insertRow('task_checklist', ['task_id' => $taskTodo, 'text' => 'A', 'is_done' => 1, 'position' => 1]);
        $this->insertRow('task_checklist', ['task_id' => $taskTodo, 'text' => 'B', 'is_done' => 0, 'position' => 2]);

        $tagId = $this->insertRow('task_tags', ['name' => 'Urgente', 'color' => '#ff0000', 'user_id' => 7]);
        $this->insertRow('task_tag_map', ['task_id' => $taskTodo, 'tag_id' => $tagId]);

        $board = $this->repo->getBoardForUser(7);

        $this->assertCount(1, $board['todo']);
        $this->assertCount(1, $board['done']);
        $this->assertSame(2, (int) $board['todo'][0]['checklist_total']);
        $this->assertSame(1, (int) $board['todo'][0]['checklist_done']);
        $this->assertCount(1, $board['todo'][0]['tags']);
        $this->assertSame('Urgente', $board['todo'][0]['tags'][0]['name']);

        $this->assertSame($taskDone, (int) $board['done'][0]['id']);
    }

    public function testFindForUserReturnsChecklistAndTags(): void
    {
        $taskId = $this->insertRow('tasks', [
            'title' => 'Task dettagli',
            'description' => 'Dettaglio',
            'status' => 'todo',
            'priority' => 'low',
            'position' => 1,
            'user_id' => 12,
            'created_at' => '2026-04-24 10:00:00',
            'updated_at' => '2026-04-24 10:00:00',
            'deleted_at' => null,
        ]);

        $this->insertRow('task_checklist', ['task_id' => $taskId, 'text' => 'Step 1', 'is_done' => 0, 'position' => 1]);
        $tagId = $this->insertRow('task_tags', ['name' => 'Cliente', 'color' => '#00ff00', 'user_id' => 12]);
        $this->insertRow('task_tag_map', ['task_id' => $taskId, 'tag_id' => $tagId]);

        $row = $this->repo->findForUser($taskId, 12);

        $this->assertNotNull($row);
        $this->assertCount(1, $row['checklist']);
        $this->assertCount(1, $row['tags']);
        $this->assertSame('Cliente', $row['tags'][0]['name']);
        $this->assertNull($this->repo->findForUser($taskId, 99));
    }

    public function testListPaginatedAppliesFiltersAndSortingWhitelist(): void
    {
        $this->insertRow('tasks', [
            'title' => 'B item',
            'description' => 'x',
            'status' => 'todo',
            'priority' => 'high',
            'position' => 2,
            'user_id' => 5,
            'created_at' => '2026-04-24 10:00:00',
            'updated_at' => '2026-04-24 10:00:00',
            'deleted_at' => null,
        ]);
        $this->insertRow('tasks', [
            'title' => 'A item',
            'description' => 'x',
            'status' => 'todo',
            'priority' => 'high',
            'position' => 1,
            'user_id' => 5,
            'created_at' => '2026-04-24 10:00:01',
            'updated_at' => '2026-04-24 10:00:01',
            'deleted_at' => null,
        ]);

        $result = $this->repo->listPaginated(5, [
            'status' => 'todo',
            'priority' => 'high',
            'sort' => 'title',
            'dir' => 'asc',
            'page' => 1,
        ]);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['data']);
        $this->assertSame('A item', $result['data'][0]['title']);
        $this->assertSame('B item', $result['data'][1]['title']);
    }
}
