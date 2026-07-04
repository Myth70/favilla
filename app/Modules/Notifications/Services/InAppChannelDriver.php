<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;

class InAppChannelDriver implements NotificationChannelDriverInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    public function channel(): string
    {
        return 'in_app';
    }

    public function send(array $job): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, title, body, type, icon, color, link, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            (int) $job['user_id'],
            (string) ($job['delivery_subject'] ?? $job['dispatch_title'] ?? ''),
            $job['delivery_body'] ?: ($job['dispatch_body'] ?? null),
            (string) ($job['dispatch_type'] ?? 'info'),
            $job['delivery_icon'] ?: ($job['dispatch_icon'] ?? null),
            $job['delivery_color'] ?: ($job['dispatch_color'] ?? null),
            $job['delivery_link'] ?: ($job['dispatch_link'] ?? null),
            $job['created_by'] ?? null,
        ]);

        return [
            'status' => 'sent',
            'provider_message_id' => (string) $this->pdo->lastInsertId(),
            'error_message' => null,
        ];
    }
}
