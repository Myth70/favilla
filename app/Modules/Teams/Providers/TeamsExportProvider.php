<?php

declare(strict_types=1);

namespace App\Modules\Teams\Providers;

use App\Contracts\ExportableModule;
use PDO;

class TeamsExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key'        => 'conversations',
                'label'      => 'Conversazioni',
                'icon'       => 'fa-message',
                'permission' => 'teams.admin',
                'fields'     => [
                    ['name' => 'id',            'label' => 'ID',              'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'name',          'label' => 'Nome',            'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'type',          'label' => 'Tipo',            'type' => 'enum',     'sortable' => true,  'filterable' => true, 'enum_values' => ['direct', 'group']],
                    ['name' => 'member_count',  'label' => 'Membri',          'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'message_count', 'label' => 'Messaggi',        'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'created_at',    'label' => 'Data creazione',  'type' => 'datetime', 'sortable' => true,  'filterable' => true],
                    ['name' => 'archived_at',   'label' => 'Archiviato il',   'type' => 'datetime', 'sortable' => true,  'filterable' => true],
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
        if ($sourceKey !== 'conversations') {
            return [];
        }

        $pdo = app(PDO::class);

        $where  = [];
        $params = [];

        // Filter: type
        if (!empty($filters['type']) && in_array($filters['type'], ['direct', 'group'], true)) {
            $where[]  = 'c.type = ?';
            $params[] = $filters['type'];
        }

        // Filter: name
        if (!empty($filters['name'])) {
            $where[]  = 'c.name LIKE ?';
            $params[] = '%' . $filters['name'] . '%';
        }

        // Date filters
        if (!empty($filters['date_from'])) {
            $where[]  = 'c.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'c.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Sort with whitelist
        $allowedSorts = ['id', 'name', 'type', 'member_count', 'message_count', 'created_at', 'archived_at'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        // For subquery-based columns, adjust ORDER BY
        $orderColumn = $sort;
        if (in_array($sort, ['member_count', 'message_count'], true)) {
            $orderColumn = $sort;
        } else {
            $orderColumn = 'c.' . $sort;
        }

        $sql = "
            SELECT
                c.id, c.name, c.type, c.created_at, c.archived_at,
                (
                    SELECT COUNT(*)
                    FROM teams_conversation_members cm
                    WHERE cm.conversation_id = c.id AND cm.left_at IS NULL
                ) AS member_count,
                (
                    SELECT COUNT(*)
                    FROM teams_messages m
                    WHERE m.conversation_id = c.id AND m.deleted_at IS NULL
                ) AS message_count
            FROM teams_conversations c
            {$whereClause}
            ORDER BY {$orderColumn} {$dir}
            LIMIT ?
        ";

        $params[] = $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getExportModuleName(): string
    {
        return 'Teams';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-comments';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey !== 'conversations') {
            return null;
        }

        $pdo = app(PDO::class);
        $stmt = $pdo->prepare(
            'SELECT
                c.id, c.name, c.type, c.created_at, c.archived_at,
                (
                    SELECT COUNT(*)
                    FROM teams_conversation_members cm
                    WHERE cm.conversation_id = c.id AND cm.left_at IS NULL
                ) AS member_count,
                (
                    SELECT COUNT(*)
                    FROM teams_messages m
                    WHERE m.conversation_id = c.id AND m.deleted_at IS NULL
                ) AS message_count
             FROM teams_conversations c
             WHERE c.id = ?
             LIMIT 1'
        );
        $stmt->execute([$recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
