<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Repositories;

use App\Repositories\BaseRepository;

class ChecklistRepository extends BaseRepository
{
    protected string $table = 'project_task_checklist_items';

    // ─── Checklist items ─────────────────────────────────────────────────────

    public function getItemsByTask(int $taskId): array
    {
        $sql = 'SELECT ci.*, u.name AS done_by_name
                FROM project_task_checklist_items ci
                LEFT JOIN users u ON u.id = ci.done_by
                WHERE ci.task_id = ?
                ORDER BY ci.position ASC, ci.id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function createItem(array $data): int
    {
        $sql = 'INSERT INTO project_task_checklist_items
                    (task_id, label, position, created_by)
                VALUES (?, ?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['task_id'],
            $data['label'],
            $data['position'] ?? 0,
            $data['created_by'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findItem(int $taskId, int $itemId): ?array
    {
        $sql = 'SELECT * FROM project_task_checklist_items
                WHERE id = ? AND task_id = ?
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$itemId, $taskId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateItemLabel(int $itemId, string $label): bool
    {
        $sql = 'UPDATE project_task_checklist_items
                SET label = ?, updated_at = NOW()
                WHERE id = ? AND is_done = 0';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$label, $itemId]);
        return $stmt->rowCount() > 0;
    }

    public function checkItem(int $itemId, int $userId, ?string $comment): bool
    {
        $sql = 'UPDATE project_task_checklist_items
                SET is_done = 1, done_at = NOW(), done_by = ?, comment = ?, updated_at = NOW()
                WHERE id = ? AND is_done = 0';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $comment, $itemId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteItem(int $taskId, int $itemId): bool
    {
        $sql = 'DELETE FROM project_task_checklist_items
                WHERE id = ? AND task_id = ? AND is_done = 0';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$itemId, $taskId]);
        return $stmt->rowCount() > 0;
    }

    public function reorderItems(int $taskId, array $orderedIds): void
    {
        if (empty($orderedIds)) {
            return;
        }
        // Build UPDATE ... CASE WHEN for atomic reorder
        $cases    = '';
        $bindings = [];
        foreach ($orderedIds as $position => $id) {
            $cases      .= ' WHEN id = ? THEN ?';
            $bindings[]  = (int) $id;
            $bindings[]  = $position;
        }
        $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));
        $bindings[]   = $taskId;
        foreach ($orderedIds as $id) {
            $bindings[] = (int) $id;
        }

        $sql = "UPDATE project_task_checklist_items
                SET position = CASE {$cases} ELSE position END,
                    updated_at = NOW()
                WHERE task_id = ?
                  AND id IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
    }

    public function countItems(int $taskId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM project_task_checklist_items WHERE task_id = ?'
        );
        $stmt->execute([$taskId]);
        return (int) $stmt->fetchColumn();
    }

    public function countDoneItems(int $taskId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM project_task_checklist_items WHERE task_id = ? AND is_done = 1'
        );
        $stmt->execute([$taskId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Restituisce [taskId => ['total' => N, 'done' => M]] in una singola query.
     * Evita N+1 nel kanban e in my_tasks.
     */
    public function getChecklistCountsForTasks(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $sql = "SELECT task_id,
                       COUNT(*) AS total,
                       SUM(is_done) AS done
                FROM project_task_checklist_items
                WHERE task_id IN ({$placeholders})
                GROUP BY task_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($taskIds));
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['task_id']] = [
                'total' => (int) $row['total'],
                'done'  => (int) $row['done'],
            ];
        }
        return $result;
    }

    public function getNextPosition(int $taskId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position), -1) + 1
             FROM project_task_checklist_items
             WHERE task_id = ?'
        );
        $stmt->execute([$taskId]);
        return (int) $stmt->fetchColumn();
    }

    // ─── Templates ───────────────────────────────────────────────────────────

    public function getTemplates(): array
    {
        $sql = 'SELECT t.*, u.name AS created_by_name,
                       COUNT(ti.id) AS item_count
                FROM project_checklist_templates t
                LEFT JOIN users u ON u.id = t.created_by
                LEFT JOIN project_checklist_template_items ti ON ti.template_id = t.id
                WHERE t.deleted_at IS NULL
                GROUP BY t.id
                ORDER BY t.name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTemplatesSimple(): array
    {
        $sql = 'SELECT id, name FROM project_checklist_templates
                WHERE deleted_at IS NULL
                ORDER BY name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findTemplate(int $tplId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM project_checklist_templates WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$tplId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createTemplate(string $name, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_checklist_templates (name, created_by) VALUES (?, ?)'
        );
        $stmt->execute([$name, $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateTemplateName(int $tplId, string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_checklist_templates SET name = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$name, $tplId]);
        return $stmt->rowCount() > 0;
    }

    public function softDeleteTemplate(int $tplId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE project_checklist_templates SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$tplId]);
        return $stmt->rowCount() > 0;
    }

    public function getTemplateItems(int $tplId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM project_checklist_template_items
             WHERE template_id = ?
             ORDER BY position ASC, id ASC'
        );
        $stmt->execute([$tplId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function deleteTemplateItems(int $tplId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM project_checklist_template_items WHERE template_id = ?'
        );
        $stmt->execute([$tplId]);
    }

    public function createTemplateItem(int $tplId, string $label, int $position): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_checklist_template_items (template_id, label, position) VALUES (?, ?, ?)'
        );
        $stmt->execute([$tplId, $label, $position]);
        return (int) $this->pdo->lastInsertId();
    }
}
