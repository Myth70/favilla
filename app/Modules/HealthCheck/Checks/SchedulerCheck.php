<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Modules\Scheduler\Services\SchedulerService;

/**
 * Rileva se il loop dello Scheduler (container/cron) ha smesso di girare.
 *
 * Non si limita a guardare l'ultimo stato di un job: confronta l'ultima
 * esecuzione di OGNI job abilitato con il proprio intervallo configurato. Un
 * job in stallo ben oltre il proprio intervallo è il segnale che il loop
 * master (docker `scheduler` service o cron) si è fermato, anche se il job
 * stesso non ha mai fallito in precedenza.
 */
class SchedulerCheck extends AbstractHealthCheck
{
    private const GRACE_MINUTES = 15;
    private const FAIL_MINUTES  = 60;

    private ?SchedulerService $scheduler;

    public function __construct(?SchedulerService $scheduler = null)
    {
        $this->scheduler = $scheduler;
    }

    private function scheduler(): SchedulerService
    {
        return $this->scheduler ??= app(SchedulerService::class);
    }

    public function key(): string
    {
        return 'scheduler';
    }

    public function label(): string
    {
        return 'Scheduler';
    }

    public function description(): string
    {
        return 'Vivacità del loop scheduler: job abilitati eseguiti entro il proprio intervallo.';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            $jobs = array_values(array_filter(
                $this->scheduler()->getJobs(),
                static fn (array $job): bool => (bool) ($job['enabled'] ?? false)
            ));

            if ($jobs === []) {
                $checks[] = $this->warn('Job abilitati', 'Nessun job schedulato è abilitato');
                return $checks;
            }

            $worstJob     = null;
            $worstOverdue = 0;

            foreach ($jobs as $job) {
                if (empty($job['last_run_at'])) {
                    // Mai eseguito: probabilmente appena creato, non è un segnale di stallo.
                    continue;
                }

                $lastRun = strtotime((string) $job['last_run_at']);
                if ($lastRun === false) {
                    continue;
                }

                $intervalMinutes = max(1, (int) ($job['interval_minutes'] ?? 60));
                $overdueMinutes  = (int) floor((time() - $lastRun) / 60) - $intervalMinutes;

                if ($overdueMinutes > $worstOverdue) {
                    $worstOverdue = $overdueMinutes;
                    $worstJob     = $job;
                }
            }

            if ($worstJob === null || $worstOverdue <= self::GRACE_MINUTES) {
                $checks[] = $this->ok('Esecuzione job', count($jobs) . ' job abilitati, tutti eseguiti entro il proprio intervallo');
                return $checks;
            }

            $name   = (string) ($worstJob['display_name'] ?? $worstJob['name'] ?? $worstJob['slug'] ?? '?');
            $detail = "\"{$name}\" in ritardo di {$worstOverdue} minuti oltre l'intervallo previsto — il loop scheduler potrebbe essersi fermato";

            $checks[] = $worstOverdue > self::FAIL_MINUTES
                ? $this->fail('Esecuzione job', $detail)
                : $this->warn('Esecuzione job', $detail);
        } catch (\Throwable $e) {
            $checks[] = $this->warn('Scheduler', 'Servizio non disponibile: ' . $e->getMessage());
        }

        return $checks;
    }
}
