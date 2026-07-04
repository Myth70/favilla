<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Documenti\Services\DocumentiAdminService;

class DocumentiExpirePublishedCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $service = app(DocumentiAdminService::class);
        $count   = $service->scadiPubblicati(0);

        echo "\nDocumenti: scadenza pubblicati\n";
        echo "===============================\n\n";
        echo '  Documenti scaduti: ' . $count . "\n\n";
    }
}
