<?php

namespace App\Modules\Calendar\Tests\Unit;

use App\Modules\Calendar\Services\CalendarService;
use PHPUnit\Framework\TestCase;

class CalendarServiceTest extends TestCase
{
    private CalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new class () extends CalendarService {
            public function __construct()
            {
            }
        };
    }

    public function testCanEditReturnsTrueForOwner(): void
    {
        $event = ['created_by' => 7];
        $this->assertTrue($this->service->canEdit($event, 7));
    }

    public function testCanEditReturnsFalseForDifferentUserWithoutPermission(): void
    {
        $event = ['created_by' => 7];
        $this->assertFalse($this->service->canEdit($event, 9));
    }

    /**
     * Regressione M2 (broken access control): calendario.edit è il permesso che
     * GIÀ protegge la rotta di modifica, quindi non deve fungere da override
     * "edit-all". Un non-proprietario che lo possiede NON può editare eventi altrui.
     */
    public function testCanEditDeniesNonOwnerHoldingEditPermission(): void
    {
        $savedSession = $_SESSION ?? [];
        $_SESSION['user_roles'] = ['user'];
        $_SESSION['user_permissions'] = ['calendar.edit', 'calendar.delete'];

        $event = ['created_by' => 7];
        $this->assertFalse($this->service->canEdit($event, 9));

        $_SESSION = $savedSession;
    }

    public function testCanEditAllowsSuperAdminRole(): void
    {
        $savedSession = $_SESSION ?? [];
        $_SESSION['user_roles'] = ['admin'];

        $event = ['created_by' => 7];
        $this->assertTrue($this->service->canEdit($event, 9));

        $_SESSION = $savedSession;
    }

    public function testParseRecurrenceRuleParsesBaseFields(): void
    {
        $parsed = $this->service->parseRecurrenceRule('FREQ=WEEKLY;INTERVAL=2;COUNT=5');

        $this->assertNotNull($parsed);
        $this->assertSame('WEEKLY', $parsed['freq']);
        $this->assertSame(2, $parsed['interval']);
        $this->assertSame(5, $parsed['count']);
    }

    public function testExpandRecurringEventInRangeGeneratesVirtualIds(): void
    {
        $event = [
            'id' => 11,
            'title' => 'Riunione',
            'start_datetime' => '2026-04-01 09:00:00',
            'end_datetime' => '2026-04-01 10:00:00',
            'all_day' => 0,
            'visibility' => 'personal',
            'location' => 'Sala A',
            'description' => '',
            'creator_name' => 'Mario',
            'recurrence_rule' => 'FREQ=DAILY;INTERVAL=1;COUNT=4',
            'recurrence_end' => null,
        ];

        $occurrences = $this->service->expandRecurringEventInRange($event, '2026-04-01 00:00:00', '2026-04-10 00:00:00');

        $this->assertCount(3, $occurrences);
        $this->assertSame('11#R#1', $occurrences[0]['id']);
        $this->assertSame('11#R#3', $occurrences[2]['id']);
        $this->assertTrue($occurrences[0]['extendedProps']['is_recurrence']);
    }

    public function testExportEventsAsIcsContainsVeventAndFields(): void
    {
        $service = new class () extends CalendarService {
            public function __construct()
            {
            }

            public function getEventsForUser(int $userId, string $start, string $end): array
            {
                return [[
                    'id' => 1,
                    'title' => 'Kickoff',
                    'start' => '2026-04-02 10:00:00',
                    'end' => '2026-04-02 11:00:00',
                    'allDay' => false,
                    'extendedProps' => [
                        'description' => 'Descrizione evento',
                        'location' => 'Ufficio',
                        'recurrence_rule' => null,
                    ],
                ]];
            }
        };

        $ics = $service->exportEventsAsIcs(1, '2026-04-01', '2026-04-30');

        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('SUMMARY:Kickoff', $ics);
        $this->assertStringContainsString('DESCRIPTION:Descrizione evento', $ics);
        $this->assertStringContainsString('LOCATION:Ufficio', $ics);
    }
}
