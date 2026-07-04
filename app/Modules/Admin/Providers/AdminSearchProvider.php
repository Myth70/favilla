<?php

declare(strict_types=1);

namespace App\Modules\Admin\Providers;

use App\Contracts\SearchableModule;
use PDO;

class AdminSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        if (!has_permission('admin.users.view')) {
            return [];
        }

        $pdo  = app(PDO::class);
        $like = '%' . $query . '%';

        $stmt = $pdo->prepare(
            'SELECT id, name, email, username, is_active
             FROM users
             WHERE deleted_at IS NULL
               AND (name LIKE ? OR email LIKE ? OR username LIKE ?)
             ORDER BY name ASC
             LIMIT ?'
        );
        $stmt->execute([$like, $like, $like, $limit]);

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $results[] = [
                'title'    => $row['name'],
                'subtitle' => $row['email'] . ' (@' . $row['username'] . ')',
                'url'      => route('admin.users.show', ['id' => $row['id']]),
                'icon'     => 'fa-user',
                'badge'    => !$row['is_active'] ? 'disattivato' : null,
            ];
        }
        return $results;
    }

    public function getSearchLabel(): string
    {
        return 'Utenti';
    }

    public function getSearchIcon(): string
    {
        return 'fa-users';
    }
}
