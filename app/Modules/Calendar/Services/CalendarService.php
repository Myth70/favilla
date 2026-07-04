<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Repositories\CalendarRepository;
use App\Modules\Calendar\Support\RecurrenceExpander;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\AuditService;
use DateTimeImmutable;
use PDO;

/**
 * Orchestratore del Calendario: CRUD eventi, visibilità, notifiche.
 * La logica pura vive nei collaboratori: RecurrenceExpander (ricorrenze)
 * e CalendarIcsService (import/export ICS). I metodi parse/expand/ics
 * restano qui come facciata per controller e chiamanti esistenti.
 */
class CalendarService
{
    private CalendarRepository $repo;

    /** Lazy: i test istanziano il service bypassando il costruttore. */
    private ?RecurrenceExpander $expanderInstance = null;
    private ?CalendarIcsService $icsInstance = null;

    public function __construct()
    {
        $this->repo = app(CalendarRepository::class);
    }

    public function getEventsForUser(int $userId, string $start, string $end): array
    {
        $roleIds = $this->getUserRoleIds($userId);
        $events = $this->repo->findByDateRange($start, $end, $userId, $roleIds);

        $rangeStart = RecurrenceExpander::toDateTimeImmutable($start)
            ?? new DateTimeImmutable('first day of this month 00:00:00');
        $rangeEnd = RecurrenceExpander::toDateTimeImmutable($end)
            ?? new DateTimeImmutable('last day of this month 23:59:59');

        $items = [];
        foreach ($events as $event) {
            if (RecurrenceExpander::eventOverlapsRange($event, $rangeStart, $rangeEnd)) {
                $items[] = $this->formatEventForCalendar($event);
            }

            if (!empty($event['recurrence_rule'])) {
                $items = array_merge(
                    $items,
                    $this->expandRecurringEventInRange($event, $rangeStart->format('Y-m-d H:i:s'), $rangeEnd->format('Y-m-d H:i:s'))
                );
            }
        }

        return $items;
    }

    public function createEvent(array $data, int $userId): int
    {
        $data['created_by'] = $userId;
        $data['all_day'] = (int) ($data['all_day'] ?? 0);

        if (($data['visibility'] ?? 'personal') !== 'role') {
            $data['visible_to_role'] = null;
        }

        if (empty($data['end_datetime'])) {
            $data['end_datetime'] = null;
        }

        if (empty($data['reminder_minutes'])) {
            $data['reminder_minutes'] = null;
        }

        if (empty($data['recurrence_rule'])) {
            $data['recurrence_rule'] = null;
            $data['recurrence_end'] = null;
        } elseif (empty($data['recurrence_end'])) {
            $data['recurrence_end'] = null;
        }

        // Nota: evento, ricorrenza e reminder sono colonne della stessa riga
        // (un solo INSERT): nessuna transazione multi-statement necessaria.
        $eventId = $this->repo->create($data);

        if (($data['visibility'] ?? 'personal') === 'role' && !empty($data['visible_to_role'])) {
            $this->notifyRole($data, $eventId, $userId);
        }

        AuditService::log('evento_created', 'calendar', $eventId, null, [
            'title'   => $data['title'] ?? '',
            'user_id' => $userId,
        ]);

        return $eventId;
    }

    public function updateEvent(int $id, array $data, int $userId): bool
    {
        $event = $this->repo->findWithCreator($id);
        if (!$event) {
            throw new \RuntimeException('Evento non trovato.');
        }

        if ((int) $event['created_by'] !== $userId && !is_admin()) {
            throw new \RuntimeException('Non hai i permessi per modificare questo evento.');
        }

        $data['all_day'] = (int) ($data['all_day'] ?? 0);

        if (($data['visibility'] ?? 'personal') !== 'role') {
            $data['visible_to_role'] = null;
        }

        if (empty($data['end_datetime'])) {
            $data['end_datetime'] = null;
        }

        if (empty($data['reminder_minutes'])) {
            $data['reminder_minutes'] = null;
        }

        if (empty($data['recurrence_rule'])) {
            $data['recurrence_rule'] = null;
            $data['recurrence_end'] = null;
        } elseif (empty($data['recurrence_end'])) {
            $data['recurrence_end'] = null;
        }

        $result = $this->repo->update($id, $data);
        if ($result) {
            AuditService::log(
                'evento_updated',
                'calendar',
                $id,
                ['title' => $event['title'] ?? ''],
                ['title' => $data['title'] ?? $event['title'] ?? '']
            );
        }
        return $result;
    }

