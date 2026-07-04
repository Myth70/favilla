<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Scheduler\Services\SchedulerService;

/**
 * Esegue un singolo job scheduler per ID.
 *
 * Usato internamente da "Esegui ora" nel pannello web come processo background detached.
 * Non deve essere aggiunto alla allowlist dei comandi scheduler (non è un job operativo).
 *
 * Uso: php favilla scheduler:run-single {id}
 */
class SchedulerRunSingleCommand
{
    public function handle(array $args): void
    {
        $id = isset($args[0]) ? (int) $args[0] : 0;

        if ($id <= 0) {
            echo "Errore: ID job mancante o non valido.\n";
            exit(1);
        }

        CliBootstrap::boot();

        $service = app(SchedulerService::class);

        try {
            $result = $service->runJobById($id);
            $ts     = date('Y-m-d H:i:s');
            echo "[{$ts}] Job #{$id} completato — stato: {$result['status']} ({$result['duration_ms']}ms)\n";
            exit($result['status'] === 'success' ? 0 : 1);
        } catch (\RuntimeException $e) {
            echo 'Errore: ' . $e->getMessage() . "\n";
            exit(1);
        }
    }
}
