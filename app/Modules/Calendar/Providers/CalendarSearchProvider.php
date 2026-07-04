<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Providers;

use App\Contracts\SearchableModule;
use PDO;

class CalendarSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        $pdo  = app(PDO::class);
        $like = '%' . $query . '%';

        // Resolve user role IDs for visibility filter
        $stmtR = $pdo->prepare('SELECT role_id FROM user_role WHERE user_id = ?');
        $stmtR->execute([$userId]);
        $roleIds = array_column($stmtR->fetchAll(), 'role_id');

        // Build visibility filter
        $visConds = ['(e.visibility = ? AND e.created_by = ?)'];
        $params   = [$like, $like, 'personal', $userId];

        if (!empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $visConds[]   = "(e.visibility = ? AND e.visible_to_role IN ($placeholders))";
            $params[]     = 'role';
            $params       = array_merge($params, $roleIds);
        }

        $visFilter = '(' . implode(' OR ', $visConds) . ')';
        $params[]  = $limit;

        $sql = "SELECT e.id, e.title, e.description, e.start_datetime, e.visibility
                FROM calendar_events e
                WHERE e.deleted_at IS NULL
                  AND (e.title LIKE ? OR e.description LIKE ?)
                  AND {$visFilter}
                ORDER BY e.start_datetime DESC
                LIMIT ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $subtitle = format_date_it($row['start_datetime'], 'compact');
            if ($row['description']) {
                $subtitle .= ' — ' . mb_substr($row['description'], 0, 80);
            }

            $results[] = [
                'title'    => $row['title'],
                'subtitle' => $subtitle,
                'url'      => route('calendar.show', ['id' => $row['id']]),
                'icon'     => 'fa-calendar',
                'badge'    => $row['visibility'] === 'role' ? 'Condiviso' : null,
            ];
        }

        return $results;
    }

    public function getSearchLabel(): string
    {
        return 'Calendario';
    }

    public function getSearchIcon(): string
    {
        return 'fa-calendar';
    }
}
