<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

use App\Modules\Notifications\Repositories\NotificationQueueRepository;

/**
 * Backlog della coda di invio notifiche: un accumulo prolungato segnala che
 * il worker (job schedulato `notifications:process-queue`, ogni 5 minuti di
 * default) si è fermato o non riesce a smaltire il traffico.
 */
class NotificationQueueCheck extends AbstractHealthCheck
{
    private const WARN_MINUTES = 30;
    private const FAIL_MINUTES = 120;

    private ?NotificationQueueRepository $repo;

    public function __construct(?NotificationQueueRepository $repo = null)
    {
        $this->repo = $repo;
    }

    private function repo(): NotificationQueueRepository
    {
        return $this->repo ??= app(NotificationQueueRepository::class);
    }

    public function key(): string
    {
        return 'notification_queue';
    }

    public function label(): string
    {
        return 'Coda notifiche';
    }

    public function description(): string
    {
        return 'Backlog della coda di invio notifiche (in-app, email, Telegram).';
    }

    protected function checks(): array
    {
        $checks = [];

        try {
            $summary = $this->repo()->getBacklogSummary();
            $pending = $summary['pending'];
            $oldest  = $summary['oldest_pending_minutes'];

            if ($pending === 0) {
                $checks[] = $this->ok('Backlog coda', 'Nessun elemento in attesa');
                return $checks;
            }

            $detail = "{$pending} in attesa" . ($oldest !== null ? ", il più vecchio da {$oldest} minuti" : '');

            if ($oldest !== null && $oldest > self::FAIL_MINUTES) {
                $checks[] = $this->fail('Backlog coda', $detail);
            } elseif ($oldest !== null && $oldest > self::WARN_MINUTES) {
                $checks[] = $this->warn('Backlog coda', $detail);
            } else {
                $checks[] = $this->ok('Backlog coda', $detail);
            }
        } catch (\Throwable $e) {
            $checks[] = $this->warn('Coda notifiche', 'Tabella notification_queue non disponibile: ' . $e->getMessage());
        }

        return $checks;
    }
}