    public function moveEvent(int $id, string $start, ?string $end, ?bool $allDay, int $userId): bool
    {
        $event = $this->repo->findWithCreator($id);
        if (!$event) {
            throw new \RuntimeException('Evento non trovato.');
        }

        if ((int) $event['created_by'] !== $userId && !is_admin()) {
            throw new \RuntimeException('Non hai i permessi per modificare questo evento.');
        }

        $data = ['start_datetime' => $start];
        $data['end_datetime'] = ($end !== null && $end !== '') ? $end : null;

        if ($allDay !== null) {
            $data['all_day'] = (int) $allDay;
        }

        return $this->repo->update($id, $data);
    }

    public function deleteEvent(int $id, int $userId): bool
    {
        $event = $this->repo->findWithCreator($id);
        if (!$event) {
            throw new \RuntimeException('Evento non trovato.');
        }

        if ((int) $event['created_by'] !== $userId && !is_admin()) {
            throw new \RuntimeException('Non hai i permessi per eliminare questo evento.');
        }

        $this->repo->clearLinkedTaskReferences($id);
        $this->repo->clearLinkedRecurrenceReferences($id);

        $deleted = $this->repo->delete($id);
        if ($deleted) {
            AuditService::log('evento_deleted', 'calendar', $id, [
                'title'   => $event['title'] ?? '',
                'user_id' => $userId,
            ], null);
        }
        return $deleted;
    }

    public function getUpcomingEvents(int $userId, int $limit = 5): array
    {
        $roleIds = $this->getUserRoleIds($userId);
        return $this->repo->findUpcoming($userId, $roleIds, $limit);
    }

    public function countUpcomingEvents(int $userId): int
    {
        $roleIds = $this->getUserRoleIds($userId);
        return $this->repo->countUpcoming($userId, $roleIds, 7);
    }

    public function getHeroStats(int $userId): array
    {
        $roleIds   = $this->getUserRoleIds($userId);
        $snapshot  = $this->repo->getHeroStats($userId, $roleIds);
        $upcoming  = $this->repo->countUpcoming($userId, $roleIds, 7);

        return [
            [
                'value' => $upcoming,
                'label' => 'Prossimi 7 giorni',
                'icon'  => 'fa-solid fa-calendar-week',
                'color' => 'info',
            ],
            [
                'value' => $snapshot['owned'] ?? 0,
                'label' => 'I miei eventi',
                'icon'  => 'fa-solid fa-user',
                'color' => 'primary',
            ],
            [
                'value' => $snapshot['shared'] ?? 0,
                'label' => 'Condivisi',
                'icon'  => 'fa-solid fa-users',
                'color' => 'warning',
            ],
            [
                'value' => $snapshot['all_day'] ?? 0,
                'label' => 'Giornata intera',
                'icon'  => 'fa-solid fa-sun',
                'color' => 'success',
            ],
        ];
    }

    public function getEvent(int $id, int $userId): ?array
    {
        $event = $this->repo->findWithCreator($id);
        if (!$event) {
            return null;
        }

        // Solo il super-admin vede ogni evento. calendario.edit è un permesso
        // baseline (gestione dei PROPRI eventi), non un "vedi tutto": usarlo qui
        // esporrebbe gli eventi 'personal' altrui a chiunque possa editare i propri.
        if (is_admin()) {
            return $event;
        }

        if ((int) $event['created_by'] === $userId) {
            return $event;
        }

        if ($event['visibility'] === 'personal') {
            return null;
        }

        if ($event['visibility'] === 'public') {
            return $event;
        }

        if ($event['visibility'] === 'role' && $event['visible_to_role']) {
            $roleIds = $this->getUserRoleIds($userId);
            return in_array((int) $event['visible_to_role'], $roleIds, true) ? $event : null;
        }

        return null;
    }

    public function canEdit(array $event, int $userId): bool
    {
        return (int) $event['created_by'] === $userId || is_admin();
    }

    public function getLinkedContexts(int $eventId, int $userId): array
    {
        return [
            'task' => $this->repo->findLinkedTaskByEventId($eventId, $userId),
            'ricorrenza' => $this->repo->findLinkedRecurrenceByEventId($eventId, $userId),
        ];
    }

