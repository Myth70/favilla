<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Documenti\Repositories\DocumentoFileRepository;
use App\Modules\Documenti\Services\DocumentiStorageService;

class DocumentiCleanupOrphansCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $hours = 24;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--hours=')) {
                $hours = max(1, (int) substr($arg, strlen('--hours=')));
            }
        }
        $dryRun = in_array('--dry-run', $args, true);

        $fileRepo = app(DocumentoFileRepository::class);
        $storage  = app(DocumentiStorageService::class);

        echo "\nDocumenti: pulizia file orfani\n";
        echo "================================\n\n";
        if ($dryRun) {
            echo "[DRY-RUN] Nessuna modifica verrà salvata.\n\n";
        }

        $orphans = $fileRepo->findOrphansOlderThan($hours);
        $count   = 0;

        foreach ($orphans as $file) {
            echo "  [{$file['id']}] {$file['stored_name']}";
            if (!$dryRun) {
                $storage->cleanup((int) $file['id']);
                echo ' (eliminato)';
            }
            echo "\n";
            $count++;
        }

        echo "\n  File orfani trovati: {$count}\n\n";
    }
}
