<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Providers;

use App\Contracts\SearchableModule;
use PDO;

class NotifSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        $pdo  = app(PDO::class);
        $like = '%' . $query . '%';

        $stmt = $pdo->prepare(
            'SELECT id, title, body, type, link, read_at, created_at
             FROM notifications
             WHERE user_id = ?
               AND (title LIKE ? OR body LIKE ?)
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $like, $like, $limit]);

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $results[] = [
                'title'    => $row['title'],
                'subtitle' => $row['body'] ? mb_substr($row['body'], 0, 100) : '',
                'url'      => $row['link'] ?: route('notifications.index'),
                'icon'     => 'fa-bell',
                'badge'    => $row['read_at'] ? null : 'nuova',
            ];
        }
        return $results;
    }

    public function getSearchLabel(): string
    {
        return 'Notifiche';
    }

    public function getSearchIcon(): string
    {
        return 'fa-bell';
    }
}
