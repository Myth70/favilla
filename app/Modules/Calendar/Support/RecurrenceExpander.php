<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Espansione delle ricorrenze RRULE (sottoinsieme RFC 5545: FREQ
 * DAILY/WEEKLY/MONTHLY + INTERVAL, COUNT, UNTIL).
 *
 * Logica pura e senza dipendenze: il service la usa per calcolare le
 * occorrenze, la formattazione per il calendario resta al chiamante.
 */
class RecurrenceExpander
{
    /** Tetto di sicurezza contro regole degeneri (loop infiniti). */
    private const SAFETY_LIMIT = 1000;

    /**
     * Parsa una RRULE. Ritorna null se assente/non supportata.
     *
     * @return array{freq: string, interval: int, count: ?int, until: ?DateTimeImmutable}|null
     */
    public function parseRule(?string $rule): ?array
    {
        if ($rule === null || trim($rule) === '') {
            return null;
        }

        $parts = explode(';', strtoupper(trim($rule)));
        $data = [];
        foreach ($parts as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            $data[$pair[0]] = $pair[1];
        }

        $freq = $data['FREQ'] ?? '';
        if (!in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
            return null;
        }

        $interval = isset($data['INTERVAL']) ? (int) $data['INTERVAL'] : 1;
        if ($interval < 1) {
            $interval = 1;
        }

        $count = isset($data['COUNT']) ? (int) $data['COUNT'] : null;
        if ($count !== null && $count < 1) {
            $count = null;
        }

        $until = isset($data['UNTIL']) ? self::parseIcsDateTime($data['UNTIL']) : null;

        return [
            'freq' => $freq,
            'interval' => $interval,
            'count' => $count,
            'until' => $until,
        ];
    }

    /**
     * Una regola vuota è "supportata" (= nessuna ricorrenza).
     */
    public function isSupported(?string $rule): bool
    {
        if ($rule === null || trim($rule) === '') {
            return true;
        }
        return $this->parseRule($rule) !== null;
    }

    /**
     * Occorrenze SUCCESSIVE alla master che intersecano il range.
     * L'indice parte da 1 (la master è l'occorrenza 0) — stessa semantica
     * degli ID virtuali "{id}#R#{index}" usati dal calendario.
     *
     * @return array<int, array{start: DateTimeImmutable, end: ?DateTimeImmutable, index: int}>
     */
    public function occurrencesInRange(array $event, string $start, string $end): array
    {
        $rule = $this->parseRule($event['recurrence_rule'] ?? null);
        if ($rule === null) {
            return [];
        }

        $rangeStart = self::toDateTimeImmutable($start);
        $rangeEnd = self::toDateTimeImmutable($end);
        $masterStart = self::toDateTimeImmutable((string) ($event['start_datetime'] ?? ''));
        if ($rangeStart === null || $rangeEnd === null || $masterStart === null) {
            return [];
        }

        $masterEnd = !empty($event['end_datetime'])
            ? self::toDateTimeImmutable((string) $event['end_datetime'])
            : null;
        $durationSeconds = $masterEnd ? max(0, $masterEnd->getTimestamp() - $masterStart->getTimestamp()) : 0;

        $countLimit = $rule['count'] ?? null;
        $untilLimit = $rule['until'] ?? null;
        $recurrenceEnd = !empty($event['recurrence_end'])
            ? self::toDateTimeImmutable((string) $event['recurrence_end'])
            : null;
        if ($recurrenceEnd !== null && ($untilLimit === null || $recurrenceEnd < $untilLimit)) {
            $untilLimit = $recurrenceEnd;
        }

        $occurrences = [];
        $occurrenceStart = $masterStart;
        $occurrenceNumber = 1;
        $safety = 0;

        while ($safety < self::SAFETY_LIMIT) {
            $safety++;
            $nextStart = $this->incrementByRule($occurrenceStart, $rule);
            if ($nextStart === null) {
                break;
            }

            $occurrenceNumber++;

            if ($countLimit !== null && $occurrenceNumber > $countLimit) {
                break;
            }

            if ($untilLimit !== null && $nextStart > $untilLimit) {
                break;
            }

            if ($nextStart > $rangeEnd) {
                break;
            }

            $nextEnd = $durationSeconds > 0
                ? $nextStart->modify('+' . $durationSeconds . ' seconds')
                : null;

            $overlaps = $nextEnd === null
                ? ($nextStart >= $rangeStart && $nextStart <= $rangeEnd)
                : ($nextStart <= $rangeEnd && $nextEnd >= $rangeStart);

            if ($overlaps) {
                $occurrences[] = [
                    'start' => $nextStart,
                    'end' => $nextEnd,
                    'index' => $occurrenceNumber - 1,
                ];
            }

            $occurrenceStart = $nextStart;
        }

        return $occurrences;
    }

    /**
     * True se l'evento (start/end_datetime come stringhe) interseca il range.
     */
    public static function eventOverlapsRange(array $event, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): bool
    {
        $start = self::toDateTimeImmutable((string) ($event['start_datetime'] ?? ''));
        if ($start === null) {
            return false;
        }

        $end = !empty($event['end_datetime'])
            ? self::toDateTimeImmutable((string) $event['end_datetime'])
            : null;
        if ($end === null) {
            return $start >= $rangeStart && $start <= $rangeEnd;
        }

        return $start <= $rangeEnd && $end >= $rangeStart;
    }

    /**
     * Parser permissivo dei formati data usati da calendario e form.
     */
    public static function toDateTimeImmutable(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value . ' 00:00:00') ?: null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1) {
            return DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $value) ?: null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            return DateTimeImmutable::createFromFormat('Y-m-d H:i', $value) ?: null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parser dei formati data ICS (DATE, DATE-TIME UTC e floating).
     * Vive qui perché UNTIL dentro la RRULE usa questo formato.
     */
    public static function parseIcsDateTime(string $raw): ?DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{8}$/', $raw) === 1) {
            return DateTimeImmutable::createFromFormat('Ymd H:i:s', $raw . ' 00:00:00', new DateTimeZone('UTC')) ?: null;
        }

        if (preg_match('/^\d{8}T\d{6}Z$/', $raw) === 1) {
            return DateTimeImmutable::createFromFormat('Ymd\\THis\\Z', $raw, new DateTimeZone('UTC')) ?: null;
        }

        if (preg_match('/^\d{8}T\d{6}$/', $raw) === 1) {
            return DateTimeImmutable::createFromFormat('Ymd\\THis', $raw) ?: null;
        }

        return self::toDateTimeImmutable($raw);
    }

    private function incrementByRule(DateTimeImmutable $current, array $rule): ?DateTimeImmutable
    {
        $interval = max(1, (int) ($rule['interval'] ?? 1));

        return match ($rule['freq']) {
            'DAILY' => $current->add(new DateInterval('P' . $interval . 'D')),
            'WEEKLY' => $current->add(new DateInterval('P' . $interval . 'W')),
            'MONTHLY' => $current->add(new DateInterval('P' . $interval . 'M')),
            default => null,
        };
    }
}
