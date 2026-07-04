<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\HelpOnline\Services\HelpContentService;

/**
 * Esporta il contenuto della KB Help Online (DB) nei file versionati
 * `database/help/<module_key>.json`, uno per modulo.
 *
 * Usage:
 *   php favilla help:export
 */
class HelpExportCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        echo "\n=== Help Online — export contenuto KB ===\n\n";

        /** @var HelpContentService $service */
        $service = app(HelpContentService::class);

        if (!$service->isSchemaReady()) {
            echo "[ERRORE] Schema Help Online non disponibile. Esegui prima la migration del modulo.\n";
            exit(1);
        }

        $targetDir = BASE_PATH . '/database/help';
        $summary = $service->exportAll($targetDir);

        if ($summary === []) {
            echo "[ATTENZIONE] Nessun modulo help trovato: nulla da esportare.\n";
            return;
        }

        foreach ($summary as $moduleKey => $count) {
            printf("  [OK] %-20s %d entry canoniche\n", $moduleKey, $count);
        }

        echo "\n--- Riepilogo ---\n";
        echo '  Moduli esportati:       ' . count($summary) . "\n";
        echo '  Entry canoniche totali: ' . array_sum($summary) . "\n";
        echo "  Directory:              {$targetDir}\n";
    }
}
