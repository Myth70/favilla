<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Repositories;

use App\Repositories\BaseRepository;

class TasksRepository extends BaseRepository
{
    protected string $table     = 'tasks';
    protected bool $softDelete  = true;
    protected bool $timestamps  = true;
    protected bool $auditable   = true;
    protected string $auditEntity = 'tasks';

    protected array $fillable = [
        'title', 'description', 'status', 'priority', 'due_date', 'due_time',
        'color', 'position', 'completed_at', 'calendar_event_id', 'user_id',
    ];

    /**
     * Tutte le attività dell'utente raggruppate per status (per kanban).
     */
    public function getBoardForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, 
                    (SELECT COUNT(*) FROM task_checklist c WHERE c.task_id = a.id) AS checklist_total,
                    (SELECT COUNT(*) FROM task_checklist c WHERE c.task_id = a.id AND c.is_done = 1) AS checklist_done
             FROM {$this->table} a
             WHERE a.user_id = ? AND a.deleted_at IS NULL
             ORDER BY a.position ASC, a.created_at DESC"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        // Carica i tag per tutte le attività in batch
        $taskIds = array_column($rows, 'id');
        $tagsByTask = $this->getTagsForTasks($taskIds);

        $board = [
            'backlog'     => [],
            'todo'        => [],
            'in_progress' => [],
            'review'      => [],
            'done'        => [],
        ];

        foreach ($rows as $row) {
            $row['tags'] = $tagsByTask[(int) $row['id']] ?? [];
            $board[$row['status']][] = $row;
        }

