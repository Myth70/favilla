<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Providers;

use App\Contracts\ExportableModule;
use PDO;

class NotifExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key'        => 'notifications',
                'label'      => 'Notifiche',
                'icon'       => 'fa-bell',
                'permission' => null,
                'fields'     => [
                    ['name' => 'id',         'label' => 'ID',         'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'title',      'label' => 'Titolo',     'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'body',       'label' => 'Corpo',      'type' => 'string',   'sortable' => false, 'filterable' => true],
                    ['name' => 'type',       'label' => 'Tipo',       'type' => 'enum',     'sortable' => true,  'filterable' => true, 'enum_values' => ['info', 'success', 'warning', 'danger']],
                    ['name' => 'icon',       'label' => 'Icona',      'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'read_at',    'label' => 'Letto il',   'type' => 'datetime', 'sortable' => true,  'filterable' => true],
                    ['name' => 'created_at', 'label' => 'Data',       'type' => 'datetime', 'sortable' => true,  'filterable' => true],
                ],
            ],
        ];
    }

    public function getExportData(
        string $sourceKey,
        array  $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        int    $limit = 10000
    ): array {
        if ($sourceKey !== 'notifications') {
            return [];
        }

        $pdo    = app(PDO::class);
        $userId = auth()['id'] ?? null;
        if (!$userId) {
            return [];
        }

        $where  = ['n.user_id = ?'];
        $params = [$userId];

        // Filter: title
        if (!empty($filters['title'])) {
            $where[]  = 'n.title LIKE ?';
            $params[] = '%' . $filters['title'] . '%';
        }

        // Filter: body
        if (!empty($filters['body'])) {
            $where[]  = 'n.body LIKE ?';
            $params[] = '%' . $filters['body'] . '%';
        }

        // Filter: type
        if (!empty($filters['type']) && in_array($filters['type'], ['info', 'success', 'warning', 'danger'], true)) {
            $where[]  = 'n.type = ?';
            $params[] = $filters['type'];
        }

        // Filter: read status
        if (isset($filters['read_at']) && $filters['read_at'] !== '') {
            if ($filters['read_at'] === 'unread') {
                $where[] = 'n.read_at IS NULL';
            } elseif ($filters['read_at'] === 'read') {
                $where[] = 'n.read_at IS NOT NULL';
            }
        }

        // Date filters
        if (!empty($filters['date_from'])) {
            $where[]  = 'n.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'n.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Sort with whitelist
        $allowedSorts = ['id', 'title', 'type', 'read_at', 'created_at'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT n.id, n.title, n.body, n.type, n.icon, n.read_at, n.created_at
            FROM notifications n
            {$whereClause}
            ORDER BY n.{$sort} {$dir}
            LIMIT ?
        ";

        $params[] = $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getExportModuleName(): string
    {
        return 'Notifiche';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-bell';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey !== 'notifications') {
            return null;
        }

        $userId = auth()['id'] ?? null;
        if (!$userId) {
            return null;
        }

        $pdo = app(PDO::class);
        $stmt = $pdo->prepare(
            'SELECT id, title, body, type, icon, read_at, created_at
             FROM notifications
             WHERE id = ? AND user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$recordId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
