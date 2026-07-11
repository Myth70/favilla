<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Scheduler\Repositories\SchedulerRepository;

/**
 * Copre le query dello scheduler che NON sono esprimibili su SQLite e quindi
 * restano invisibili alla suite di default:
 *   - `SELECT … FOR UPDATE` (errore di sintassi su SQLite → il path non gira mai);
 *   - `DATE_ADD/DATE_SUB(… INTERVAL … MINUTE)` per la scadenza e la stuck-detection.
 *
 * getDueJobs() apre una PROPRIA transazione (FOR UPDATE), quindi disattiviamo la
 * transazione-di-isolamento della base (MariaDB non annida le transazioni) e
 * ripuliamo le tabelle a mano ad ogni test.
 */
final class SchedulerRepositoryIntegrationTest extends DatabaseIntegrationTestCase
{
    protected bool $useTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::$pdo->exec('DELETE FROM scheduler_log');
        self::$pdo->exec('DELETE FROM scheduler_jobs');
    }

    private function repo(): SchedulerRepository
    {
        return new SchedulerRepository();
    }

    private function ago(string $expr): string
    {
        return date('Y-m-d H:i:s', strtotime($expr));
    }

    public function testGetDueJobsClaimsDueAndSkipsFresh(): void
    {
        // Scaduto: ultimo run 2 ore fa, intervallo 60 min.
        $dueId = $this->insertRow('scheduler_jobs', [
            'slug'             => 'due-job',
            'name'             => 'Due job',
            'command'          => 'demo:noop',
            'interval_minutes' => 60,
            'enabled'          => 1,
            'last_run_at'      => $this->ago('-2 hours'),
            'last_status'      => 'success',
        ]);

        // Fresco: appena eseguito, non ancora scaduto.
        $freshId = $this->insertRow('scheduler_jobs', [
            'slug'             => 'fresh-job',
            'name'             => 'Fresh job',
            'command'          => 'demo:noop',
            'interval_minutes' => 60,
            'enabled'          => 1,
            'last_run_at'      => $this->ago('-1 minute'),
            'last_status'      => 'success',
        ]);

        // Disabilitato: mai selezionato anche se scaduto.
        $this->insertRow('scheduler_jobs', [
            'slug'             => 'disabled-job',
            'name'             => 'Disabled job',
            'command'          => 'demo:noop',
            'interval_minutes' => 60,
            'enabled'          => 0,
            'last_run_at'      => $this->ago('-2 hours'),
            'last_status'      => 'success',
        ]);

        $due = $this->repo()->getDueJobs();
        $ids = array_map('intval', array_column($due, 'id'));

        $this->assertContains($dueId, $ids, 'il job scaduto deve essere selezionato');
        $this->assertNotContains($freshId, $ids, 'il job fresco non deve essere selezionato');
        $this->assertCount(1, $due);

        // Il job selezionato è stato atomicamente marcato running.
        $claimed = $this->repo()->find($dueId);
        $this->assertSame('running', $claimed['last_status']);
    }

    public function testGetDueJobsRecoversStuckRunningAfterTenMinutes(): void
    {
        // running da 15 minuti → considerato bloccato, riselezionabile.
        $stuckId = $this->insertRow('scheduler_jobs', [
            'slug'             => 'stuck-job',
            'name'             => 'Stuck job',
            'command'          => 'demo:noop',
            'interval_minutes' => 5,
            'enabled'          => 1,
            'last_run_at'      => $this->ago('-15 minutes'),
            'last_status'      => 'running',
        ]);

        // running da 2 minuti → ancora in corso, da non toccare.
        $runningId = $this->insertRow('scheduler_jobs', [
            'slug'             => 'running-job',
            'name'             => 'Running job',
            'command'          => 'demo:noop',
            'interval_minutes' => 5,
            'enabled'          => 1,
            'last_run_at'      => $this->ago('-2 minutes'),
            'last_status'      => 'running',
        ]);

        $ids = array_map('intval', array_column($this->repo()->getDueJobs(), 'id'));

        $this->assertContains($stuckId, $ids);
        $this->assertNotContains($runningId, $ids);
    }

    public function testGetDueJobsHonoursNextRetryAt(): void
    {
        // next_retry_at nel passato → dovuto (anche se appena eseguito).
        $retryDueId = $this->insertRow('scheduler_jobs', [
            'slug'             => 'retry-due',
            'name'             => 'Retry due',
            'command'          => 'demo:noop',
            'interval_minutes' => 60,
            'enabled'          => 1,
            'last_run_at'      => $this->ago('-1 minute'),
            'last_status'      => 'failed',
            'next_retry_at'    => $this->ago('-30 seconds'),
        ]);

        // next_retry_at nel futuro → non ancora dovuto.
        $retryPendingId = $this->insertRow('scheduler_jobs', [
            'slug'             => 'retry-pending',
            'name'             => 'Retry pending',
            'command'          => 'demo:noop',
            'interval_minutes' => 60,
            'enabled'          => 1,
            'last_run_at'      => $this->ago('-1 minute'),
            'last_status'      => 'failed',
            'next_retry_at'    => $this->ago('+30 minutes'),
        ]);

        $ids = array_map('intval', array_column($this->repo()->getDueJobs(), 'id'));

        $this->assertContains($retryDueId, $ids);
        $this->assertNotContains($retryPendingId, $ids);
    }

    public function testUpdateFailureWithRetrySchedulesBackoff(): void
    {
        $repo = $this->repo();
        $id   = $repo->create([
            'slug'             => 'backoff-job',
            'name'             => 'Backoff job',
            'command'          => 'demo:noop',
            'interval_minutes' => 60,
        ]);
        // Consenti almeno un retry.
        self::$pdo->prepare('UPDATE scheduler_jobs SET max_retries = 3 WHERE id = ?')->execute([$id]);

        $repo->updateFailureWithRetry($id, 'boom', 12);

        $job = $repo->find($id);
        $this->assertSame('failed', $job['last_status']);
        $this->assertSame(1, (int) $job['retry_count']);
        $this->assertNotNull($job['next_retry_at'], 'un retry disponibile deve pianificare next_retry_at');
    }

    public function testResetRunningOnlyAffectsRunningJobs(): void
    {
        $repo = $this->repo();

        $runningId = $this->insertRow('scheduler_jobs', [
            'slug'        => 'reset-running',
            'name'        => 'Reset running',
            'command'     => 'demo:noop',
            'last_status' => 'running',
        ]);
        $successId = $this->insertRow('scheduler_jobs', [
            'slug'        => 'reset-success',
            'name'        => 'Reset success',
            'command'     => 'demo:noop',
            'last_status' => 'success',
        ]);

        $repo->resetRunning($runningId);
        $repo->resetRunning($successId);

        $this->assertSame('failed', $repo->find($runningId)['last_status']);
        $this->assertSame('success', $repo->find($successId)['last_status'], 'un job non-running non va toccato');
    }
}
