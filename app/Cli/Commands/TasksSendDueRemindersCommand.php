<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Tasks\Services\TasksService;

class TasksSendDueRemindersCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $pdo = app(\PDO::class);

        // Tutti gli utenti con task non completati con scadenza impostata
        $stmt = $pdo->query("
            SELECT DISTINCT user_id
            FROM tasks
            WHERE due_date IS NOT NULL
              AND status NOT IN ('done')
              AND deleted_at IS NULL
        ");
        $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $service    = app(TasksService::class);
        $totalUsers = count($userIds);

        foreach ($userIds as $userId) {
            $service->sendDueReminders((int) $userId);
        }

        echo "\nAttivita: reminder scadenze\n";
        echo "============================\n\n";
        echo '  Utenti processati: ' . $totalUsers . "\n\n";
    }
}
