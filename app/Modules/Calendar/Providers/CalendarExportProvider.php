<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Providers;

use App\Contracts\ExportableModule;
use PDO;

class CalendarExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key' => 'events',
                'label' => 'Eventi calendario',
                'icon' => 'fa-calendar',
                'permission' => 'calendar.view',
                'fields' => [
                    ['name' => 'id', 'label' => 'ID', 'type' => 'integer', 'sortable' => true, 'filterable' => false],
                    ['name' => 'title', 'label' => 'Titolo', 'type' => 'string', 'sortable' => true, 'filterable' => true],
                    ['name' => 'start_datetime', 'label' => 'Inizio', 'type' => 'datetime', 'sortable' => true, 'filterable' => true],
                    ['name' => 'end_datetime', 'label' => 'Fine', 'type' => 'datetime', 'sortable' => true, 'filterable' => true],
                    ['name' => 'visibility', 'label' => 'Visibilità', 'type' => 'string', 'sortable' => true, 'filterable' => true],
                    ['name' => 'location', 'label' => 'Luogo', 'type' => 'string', 'sortable' => false, 'filterable' => true],
                    ['name' => 'created_at', 'label' => 'Creato il', 'type' => 'datetime', 'sortable' => true, 'filterable' => true],
                ],
            ],
        ];
    }

    public function getExportData(string $sourceKey, array $filters = [], string $sortBy = 'start_datetime', string $sortDir = 'DESC', int $limit = 10000): array
    {
        if ($sourceKey !== 'events') {
            return [];
        }

        $user = auth();
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $pdo = app(PDO::class);
        $stmtR = $pdo->prepare('SELECT role_id FROM user_role WHERE user_id = ?');
        $stmtR->execute([$userId]);
        $roleIds = array_column($stmtR->fetchAll(PDO::FETCH_ASSOC), 'role_id');

        $where = ['e.deleted_at IS NULL'];
        $params = [];

        $vis = ['(e.visibility = ? AND e.created_by = ?)'];
        $params[] = 'personal';
        $params[] = $userId;

        $vis[] = '(e.visibility = ?)';
        $params[] = 'public';

        if (!empty($roleIds)) {
            $ph = implode(',', array_fill(0, count($roleIds), '?'));
            $vis[] = "(e.visibility = ? AND e.visible_to_role IN ({$ph}))";
            $params[] = 'role';
            foreach ($roleIds as $roleId) {
                $params[] = (int) $roleId;
            }
        }

        $where[] = '(' . implode(' OR ', $vis) . ')';

        $allowedSort = ['id', 'title', 'start_datetime', 'end_datetime', 'visibility', 'created_at'];
        $sort = in_array($sortBy, $allowedSort, true) ? $sortBy : 'start_datetime';
        $dir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = 'SELECT e.id, e.title, e.start_datetime, e.end_datetime, e.visibility, e.location, e.created_at
                FROM calendar_events e
                WHERE ' . implode(' AND ', $where) .
               " ORDER BY e.{$sort} {$dir} LIMIT ?";
        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExportModuleName(): string
    {
        return 'Calendario';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-calendar';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey !== 'events') {
            return null;
        }

        $rows = $this->getExportData('events', [], 'id', 'ASC', 10000);
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $recordId) {
                return $row;
            }
        }

        return null;
    }
}
