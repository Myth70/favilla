<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Providers;

use App\Contracts\ExportableModule;
use PDO;

class TasksExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key'        => 'tasks',
                'label'      => 'Attività',
                'icon'       => 'fa-clipboard-check',
                'permission' => 'tasks.view',
                'fields'     => [
                    ['name' => 'id',           'label' => 'ID',           'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'title',        'label' => 'Titolo',       'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'status',       'label' => 'Stato',        'type' => 'enum',     'sortable' => true,  'filterable' => true,
                     'enum_values' => ['backlog', 'todo', 'in_progress', 'review', 'done']],
                    ['name' => 'priority',     'label' => 'Priorità',     'type' => 'enum',     'sortable' => true,  'filterable' => true,
                     'enum_values' => ['low', 'medium', 'high', 'urgent']],
                    ['name' => 'due_date',     'label' => 'Scadenza',     'type' => 'date',     'sortable' => true,  'filterable' => true],
                    ['name' => 'user_name',    'label' => 'Utente',       'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'completed_at', 'label' => 'Completata il','type' => 'datetime', 'sortable' => true,  'filterable' => false],
                    ['name' => 'created_at',   'label' => 'Creata il',    'type' => 'datetime', 'sortable' => true,  'filterable' => true],
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
        if ($sourceKey !== 'tasks') {
            return [];
        }

        $allowedSorts = ['id', 'title', 'status', 'priority', 'due_date', 'completed_at', 'created_at'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $currentUser = auth();
        $userId      = (int) $currentUser['id'];
        $isAdmin     = has_permission('admin.users.view');

        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        if (!$isAdmin) {
            $where[]  = 'a.user_id = ?';
            $params[] = $userId;
        }

        if (!empty($filters['title'])) {
            $where[]  = 'a.title LIKE ?';
            $params[] = '%' . $filters['title'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where[]  = 'a.priority = ?';
            $params[] = $filters['priority'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $pdo      = app(PDO::class);

        $stmt = $pdo->prepare(
            "SELECT a.id, a.title, a.status, a.priority, a.due_date, a.completed_at, a.created_at,
                    u.name AS user_name
             FROM tasks a
             LEFT JOIN users u ON u.id = a.user_id
             {$whereSql}
             ORDER BY a.{$sort} {$dir}
             LIMIT " . (int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExportModuleName(): string
    {
        return 'Attività';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-clipboard-check';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey !== 'tasks') {
            return null;
        }

        $pdo  = app(PDO::class);
        $stmt = $pdo->prepare(
            'SELECT a.*, u.name AS user_name
             FROM tasks a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.id = ? AND a.deleted_at IS NULL'
        );
        $stmt->execute([$recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
