<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Documenti\Services\ReminderService;

class DocumentiSendExpiryRemindersCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $service = app(ReminderService::class);
        $sent    = $service->sendDueReminders();

        echo "\nDocumenti: reminder scadenze\n";
        echo "=============================\n\n";
        echo '  Promemoria inviati: ' . $sent . "\n\n";
    }
}
