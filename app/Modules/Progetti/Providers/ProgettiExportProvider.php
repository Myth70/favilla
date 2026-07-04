<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Providers;

use App\Contracts\ExportableModule;
use PDO;

class ProgettiExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key' => 'projects_summary',
                'label' => 'Progetti',
                'icon' => 'fa-diagram-project',
                'permission' => 'progetti.export',
                'fields' => [
                    ['name' => 'id', 'label' => 'ID', 'type' => 'integer', 'sortable' => true, 'filterable' => false],
                    ['name' => 'name', 'label' => 'Nome', 'type' => 'string', 'sortable' => true, 'filterable' => true],
                    ['name' => 'status', 'label' => 'Stato', 'type' => 'enum', 'sortable' => true, 'filterable' => true, 'enum_values' => ['planning', 'active', 'on_hold', 'completed', 'cancelled']],
                    ['name' => 'owner_name', 'label' => 'Responsabile progetto', 'type' => 'string', 'sortable' => false, 'filterable' => true],
                    ['name' => 'budget_planned', 'label' => 'Budget Preventivo', 'type' => 'decimal', 'sortable' => true, 'filterable' => false, 'format' => 'currency'],
                    ['name' => 'progress_cached', 'label' => 'Avanzamento', 'type' => 'decimal', 'sortable' => true, 'filterable' => false, 'format' => 'percentage'],
                    ['name' => 'created_at', 'label' => 'Creato il', 'type' => 'datetime', 'sortable' => true, 'filterable' => true],
                ],
            ],
            [
                'key' => 'timesheets_detail',
                'label' => 'Registro ore - dettaglio',
                'icon' => 'fa-clock',
                'permission' => 'progetti.export',
                'fields' => [
                    ['name' => 'project_name', 'label' => 'Progetto', 'type' => 'string', 'sortable' => true, 'filterable' => true],
                    ['name' => 'task_title', 'label' => 'Attivita', 'type' => 'string', 'sortable' => true, 'filterable' => false],
                    ['name' => 'user_name', 'label' => 'Utente', 'type' => 'string', 'sortable' => true, 'filterable' => true],
                    ['name' => 'work_date', 'label' => 'Data', 'type' => 'date', 'sortable' => true, 'filterable' => false],
                    ['name' => 'hours', 'label' => 'Ore', 'type' => 'decimal', 'sortable' => true, 'filterable' => false],
                    ['name' => 'hourly_rate', 'label' => 'Tariffa €/h', 'type' => 'decimal', 'sortable' => false, 'filterable' => false],
                    ['name' => 'cost', 'label' => 'Costo €', 'type' => 'decimal', 'sortable' => false, 'filterable' => false, 'format' => 'currency'],
                    ['name' => 'note', 'label' => 'Nota', 'type' => 'string', 'sortable' => false, 'filterable' => false],
                ],
            ],
        ];
    }

    public function getExportData(
        string $sourceKey,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        int $limit = 10000
    ): array {
        if ($sourceKey === 'timesheets_detail') {
            return $this->getTimesheetsData($filters, $sortBy, $sortDir, $limit);
        }

        if ($sourceKey !== 'projects_summary') {
            return [];
        }

        // $sort/$dir interpolati nell'ORDER BY: protetti da whitelist + fallback.
        $allowedSorts = ['id', 'name', 'status', 'budget_planned', 'progress_cached', 'created_at'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        assert(in_array($sort, $allowedSorts, true) && in_array($dir, ['ASC', 'DESC'], true));

        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['name'])) {
            $where[] = 'p.name LIKE ?';
            $params[] = '%' . $filters['name'] . '%';
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['planning', 'active', 'on_hold', 'completed', 'cancelled'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $filters['status'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT p.id, p.name, p.status, p.budget_planned, p.progress_cached, p.created_at,
                   u.name AS owner_name
            FROM projects p
            LEFT JOIN users u ON u.id = p.owner_user_id
            {$whereSql}
            ORDER BY p.{$sort} {$dir}
            LIMIT ?
        ";

        $params[] = $limit;
        $stmt = app(PDO::class)->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function getTimesheetsData(array $filters, string $sortBy, string $sortDir, int $limit): array
    {
        // $sort/$dir interpolati nell'ORDER BY: protetti da whitelist + fallback.
        $allowedSorts = ['project_name', 'task_title', 'user_name', 'work_date', 'hours'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'work_date';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        assert(in_array($sort, $allowedSorts, true) && in_array($dir, ['ASC', 'DESC'], true));

        $where  = ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['project_name'])) {
            $where[] = 'p.name LIKE ?';
            $params[] = '%' . $filters['project_name'] . '%';
        }
        if (!empty($filters['user_name'])) {
            $where[] = 'u.name LIKE ?';
            $params[] = '%' . $filters['user_name'] . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT
                p.name AS project_name,
                t.title AS task_title,
                u.name AS user_name,
                ts.work_date,
                ts.hours,
                COALESCE(pm.hourly_rate_override, 0) AS hourly_rate,
                CASE WHEN COALESCE(pm.hourly_rate_override, 0) > 0
                     THEN ts.hours * pm.hourly_rate_override
                     ELSE 0 END AS cost,
                ts.note
            FROM project_timesheets ts
            INNER JOIN projects p ON p.id = ts.project_id
            INNER JOIN project_tasks t ON t.id = ts.task_id
            INNER JOIN users u ON u.id = ts.user_id
            LEFT JOIN project_members pm ON pm.project_id = ts.project_id AND pm.user_id = ts.user_id
            {$whereSql}
            ORDER BY {$sort} {$dir}, ts.id DESC
            LIMIT ?
        ";

        $params[] = $limit;
        $stmt = app(PDO::class)->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getExportModuleName(): string
    {
        return 'Progetti';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-diagram-project';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if (!in_array($sourceKey, ['projects_summary', 'timesheets_detail'], true)) {
            return null;
        }

        $stmt = app(PDO::class)->prepare(
            'SELECT p.*, u.name AS owner_name
             FROM projects p
             LEFT JOIN users u ON u.id = p.owner_user_id
             WHERE p.id = ? AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$recordId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
