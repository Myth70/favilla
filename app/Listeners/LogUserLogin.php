<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserLoggedIn;
use PDO;

class LogUserLogin
{
    /**
     * Log a user login event to audit_logs.
     */
    public function handle(UserLoggedIn $event): void
    {
        $pdo = app(PDO::class);
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
             VALUES (?, 'login', 'user', ?, ?)"
        );
        $stmt->execute([$event->userId, $event->userId, $event->ip]);
    }
}
