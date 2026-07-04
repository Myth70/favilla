<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Scheduler\Services\SchedulerService;

/**
 * Master scheduler command.
 *
 * Aggiungere a crontab (un'unica entry):
 *   * * * * *  cd /path/to/favilla && php favilla scheduler:run >> storage/logs/scheduler.log 2>&1
 *
 * Ogni job ha il proprio intervallo configurabile via DB o UI admin.
 */
class SchedulerRunCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $service = app(SchedulerService::class);
        $stats   = $service->runDueJobs();

        $ts = date('Y-m-d H:i:s');

        if ($stats['checked'] === 0) {
            echo "[{$ts}] Nessun job in scadenza.\n";
            return;
        }

        echo "\n[{$ts}] Scheduler run\n";
        echo str_repeat('─', 40) . "\n";
        echo '  Job controllati: ' . $stats['checked'] . "\n";
        echo '  Job eseguiti:    ' . $stats['executed'] . "\n";
        echo '  Successi:        ' . $stats['success'] . "\n";
        echo '  Falliti:         ' . $stats['failed'] . "\n\n";
    }
}
