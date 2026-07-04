<?php

namespace App\Modules\Scheduler\Tests\Unit;

use App\Modules\Scheduler\Repositories\SchedulerRepository;
use App\Modules\Scheduler\Services\SchedulerService;
use Tests\ModuleTestCase;

class SchedulerServiceTest extends ModuleTestCase
{
    private SchedulerService $service;
    private SchedulerRepository $repo;
    private string $logDir;
    /** @var string[] */
    private array $createdFiles = [];

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
                retry_count INTEGER NOT NULL DEFAULT 0,
                max_retries INTEGER NOT NULL DEFAULT 0,
                next_retry_at TEXT DEFAULT NULL,
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
        $this->service = new SchedulerService($this->repo);
        $this->logDir = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'scheduler';

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testCreateJobRejectsCommandOutsideAllowlist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non è consentito');

        $this->service->createJob([
            'slug' => 'forbidden.command',
            'name' => 'Comando non consentito',
            'command' => 'dangerous:command',
            'interval_minutes' => 5,
            'args_json' => null,
            'enabled' => 1,
        ]);
    }

    public function testCreateJobRejectsDangerousArguments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('operatori shell non consentiti');

        $this->service->createJob([
            'slug' => 'dangerous.args',
            'name' => 'Argomenti pericolosi',
            'command' => 'cleanup',
            'interval_minutes' => 5,
            'args_json' => '["--days=30", "&& whoami"]',
            'enabled' => 1,
        ]);
    }

    public function testRunJobWritesExecutionStateAndLogForRejectedPersistedCommand(): void
    {
        $jobId = $this->repo->create([
            'slug' => 'retry.job',
            'name' => 'Retry Job',
            'command' => 'nonexistent:command',
            'args_json' => null,
            'interval_minutes' => 1,
            'enabled' => 1,
        ]);

        $this->pdo->prepare('UPDATE scheduler_jobs SET max_retries = 2 WHERE id = ?')->execute([$jobId]);

        $job = $this->repo->find($jobId);
        $result = $this->service->runJob($job);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('non è consentito', $result['output']);

        $updated = $this->repo->find($jobId);
        $this->assertSame($result['status'], $updated['last_status']);
        $this->assertGreaterThanOrEqual(0, (int) $updated['last_duration_ms']);

        $logCount = (int) $this->pdo->query('SELECT COUNT(*) FROM scheduler_log')->fetchColumn();
        $this->assertSame(1, $logCount);
    }

    public function testRunJobFailsWhenPersistedArgumentsAreUnsafe(): void
    {
        $jobId = $this->repo->create([
            'slug' => 'unsafe.persisted.job',
            'name' => 'Unsafe Persisted Job',
            'command' => 'cleanup',
            'args_json' => '["--days=30", "bad;arg"]',
            'interval_minutes' => 1,
            'enabled' => 1,
        ]);

        $job = $this->repo->find($jobId);
        $result = $this->service->runJob($job);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('operatori shell non consentiti', $result['output']);
    }

    public function testRunJobContinuesWhenOutputFileArchivingFails(): void
    {
        $jobId = $this->repo->create([
            'slug' => 'archiving.failure.job',
            'name' => 'Archiving Failure Job',
            'command' => 'cleanup',
            'args_json' => null,
            'interval_minutes' => 1,
            'enabled' => 1,
        ]);

        $job = $this->repo->find($jobId);

        $service = new class ($this->repo) extends SchedulerService {
            public string $mockOutput = '';
            public int $mockExitCode = 0;

            protected function exec(string $command, array $args): array
            {
                return [$this->mockOutput, $this->mockExitCode];
            }

            protected function writeOutputToFile(string $slug, string $startedAt, string $output): string
            {
                throw new \RuntimeException('disk full');
            }
        };

        $service->mockOutput = str_repeat('A', 9000);
        $service->mockExitCode = 0;

        $result = $service->runJob($job);
        $updated = $this->repo->find($jobId);

        $this->assertSame('success', $result['status']);
        $this->assertSame('success', $updated['last_status']);
        $this->assertNull($updated['last_output_file']);
        $this->assertStringContainsString('Archiviazione file output non riuscita', $updated['last_output']);
    }

    public function testDeleteJobRemovesOutputFilesFromFilesystem(): void
    {
        $jobId = $this->repo->create([
            'slug' => 'cleanup.delete.job',
            'name' => 'Cleanup Delete Job',
            'command' => 'scheduler:run',
            'args_json' => null,
            'interval_minutes' => 1,
            'enabled' => 1,
        ]);

        $lastFile = $this->createSchedulerLogFile('test_scheduler_delete_last.log');
        $logFile = $this->createSchedulerLogFile('test_scheduler_delete_log.log');

        $this->pdo->prepare('UPDATE scheduler_jobs SET last_output_file = ? WHERE id = ?')
            ->execute([basename($lastFile), $jobId]);
        $this->repo->log('cleanup.delete.job', '2026-04-01 10:00:00', '2026-04-01 10:00:01', 'success', 'ok', 1000, basename($logFile));

        $this->service->deleteJob($jobId);

        $this->assertFileDoesNotExist($lastFile);
        $this->assertFileDoesNotExist($logFile);
        $this->assertNull($this->repo->find($jobId));
    }

    public function testPruneLogDeletesOnlyUnreferencedOutputFiles(): void
    {
        $jobId = $this->repo->create([
            'slug' => 'cleanup.prune.job',
            'name' => 'Cleanup Prune Job',
            'command' => 'scheduler:run',
            'args_json' => null,
            'interval_minutes' => 1,
            'enabled' => 1,
        ]);

        $deleteFile = $this->createSchedulerLogFile('test_scheduler_prune_delete.log');
        $keepFile = $this->createSchedulerLogFile('test_scheduler_prune_keep.log');

        $this->pdo->prepare('UPDATE scheduler_jobs SET last_output_file = ? WHERE id = ?')
            ->execute([basename($keepFile), $jobId]);

        $this->repo->log('cleanup.prune.job', '2025-01-01 10:00:00', '2025-01-01 10:00:01', 'success', 'old', 1000, basename($keepFile));
        $this->repo->log('orphan.prune.job', '2025-01-01 11:00:00', '2025-01-01 11:00:01', 'success', 'old', 1000, basename($deleteFile));

        $deleted = $this->service->pruneLog(30);

        $this->assertSame(2, $deleted);
        $this->assertFileDoesNotExist($deleteFile);
        $this->assertFileExists($keepFile);
    }

    public function testGetJobsLocalizesDisplayNameRespectingItalianCanonical(): void
    {
        $this->repo->create([
            'slug' => 'my.cleanup',
            'name' => 'Pulizia notturna',     // nome admin-canonico (IT)
            'command' => 'cleanup',           // comando standard → presente nell'overlay
            'args_json' => null,
            'interval_minutes' => 60,
            'enabled' => 1,
        ]);
        $this->repo->create([
            'slug' => 'custom.job',
            'name' => 'Lavoro personalizzato',
            'command' => 'scheduler:run',     // nessuna chiave overlay
            'args_json' => null,
            'interval_minutes' => 60,
            'enabled' => 1,
        ]);

        // Italiano: il nome salvato a DB non viene MAI sovrascritto dall'overlay.
        set_locale('it');
        $it = array_column($this->service->getJobs(), 'display_name', 'slug');
        $this->assertSame('Pulizia notturna', $it['my.cleanup']);
        $this->assertSame('Lavoro personalizzato', $it['custom.job']);

        // Inglese: comando standard → traduzione overlay; comando senza chiave
        // overlay → fallback al nome italiano salvato.
        set_locale('en');
        try {
            $en = array_column($this->service->getJobs(), 'display_name', 'slug');
        } finally {
            set_locale('it');
        }
        $this->assertSame('Stale data cleanup', $en['my.cleanup']);
        $this->assertSame('Lavoro personalizzato', $en['custom.job']);
    }

    private function createSchedulerLogFile(string $filename): string
    {
        $path = $this->logDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, 'test');
        $this->createdFiles[] = $path;

        return $path;
    }
}
