<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Repositories;

use App\Repositories\BaseRepository;

class ProgettiRepository extends BaseRepository
{
    protected string $table = 'projects';
    protected bool $softDelete = true;
    protected bool $timestamps = true;
    protected bool $auditable = true;
    protected string $auditEntity = 'project';

    protected array $fillable = [
        'name',
        'code',
        'description',
        'client_name',
        'owner_user_id',
        'status',
        'start_date',
        'end_date',
        'estimated_hours',
        'budget_planned',
        'budget_actual_cached',
        'progress_cached',
        'teams_conversation_id',
        'created_by',
    ];

    public function listForUser(int $userId, bool $canViewAll, array $filters = []): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // $sort/$dir vengono interpolati nell'ORDER BY: NON usare PDO bind.
        // Sicurezza: whitelist via in_array + fallback fisso. Mantenere allineata.
        $allowedSorts = ['created_at', 'updated_at', 'name', 'status', 'end_date', 'budget_planned'];
        $sort = (string) ($filters['sort'] ?? 'updated_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'updated_at';
        }
        assert(in_array($sort, $allowedSorts, true) && in_array($dir, ['ASC', 'DESC'], true));

        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (!$canViewAll) {
            $where[] = '(p.owner_user_id = ? OR EXISTS (
                SELECT 1 FROM project_members pmx
                WHERE pmx.project_id = p.id AND pmx.user_id = ?
            ))';
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.code LIKE ? OR p.client_name LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM projects p {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT
                p.*,
                u.name AS owner_name,
                (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS tasks_total,
                (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS tasks_done
            FROM projects p
            LEFT JOIN users u ON u.id = p.owner_user_id
            {$whereSql}
            ORDER BY p.{$sort} {$dir}
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $paramsWithPaging = $params;
        $paramsWithPaging[] = $perPage;
        $paramsWithPaging[] = $offset;
        $stmt->execute($paramsWithPaging);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'lastPage' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
        ];
    }

    public function adminStats(): array
    {
        $sql = '
            SELECT
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS total_projects,
                SUM(CASE WHEN deleted_at IS NULL AND status NOT IN ("completed", "cancelled") THEN 1 ELSE 0 END) AS active_projects,
                SUM(CASE WHEN status = "completed" AND deleted_at IS NULL THEN 1 ELSE 0 END) AS completed_projects,
                SUM(CASE WHEN status = "cancelled" AND deleted_at IS NULL THEN 1 ELSE 0 END) AS cancelled_projects,
                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS trashed_projects
            FROM projects
        ';

        $row = $this->pdo->query($sql)->fetch() ?: [];

        return [
            'total_projects' => (int) ($row['total_projects'] ?? 0),
            'active_projects' => (int) ($row['active_projects'] ?? 0),
            'completed_projects' => (int) ($row['completed_projects'] ?? 0),
            'cancelled_projects' => (int) ($row['cancelled_projects'] ?? 0),
            'trashed_projects' => (int) ($row['trashed_projects'] ?? 0),
        ];
    }

    public function adminOwnerOptions(): array
    {
        $sql = '
            SELECT DISTINCT u.id, u.name
            FROM projects p
            INNER JOIN users u ON u.id = p.owner_user_id
            WHERE p.deleted_at IS NULL
            ORDER BY u.name ASC
        ';

        return $this->pdo->query($sql)->fetchAll();
    }

    public function adminList(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildAdminWhere($filters);

        // $sort/$dir interpolati nell'ORDER BY: protetti da whitelist + fallback.
        $allowedSorts = ['updated_at', 'created_at', 'name', 'status', 'end_date', 'budget_planned'];
        $sort = (string) ($filters['sort'] ?? 'updated_at');
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'updated_at';
        }
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        assert(in_array($sort, $allowedSorts, true) && in_array($dir, ['ASC', 'DESC'], true));

        $sql = "
            SELECT
                p.*,
                u.name AS owner_name,
                (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS tasks_total,
                (SELECT COUNT(*) FROM project_tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS tasks_done
            FROM projects p
            LEFT JOIN users u ON u.id = p.owner_user_id
            {$whereSql}
            ORDER BY p.{$sort} {$dir}
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $queryParams = $params;
        $queryParams[] = $perPage;
        $queryParams[] = $offset;
        $stmt->execute($queryParams);

        return $stmt->fetchAll();
    }

    public function adminCount(array $filters): int
    {
        [$whereSql, $params] = $this->buildAdminWhere($filters);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM projects p {$whereSql}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function buildAdminWhere(array $filters): array
    {
        $scope = (string) ($filters['scope'] ?? 'active');
        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $ownerId = (int) ($filters['owner_id'] ?? 0);

        $where = [];
        $params = [];

        if ($scope === 'trash') {
            $where[] = 'p.deleted_at IS NOT NULL';
        } else {
            $where[] = 'p.deleted_at IS NULL';
        }

        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.code LIKE ? OR p.client_name LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        if ($ownerId > 0) {
            $where[] = 'p.owner_user_id = ?';
            $params[] = $ownerId;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        return [$whereSql, $params];
    }

    public function findForUser(int $projectId, int $userId, bool $canViewAll): ?array
    {
        $params = [$projectId];
        $scope = '';

        if (!$canViewAll) {
            $scope = ' AND (p.owner_user_id = ? OR EXISTS (
                SELECT 1 FROM project_members pmx
                WHERE pmx.project_id = p.id AND pmx.user_id = ?
            ))';
            $params[] = $userId;
            $params[] = $userId;
        }

        $sql = "
            SELECT p.*, u.name AS owner_name
            FROM projects p
            LEFT JOIN users u ON u.id = p.owner_user_id
            WHERE p.id = ? AND p.deleted_at IS NULL {$scope}
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createProject(array $data, int $userId): int
    {
        return $this->transaction(function () use ($data, $userId): int {
            $projectId = $this->create($data);

            $memberStmt = $this->pdo->prepare(
                'INSERT INTO project_members (project_id, user_id, role, hourly_rate_override) VALUES (?, ?, ?, ?)'
            );
            $memberStmt->execute([$projectId, $userId, 'owner', null]);

            return $projectId;
        });
    }

    public function getDashboardKpi(int $projectId): array
    {
        $taskSql = "
            SELECT
                COUNT(*) AS total_tasks,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done_tasks,
                SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status <> 'done' THEN 1 ELSE 0 END) AS overdue_tasks,
                SUM(estimated_hours) AS estimated_hours
            FROM project_tasks
            WHERE project_id = ? AND deleted_at IS NULL
        ";
        $taskStmt = $this->pdo->prepare($taskSql);
        $taskStmt->execute([$projectId]);
        $taskRow = $taskStmt->fetch() ?: [];

        $timesheetSql = '
            SELECT
                COALESCE(SUM(pt.hours), 0) AS consumed_hours,
                COALESCE(SUM(pt.hours * COALESCE(pm.hourly_rate_override, 0)), 0) AS actual_cost
            FROM project_timesheets pt
            LEFT JOIN project_members pm
                ON pm.project_id = pt.project_id AND pm.user_id = pt.user_id
            WHERE pt.project_id = ?
        ';
        $timeStmt = $this->pdo->prepare($timesheetSql);
        $timeStmt->execute([$projectId]);
        $timeRow = $timeStmt->fetch() ?: [];

        $milestoneSql = "
            SELECT
                COUNT(*) AS total_milestones,
                SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status <> 'done' THEN 1 ELSE 0 END) AS missed_milestones,
                SUM(CASE WHEN billable = 1 THEN 1 ELSE 0 END) AS billable_milestones,
                SUM(CASE WHEN billable = 1 AND status = 'done' THEN 1 ELSE 0 END) AS billable_done
            FROM project_milestones
            WHERE project_id = ? AND deleted_at IS NULL
        ";
        $mileStmt = $this->pdo->prepare($milestoneSql);
        $mileStmt->execute([$projectId]);
        $mileRow = $mileStmt->fetch() ?: [];

        $projectStmt = $this->pdo->prepare('SELECT budget_planned FROM projects WHERE id = ? LIMIT 1');
        $projectStmt->execute([$projectId]);
        $budgetPlanned = (float) ($projectStmt->fetchColumn() ?: 0);

        return [
            'total_tasks' => (int) ($taskRow['total_tasks'] ?? 0),
            'done_tasks' => (int) ($taskRow['done_tasks'] ?? 0),
            'overdue_tasks' => (int) ($taskRow['overdue_tasks'] ?? 0),
            'estimated_hours' => (float) ($taskRow['estimated_hours'] ?? 0),
            'consumed_hours' => (float) ($timeRow['consumed_hours'] ?? 0),
            'actual_cost' => (float) ($timeRow['actual_cost'] ?? 0),
            'budget_planned' => $budgetPlanned,
            'total_milestones' => (int) ($mileRow['total_milestones'] ?? 0),
            'missed_milestones' => (int) ($mileRow['missed_milestones'] ?? 0),
            'billable_milestones' => (int) ($mileRow['billable_milestones'] ?? 0),
            'billable_done' => (int) ($mileRow['billable_done'] ?? 0),
        ];
    }

    public function getTaskBoard(int $projectId): array
    {
        $sql = '
            SELECT t.id, t.title, t.status, t.priority, t.due_date,
                   t.assigned_user_id, t.estimated_hours,
                   u.name AS assigned_user_name
            FROM project_tasks t
            LEFT JOIN users u ON u.id = t.assigned_user_id
            WHERE t.project_id = ? AND t.deleted_at IS NULL
            ORDER BY t.position ASC, t.id ASC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function getGanttRows(int $projectId): array
    {
        $sql = "
            SELECT
                'milestone' AS row_type,
                m.id AS row_id,
                m.name AS row_label,
                m.status AS task_status,
                m.due_date AS start_date,
                m.due_date AS end_date
            FROM project_milestones m
            WHERE m.project_id = ? AND m.deleted_at IS NULL

            UNION ALL

            SELECT
                'task' AS row_type,
                t.id AS row_id,
                t.title AS row_label,
                t.status AS task_status,
                t.start_date,
                t.due_date AS end_date
            FROM project_tasks t
            WHERE t.project_id = ? AND t.deleted_at IS NULL
            ORDER BY row_type DESC, end_date ASC, row_id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId, $projectId]);
        return $stmt->fetchAll();
    }

    public function getTimesheetRows(int $projectId, int $limit = 200): array
    {
        $sql = '
            SELECT
                ts.id,
                ts.user_id,
                ts.work_date,
                ts.hours,
                ts.note,
                u.name AS user_name,
                t.title AS task_title,
                COALESCE(pm.hourly_rate_override, 0) AS hourly_rate
            FROM project_timesheets ts
            INNER JOIN users u ON u.id = ts.user_id
            INNER JOIN project_tasks t ON t.id = ts.task_id
            LEFT JOIN project_members pm ON pm.project_id = ts.project_id AND pm.user_id = ts.user_id
            WHERE ts.project_id = ?
            ORDER BY ts.work_date DESC, ts.id DESC
            LIMIT ?
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId, $limit]);
        return $stmt->fetchAll();
    }

    public function getWidgetStats(int $userId, bool $canViewAll): array
    {
        if ($canViewAll) {
            $sql = "
                SELECT
                    COUNT(*) AS total_projects,
                    SUM(CASE WHEN status IN ('planning', 'active', 'on_hold') THEN 1 ELSE 0 END) AS active_projects,
                    SUM(CASE WHEN end_date IS NOT NULL AND end_date < CURDATE() AND status <> 'completed' THEN 1 ELSE 0 END) AS delayed_projects
                FROM projects
                WHERE deleted_at IS NULL
            ";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetch() ?: [];
        }

        $sql = "
            SELECT
                COUNT(*) AS total_projects,
                SUM(CASE WHEN p.status IN ('planning', 'active', 'on_hold') THEN 1 ELSE 0 END) AS active_projects,
                SUM(CASE WHEN p.end_date IS NOT NULL AND p.end_date < CURDATE() AND p.status <> 'completed' THEN 1 ELSE 0 END) AS delayed_projects
            FROM projects p
            WHERE p.deleted_at IS NULL
              AND (p.owner_user_id = ? OR EXISTS (
                    SELECT 1 FROM project_members pmx
                    WHERE pmx.project_id = p.id AND pmx.user_id = ?
              ))
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Conteggio progetti per stato (per il widget grafico della dashboard).
     *
     * @return array<string, int>  ['status' => count]
     */
    public function getStatusBreakdown(int $userId, bool $canViewAll): array
    {
        if ($canViewAll) {
            $stmt = $this->pdo->query(
                'SELECT status, COUNT(*) AS total
                 FROM projects
                 WHERE deleted_at IS NULL
                 GROUP BY status'
            );
            $rows = $stmt->fetchAll();
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT p.status, COUNT(*) AS total
                 FROM projects p
                 WHERE p.deleted_at IS NULL
                   AND (p.owner_user_id = ? OR EXISTS (
                         SELECT 1 FROM project_members pmx
                         WHERE pmx.project_id = p.id AND pmx.user_id = ?
                   ))
                 GROUP BY p.status'
            );
            $stmt->execute([$userId, $userId]);
            $rows = $stmt->fetchAll();
        }

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['total'];
        }
        return $out;
    }

    /**
     * Budget aggregato (pianificato vs consuntivo cache-ato) sui progetti attivi.
     *
     * @return array{planned: float, actual: float}
     */
    public function getBudgetAggregate(int $userId, bool $canViewAll): array
    {
        $statusFilter = "p.status IN ('planning', 'active', 'on_hold')";

        if ($canViewAll) {
            $stmt = $this->pdo->query(
                "SELECT
                     COALESCE(SUM(p.budget_planned), 0)        AS planned,
                     COALESCE(SUM(p.budget_actual_cached), 0)  AS actual
                 FROM projects p
                 WHERE p.deleted_at IS NULL AND {$statusFilter}"
            );
            $row = $stmt->fetch() ?: [];
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT
                     COALESCE(SUM(p.budget_planned), 0)        AS planned,
                     COALESCE(SUM(p.budget_actual_cached), 0)  AS actual
                 FROM projects p
                 WHERE p.deleted_at IS NULL AND {$statusFilter}
                   AND (p.owner_user_id = ? OR EXISTS (
                         SELECT 1 FROM project_members pmx
                         WHERE pmx.project_id = p.id AND pmx.user_id = ?
                   ))"
            );
            $stmt->execute([$userId, $userId]);
            $row = $stmt->fetch() ?: [];
        }

        return [
            'planned' => (float) ($row['planned'] ?? 0),
            'actual'  => (float) ($row['actual'] ?? 0),
        ];
    }

    /**
     * Task assegnati all'utente in scadenza (inclusi gli scaduti), non chiusi.
     */
    public function getMyTasksDueSoon(int $userId, int $days = 7): array
    {
        $days = max(1, min(365, $days));
        $sql = "
            SELECT
                t.id,
                t.title,
                t.status,
                t.priority,
                t.due_date,
                p.id   AS project_id,
                p.name AS project_name
            FROM project_tasks t
            INNER JOIN projects p ON p.id = t.project_id
            WHERE t.deleted_at IS NULL
              AND p.deleted_at IS NULL
              AND t.assigned_user_id = ?
              AND t.status NOT IN ('done', 'blocked')
              AND t.due_date IS NOT NULL
              AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY t.due_date ASC, t.id ASC
            LIMIT 8
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll();
    }

    /**
     * Milestone in scadenza (entro N giorni) sui progetti visibili all'utente, non chiuse.
     */
    public function getMilestonesDueSoon(int $userId, bool $canViewAll, int $days = 30): array
    {
        $days = max(1, min(365, $days));

        $where = [
            'm.deleted_at IS NULL',
            'p.deleted_at IS NULL',
            "m.status NOT IN ('done', 'missed')",
            'm.due_date IS NOT NULL',
            'm.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)',
        ];
        $params = [$days];

        if (!$canViewAll) {
            $where[]  = '(p.owner_user_id = ? OR EXISTS (
                            SELECT 1 FROM project_members pmx
                            WHERE pmx.project_id = p.id AND pmx.user_id = ?
                        ))';
            $params[] = $userId;
            $params[] = $userId;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT
                m.id,
                m.name,
                m.due_date,
                m.status,
                p.id   AS project_id,
                p.name AS project_name
            FROM project_milestones m
            INNER JOIN projects p ON p.id = m.project_id
            WHERE {$whereClause}
            ORDER BY m.due_date ASC, m.id ASC
            LIMIT 8
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTasksForUser(int $userId, array $filters = []): array
    {
        // $sort/$dir interpolati nell'ORDER BY: protetti da whitelist + fallback.
        // due_date/status sono qualificati con "t." perché sia project_tasks che
        // project_milestones (LEFT JOIN m) hanno una colonna con lo stesso nome:
        // un ORDER BY non qualificato sarebbe ambiguo (MySQL e SQLite lo rifiutano).
        $sortColumns = [
            'due_date' => 't.due_date',
            'priority' => 't.priority',
            'status' => 't.status',
            'project_name' => 'project_name',
            'title' => 't.title',
        ];
        $sortKey = in_array($filters['sort'] ?? '', array_keys($sortColumns), true) ? $filters['sort'] : 'due_date';
        $sort = $sortColumns[$sortKey];
        $dir  = strtoupper($filters['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
        assert(in_array($sort, $sortColumns, true) && in_array($dir, ['ASC', 'DESC'], true));

        $where = ['t.assigned_user_id = ?', 't.deleted_at IS NULL', 'p.deleted_at IS NULL'];
        $params = [$userId];

        if (!empty($filters['status'])) {
            $allowed = ['todo', 'in_progress', 'review', 'blocked', 'done'];
            if (in_array($filters['status'], $allowed, true)) {
                $where[] = 't.status = ?';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['priority'])) {
            $allowed = ['low', 'medium', 'high', 'urgent'];
            if (in_array($filters['priority'], $allowed, true)) {
                $where[] = 't.priority = ?';
                $params[] = $filters['priority'];
            }
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT
                t.id,
                t.title,
                t.description,
                t.status,
                t.priority,
                t.start_date,
                t.due_date,
                t.estimated_hours,
                t.assigned_user_id,
                t.milestone_id,
                m.name AS milestone_name,
                p.id AS project_id,
                p.name AS project_name,
                p.status AS project_status
            FROM project_tasks t
            INNER JOIN projects p ON p.id = t.project_id
            LEFT JOIN project_milestones m ON m.id = t.milestone_id AND m.deleted_at IS NULL
            WHERE $whereClause
            ORDER BY $sort $dir, t.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getMilestones(int $projectId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, description, due_date, status, billable
             FROM project_milestones
             WHERE project_id = ? AND deleted_at IS NULL
             ORDER BY due_date IS NULL, due_date ASC, id ASC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function getTasks(int $projectId): array
    {
        $sql = '
            SELECT
                t.id,
                t.title,
                t.description,
                t.milestone_id,
                t.assigned_user_id,
                t.status,
                t.priority,
                t.start_date,
                t.due_date,
                t.estimated_hours,
                m.name AS milestone_name,
                u.name AS assigned_user_name,
                COALESCE(dep.dep_count, 0) AS dependencies_count
            FROM project_tasks t
            LEFT JOIN project_milestones m ON m.id = t.milestone_id
            LEFT JOIN users u ON u.id = t.assigned_user_id
            LEFT JOIN (
                SELECT successor_task_id, COUNT(*) AS dep_count
                FROM project_task_dependencies
                GROUP BY successor_task_id
            ) dep ON dep.successor_task_id = t.id
            WHERE t.project_id = ? AND t.deleted_at IS NULL
            ORDER BY t.position ASC, t.id ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function getTaskOptions(int $projectId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title
             FROM project_tasks
             WHERE project_id = ? AND deleted_at IS NULL
             ORDER BY title ASC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function getMemberOptions(int $projectId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.name
             FROM project_members pm
             INNER JOIN users u ON u.id = pm.user_id
             WHERE pm.project_id = ?
             ORDER BY u.name ASC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function getMembers(int $projectId): array
    {
        $sqlWithAvatar =
            'SELECT pm.user_id, pm.role, pm.hourly_rate_override, pm.joined_at, u.name, u.avatar_path
             FROM project_members pm
             INNER JOIN users u ON u.id = pm.user_id
             WHERE pm.project_id = ?
             ORDER BY CASE pm.role WHEN "owner" THEN 0 WHEN "member" THEN 1 ELSE 2 END, u.name ASC';

        try {
            $stmt = $this->pdo->prepare($sqlWithAvatar);
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        } catch (\PDOException) {
            // SQLite test schema may not include users.avatar_path.
            $stmt = $this->pdo->prepare(
                'SELECT pm.user_id, pm.role, pm.hourly_rate_override, pm.joined_at, u.name, NULL AS avatar_path
                 FROM project_members pm
                 INNER JOIN users u ON u.id = pm.user_id
                 WHERE pm.project_id = ?
                 ORDER BY CASE pm.role WHEN "owner" THEN 0 WHEN "member" THEN 1 ELSE 2 END, u.name ASC'
            );
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        }
    }

    public function getAvailableUsersForProject(int $projectId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT u.id, u.name
             FROM users u
             LEFT JOIN project_members pm
                ON pm.user_id = u.id AND pm.project_id = ?
             INNER JOIN user_role ur ON ur.user_id = u.id
             INNER JOIN role_permission rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE pm.user_id IS NULL
               AND u.is_active = 1
               AND u.deleted_at IS NULL
               AND p.slug = ?
             ORDER BY u.name ASC'
        );
        $stmt->execute([$projectId, 'progetti.view']);
        return $stmt->fetchAll();
    }

    public function projectExists(int $projectId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM projects
             WHERE id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$projectId]);
        return (bool) $stmt->fetchColumn();
    }

    public function userExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function isProjectOwner(int $projectId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM projects
             WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$projectId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function findProjectMember(int $projectId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT project_id, user_id, role, hourly_rate_override, joined_at
             FROM project_members
             WHERE project_id = ? AND user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$projectId, $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function addProjectMember(int $projectId, int $userId, string $role, ?float $hourlyRate): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_members (project_id, user_id, role, hourly_rate_override)
             VALUES (?, ?, ?, ?)'
        );
        return $stmt->execute([$projectId, $userId, $role, $hourlyRate]);
    }

    public function updateProjectMember(int $projectId, int $userId, string $role, ?float $hourlyRate): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_members
             SET role = ?, hourly_rate_override = ?
             WHERE project_id = ? AND user_id = ?'
        );
        return $stmt->execute([$role, $hourlyRate, $projectId, $userId]);
    }

    public function removeProjectMember(int $projectId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM project_members
             WHERE project_id = ? AND user_id = ?'
        );
        return $stmt->execute([$projectId, $userId]);
    }

    public function countTasksAssignedToMember(int $projectId, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM project_tasks
             WHERE project_id = ? AND assigned_user_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$projectId, $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function createMilestone(array $data): int
    {
        $sql = '
            INSERT INTO project_milestones
            (project_id, name, description, due_date, billable, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['project_id'],
            $data['name'],
            $data['description'],
            $data['due_date'],
            $data['billable'],
            $data['status'],
            $data['created_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findMilestone(int $projectId, int $milestoneId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM project_milestones
             WHERE id = ? AND project_id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$milestoneId, $projectId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateMilestone(int $projectId, int $milestoneId, array $data): bool
    {
        $sql = '
            UPDATE project_milestones
            SET name = ?, description = ?, due_date = ?, status = ?, billable = ?, updated_at = ?
            WHERE id = ? AND project_id = ? AND deleted_at IS NULL
        ';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['due_date'],
            $data['status'],
            $data['billable'],
            $data['updated_at'],
            $milestoneId,
            $projectId,
        ]);
    }

    public function deleteMilestone(int $projectId, int $milestoneId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_milestones
             SET deleted_at = NOW()
             WHERE id = ? AND project_id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$milestoneId, $projectId]);
    }

    public function getNextTaskPosition(int $projectId, string $status): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position), 0) + 1
             FROM project_tasks
             WHERE project_id = ? AND status = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$projectId, $status]);
        return (int) $stmt->fetchColumn();
    }

    public function createTask(array $data): int
    {
        $sql = '
            INSERT INTO project_tasks
            (project_id, milestone_id, title, description, assigned_user_id, priority, status, start_date, due_date, estimated_hours, position, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['project_id'],
            $data['milestone_id'],
            $data['title'],
            $data['description'],
            $data['assigned_user_id'],
            $data['priority'],
            $data['status'],
            $data['start_date'],
            $data['due_date'],
            $data['estimated_hours'],
            $data['position'],
            $data['created_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findTask(int $projectId, int $taskId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM project_tasks
             WHERE id = ? AND project_id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$taskId, $projectId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateTask(int $projectId, int $taskId, array $data): bool
    {
        $sql = '
            UPDATE project_tasks
            SET title = ?, description = ?, milestone_id = ?, assigned_user_id = ?, priority = ?, status = ?, start_date = ?, due_date = ?, estimated_hours = ?, completed_at = ?, updated_at = ?
            WHERE id = ? AND project_id = ? AND deleted_at IS NULL
        ';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['title'],
            $data['description'],
            $data['milestone_id'],
            $data['assigned_user_id'],
            $data['priority'],
            $data['status'],
            $data['start_date'],
            $data['due_date'],
            $data['estimated_hours'],
            $data['completed_at'],
            $data['updated_at'],
            $taskId,
            $projectId,
        ]);
    }

    public function deleteTask(int $projectId, int $taskId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_tasks
             SET deleted_at = NOW()
             WHERE id = ? AND project_id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$taskId, $projectId]);
    }

    public function countOpenPredecessors(int $projectId, int $taskId): int
    {
        $sql = '
            SELECT COUNT(*)
            FROM project_task_dependencies d
            INNER JOIN project_tasks p
                ON p.id = d.predecessor_task_id
               AND p.project_id = ?
               AND p.deleted_at IS NULL
            WHERE d.successor_task_id = ?
              AND p.status <> "done"
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId, $taskId]);
        return (int) $stmt->fetchColumn();
    }

    public function dependencyExists(int $predecessorTaskId, int $successorTaskId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM project_task_dependencies
             WHERE predecessor_task_id = ? AND successor_task_id = ?'
        );
        $stmt->execute([$predecessorTaskId, $successorTaskId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getDependencyEdges(int $projectId): array
    {
        $sql = '
            SELECT d.predecessor_task_id, d.successor_task_id
            FROM project_task_dependencies d
            INNER JOIN project_tasks p1 ON p1.id = d.predecessor_task_id AND p1.project_id = ? AND p1.deleted_at IS NULL
            INNER JOIN project_tasks p2 ON p2.id = d.successor_task_id AND p2.project_id = ? AND p2.deleted_at IS NULL
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId, $projectId]);
        return $stmt->fetchAll();
    }

    public function createDependency(int $predecessorTaskId, int $successorTaskId): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_task_dependencies (predecessor_task_id, successor_task_id, dependency_type)
             VALUES (?, ?, "FS")'
        );
        return $stmt->execute([$predecessorTaskId, $successorTaskId]);
    }

    public function deleteDependency(int $predecessorTaskId, int $successorTaskId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM project_task_dependencies
             WHERE predecessor_task_id = ? AND successor_task_id = ?'
        );
        return $stmt->execute([$predecessorTaskId, $successorTaskId]);
    }

    public function setTeamsConversation(int $projectId, int $conversationId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE projects
             SET teams_conversation_id = ?, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$conversationId, $projectId]);
    }

    public function setMilestoneCalendarEvent(int $milestoneId, ?int $calEventId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_milestones
             SET calendar_event_id = ?, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$calEventId, $milestoneId]);
    }

    public function setTaskCalendarEvent(int $taskId, ?int $calEventId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_tasks
             SET calendar_event_id = ?, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$calEventId, $taskId]);
    }

    public function getProjectFiles(int $projectId): array
    {
        $sql = '
            SELECT pf.file_id, pf.linked_by, pf.linked_at,
                   f.original_name, f.stored_name, f.directory, f.mime_type, f.extension, f.size_bytes,
                   u.name AS linked_by_name
            FROM project_files pf
            INNER JOIN files f ON f.id = pf.file_id AND f.deleted_at IS NULL
            LEFT JOIN users u ON u.id = pf.linked_by
            WHERE pf.project_id = ?
            ORDER BY pf.linked_at DESC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function isFileLinkExists(int $projectId, int $fileId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM project_files WHERE project_id = ? AND file_id = ?'
        );
        $stmt->execute([$projectId, $fileId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function linkFile(int $projectId, int $fileId, int $linkedBy): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO project_files (project_id, file_id, linked_by) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$projectId, $fileId, $linkedBy]);
    }

    public function unlinkFile(int $projectId, int $fileId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM project_files WHERE project_id = ? AND file_id = ?'
        );
        return $stmt->execute([$projectId, $fileId]);
    }

    public function getActiveLinkedFileIds(int $projectId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pf.file_id
             FROM project_files pf
             INNER JOIN files f ON f.id = pf.file_id AND f.deleted_at IS NULL
             WHERE pf.project_id = ?'
        );
        $stmt->execute([$projectId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'file_id'));
    }

    public function getReportData(int $projectId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT status,
                   COUNT(*) AS cnt,
                   COALESCE(SUM(estimated_hours), 0) AS estimated_hours
            FROM project_tasks
            WHERE project_id = ? AND deleted_at IS NULL
            GROUP BY status
        ');
        $stmt->execute([$projectId]);
        $tasksByStatus = $stmt->fetchAll();

        $stmt = $this->pdo->prepare('
            SELECT status, COUNT(*) AS cnt,
                   SUM(CASE WHEN billable = 1 THEN 1 ELSE 0 END) AS billable_cnt
            FROM project_milestones
            WHERE project_id = ? AND deleted_at IS NULL
            GROUP BY status
        ');
        $stmt->execute([$projectId]);
        $milestonesSummary = $stmt->fetchAll();

        $stmt = $this->pdo->prepare('
            SELECT u.name AS user_name,
                   SUM(pt.hours) AS total_hours,
                   COALESCE(SUM(pt.hours * COALESCE(pm.hourly_rate_override, 0)), 0) AS total_cost
            FROM project_timesheets pt
            INNER JOIN users u ON u.id = pt.user_id
            LEFT JOIN project_members pm
                ON pm.project_id = pt.project_id AND pm.user_id = pt.user_id
            WHERE pt.project_id = ?
            GROUP BY pt.user_id, u.name
            ORDER BY total_hours DESC
        ');
        $stmt->execute([$projectId]);
        $timesheetByUser = $stmt->fetchAll();

        $stmt = $this->pdo->prepare('
            SELECT t.title, t.status, t.priority, t.due_date, t.completed_at, t.estimated_hours,
                   u.name AS assigned_user_name,
                   m.name AS milestone_name
            FROM project_tasks t
            LEFT JOIN users u ON u.id = t.assigned_user_id
            LEFT JOIN project_milestones m ON m.id = t.milestone_id AND m.deleted_at IS NULL
            WHERE t.project_id = ? AND t.deleted_at IS NULL
            ORDER BY t.status ASC, t.due_date ASC
        ');
        $stmt->execute([$projectId]);
        $tasksList = $stmt->fetchAll();

        $stmt = $this->pdo->prepare('
            SELECT m.id, m.name, m.status, m.due_date, m.billable, m.description,
                   m.progress_cached,
                   COUNT(t.id)                                           AS task_count,
                   SUM(CASE WHEN t.status = \'done\' THEN 1 ELSE 0 END) AS done_tasks
            FROM project_milestones m
            LEFT JOIN project_tasks t ON t.milestone_id = m.id AND t.deleted_at IS NULL
            WHERE m.project_id = ? AND m.deleted_at IS NULL
            GROUP BY m.id, m.name, m.status, m.due_date, m.billable, m.description, m.progress_cached
            ORDER BY m.due_date ASC, m.name ASC
        ');
        $stmt->execute([$projectId]);
        $milestonesList = $stmt->fetchAll();

        return [
            'tasks_by_status'    => $tasksByStatus,
            'milestones_summary' => $milestonesSummary,
            'milestones_list'    => $milestonesList,
            'timesheet_by_user'  => $timesheetByUser,
            'tasks_list'         => $tasksList,
        ];
    }

    public function getBudgetByMilestone(int $projectId): array
    {
        $sql = "
            SELECT
                COALESCE(m.name, '— Senza milestone') AS milestone_name,
                COALESCE(m.status, '')               AS milestone_status,
                COUNT(DISTINCT t.id)                 AS task_count,
                COALESCE(SUM(t.estimated_hours), 0)  AS estimated_hours,
                COALESCE(SUM(ts_agg.hours_sum), 0)   AS consumed_hours,
                COALESCE(SUM(ts_agg.cost_sum), 0)    AS consumed_cost
            FROM project_tasks t
            LEFT JOIN project_milestones m ON m.id = t.milestone_id AND m.deleted_at IS NULL
            LEFT JOIN (
                SELECT task_id,
                       SUM(ts.hours) AS hours_sum,
                       SUM(ts.hours * COALESCE(pm.hourly_rate_override, 0)) AS cost_sum
                FROM project_timesheets ts
                LEFT JOIN project_members pm ON pm.project_id = ts.project_id AND pm.user_id = ts.user_id
                GROUP BY task_id
            ) ts_agg ON ts_agg.task_id = t.id
            WHERE t.project_id = ? AND t.deleted_at IS NULL
            GROUP BY t.milestone_id, m.name, m.status
            ORDER BY m.due_date IS NULL, m.due_date ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function createTimesheet(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_timesheets (project_id, task_id, user_id, work_date, hours, note)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['task_id'],
            $data['user_id'],
            $data['work_date'],
            $data['hours'],
            $data['note'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateTimesheet(int $projectId, int $timesheetId, int $userId, float $hours, ?string $note): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_timesheets
             SET hours = ?, note = ?
             WHERE id = ? AND project_id = ? AND user_id = ?'
        );
        return $stmt->execute([$hours, $note, $timesheetId, $projectId, $userId]);
    }

    public function updateTimesheetAdmin(int $projectId, int $timesheetId, float $hours, ?string $note): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_timesheets
             SET hours = ?, note = ?
             WHERE id = ? AND project_id = ?'
        );
        return $stmt->execute([$hours, $note, $timesheetId, $projectId]);
    }

    public function deleteTimesheet(int $projectId, int $timesheetId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM project_timesheets
             WHERE id = ? AND project_id = ? AND user_id = ?'
        );
        return $stmt->execute([$timesheetId, $projectId, $userId]);
    }

    public function deleteTimesheetAdmin(int $projectId, int $timesheetId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM project_timesheets
             WHERE id = ? AND project_id = ?'
        );
        return $stmt->execute([$timesheetId, $projectId]);
    }

    public function updateTaskStatus(int $projectId, int $taskId, string $status, ?string $completedAt): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_tasks
             SET status = ?, completed_at = ?, updated_at = NOW()
             WHERE id = ? AND project_id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$status, $completedAt, $taskId, $projectId]);
    }

    public function updateTaskPositionAndStatus(int $projectId, int $taskId, string $status, int $position, ?string $completedAt = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_tasks
             SET status = ?, position = ?, completed_at = ?, updated_at = NOW()
             WHERE id = ? AND project_id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$status, $position, $completedAt, $taskId, $projectId]);
    }

    public function updateProgressCache(int $projectId): void
    {
        $sql = '
            UPDATE projects
            SET progress_cached = (
                SELECT CASE WHEN COUNT(*) = 0 THEN 0.00
                            ELSE ROUND(SUM(CASE WHEN status = "done" THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2)
                       END
                FROM project_tasks
                WHERE project_id = projects.id AND deleted_at IS NULL
            )
            WHERE id = ? AND deleted_at IS NULL
        ';
        $this->pdo->prepare($sql)->execute([$projectId]);
    }

    public function updateBudgetCache(int $projectId): void
    {
        $sql = '
            UPDATE projects
            SET budget_actual_cached = (
                SELECT COALESCE(SUM(pt.hours * COALESCE(pm.hourly_rate_override, 0)), 0)
                FROM project_timesheets pt
                LEFT JOIN project_members pm
                    ON pm.project_id = pt.project_id AND pm.user_id = pt.user_id
                WHERE pt.project_id = projects.id
            )
            WHERE id = ? AND deleted_at IS NULL
        ';
        $this->pdo->prepare($sql)->execute([$projectId]);
    }

    /**
     * Ore totali registrate per ciascun membro del team (per grafici KPI).
     * Restituisce array ordinato per ore decrescenti.
     */
    public function getTimesheetByUser(int $projectId): array
    {
        $sql = '
            SELECT u.name AS user_name,
                   ROUND(COALESCE(SUM(pt.hours), 0), 2) AS hours,
                   ROUND(COALESCE(SUM(pt.hours * COALESCE(pm.hourly_rate_override, 0)), 0), 2) AS cost
            FROM project_members pm
            INNER JOIN users u ON u.id = pm.user_id
            LEFT JOIN project_timesheets pt
                ON pt.project_id = pm.project_id AND pt.user_id = pm.user_id
            WHERE pm.project_id = ?
            GROUP BY pm.user_id, u.name
            ORDER BY hours DESC, u.name ASC
            LIMIT 20
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /**
     * Ore giornaliere delle ultime $days settimane (per grafico trend KPI).
     * Aggrega per settimana ISO (lunedì) per limitare i punti nel grafico.
     */
    public function getTimesheetTrend(int $projectId, int $weeks = 10): array
    {
        $sql = '
            SELECT DATE_FORMAT(
                       DATE_SUB(work_date, INTERVAL (WEEKDAY(work_date)) DAY),
                       "%Y-%m-%d"
                   ) AS week_start,
                   ROUND(SUM(hours), 2) AS hours
            FROM project_timesheets
            WHERE project_id = ?
              AND work_date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
            GROUP BY week_start
            ORDER BY week_start ASC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$projectId, $weeks]);
        return $stmt->fetchAll();
    }

    /**
     * Task con scadenza entro N giorni (non completati e assegnati).
     * Usato per reminder notifiche automatiche.
     */
    public function getTasksDueSoon(int $limit = 500): array
    {
        $sql = '
            SELECT
                t.id,
                t.project_id,
                p.name AS project_name,
                t.title AS task_title,
                t.due_date,
                t.assigned_user_id,
                t.last_reminded_date
            FROM project_tasks t
            INNER JOIN projects p ON p.id = t.project_id
            WHERE t.deleted_at IS NULL
              AND p.deleted_at IS NULL
              AND t.assigned_user_id IS NOT NULL
              AND t.status NOT IN (\'done\', \'blocked\')
              AND t.due_date IS NOT NULL
            ORDER BY t.due_date ASC, t.id ASC
            LIMIT ?
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
