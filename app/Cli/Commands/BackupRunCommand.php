<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Backup\Services\BackupService;

class BackupRunCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $service = app(BackupService::class);

        if ($service->isBackupRunning()) {
            echo "Backup già in corso. Operazione annullata.\n";
            return;
        }

        echo "Avvio backup database...\n";

        $result = $service->createBackup();

        $format = $service->isZipBackup($result['filename'] ?? '') ? 'zip' : 'sqlgz';
        $service->recordHistory(
            $result['filename'] ?? '',
            (int) ($result['size'] ?? 0),
            (int) ($result['table_count'] ?? 0),
            null,
            $format,
            $result['databases'] ?? null
        );

        echo "\nBackup completato\n";
        echo "=================\n\n";
        echo '  File:       ' . ($result['filename'] ?? 'n/a') . "\n";
        echo '  Dimensione: ' . number_format(($result['size'] ?? 0) / 1024 / 1024, 2) . " MB\n";
        echo '  Tabelle:    ' . ($result['table_count'] ?? 0) . "\n";
        echo '  Database:   ' . count($result['databases'] ?? []) . "\n\n";

        if (!empty($result['partial'])) {
            echo "  [ATTENZIONE] Backup parziale: uno o più database di modulo non erano raggiungibili.\n\n";
        }

        $service->rotate();
        echo "  Rotazione backup applicata.\n\n";
    }
}
