<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tasks\Repositories\TasksRepository;
use App\Services\AuditService;

class TasksService
{
    private TasksRepository $repo;

    public function __construct()
    {
        $this->repo = app(TasksRepository::class);
    }

    private const STATUSES = [
        'backlog'     => ['label' => 'Backlog',       'color' => 'secondary', 'icon' => 'fa-inbox'],
        'todo'        => ['label' => 'Da fare',       'color' => 'info',      'icon' => 'fa-list'],
        'in_progress' => ['label' => 'In corso',      'color' => 'primary',   'icon' => 'fa-spinner'],
        'review'      => ['label' => 'In revisione',  'color' => 'warning',   'icon' => 'fa-eye'],
        'done'        => ['label' => 'Completato',    'color' => 'success',   'icon' => 'fa-check'],
    ];

    private const PRIORITIES = [
        'low'    => ['label' => 'Bassa',   'color' => 'secondary', 'icon' => 'fa-arrow-down'],
        'medium' => ['label' => 'Media',   'color' => 'info',      'icon' => 'fa-minus'],
        'high'   => ['label' => 'Alta',    'color' => 'warning',   'icon' => 'fa-arrow-up'],
        'urgent' => ['label' => 'Urgente', 'color' => 'danger',    'icon' => 'fa-fire'],
    ];

    public static function getStatuses(): array
    {
        $out = self::STATUSES;
        foreach ($out as $key => &$meta) {
            $meta['label'] = t('tasks.status.' . $key);
        }
        return $out;
    }

    public static function getPriorities(): array
    {
        $out = self::PRIORITIES;
        foreach ($out as $key => &$meta) {
            $meta['label'] = t('tasks.priority.' . $key);
        }
        return $out;
    }

    // ── Board ────────────────────────────────────────────────────────

    public function getBoard(int $userId): array
    {
        return $this->repo->getBoardForUser($userId);
    }

    // ── CRUD ─────────────────────────────────────────────────────────

    public function list(int $userId, array $filters = []): array
    {
        return $this->repo->listPaginated($userId, $filters);
    }

    public function find(int $id, int $userId): ?array
    {
        return $this->repo->findForUser($id, $userId);
    }

    public function create(array $data, int $userId): int
    {
        $data['user_id']  = $userId;
        $data['position'] = $this->repo->getNextPosition($userId, $data['status'] ?? 'todo');

        $taskId = $this->repo->create($data);

        // Sync tag se presenti
        if (!empty($data['tag_ids'])) {
            $this->repo->syncTags($taskId, $data['tag_ids'], $userId);
        }

        // Integrazione Calendario per scadenza
        $this->syncCalendarEvent($taskId, $data, $userId);

        AuditService::log('task_created', 'tasks', $taskId, null, [
            'title'   => $data['title'] ?? '',
            'status'  => $data['status'] ?? 'todo',
            'user_id' => $userId,
        ]);

        return $taskId;
    }

    public function update(int $id, array $data, int $userId): bool
    {
        $task = $this->repo->findForUser($id, $userId);
        if (!$task) {
            throw new \RuntimeException('Attività non trovata.');
        }

        // Se lo status cambia in 'done', imposta completed_at
        if (($data['status'] ?? '') === 'done' && $task['status'] !== 'done') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        } elseif (($data['status'] ?? $task['status']) !== 'done') {
            $data['completed_at'] = null;
        }

        $result = $this->repo->update($id, $data);

        // Sync tag
        if (isset($data['tag_ids'])) {
            $this->repo->syncTags($id, $data['tag_ids'], $userId);
        }

        // Aggiorna evento calendario
        $updatedTask = array_merge($task, $data);
        $this->syncCalendarEvent($id, $updatedTask, $userId);

        if ($result) {
            AuditService::log(
                'task_updated',
                'tasks',
                $id,
                ['status' => $task['status'] ?? '', 'title' => $task['title'] ?? ''],
                ['status' => $data['status'] ?? $task['status'] ?? '', 'title' => $data['title'] ?? $task['title'] ?? '']
            );
        }