    public function getRolesList(): array
    {
        $pdo = app(PDO::class);
        $stmt = $pdo->query('SELECT id, name, slug FROM roles ORDER BY name');
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Facciata ricorrenze (logica in Support\RecurrenceExpander)
    // ------------------------------------------------------------------

    public function parseRecurrenceRule(?string $rule): ?array
    {
        return $this->expander()->parseRule($rule);
    }

    public function isSupportedRecurrenceRule(?string $rule): bool
    {
        return $this->expander()->isSupported($rule);
    }

    public function expandRecurringEventInRange(array $event, string $start, string $end): array
    {
        $items = [];
        foreach ($this->expander()->occurrencesInRange($event, $start, $end) as $occurrence) {
            $virtual = $event;
            $virtual['start_datetime'] = $occurrence['start']->format('Y-m-d H:i:s');
            $virtual['end_datetime'] = $occurrence['end']?->format('Y-m-d H:i:s');

            $items[] = $this->formatEventForCalendar(
                $virtual,
                (string) $event['id'] . '#R#' . $occurrence['index'],
                $occurrence['index']
            );
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // Facciata ICS (logica in CalendarIcsService)
    // ------------------------------------------------------------------

    public function exportEventsAsIcs(int $userId, string $start, string $end): string
    {
        return $this->ics()->buildIcs($this->getEventsForUser($userId, $start, $end));
    }

    public function importEventsFromIcs(array $uploadedFile, int $userId): array
    {
        $parsed = $this->ics()->parseUpload($uploadedFile);

        $result = [
            'imported' => 0,
            'skipped' => $parsed['skipped'],
            'errors' => $parsed['errors'],
        ];

        foreach ($parsed['payloads'] as $payload) {
            try {
                $this->createEvent($payload['data'], $userId);
                $result['imported']++;
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = 'Evento #' . $payload['number'] . ' non importato: ' . $e->getMessage();
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Interni
    // ------------------------------------------------------------------

    private function formatEventForCalendar(array $event, ?string $virtualId = null, ?int $recurrenceIndex = null): array
    {
        $item = [
            'id' => $virtualId ?? (int) $event['id'],
            'title' => $event['title'],
            'start' => $event['all_day'] ? substr((string) $event['start_datetime'], 0, 10) : $event['start_datetime'],
            'allDay' => (bool) $event['all_day'],
        ];

        if (!empty($event['end_datetime'])) {
            $item['end'] = $event['all_day'] ? substr((string) $event['end_datetime'], 0, 10) : $event['end_datetime'];
        }

        if (!empty($event['color'])) {
            $item['color'] = $event['color'];
        }

        $item['extendedProps'] = [
            'visibility' => $event['visibility'],
            'location' => $event['location'] ?? '',
            'description' => $event['description'] ?? '',
            'creator_name' => $event['creator_name'] ?? '',
            'created_by' => (int) ($event['created_by'] ?? 0),
            'recurrence_rule' => $event['recurrence_rule'] ?? null,
            'recurrence_end' => $event['recurrence_end'] ?? null,
            'is_recurrence' => $virtualId !== null,
            'recurrence_index' => $recurrenceIndex,
            'recurrence_parent_id' => (int) $event['id'],
        ];

        return $item;
    }

    private function expander(): RecurrenceExpander
    {
        return $this->expanderInstance ??= new RecurrenceExpander();
    }

    private function ics(): CalendarIcsService
    {
        return $this->icsInstance ??= app(CalendarIcsService::class);
    }

    private function getUserRoleIds(int $userId): array
    {
        $pdo = app(PDO::class);
        $stmt = $pdo->prepare('SELECT role_id FROM user_role WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'role_id');
    }

    private function notifyRole(array $data, int $eventId, int $userId): void
    {
        try {
            $pdo = app(PDO::class);
            $stmt = $pdo->prepare('SELECT slug FROM roles WHERE id = ?');
            $stmt->execute([$data['visible_to_role']]);
            $role = $stmt->fetch();

            if ($role) {
                $dateLabel = format_date_it($data['start_datetime'], 'compact');

                NotificationService::dispatchEventToRole(
                    'calendar.shared_event_created',
                    'Calendar',
                    $role['slug'],
                    [
                        'event_id' => $eventId,
                        'event_title' => $data['title'],
                        'start_label' => $dateLabel,
                        'location' => $data['location'] ?? '',
                    ],
                    route('calendar.show', ['id' => $eventId]),
                    $userId
                );
            }
        } catch (\Throwable) {
            // Notifica non bloccante
        }
    }
}
