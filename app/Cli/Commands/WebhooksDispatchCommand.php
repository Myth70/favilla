<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Webhooks\Services\WebhookDispatchService;

class WebhooksDispatchCommand
{
    public function handle(array $args): void
    {
        $limit = 50;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--limit=')) {
                $limit = max(1, (int) substr($arg, strlen('--limit=')));
            }
        }

        CliBootstrap::boot();

        $stats = app(WebhookDispatchService::class)->dispatch($limit);

        echo "\nWebhook dispatcher\n";
        echo "==================\n\n";
        echo '  Processati: ' . $stats['processed'] . "\n";
        echo '  Inviati:    ' . $stats['sent'] . "\n";
        echo '  In retry:   ' . $stats['released'] . "\n";
        echo '  Falliti:    ' . $stats['failed'] . "\n\n";
    }
}
