<?php

namespace App\Modules\Calendar\Tests\Unit;

use App\Modules\Calendar\Support\RecurrenceExpander;
use PHPUnit\Framework\TestCase;

class RecurrenceExpanderTest extends TestCase
{
    private RecurrenceExpander $expander;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expander = new RecurrenceExpander();
    }

    // ── parseRule ───────────────────────────────────────────────────

    public function testParseRuleParsesBaseFields(): void
    {
        $parsed = $this->expander->parseRule('FREQ=WEEKLY;INTERVAL=2;COUNT=5');

        $this->assertNotNull($parsed);
        $this->assertSame('WEEKLY', $parsed['freq']);
        $this->assertSame(2, $parsed['interval']);
        $this->assertSame(5, $parsed['count']);
        $this->assertNull($parsed['until']);
    }

    public function testParseRuleRejectsUnsupportedFreq(): void
    {
        $this->assertNull($this->expander->parseRule('FREQ=YEARLY'));
        $this->assertNull($this->expander->parseRule('FREQ=HOURLY;INTERVAL=1'));
        $this->assertNull($this->expander->parseRule('garbage'));
    }

    public function testParseRuleNormalizesInvalidIntervalAndCount(): void
    {
        $parsed = $this->expander->parseRule('FREQ=DAILY;INTERVAL=0;COUNT=0');

        $this->assertNotNull($parsed);
        $this->assertSame(1, $parsed['interval']);
        $this->assertNull($parsed['count']);
    }

    public function testParseRuleParsesUntilInIcsFormat(): void
    {
        $parsed = $this->expander->parseRule('FREQ=DAILY;UNTIL=20260415T000000Z');

        $this->assertNotNull($parsed);
        $this->assertNotNull($parsed['until']);
        $this->assertSame('2026-04-15', $parsed['until']->format('Y-m-d'));
    }

    public function testIsSupportedTreatsEmptyRuleAsSupported(): void
    {
        $this->assertTrue($this->expander->isSupported(null));
        $this->assertTrue($this->expander->isSupported('  '));
        $this->assertTrue($this->expander->isSupported('FREQ=MONTHLY'));
        $this->assertFalse($this->expander->isSupported('FREQ=YEARLY'));
    }

    // ── occurrencesInRange ──────────────────────────────────────────

    private function dailyEvent(array $overrides = []): array
    {
        return array_merge([
            'id' => 11,
            'start_datetime' => '2026-04-01 09:00:00',
            'end_datetime' => '2026-04-01 10:00:00',
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1;COUNT=4',
            'recurrence_end' => null,
        ], $overrides);
    }

    public function testOccurrencesRespectCountLimitAndIndexing(): void
    {
        // COUNT=4 include la master: 3 occorrenze successive, indici 1..3.
        $occurrences = $this->expander->occurrencesInRange(
            $this->dailyEvent(),
            '2026-04-01 00:00:00',
            '2026-04-10 00:00:00'
        );

        $this->assertCount(3, $occurrences);
        $this->assertSame(1, $occurrences[0]['index']);
        $this->assertSame(3, $occurrences[2]['index']);
        $this->assertSame('2026-04-02 09:00:00', $occurrences[0]['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-02 10:00:00', $occurrences[0]['end']->format('Y-m-d H:i:s'));
    }

    public function testOccurrencesStopAtRecurrenceEnd(): void
    {
        $occurrences = $this->expander->occurrencesInRange(
            $this->dailyEvent([
                'recurrence_rule' => 'FREQ=DAILY',
                'recurrence_end' => '2026-04-03 23:59:59',
            ]),
            '2026-04-01 00:00:00',
            '2026-04-30 00:00:00'
        );

        $this->assertCount(2, $occurrences);
        $this->assertSame('2026-04-03 09:00:00', end($occurrences)['start']->format('Y-m-d H:i:s'));
    }

    public function testOccurrencesStopAtRangeEnd(): void
    {
        $occurrences = $this->expander->occurrencesInRange(
            $this->dailyEvent(['recurrence_rule' => 'FREQ=DAILY']),
            '2026-04-01 00:00:00',
            '2026-04-04 12:00:00'
        );

        $this->assertCount(3, $occurrences);
    }

    public function testWeeklyIntervalSkipsWeeks(): void
    {
        $occurrences = $this->expander->occurrencesInRange(
            $this->dailyEvent(['recurrence_rule' => 'FREQ=WEEKLY;INTERVAL=2;COUNT=3']),
            '2026-04-01 00:00:00',
            '2026-06-01 00:00:00'
        );

        $this->assertCount(2, $occurrences);
        $this->assertSame('2026-04-15 09:00:00', $occurrences[0]['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-29 09:00:00', $occurrences[1]['start']->format('Y-m-d H:i:s'));
    }

    public function testEventWithoutRuleProducesNoOccurrences(): void
    {
        $occurrences = $this->expander->occurrencesInRange(
            $this->dailyEvent(['recurrence_rule' => null]),
            '2026-04-01 00:00:00',
            '2026-04-30 00:00:00'
        );

        $this->assertSame([], $occurrences);
    }

    // ── Helper di parsing date ──────────────────────────────────────

    public function testToDateTimeImmutableAcceptsCommonFormats(): void
    {
        $this->assertSame(
            '2026-04-01 00:00:00',
            RecurrenceExpander::toDateTimeImmutable('2026-04-01')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-04-01 09:30:00',
            RecurrenceExpander::toDateTimeImmutable('2026-04-01T09:30')->format('Y-m-d H:i:s')
        );
        $this->assertNull(RecurrenceExpander::toDateTimeImmutable(''));
        $this->assertNull(RecurrenceExpander::toDateTimeImmutable('non-una-data'));
    }

    public function testParseIcsDateTimeAcceptsDateAndUtcFormats(): void
    {
        $date = RecurrenceExpander::parseIcsDateTime('20260401');
        $this->assertSame('2026-04-01 00:00:00', $date->format('Y-m-d H:i:s'));

        $utc = RecurrenceExpander::parseIcsDateTime('20260401T093000Z');
        $this->assertSame('2026-04-01 09:30:00', $utc->format('Y-m-d H:i:s'));

        $floating = RecurrenceExpander::parseIcsDateTime('20260401T093000');
        $this->assertSame('2026-04-01 09:30:00', $floating->format('Y-m-d H:i:s'));

        $this->assertNull(RecurrenceExpander::parseIcsDateTime(''));
    }
}
