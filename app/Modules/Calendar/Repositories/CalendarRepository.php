<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Repositories;

use App\Repositories\BaseRepository;

class CalendarRepository extends BaseRepository
{
    protected string $table    = 'calendar_events';
    protected bool $softDelete = true;
    protected bool $timestamps = true;
    protected bool $auditable  = true;
    protected string $auditEntity = 'calendar_event';

    protected array $fillable = [
        'title', 'description', 'start_datetime', 'end_datetime',
        'all_day', 'color', 'category', 'location', 'visibility',
        'visible_to_role', 'reminder_minutes', 'recurrence_rule',
        'recurrence_end', 'created_by',
    ];

    /**
     * Eventi nel range visibili all'utente (personali + ruolo).
     */
    public function findByDateRange(string $start, string $end, int $userId, array $roleIds): array
    {
        $where  = ['e.deleted_at IS NULL'];
        $params = [];

        // Include both normal overlap and recurring masters.
        $where[]  = '((e.start_datetime < ? AND (e.end_datetime > ? OR e.end_datetime IS NULL)) OR e.recurrence_rule IS NOT NULL)';
        $params[] = $end;
        $params[] = $start;

        // Visibility filter:
        // 1. creator vede sempre i propri eventi (personale, ruolo, pubblico)
        // 2. eventi pubblici visibili a tutti
        // 3. eventi per ruolo: utenti con quel ruolo
        $visConds = ['(e.created_by = ?)'];
        $params[] = $userId;

        $visConds[] = 'e.visibility = ?';
        $params[]   = 'public';

        if (!empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $visConds[]   = "(e.visibility = ? AND e.visible_to_role IN ($placeholders))";
            $params[]     = 'role';
            $params       = array_merge($params, $roleIds);
        }

        $where[] = '(' . implode(' OR ', $visConds) . ')';

        $sql = "SELECT e.*, u.name AS creator_name
                FROM {$this->table} e
                LEFT JOIN users u ON u.id = e.created_by
                WHERE " . implode(' AND ', $where) . '
                ORDER BY e.start_datetime ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Prossimi N eventi visibili all'utente.
     */
    public function findUpcoming(int $userId, array $roleIds, int $limit = 5): array
    {
        $params = [];

        $visConds = ['(e.created_by = ?)'];
        $params[] = $userId;

        $visConds[] = 'e.visibility = ?';
        $params[]   = 'public';

        if (!empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $visConds[]   = "(e.visibility = ? AND e.visible_to_role IN ($placeholders))";
            $params[]     = 'role';
            $params       = array_merge($params, $roleIds);
        }

        $visFilter = '(' . implode(' OR ', $visConds) . ')';
        $params[]  = $limit;

        $sql = "SELECT e.*, u.name AS creator_name
                FROM {$this->table} e
                LEFT JOIN users u ON u.id = e.created_by
                WHERE e.deleted_at IS NULL
                  AND e.start_datetime >= NOW()
                  AND {$visFilter}
                ORDER BY e.start_datetime ASC
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Conteggio eventi nei prossimi N giorni.
     */
    public function countUpcoming(int $userId, array $roleIds, int $days = 7): int
    {
        $params = [];

        $visConds = ['(e.created_by = ?)'];
        $params[] = $userId;

        $visConds[] = 'e.visibility = ?';
        $params[]   = 'public';

        if (!empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $visConds[]   = "(e.visibility = ? AND e.visible_to_role IN ($placeholders))";
            $params[]     = 'role';
            $params       = array_merge($params, $roleIds);
        }

        $visFilter = '(' . implode(' OR ', $visConds) . ')';
        $params[]  = $days;

        $sql = "SELECT COUNT(*) FROM {$this->table} e
                WHERE e.deleted_at IS NULL
                  AND e.start_datetime >= NOW()
                  AND e.start_datetime <= DATE_ADD(NOW(), INTERVAL ? DAY)
                  AND {$visFilter}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Snapshot conteggi per la hero della pagina indice.
     *
     * @return array{owned:int,shared:int,all_day:int,visible_total:int}
     */
    public function getHeroStats(int $userId, array $roleIds): array
    {
        $params = [];

        $visConds = ['(e.created_by = ?)'];
        $params[] = $userId;

        $visConds[] = 'e.visibility = ?';
        $params[]   = 'public';

        if (!empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $visConds[]   = "(e.visibility = ? AND e.visible_to_role IN ($placeholders))";
            $params[]     = 'role';
            $params       = array_merge($params, $roleIds);
        }

        $visFilter = '(' . implode(' OR ', $visConds) . ')';

        $sql = "SELECT
                    COUNT(*) AS visible_total,
                    SUM(CASE WHEN e.created_by = ? THEN 1 ELSE 0 END) AS owned,
                    SUM(CASE WHEN e.created_by <> ? AND (e.visibility = 'public' OR e.visibility = 'role') THEN 1 ELSE 0 END) AS shared,
                    SUM(CASE WHEN e.all_day = 1 THEN 1 ELSE 0 END) AS all_day
                FROM {$this->table} e
                WHERE e.deleted_at IS NULL
                  AND {$visFilter}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$userId, $userId], $params));
        $row = $stmt->fetch() ?: [];

        return [
            'owned'       => (int) ($row['owned'] ?? 0),
            'shared'      => (int) ($row['shared'] ?? 0),
            'all_day'     => (int) ($row['all_day'] ?? 0),
            'visible_total' => (int) ($row['visible_total'] ?? 0),
        ];
    }

    /**
     * Singolo evento con nome creatore.
     */
    public function findWithCreator(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, u.name AS creator_name
             FROM {$this->table} e
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.id = ? AND e.deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findLinkedTaskByEventId(int $eventId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, status, priority, due_date, due_time, calendar_event_id
             FROM tasks
             WHERE calendar_event_id = ? AND user_id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$eventId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findLinkedRecurrenceByEventId(int $eventId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.titolo, r.tipo, r.contatto_id, r.calendario_event_id, c.nome, c.cognome
             FROM contact_recurrences r
             INNER JOIN contacts c ON c.id = r.contatto_id
             WHERE r.calendario_event_id = ? AND r.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$eventId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function clearLinkedTaskReferences(int $eventId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tasks SET calendar_event_id = NULL, updated_at = NOW() WHERE calendar_event_id = ?'
        );
        $stmt->execute([$eventId]);
    }

    public function clearLinkedRecurrenceReferences(int $eventId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contact_recurrences SET calendario_event_id = NULL, updated_at = NOW() WHERE calendario_event_id = ?'
        );
        $stmt->execute([$eventId]);
    }
}
