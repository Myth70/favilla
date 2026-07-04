<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Support\RecurrenceExpander;

/**
 * Import/export ICS (RFC 5545, sottoinsieme) per il Calendario.
 *
 * buildIcs() serializza eventi già formattati per il calendario;
 * parseUpload() valida il file e normalizza i VEVENT in payload pronti
 * per CalendarService::createEvent — l'orchestrazione (insert, audit,
 * notifiche) resta al service.
 */
class CalendarIcsService
{
    private ?RecurrenceExpander $expander = null;

    /**
     * Serializza in ICS gli eventi nel formato calendario
     * (id, title, start, end, allDay, extendedProps).
     */
    public function buildIcs(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Favilla//Calendario//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($events as $event) {
            $description = (string) ($event['extendedProps']['description'] ?? '');
            $location = (string) ($event['extendedProps']['location'] ?? '');
            $uid = preg_replace('/[^A-Za-z0-9#\-]/', '', (string) $event['id']) . '@favilla.local';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\\THis\\Z');
            if (!empty($event['allDay'])) {
                $lines[] = 'DTSTART;VALUE=DATE:' . gmdate('Ymd', strtotime((string) $event['start']));
                if (!empty($event['end'])) {
                    $lines[] = 'DTEND;VALUE=DATE:' . gmdate('Ymd', strtotime((string) $event['end']));
                }
            } else {
                $lines[] = 'DTSTART:' . gmdate('Ymd\\THis\\Z', strtotime((string) $event['start']));
                if (!empty($event['end'])) {
                    $lines[] = 'DTEND:' . gmdate('Ymd\\THis\\Z', strtotime((string) $event['end']));
                }
            }

            $lines[] = 'SUMMARY:' . $this->escapeIcsText((string) ($event['title'] ?? 'Evento'));
            if ($description !== '') {
                $lines[] = 'DESCRIPTION:' . $this->escapeIcsText($description);
            }
            if ($location !== '') {
                $lines[] = 'LOCATION:' . $this->escapeIcsText($location);
            }
            if (!empty($event['extendedProps']['recurrence_rule']) && !str_contains((string) $event['id'], '#R#')) {
                $lines[] = 'RRULE:' . strtoupper((string) $event['extendedProps']['recurrence_rule']);
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Valida il file caricato e normalizza i VEVENT.
     *
     * @return array{
     *   payloads: array<int, array{number: int, data: array}>,
     *   skipped: int,
     *   errors: string[]
     * }
     */
    public function parseUpload(array $uploadedFile): array
    {
        $result = ['payloads' => [], 'skipped' => 0, 'errors' => []];

        $name = (string) ($uploadedFile['name'] ?? '');
        $tmp = (string) ($uploadedFile['tmp_name'] ?? '');
        $type = strtolower((string) ($uploadedFile['type'] ?? ''));

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $result['errors'][] = 'File non valido.';
            return $result;
        }

        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'ics') {
            $result['errors'][] = 'Il file deve avere estensione .ics';
            return $result;
        }

        if ($type !== '' && !str_contains($type, 'text/calendar')) {
            $result['errors'][] = 'Tipo file non supportato: è richiesto text/calendar.';
            return $result;
        }

        $raw = file_get_contents($tmp);
        if (!is_string($raw) || trim($raw) === '') {
            $result['errors'][] = 'File ICS vuoto o non leggibile.';
            return $result;
        }

        $expander = $this->expander ??= new RecurrenceExpander();

        foreach ($this->parseIcsEvents($raw) as $index => $event) {
            $number = $index + 1;
            $title = trim((string) ($event['SUMMARY'] ?? ''));
            $start = RecurrenceExpander::parseIcsDateTime((string) ($event['DTSTART'] ?? ''));
            $end = RecurrenceExpander::parseIcsDateTime((string) ($event['DTEND'] ?? ''));
            $isAllDay = !empty($event['DTSTART_IS_DATE']);
            $rule = isset($event['RRULE']) ? strtoupper(trim((string) $event['RRULE'])) : null;

            if ($title === '' || $start === null) {
                $result['skipped']++;
                $result['errors'][] = 'Evento #' . $number . ' ignorato: titolo o data inizio mancanti.';
                continue;
            }

            if ($rule !== null && $expander->parseRule($rule) === null) {
                $result['skipped']++;
                $result['errors'][] = 'Evento #' . $number . ' ignorato: RRULE non supportata.';
                continue;
            }

            $result['payloads'][] = [
                'number' => $number,
                'data' => [
                    'title' => mb_substr($title, 0, 255),
                    'description' => mb_substr((string) ($event['DESCRIPTION'] ?? ''), 0, 65535),
                    'location' => mb_substr((string) ($event['LOCATION'] ?? ''), 0, 255),
                    'color' => null,
                    'category' => null,
                    'visibility' => 'personal',
                    'start_datetime' => $start->format('Y-m-d H:i:s'),
                    'end_datetime' => $end?->format('Y-m-d H:i:s'),
                    'all_day' => $isAllDay ? 1 : 0,
                    'visible_to_role' => null,
                    'reminder_minutes' => null,
                    'recurrence_rule' => $rule,
                    'recurrence_end' => null,
                ],
            ];
        }

        return $result;
    }

    /**
     * Spezza il contenuto ICS in VEVENT grezzi (unfolding incluso).
     */
    private function parseIcsEvents(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $raw = preg_replace("/\n[ \t]/", '', $raw) ?? $raw;
        $lines = explode("\n", $raw);

        $events = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === 'BEGIN:VEVENT') {
                $current = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if (is_array($current)) {
                    $events[] = $current;
                }
                $current = null;
                continue;
            }

            if (!is_array($current)) {
                continue;
            }

            $pair = explode(':', $line, 2);
            if (count($pair) !== 2) {
                continue;
            }

            $prop = strtoupper(trim($pair[0]));
            $value = trim($pair[1]);
            $baseProp = explode(';', $prop, 2)[0];

            $current[$baseProp] = $value;
            if ($baseProp === 'DTSTART' && str_contains($prop, 'VALUE=DATE')) {
                $current['DTSTART_IS_DATE'] = true;
            }
        }

        return $events;
    }

    private function escapeIcsText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace(',', '\\,', $text);
        return str_replace(["\r\n", "\n", "\r"], '\\n', $text);
    }
}