        return $board;
    }

    /**
     * Lista paginata con filtri.
     */
    public function listPaginated(int $userId, array $filters = []): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $where  = ['a.user_id = ?', 'a.deleted_at IS NULL'];
        $params = [$userId];

        if (!empty($filters['q'])) {
            $where[]  = '(a.title LIKE ? OR a.description LIKE ?)';
            $like     = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where[]  = 'a.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['scope'])) {
            switch ($filters['scope']) {
                case 'today':
                    $where[] = 'a.due_date = CURDATE() AND a.status <> ?';
                    $params[] = 'done';
                    break;
                case 'week':
                    $where[] = 'a.due_date IS NOT NULL AND a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND a.status <> ?';
                    $params[] = 'done';
                    break;
                case 'overdue':
                    $where[] = 'a.due_date IS NOT NULL AND a.due_date < CURDATE() AND a.status <> ?';
                    $params[] = 'done';
                    break;
                case 'linked':
                    $where[] = 'a.calendar_event_id IS NOT NULL AND a.calendar_event_id > 0';
                    break;
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $allowedSorts = ['title', 'status', 'priority', 'due_date', 'created_at', 'position'];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true)
            ? $filters['sort']
            : 'position';
        $dir = (strtolower($filters['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} a {$whereSql}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM task_checklist c WHERE c.task_id = a.id) AS checklist_total,
                    (SELECT COUNT(*) FROM task_checklist c WHERE c.task_id = a.id AND c.is_done = 1) AS checklist_done
             FROM {$this->table} a
             {$whereSql}
             ORDER BY a.{$sort} {$dir}
             {$this->limitClause($perPage, $offset)}"
        );
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        // Carica tag in batch
        $taskIds = array_column($data, 'id');
        $tagsByTask = $this->getTagsForTasks($taskIds);
        foreach ($data as &$row) {
            $row['tags'] = $tagsByTask[(int) $row['id']] ?? [];
        }

        return [
            'data'     => $data,
            'total'    => $total,
            'lastPage' => (int) ceil($total / max($perPage, 1)),
            'page'     => $page,
        ];
    }

    /**
     * Singola attività con checklist e tag (solo se dell'utente).
     */
    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM task_checklist c WHERE c.task_id = a.id) AS checklist_total,
                    (SELECT COUNT(*) FROM task_checklist c WHERE c.task_id = a.id AND c.is_done = 1) AS checklist_done
             FROM {$this->table} a
             WHERE a.id = ? AND a.user_id = ? AND a.deleted_at IS NULL"
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['checklist'] = $this->getChecklist($id);
        $row['tags'] = $this->getTagsForTask($id);

        return $row;
    }

    /**
     * Aggiorna posizione e status (kanban drag & drop).
     */
    public function moveTask(int $id, string $status, int $position): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET status = ?, position = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$status, $position, $id]);
    }

    /**
     * Segna come completata / riapri.
     */
    public function toggleComplete(int $id, bool $done): bool
    {
        if ($done) {
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET status = 'done', completed_at = NOW(), updated_at = NOW() WHERE id = ?"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET status = 'todo', completed_at = NULL, updated_at = NOW() WHERE id = ?"
            );
        }
        return $stmt->execute([$id]);
    }

    /**
     * Prossima posizione disponibile nello status.
     */
    public function getNextPosition(int $userId, string $status): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->table}
             WHERE user_id = ? AND status = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$userId, $status]);
        return (int) $stmt->fetchColumn();
    }

    // ── Checklist ────────────────────────────────────────────────────

    public function getChecklist(int $taskId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM task_checklist WHERE task_id = ? ORDER BY position ASC, id ASC'
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public function addChecklistItem(int $taskId, string $text): int
    {
        $pos = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM task_checklist WHERE task_id = ?'
        );
        $pos->execute([$taskId]);
        $position = (int) $pos->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO task_checklist (task_id, text, position) VALUES (?, ?, ?)'
        );
        $stmt->execute([$taskId, $text, $position]);
        return (int) $this->pdo->lastInsertId();
    }

    public function toggleChecklistItem(int $itemId, int $taskId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE task_checklist SET is_done = NOT is_done WHERE id = ? AND task_id = ?'
        );
        return $stmt->execute([$itemId, $taskId]);
    }

    public function deleteChecklistItem(int $itemId, int $taskId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM task_checklist WHERE id = ? AND task_id = ?'
        );
        return $stmt->execute([$itemId, $taskId]);
    }

    // ── Tags ─────────────────────────────────────────────────────────

    public function getUserTags(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, COUNT(tm.task_id) AS usage_count
             FROM task_tags t
             LEFT JOIN task_tag_map tm ON tm.tag_id = t.id
             WHERE t.user_id = ?
             GROUP BY t.id
             ORDER BY t.name ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function createTag(int $userId, string $name, string $color): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO task_tags (name, color, user_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $color, $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteTag(int $tagId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM task_tags WHERE id = ? AND user_id = ?');
        return $stmt->execute([$tagId, $userId]);
    }

    public function syncTags(int $taskId, array $tagIds, int $userId): void
    {
        $this->pdo->prepare('DELETE FROM task_tag_map WHERE task_id = ?')->execute([$taskId]);

        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
        if (empty($tagIds)) {
            return;
        }

        $allowedTagIds = $this->getAllowedTagIdsForUser($userId, $tagIds);
        if (empty($allowedTagIds)) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT IGNORE INTO task_tag_map (task_id, tag_id) VALUES (?, ?)');
        foreach ($allowedTagIds as $tagId) {
            $stmt->execute([$taskId, $tagId]);
        }
    }

    private function getAllowedTagIdsForUser(int $userId, array $tagIds): array
    {
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $params = array_merge([$userId], $tagIds);
        $stmt = $this->pdo->prepare(
            "SELECT id FROM task_tags WHERE user_id = ? AND id IN ({$placeholders})"
        );
        $stmt->execute($params);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    public function getTagsForTask(int $taskId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM task_tags t
             JOIN task_tag_map tm ON tm.tag_id = t.id
             WHERE tm.task_id = ?
             ORDER BY t.name ASC'
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public function getTagsForTasks(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT tm.task_id, t.* FROM task_tags t
             JOIN task_tag_map tm ON tm.tag_id = t.id
             WHERE tm.task_id IN ({$placeholders})
             ORDER BY t.name ASC"
        );
        $stmt->execute($taskIds);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['task_id']][] = $row;
        }
        return $result;
    }

    // ── Statistiche ──────────────────────────────────────────────────

    public function countByStatus(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT status, COUNT(*) AS total FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
             GROUP BY status"
        );
        $stmt->execute([$userId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }

    public function countOverdue(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND due_date < CURDATE()
               AND status <> 'done'"
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function countDueSoon(int $userId, int $days = 3): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND due_date IS NOT NULL
               AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND status <> 'done'"
        );
        $stmt->execute([$userId, $days]);

        return (int) $stmt->fetchColumn();
    }

    public function getOverdue(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND due_date < CURDATE()
               AND status NOT IN ('done')
             ORDER BY due_date ASC
             LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public function getDueToday(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND due_date = CURDATE()
               AND status NOT IN ('done')
             ORDER BY due_time ASC, priority DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getDueSoon(int $userId, int $days = 3, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND due_date IS NOT NULL
               AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND status NOT IN ('done')
             ORDER BY due_date ASC, due_time ASC
             LIMIT ?"
        );
        $stmt->execute([$userId, $days, $limit]);
        return $stmt->fetchAll();
    }

    public function getCompletedThisWeek(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND status = 'done'
               AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function countCompletedToday(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND status = 'done'
               AND DATE(completed_at) = CURDATE()"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getCompletedToday(int $userId, int $limit = 25): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND status = 'done'
               AND DATE(completed_at) = CURDATE()
             ORDER BY completed_at DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public function getWeeklyTrend(int $userId, int $weeks = 8): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(completed_at, '%Y-%u') AS week_key,
                    MIN(DATE(completed_at)) AS week_start,
                    COUNT(*) AS completed
             FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND status = 'done'
               AND completed_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
             GROUP BY week_key
             ORDER BY week_key ASC"
        );
        $stmt->execute([$userId, $weeks]);
        return $stmt->fetchAll();
    }

    /**
     * Ricerca testuale per la global search.
     */
    public function searchForUser(int $userId, string $query, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND (title LIKE ? OR description LIKE ?)
             ORDER BY
                CASE WHEN status = 'done' THEN 1 ELSE 0 END ASC,
                updated_at DESC
             LIMIT ?"
        );
        $like = '%' . $query . '%';
        $stmt->execute([$userId, $like, $like, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Attività con scadenza che non hanno ancora ricevuto la notifica.
     */
    public function getTasksNeedingReminder(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE user_id = ? AND deleted_at IS NULL
               AND due_date IS NOT NULL
               AND due_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
               AND status NOT IN ('done')
               AND (calendar_event_id IS NULL OR calendar_event_id = 0)
             ORDER BY due_date ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
