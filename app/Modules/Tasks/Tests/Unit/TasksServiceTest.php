<?php

namespace App\Modules\Tasks\Tests\Unit;

use App\Modules\Tasks\Services\TasksService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TasksService.
 * Testa la logica pura che non richiede accesso al database.
 */
class TasksServiceTest extends TestCase
{
    private TasksService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Anonymous subclass per bypassare il costruttore (che usa DI container)
        $this->service = new class () extends TasksService {
            public function __construct()
            {
                // Skip parent constructor — no DI container needed
            }
        };
    }

    // ── getStatuses ──────────────────────────────────────────────────

    public function testGetStatusesReturnsAllFiveStatuses(): void
    {
        $statuses = TasksService::getStatuses();

        $this->assertCount(5, $statuses);
        $this->assertArrayHasKey('backlog', $statuses);
        $this->assertArrayHasKey('todo', $statuses);
        $this->assertArrayHasKey('in_progress', $statuses);
        $this->assertArrayHasKey('review', $statuses);
        $this->assertArrayHasKey('done', $statuses);
    }

    public function testEachStatusHasLabelColorAndIcon(): void
    {
        foreach (TasksService::getStatuses() as $key => $status) {
            $this->assertArrayHasKey('label', $status, "Status '{$key}' manca 'label'");
            $this->assertArrayHasKey('color', $status, "Status '{$key}' manca 'color'");
            $this->assertArrayHasKey('icon', $status, "Status '{$key}' manca 'icon'");
            $this->assertNotEmpty($status['label'], "Status '{$key}' ha label vuoto");
        }
    }

    // ── getPriorities ────────────────────────────────────────────────

    public function testGetPrioritiesReturnsAllFourPriorities(): void
    {
        $priorities = TasksService::getPriorities();

        $this->assertCount(4, $priorities);
        $this->assertArrayHasKey('low', $priorities);
        $this->assertArrayHasKey('medium', $priorities);
        $this->assertArrayHasKey('high', $priorities);
        $this->assertArrayHasKey('urgent', $priorities);
    }

    public function testEachPriorityHasLabelColorAndIcon(): void
    {
        foreach (TasksService::getPriorities() as $key => $priority) {
            $this->assertArrayHasKey('label', $priority, "Priority '{$key}' manca 'label'");
            $this->assertArrayHasKey('color', $priority, "Priority '{$key}' manca 'color'");
            $this->assertArrayHasKey('icon', $priority, "Priority '{$key}' manca 'icon'");
            $this->assertNotEmpty($priority['label'], "Priority '{$key}' ha label vuoto");
        }
    }

    // ── Status validation (logic used in moveTask) ───────────────────

    public function testAllStatusKeysAreValid(): void
    {
        $validKeys = array_keys(TasksService::getStatuses());

        // Ogni chiave deve essere una stringa snake_case non vuota
        foreach ($validKeys as $key) {
            $this->assertMatchesRegularExpression(
                '/^[a-z_]+$/',
                $key,
                "Status key '{$key}' non è snake_case valido"
            );
        }
    }

    public function testDoneStatusExists(): void
    {
        // 'done' è critico: usato per completedAt logic in update() e moveTask()
        $statuses = TasksService::getStatuses();
        $this->assertArrayHasKey('done', $statuses);
        $this->assertSame('success', $statuses['done']['color']);
    }

    // ── Priority ordering (urgente deve essere ultimo/massimo) ───────

    public function testUrgentPriorityHasDangerColor(): void
    {
        $priorities = TasksService::getPriorities();
        $this->assertSame('danger', $priorities['urgent']['color']);
    }

    public function testLowPriorityHasSecondaryColor(): void
    {
        $priorities = TasksService::getPriorities();
        $this->assertSame('secondary', $priorities['low']['color']);
    }

    // ── search: empty query returns empty array ──────────────────────

    public function testSearchWithEmptyQueryReturnsEmpty(): void
    {
        // search() ha un early return se query === '' — testabile senza DB
        $result = $this->service->search(1, '');
        $this->assertSame([], $result);
    }
}
