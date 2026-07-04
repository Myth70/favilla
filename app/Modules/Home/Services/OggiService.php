<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

use App\Modules\Calendar\Services\CalendarService;
use App\Modules\Contacts\Services\ContactsReminderService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tasks\Services\TasksService;
use DateTimeImmutable;

class OggiService
{
    /**
     * @return array{items: array<int, array<string, mixed>>, counts: array<string, int>, generated_at: string}
     */
    public function buildFeed(int $userId): array
    {
        $now = new DateTimeImmutable('now');
        $horizon = $now->modify('+24 hours');
        $nowTs = $now->getTimestamp();
        $twoHoursTs = $now->modify('+2 hours')->getTimestamp();
        $endOfTodayTs = (new DateTimeImmutable('tomorrow 00:00:00'))->getTimestamp();

        $items = array_merge(
            $this->collectTaskItems($userId, $now, $horizon),
            $this->collectCalendarItems($userId, $now, $horizon),
            $this->collectRecurrenceItems($userId, $now, $horizon),
            $this->collectNotificationItems($userId)
        );

        usort($items, static function (array $a, array $b): int {
            $scoreDiff = (int) ($b['priority_score'] ?? 0) <=> (int) ($a['priority_score'] ?? 0);
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }

            $aTs = $a['due_ts'] ?? null;
            $bTs = $b['due_ts'] ?? null;

            if ($aTs === null && $bTs !== null) {
                return 1;
            }
            if ($aTs !== null && $bTs === null) {
                return -1;
            }
            if ($aTs !== null && $bTs !== null) {
                $tsDiff = (int) $aTs <=> (int) $bTs;
                if ($tsDiff !== 0) {
                    return $tsDiff;
                }
            }

            return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        $sourceColorMap = [
            'tasks'      => 'purple',
            'calendar'    => 'success',
            'contacts'      => 'teal',
            'notifications' => 'warning',
        ];

        foreach ($items as &$item) {
            $dueTs = $item['due_ts'] ?? null;
            if ($dueTs === null) {
                $item['time_group'] = 'unscheduled';
            } elseif ($dueTs < $nowTs) {
                $item['time_group'] = 'overdue';
            } elseif ($dueTs < $twoHoursTs) {
                $item['time_group'] = 'soon';
            } elseif ($dueTs < $endOfTodayTs) {
                $item['time_group'] = 'today';
            } else {
                $item['time_group'] = 'hours24';
            }
            $item['source_color'] = $sourceColorMap[$item['source_key'] ?? ''] ?? 'secondary';
        }
        unset($item);

        $stats = [
            'overdue_tasks'  => 0,
            'calendar_today' => 0,
        ];

        $nextEventTs    = null;
        $nextEventTitle = null;
        $openToday      = 0;

        foreach ($items as $item) {
            $source = (string) ($item['source_key'] ?? '');
            $group  = (string) ($item['time_group'] ?? '');

            if ($source === 'tasks' && $group === 'overdue') {
                $stats['overdue_tasks']++;
            }
            if ($source === 'calendar') {
                $stats['calendar_today']++;
            }

            if ($source === 'tasks' && in_array($group, ['overdue', 'soon', 'today'], true)) {
                $openToday++;
            }

            if ($source === 'calendar' && isset($item['due_ts']) && (int) $item['due_ts'] >= $nowTs) {
                $ts = (int) $item['due_ts'];
                if ($nextEventTs === null || $ts < $nextEventTs) {
                    $nextEventTs    = $ts;
                    $nextEventTitle = (string) ($item['title'] ?? '');
                }
            }
        }

        $items = array_slice($items, 0, 25);

        $counts = [
            'tasks' => 0,
            'calendar' => 0,
            'contacts' => 0,
            'notifications' => 0,
        ];

        foreach ($items as $item) {
            $source = (string) ($item['source_key'] ?? '');
            if (isset($counts[$source])) {
                $counts[$source]++;
            }
        }

        $completedToday = 0;
        if (isModuleEnabled('Tasks') && has_permission('tasks.view')) {
            $completedToday = app(TasksService::class)->countCompletedToday($userId);
        }

        $stats['completed_today'] = $completedToday;
        $stats['open_today']      = $openToday;
        $stats['progress_total']  = $completedToday + $openToday;
        $stats['next_event']      = $nextEventTs !== null
            ? ['ts' => $nextEventTs, 'title' => $nextEventTitle, 'time' => date('H:i', $nextEventTs)]
            : null;
        $stats['total_items']     = count($items);

        return [
            'items'        => $items,
            'counts'       => $counts,
            'stats'        => $stats,
            'generated_at' => $now->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Lista compatta delle attività completate oggi, per il pannello "Completate oggi".
     *
     * @return array<int, array{id:int, title:string, priority:string, completed_at:string, link:string}>
     */
    public function getCompletedTodayList(int $userId, int $limit = 25): array
    {
        if (!isModuleEnabled('Tasks') || !has_permission('tasks.view')) {
            return [];
        }

        $rows = app(TasksService::class)->getCompletedToday($userId, $limit);

        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id'           => $id,
                'title'        => (string) ($row['title'] ?? ''),
                'priority'     => (string) ($row['priority'] ?? 'medium'),
                'completed_at' => (string) ($row['completed_at'] ?? ''),
                'link'         => route('tasks.show', ['id' => $id]),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectTaskItems(int $userId, DateTimeImmutable $now, DateTimeImmutable $horizon): array
    {
        if (!isModuleEnabled('Tasks') || !has_permission('tasks.view')) {
            return [];
        }

        $service = app(TasksService::class);
        $overdue = $service->getOverdue($userId, 8);
        $dueSoon = $service->getDueSoon($userId, 1, 12);

        $priorityMap = TasksService::getPriorities();
        $priorityBoost = [
            'low' => 0,
            'medium' => 2,
            'high' => 4,
            'urgent' => 6,
        ];

        $items = [];
        $seen = [];
        $nowTs = $now->getTimestamp();
        $horizonTs = $horizon->getTimestamp();

        foreach (array_merge($overdue, $dueSoon) as $task) {
            $taskId = (int) ($task['id'] ?? 0);
            if ($taskId <= 0 || isset($seen[$taskId])) {
                continue;
            }
            $seen[$taskId] = true;

            if (($task['status'] ?? '') === 'done') {
                continue;
            }

            $dueDate = trim((string) ($task['due_date'] ?? ''));
            if ($dueDate === '') {
                continue;
            }

            $dueTime = trim((string) ($task['due_time'] ?? ''));
            $dueTs = $this->resolveTaskDueTimestamp($dueDate, $dueTime);

            if ($dueTs === null) {
                continue;
            }

            $isOverdue = $dueTs < $nowTs;
            if (!$isOverdue && $dueTs > $horizonTs) {
                continue;
            }

            $priority = (string) ($task['priority'] ?? 'medium');
            $priorityMeta = $priorityMap[$priority] ?? ['label' => 'Media', 'color' => 'info'];
            $boost = $priorityBoost[$priority] ?? 0;

            $urgencyClass = $isOverdue ? 'danger' : 'warning';
            $urgencyLabel = $isOverdue ? 'In ritardo' : 'Entro 24 ore';
            $priorityScore = ($isOverdue ? 100 : 80) + $boost;

            $dueLabel = format_date_it($dueDate, 'short');
            if ($dueTime !== '') {
                $dueLabel .= ' ' . $dueTime;
            }

            $items[] = [
                'id' => 'task:' . $taskId,
                'source_key' => 'tasks',
                'source_label' => 'Attività',
                'source_icon' => 'fa-list-check',
                'title' => (string) ($task['title'] ?? 'Attività'),
                'subtitle' => (string) ($priorityMeta['label'] ?? 'Media'),
                'urgency_label' => $urgencyLabel,
                'urgency_class' => $urgencyClass,
                'priority_score' => $priorityScore,
                'due_ts' => $dueTs,
                'due_label' => $dueLabel,
                'link' => route('tasks.show', ['id' => $taskId]),
                'action' => has_permission('tasks.edit')
                    ? [
                        'kind' => 'complete_task',
                        'url' => route('home.today.action.complete-task', ['id' => $taskId]),
                    ]
                    : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectCalendarItems(int $userId, DateTimeImmutable $now, DateTimeImmutable $horizon): array
    {
        if (!isModuleEnabled('Calendar') || !has_permission('calendar.view')) {
            return [];
        }

        $service = app(CalendarService::class);
        $events = $service->getUpcomingEvents($userId, 12);

        $items = [];
        $nowTs = $now->getTimestamp();
        $horizonTs = $horizon->getTimestamp();

        foreach ($events as $event) {
            $eventId = (int) ($event['id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $startRaw = trim((string) ($event['start_datetime'] ?? ''));
            if ($startRaw === '') {
                continue;
            }

            $startTs = strtotime($startRaw);
            if ($startTs === false) {
                continue;
            }

            if ($startTs < $nowTs || $startTs > $horizonTs) {
                continue;
            }

            $hoursToStart = (int) floor(($startTs - $nowTs) / 3600);
            $isSoon = $hoursToStart <= 2;

            $urgencyClass = $isSoon ? 'warning' : 'info';
            $urgencyLabel = $isSoon ? 'Inizia tra poco' : 'Prossime 24 ore';
            $priorityScore = $isSoon ? 65 : 60;

            $dueLabel = format_date_it($startRaw, 'short');
            $timePart = date('H:i', $startTs);
            if ($timePart !== '00:00') {
                $dueLabel .= ' ' . $timePart;
            }

            $items[] = [
                'id' => 'calendar:' . $eventId,
                'source_key' => 'calendar',
                'source_label' => 'Calendario',
                'source_icon' => 'fa-calendar-days',
                'title' => (string) ($event['title'] ?? 'Evento'),
                'subtitle' => (string) ($event['visibility'] ?? 'personale'),
                'urgency_label' => $urgencyLabel,
                'urgency_class' => $urgencyClass,
                'priority_score' => $priorityScore,
                'due_ts' => $startTs,
                'due_label' => $dueLabel,
                'link' => route('calendar.show', ['id' => $eventId]),
                'action' => null,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectRecurrenceItems(int $userId, DateTimeImmutable $now, DateTimeImmutable $horizon): array
    {
        if (!isModuleEnabled('Contacts') || !has_permission('contacts.view')) {
            return [];
        }

        $service = app(ContactsReminderService::class);
        $prossime = $service->getProssime($userId, 1);

        $items = [];
        $horizonTs = $horizon->getTimestamp();

        foreach ($prossime as $ric) {
            $contattoId = (int) ($ric['contatto_id'] ?? 0);
            if ($contattoId <= 0) {
                continue;
            }

            $dateRaw = trim((string) ($ric['prossima_data'] ?? ''));
            if ($dateRaw === '') {
                continue;
            }

            $eventTs = strtotime($dateRaw . ' 09:00:00');
            if ($eventTs === false || $eventTs > $horizonTs) {
                continue;
            }

            $days = (int) ($ric['giorni_mancanti'] ?? 0);
            $urgencyClass = $days <= 0 ? 'warning' : 'secondary';
            $urgencyLabel = $days <= 0 ? 'Oggi' : 'Domani';

            $nome = trim((string) (($ric['nome'] ?? '') . ' ' . ($ric['cognome'] ?? '')));
            $titolo = trim((string) ($ric['titolo'] ?? 'Ricorrenza'));
            $fullTitle = trim($titolo . ' - ' . $nome, ' -');

            $items[] = [
                'id' => 'contact:' . (int) ($ric['id'] ?? 0),
                'source_key' => 'contacts',
                'source_label' => 'Contatti',
                'source_icon' => 'fa-address-book',
                'title' => $fullTitle !== '' ? $fullTitle : 'Ricorrenza',
                'subtitle' => (string) ($ric['tipo'] ?? 'evento'),
                'urgency_label' => $urgencyLabel,
                'urgency_class' => $urgencyClass,
                'priority_score' => 40,
                'due_ts' => $eventTs,
                'due_label' => format_date_it($dateRaw, 'compact'),
                'link' => route('contacts.show', ['id' => $contattoId]),
                'action' => null,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectNotificationItems(int $userId): array
    {
        if (!isModuleEnabled('Notifications')) {
            return [];
        }

        $items = [];
        $notifications = NotificationService::getUnread($userId, 8);
        $typeScoreBoost = [
            'danger' => 5,
            'warning' => 3,
            'success' => 1,
            'info' => 0,
        ];

        foreach ($notifications as $notification) {
            $id = (int) ($notification['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $type = (string) ($notification['type'] ?? 'info');
            $urgencyClass = in_array($type, ['danger', 'warning', 'success', 'info'], true) ? $type : 'info';

            $createdAt = trim((string) ($notification['created_at'] ?? ''));
            $createdTs = $createdAt !== '' ? strtotime($createdAt) : false;

            $items[] = [
                'id' => 'notification:' . $id,
                'source_key' => 'notifications',
                'source_label' => 'Notifiche',
                'source_icon' => 'fa-bell',
                'title' => (string) ($notification['title'] ?? 'Notifica'),
                'subtitle' => (string) ($notification['body'] ?? ''),
                'urgency_label' => 'Da leggere',
                'urgency_class' => $urgencyClass,
                'priority_score' => 20 + ($typeScoreBoost[$type] ?? 0),
                'due_ts' => $createdTs !== false ? (int) $createdTs : null,
                'due_label' => $createdAt !== '' ? format_date_it($createdAt, 'relative') : 'Adesso',
                'link' => !empty($notification['link']) ? (string) $notification['link'] : route('notifications.index'),
                'action' => [
                    'kind' => 'mark_notification_read',
                    'url' => route('notifications.read', ['id' => $id]),
                ],
            ];
        }

        return $items;
    }

    private function resolveTaskDueTimestamp(string $date, string $time): ?int
    {
        $raw = $date;
        if ($time !== '') {
            $raw .= ' ' . $time;
        } else {
            // Senza orario esplicito, trattiamo la scadenza a fine giornata.
            $raw .= ' 23:59:59';
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return (int) $ts;
    }
}
