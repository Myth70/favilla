<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Calendar\Services\CalendarReminderService;

class CalendarSendRemindersCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $service = app(CalendarReminderService::class);
        $sent    = $service->sendDueReminders();

        echo "\nCalendario: promemoria eventi\n";
        echo "=============================\n\n";
        echo '  Promemoria inviati: ' . $sent . "\n\n";
    }
}
