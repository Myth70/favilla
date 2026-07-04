<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Contacts\Services\ContactsReminderService;

class ContactsProcessRemindersCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $pdo = app(\PDO::class);

        $stmt = $pdo->query('
            SELECT DISTINCT cr.user_id
            FROM contact_recurrences cr
            JOIN contacts c ON c.id = cr.contatto_id
            WHERE cr.user_id IS NOT NULL
        ');
        $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $service    = app(ContactsReminderService::class);
        $totalSent  = 0;
        $totalUsers = count($userIds);

        foreach ($userIds as $userId) {
            $totalSent += $service->processForUser((int) $userId);
        }

        echo "\nContatti: ricorrenze e promemoria\n";
        echo "==================================\n\n";
        echo '  Utenti processati:   ' . $totalUsers . "\n";
        echo '  Notifiche inviate:   ' . $totalSent . "\n\n";
    }
}