        return $result;
    }

    public function delete(int $id, int $userId): bool
    {
        $task = $this->repo->findForUser($id, $userId);
        if (!$task) {
            throw new \RuntimeException('Attività non trovata.');
        }

        // Rimuovi evento calendario
        if (!empty($task['calendar_event_id'])) {
            $this->removeCalendarEvent((int) $task['calendar_event_id']);
        }

        $deleted = $this->repo->delete($id);
        if ($deleted) {
            AuditService::log('task_deleted', 'tasks', $id, [
                'title'   => $task['title'] ?? '',
                'user_id' => $userId,
            ], null);
        }
        return $deleted;
    }

    // ── Kanban move ──────────────────────────────────────────────────

    public function moveTask(int $id, string $status, int $position, int $userId): bool
    {
        $task = $this->repo->findForUser($id, $userId);
        if (!$task) {
            throw new \RuntimeException('Attività non trovata.');
        }

        $validStatuses = array_keys(self::STATUSES);
        if (!in_array($status, $validStatuses, true)) {
            throw new \RuntimeException('Status non valido.');
        }

        // Se move in 'done', segna come completata
        $completedAt = null;
        if ($status === 'done' && $task['status'] !== 'done') {
            $completedAt = date('Y-m-d H:i:s');
        }

        $result = $this->repo->moveTask($id, $status, $position);

        if ($completedAt) {
            $this->repo->update($id, ['completed_at' => $completedAt]);
        } elseif ($task['status'] === 'done' && $status !== 'done') {
            $this->repo->update($id, ['completed_at' => null]);
        }

        return $result;
    }

    // ── Toggle complete ──────────────────────────────────────────────

    public function toggleComplete(int $id, int $userId): array
    {
        $task = $this->repo->findForUser($id, $userId);
        if (!$task) {
            throw new \RuntimeException('Attività non trovata.');
        }

        $isDone = $task['status'] === 'done';
        $this->repo->toggleComplete($id, !$isDone);

        return [
            'done'   => !$isDone,
            'status' => !$isDone ? 'done' : 'todo',
        ];
    }

    // ── Checklist ────────────────────────────────────────────────────

    public function addChecklistItem(int $taskId, string $text, int $userId): int
    {
        $task = $this->repo->findForUser($taskId, $userId);
        if (!$task) {
            throw new \RuntimeException('Attività non trovata.');
        }
        return $this->repo->addChecklistItem($taskId, $text);
    }

    public function toggleChecklistItem(int $taskId, int $itemId, int $userId): bool
    {
        $task = $this->repo->findForUser($taskId, $userId);
        if (!$task) {
            throw new \RuntimeException('Attività non trovata.');
        }
        return $this->repo->toggleChecklistItem($itemId, $taskId);
    }

    public function deleteChecklistItem(int $taskId, int $itemId, int $userId): bool
    {
        $task = $this->repo->findForUser($taskId, $userId);
        if (!$task) {
            throw new \RuntimeException('Attività non trovata.');
        }
        return $this->repo->deleteChecklistItem($itemId, $taskId);
    }

    public function getChecklist(int $taskId): array
    {
        return $this->repo->getChecklist($taskId);
    }

    // ── Tags ─────────────────────────────────────────────────────────

    public function getUserTags(int $userId): array
    {
        return $this->repo->getUserTags($userId);
    }

    public function createTag(string $name, string $color, int $userId): int
    {
        return $this->repo->createTag($userId, $name, $color);
    }

    public function deleteTag(int $tagId, int $userId): bool
    {
        return $this->repo->deleteTag($tagId, $userId);
    }

    // ── Stats (per dashboard widget) ─────────────────────────────────

    public function getStats(int $userId): array
    {
        $counts = $this->repo->countByStatus($userId);
        $total   = array_sum($counts);
        $done    = $counts['done'] ?? 0;
        $active  = $total - $done;
        $overdue = $this->repo->countOverdue($userId);
        $completedWeek = $this->repo->getCompletedThisWeek($userId);

        return [
            'total'          => $total,
            'active'         => $active,
            'done'           => $done,
            'overdue'        => $overdue,
            'completed_week' => $completedWeek,
            'by_status'      => $counts,
        ];
    }

    public function getDueSoon(int $userId, int $days = 3, int $limit = 10): array
    {
        return $this->repo->getDueSoon($userId, $days, $limit);
    }

    public function getOverdue(int $userId, int $limit = 10): array
    {
        return $this->repo->getOverdue($userId, $limit);
    }

    public function countCompletedToday(int $userId): int
    {
        return $this->repo->countCompletedToday($userId);
    }

    public function getCompletedToday(int $userId, int $limit = 25): array
    {
        return $this->repo->getCompletedToday($userId, $limit);
    }

    public function getWeeklyTrend(int $userId, int $weeks = 8): array
    {
        return $this->repo->getWeeklyTrend($userId, $weeks);
    }

    public function search(int $userId, string $query, int $limit = 10): array
    {
        if ($query === '') {
            return [];
        }
        return $this->repo->searchForUser($userId, $query, $limit);
    }

    // ── Integrazione Calendario ──────────────────────────────────────

    private function syncCalendarEvent(int $taskId, array $data, int $userId): void
    {
        if (!isModuleEnabled('Calendar')) {
            return;
        }

        $task = $this->repo->find($taskId);
        if (!$task) {
            return;
        }

        $hasDueDate = !empty($data['due_date']);
        $existingEventId = (int) ($task['calendar_event_id'] ?? 0);

        if (!$hasDueDate) {
            // Rimuovi evento se la scadenza è stata rimossa
            if ($existingEventId > 0) {
                $this->removeCalendarEvent($existingEventId);
                $this->repo->update($taskId, ['calendar_event_id' => null]);
            }
            return;
        }

        $priorityColors = [
            'low'    => '#6c757d',
            'medium' => '#0dcaf0',
            'high'   => '#ffc107',
            'urgent' => '#dc3545',
        ];

        $startDatetime = $data['due_date'];
        $allDay = 1;
        if (!empty($data['due_time'])) {
            $startDatetime .= ' ' . $data['due_time'];
            $allDay = 0;
        }

        $eventData = [
            'title'            => '📋 ' . ($data['title'] ?? $task['title']),
            'description'      => 'Scadenza attività #' . $taskId,
            'start_datetime'   => $startDatetime,
            'end_datetime'     => null,
            'all_day'          => $allDay,
            'color'            => $priorityColors[$data['priority'] ?? $task['priority']] ?? '#0dcaf0',
            'location'         => null,
            'visibility'       => 'personal',
            'visible_to_role'  => null,
            'reminder_minutes' => null,
            'created_by'       => $userId,
        ];

        try {
            $calRepo = app(\App\Modules\Calendar\Repositories\CalendarRepository::class);

            if ($existingEventId > 0) {
                $calRepo->update($existingEventId, $eventData);
            } else {
                $newId = $calRepo->create($eventData);
                $this->repo->update($taskId, ['calendar_event_id' => $newId]);
            }
        } catch (\Throwable $e) {
            // Non bloccare se il calendario fallisce
        }
    }

    private function removeCalendarEvent(int $eventId): void
    {
        if (!isModuleEnabled('Calendar')) {
            return;
        }

        try {
            $calRepo = app(\App\Modules\Calendar\Repositories\CalendarRepository::class);
            $calRepo->delete($eventId);
        } catch (\Throwable $e) {
            // Silenzioso
        }
    }

    // ── Notifiche scadenze ───────────────────────────────────────────

    public function sendDueReminders(int $userId): void
    {
        $pdo   = app(\PDO::class);
        $today = date('Y-m-d');

        // In CLI le route non sono caricate — risolve in modo silenzioso
        $link = static function (int $id): ?string {
            try {
                return route('tasks.show', ['id' => $id]);
            } catch (\Throwable $e) {
                return null;
            }
        };

        $overdue = $this->repo->getOverdue($userId, 5);
        foreach ($overdue as $task) {
            // Un task scaduto viene notificato al massimo una volta al giorno
            if (($task['last_reminded_date'] ?? null) === $today) {
                continue;
            }

            NotificationService::dispatchEventToUser(
                'tasks.task_overdue',
                'Tasks',
                $userId,
                [
                    'task_id'    => (int) $task['id'],
                    'task_title' => $task['title'],
                    'due_date'   => $task['due_date'],
                ],
                $link((int) $task['id']),
                null
            );

            $pdo->prepare('UPDATE tasks SET last_reminded_date = ? WHERE id = ?')
                ->execute([$today, $task['id']]);
        }

        $dueToday = $this->repo->getDueToday($userId);
        foreach ($dueToday as $task) {
            if (($task['last_reminded_date'] ?? null) === $today) {
                continue;
            }

            NotificationService::dispatchEventToUser(
                'tasks.task_due_today',
                'Tasks',
                $userId,
                [
                    'task_id'    => (int) $task['id'],
                    'task_title' => $task['title'],
                    'due_time'   => $task['due_time'] ?? null,
                ],
                $link((int) $task['id']),
                null
            );

            $pdo->prepare('UPDATE tasks SET last_reminded_date = ? WHERE id = ?')
                ->execute([$today, $task['id']]);
        }
    }
}
