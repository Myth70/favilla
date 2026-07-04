<?php

namespace App\Modules\Scheduler\Tests\Unit;

use App\Modules\Scheduler\Repositories\SchedulerRepository;
use Tests\ModuleTestCase;

class SchedulerRepositoryTest extends ModuleTestCase
{
    private SchedulerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE scheduler_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL,
                name TEXT NOT NULL,
                command TEXT NOT NULL,
                args_json TEXT DEFAULT NULL,
                interval_minutes INTEGER NOT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                last_status TEXT DEFAULT NULL,
                last_output TEXT DEFAULT NULL,
                last_output_file TEXT DEFAULT NULL,
                last_duration_ms INTEGER DEFAULT NULL,
                last_run_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT NULL,
                updated_at TEXT DEFAULT NULL
            );
            CREATE TABLE scheduler_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_slug TEXT NOT NULL,
                started_at TEXT NOT NULL,
                finished_at TEXT NOT NULL,
                status TEXT NOT NULL,
                output TEXT DEFAULT NULL,
                output_file TEXT DEFAULT NULL,
                duration_ms INTEGER NOT NULL
            );
        ');

        $this->repo = new SchedulerRepository();
    }

    public function testUpdateReturnsBoolAndPersistsChanges(): void
    {
        $jobId = $this->repo->create([
            'slug' => 'job-test',
            'name' => 'Job Test',
            'command' => 'scheduler:run',
            'args_json' => null,
            'interval_minutes' => 5,
            'enabled' => 1,
        ]);

        $result = $this->repo->update($jobId, [
            'name' => 'Job Aggiornato',
            'slug' => 'job-updated',
            'command' => 'backup:run',
            'args_json' => '["--force"]',
            'interval_minutes' => 15,
            'enabled' => 0,
        ]);

        $job = $this->repo->find($jobId);

        $this->assertTrue($result);
        $this->assertSame('Job Aggiornato', $job['name']);
        $this->assertSame('job-updated', $job['slug']);
        $this->assertSame('backup:run', $job['command']);
        $this->assertSame(0, (int) $job['enabled']);
    }

    public function testDeleteReturnsBoolAndRemovesRelatedLogs(): void
    {
        $jobId = $this->repo->create([
            'slug' => 'job-delete',
            'name' => 'Job Delete',
            'command' => 'scheduler:run',
            'args_json' => null,
            'interval_minutes' => 10,
            'enabled' => 1,
        ]);

        $this->repo->log('job-delete', '2026-04-01 10:00:00', '2026-04-01 10:00:01', 'success', 'ok', 1000);

        $result = $this->repo->delete($jobId);
        $remainingLogs = (int) $this->pdo->query('SELECT COUNT(*) FROM scheduler_log')->fetchColumn();

        $this->assertTrue($result);
        $this->assertNull($this->repo->find($jobId));
        $this->assertSame(0, $remainingLogs);
    }
}
