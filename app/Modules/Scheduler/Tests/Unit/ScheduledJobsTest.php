<?php

namespace App\Modules\Scheduler\Tests\Unit;

use App\Modules\Scheduler\Repositories\SchedulerRepository;
use App\Modules\Scheduler\Services\SchedulerService;
use PHPUnit\Framework\TestCase;

/**
 * Verifica il parsing dei job dichiarati da module.json e il merge dei comandi
 * di modulo nella whitelist dello scheduler.
 */
class ScheduledJobsTest extends TestCase
{
    private function service(): SchedulerService
    {
        // Il repository è mockato: createMock disabilita il costruttore di
        // BaseRepository (che aprirebbe un PDO dal container).
        return new SchedulerService($this->createMock(SchedulerRepository::class));
    }

    public function testExtractScheduledJobsNormalizes(): void
    {
        $jobs = $this->service()->extractScheduledJobs([
            'scheduled_jobs' => [
                ['slug' => 'a', 'name' => 'A', 'command' => 'documenti:verify-integrity', 'interval_minutes' => 1440],
                ['slug' => '', 'command' => 'x'],                       // scartato: slug mancante
                ['slug' => 'b', 'command' => ''],                       // scartato: command mancante
                ['slug' => 'c', 'command' => 'documenti:cleanup-orphans', 'interval_minutes' => 0], // interval → default
                'non-array',                                           // scartato
            ],
        ]);

        $this->assertCount(2, $jobs); // validi: 'a' e 'c' ('b' ha command vuoto → scartato)
        $this->assertSame('documenti:verify-integrity', $jobs[0]['command']);
        $this->assertFalse($jobs[0]['enabled_by_default']);
        $this->assertSame('documenti:cleanup-orphans', $jobs[1]['command']);
        $this->assertSame(1440, $jobs[1]['interval_minutes']); // interval 0 → default 1440
    }

    public function testExtractScheduledJobsHandlesMissingOrInvalidBlock(): void
    {
        $svc = $this->service();
        $this->assertSame([], $svc->extractScheduledJobs([]));
        $this->assertSame([], $svc->extractScheduledJobs(['scheduled_jobs' => 'nope']));
    }

    public function testExtractScheduledJobsParsesArgs(): void
    {
        $jobs = $this->service()->extractScheduledJobs([
            'scheduled_jobs' => [
                ['slug' => 'a', 'command' => 'documenti:cleanup-orphans', 'args' => ['--hours=24', '', '  ']],
            ],
        ]);

        $this->assertSame('["--hours=24"]', $jobs[0]['args_json']);
    }

    public function testAllowedCommandsMergesModuleCommandsAndDedupes(): void
    {
        // Sottoclasse che inietta comandi di modulo senza dipendere dal ModuleLoader.
        $svc = new class ($this->createMock(SchedulerRepository::class)) extends SchedulerService {
            protected function discoverModuleCommands(): array
            {
                return ['documenti:verify-integrity', 'cleanup']; // 'cleanup' è anche core → dedup
            }
        };

        $commands = $svc->getAllowedCommandsList();

        $this->assertContains('documenti:verify-integrity', $commands, 'comando di modulo deve essere unito');
        $this->assertContains('cleanup', $commands, 'comando core deve restare');
        $this->assertSame(1, count(array_keys($commands, 'cleanup')), 'nessun duplicato');
    }
}
