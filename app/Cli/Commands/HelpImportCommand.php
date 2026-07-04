<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\HelpOnline\Services\HelpContentService;

/**
 * Importa il contenuto della KB Help Online dai file versionati
 * `database/help/<module_key>.json`. Crea i moduli mancanti; di default
 * importa solo i moduli senza entry (installazione fresca), con --force
 * rimpiazza il contenuto esistente. Ricostruisce i search term alla fine.
 *
 * Usage:
 *   php favilla help:import
 *   php favilla help:import --module=tasks
 *   php favilla help:import --force
 */
class HelpImportCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $force = in_array('--force', $args, true);
        $only  = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--module=')) {
                $only = substr($arg, strlen('--module='));
            }
        }

        $sourceDir = BASE_PATH . '/database/help';
        if (!is_dir($sourceDir)) {
            echo "[ERRORE] Directory contenuto help non trovata: {$sourceDir}\n";
            exit(1);
        }

        echo "\n=== Help Online — import contenuto KB ===\n";
        if ($force) {
            echo "[--force] Il contenuto esistente dei moduli verrà sostituito.\n";
        }
        if ($only !== null) {
            echo "[--module={$only}] Import limitato a questo modulo.\n";
        }
        echo "\n";

        /** @var HelpContentService $service */
        $service = app(HelpContentService::class);
        $result = $service->importAll($sourceDir, $only, $force);

        foreach ($result['imported'] as $moduleKey => $count) {
            printf("  [IMPORTATO] %-20s %d entry\n", $moduleKey, $count);
        }
        foreach ($result['skipped'] as $moduleKey) {
            printf("  [SALTATO]   %-20s ha già contenuto (usa --force per sostituire)\n", $moduleKey);
        }

        if ($result['imported'] === [] && $result['skipped'] === []) {
            echo "[ATTENZIONE] Nessun file JSON trovato o nessun modulo corrispondente.\n";
        }

        echo "\n--- Riepilogo ---\n";
        echo '  Moduli importati: ' . count($result['imported']) . "\n";
        echo '  Moduli saltati:   ' . count($result['skipped']) . "\n";
        echo "  Search term ricostruiti.\n";
    }
}
