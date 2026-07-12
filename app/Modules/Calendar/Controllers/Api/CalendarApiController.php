<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Controllers\Api;

use App\Modules\Api\Http\ApiController;
use App\Modules\Calendar\Services\CalendarService;

/**
 * API v1 — Calendario (sola lettura). Riusa CalendarService: la lista espande
 * le ricorrenze nell'intervallo richiesto (le occorrenze virtuali hanno id
 * stringa e recurrence.parent_id punta all'evento master); il dettaglio applica
 * le stesse regole di visibilità della UI (own/public/role, admin vede tutto).
 */
class CalendarApiController extends ApiController
{
    private const MAX_RANGE_DAYS = 400;

    private CalendarService $calendar;

    public function __construct()
    {
        $this->calendar = app(CalendarService::class);
    }

    public function index(): void
    {
        $this->requireScope('calendar.view');

        $from = $this->parseDate((string) ($_GET['from'] ?? ''), new \DateTimeImmutable('today'));
        $to = $this->parseDate((string) ($_GET['to'] ?? ''), $from?->add(new \DateInterval('P30D')));

        if ($from === null || $to === null) {
            $this->fail('validation_failed', 'Validation failed.', 422, [
                'from' => ['invalid_date'],
                'to'   => ['invalid_date'],
            ]);
            return;
        }
        if ($to < $from) {
            $this->fail('validation_failed', 'Validation failed.', 422, ['to' => ['before_from']]);
            return;
        }
        if ($to->diff($from)->days > self::MAX_RANGE_DAYS) {
            $this->fail('validation_failed', 'Validation failed.', 422, ['to' => ['range_too_wide']]);
            return;
        }

        $events = $this->calendar->getEventsForUser(
            $this->userId(),
            $from->format('Y-m-d 00:00:00'),
            $to->format('Y-m-d 23:59:59')
        );

        $items = array_map([$this, 'serializeOccurrence'], $events);
        $this->ok($items, [
            'from'  => $from->format('Y-m-d'),
            'to'    => $to->format('Y-m-d'),
            'total' => count($items),
        ]);
    }

    public function show(string $id): void
    {
        $this->requireScope('calendar.view');

        $isAdmin = in_array('admin', $this->context()->roles(), true);
        $event = $this->calendar->getEvent((int) $id, $this->userId(), $isAdmin);
        if ($event === null) {
            $this->fail('not_found', 'Event not found.', 404);
            return;
        }
        $this->ok($this->serializeEvent($event));
    }

    /**
     * Data in query string: accetta 'Y-m-d' (o datetime, ne usa la parte data).
     */
    private function parseDate(string $raw, ?\DateTimeImmutable $default): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return $default;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', substr($raw, 0, 10));
        return $date === false ? null : $date;
    }

    /**
     * Occorrenza dalla lista (già espansa dal Service, forma calendario).
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function serializeOccurrence(array $item): array
    {
        $props = is_array($item['extendedProps'] ?? null) ? $item['extendedProps'] : [];

        return [
            'id'          => $item['id'],
            'title'       => $item['title'] ?? '',
            'start'       => $item['start'] ?? null,
            'end'         => $item['end'] ?? null,
            'all_day'     => (bool) ($item['allDay'] ?? false),
            'color'       => $item['color'] ?? null,
            'description' => $props['description'] ?? '',
            'location'    => $props['location'] ?? '',
            'visibility'  => $props['visibility'] ?? 'personal',
            'created_by'  => (int) ($props['created_by'] ?? 0),
            'recurrence'  => [
                'is_occurrence' => (bool) ($props['is_recurrence'] ?? false),
                'parent_id'     => (int) ($props['recurrence_parent_id'] ?? 0),
                'rule'          => $props['recurrence_rule'] ?? null,
            ],
        ];
    }

    /**
     * Evento singolo (riga con visibilità già applicata dal Service).
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function serializeEvent(array $event): array
    {
        return [
            'id'              => (int) $event['id'],
            'title'           => $event['title'] ?? '',
            'description'     => $event['description'] ?? null,
            'start_datetime'  => $event['start_datetime'] ?? null,
            'end_datetime'    => $event['end_datetime'] ?? null,
            'all_day'         => (bool) ($event['all_day'] ?? false),
            'color'           => $event['color'] ?? null,
            'category'        => $event['category'] ?? null,
            'location'        => $event['location'] ?? null,
            'visibility'      => $event['visibility'] ?? 'personal',
            'recurrence_rule' => $event['recurrence_rule'] ?? null,
            'recurrence_end'  => $event['recurrence_end'] ?? null,
            'created_by'      => (int) ($event['created_by'] ?? 0),
            'created_at'      => $event['created_at'] ?? null,
            'updated_at'      => $event['updated_at'] ?? null,
        ];
    }
}
