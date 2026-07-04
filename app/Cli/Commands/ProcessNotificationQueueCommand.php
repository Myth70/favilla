<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Notifications\Services\NotificationQueueProcessorService;

class ProcessNotificationQueueCommand
{
    public function handle(array $args): void
    {
        $limit = 25;
        $channel = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--limit=')) {
                $limit = max(1, (int) substr($arg, strlen('--limit=')));
            }
            if (str_starts_with($arg, '--channel=')) {
                $channel = trim(substr($arg, strlen('--channel=')));
            }
        }

        CliBootstrap::boot();

        $processor = app(NotificationQueueProcessorService::class);
        $stats = $processor->process($limit, $channel ?: null);

        echo "\nNotification queue processor\n";
        echo "============================\n\n";
        echo '  Processati: ' . $stats['processed'] . "\n";
        echo '  Inviati:    ' . $stats['sent'] . "\n";
        echo '  Saltati:    ' . $stats['skipped'] . "\n";
        echo '  In retry:   ' . $stats['released'] . "\n";
        echo '  Falliti:    ' . $stats['failed'] . "\n\n";
    }
}
