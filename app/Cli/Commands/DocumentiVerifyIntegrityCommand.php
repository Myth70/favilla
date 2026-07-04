<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Documenti\Repositories\DocumentoFileRepository;
use App\Modules\Documenti\Services\DocumentiStorageService;

class DocumentiVerifyIntegrityCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $fileRepo = app(DocumentoFileRepository::class);
        $storage  = app(DocumentiStorageService::class);

        echo "\nDocumenti: verifica integrità SHA-256\n";
        echo "======================================\n\n";

        $counts = ['ok' => 0, 'mismatch' => 0, 'missing' => 0, 'no_checksum' => 0];

        foreach ($fileRepo->allForIntegrity() as $file) {
            $path   = $storage->physicalPath($file);
            $result = DocumentiStorageService::verifyChecksum($path, $file['checksum_sha256'] ?? null);
            $counts[$result]++;

            if ($result !== 'ok') {
                echo "  [{$result}] #{$file['id']} {$file['stored_name']}\n";
            }
        }

        echo "\n  File verificati: ok={$counts['ok']} mismatch={$counts['mismatch']} missing={$counts['missing']} no_checksum={$counts['no_checksum']}\n\n";

        if ($counts['mismatch'] > 0) {
            echo "[ATTENZIONE] Rilevate discrepanze di integrità sui file sopra elencati.\n\n";
        }
    }
}
