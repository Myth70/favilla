<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Tests\Unit;

use App\Modules\Api\Support\ApiRequestContext;
use App\Modules\Calendar\Controllers\Api\CalendarApiController;
use App\Modules\Calendar\Services\CalendarService;
use Tests\ControllerTestCase;

/**
 * API v1 Calendario (sola lettura): mapping delle occorrenze espanse, default
 * e validazione dell'intervallo, gate scope, pass-through del flag admin al
 * Service (che senza sessione non può risolverlo da is_admin()).
 */
class CalendarApiControllerTest extends ControllerTestCase
{
    private function authenticate(?array $scopes, array $roles = ['user']): ApiRequestContext
    {
        $context = new ApiRequestContext();
        $context->authenticate(
            7,
            ['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.test'],
            $roles,
            ['calendar.view'],
            $scopes,
            9
        );
        $this->bindInstance(ApiRequestContext::class, $context);
        return $context;
    }

    public function testIndexMapsOccurrencesAndMeta(): void
    {
        $this->authenticate(['calendar.view']);

        $calendar = $this->createMock(CalendarService::class);
        $calendar->method('getEventsForUser')->willReturn([
            [
                'id' => 3, 'title' => 'Riunione', 'start' => '2026-07-15 10:00:00',
                'end' => '2026-07-15 11:00:00', 'allDay' => false, 'color' => '#ff0000',
                'extendedProps' => [
                    'visibility' => 'public', 'location' => 'Sala A', 'description' => 'Weekly',
                    'created_by' => 7, 'recurrence_rule' => null, 'is_recurrence' => false,
                    'recurrence_parent_id' => 3,
                ],
            ],
            [
                'id' => '3_rec_1', 'title' => 'Riunione', 'start' => '2026-07-22 10:00:00',
                'allDay' => false,
                'extendedProps' => [
                    'visibility' => 'public', 'is_recurrence' => true,
                    'recurrence_parent_id' => 3, 'recurrence_rule' => 'FREQ=WEEKLY',
                    'created_by' => 7,
                ],
            ],
        ]);
        $this->bindInstance(CalendarService::class, $calendar);

        $result = $this->withGet(['from' => '2026-07-15', 'to' => '2026-07-31'])
            ->dispatch(CalendarApiController::class, 'index');

        $this->assertSame(200, $result->jsonStatus());
        $payload = $result->jsonPayload();
        $this->assertCount(2, $payload['data']);
        $this->assertSame(3, $payload['data'][0]['id']);
        $this->assertFalse($payload['data'][0]['recurrence']['is_occurrence']);
        $this->assertSame('3_rec_1', $payload['data'][1]['id']);
        $this->assertTrue($payload['data'][1]['recurrence']['is_occurrence']);
        $this->assertSame(3, $payload['data'][1]['recurrence']['parent_id']);
        $this->assertSame('2026-07-15', $payload['meta']['from']);
        $this->assertSame(2, $payload['meta']['total']);
    }

    public function testIndexPassesNormalizedRangeToService(): void
    {
        $this->authenticate(['calendar.view']);

        $capturedStart = null;
        $capturedEnd = null;
        $calendar = $this->createMock(CalendarService::class);
        $calendar->method('getEventsForUser')->willReturnCallback(
            function (int $userId, string $start, string $end) use (&$capturedStart, &$capturedEnd) {
                $capturedStart = $start;
                $capturedEnd = $end;
                return [];
            }
        );
        $this->bindInstance(CalendarService::class, $calendar);

        $this->withGet(['from' => '2026-07-01', 'to' => '2026-07-31'])
            ->dispatch(CalendarApiController::class, 'index');

        $this->assertSame('2026-07-01 00:00:00', $capturedStart);
        $this->assertSame('2026-07-31 23:59:59', $capturedEnd);
    }

    public function testIndexRejectsInvalidDate(): void
    {
        $this->authenticate(['calendar.view']);
        $this->bindInstance(CalendarService::class, $this->createMock(CalendarService::class));

        $result = $this->withGet(['from' => 'not-a-date'])
            ->dispatch(CalendarApiController::class, 'index');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame('validation_failed', $result->jsonPayload()['error']['code']);
    }

    public function testIndexRejectsReversedRange(): void
    {
        $this->authenticate(['calendar.view']);
        $this->bindInstance(CalendarService::class, $this->createMock(CalendarService::class));

        $result = $this->withGet(['from' => '2026-07-31', 'to' => '2026-07-01'])
            ->dispatch(CalendarApiController::class, 'index');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame(['before_from'], $result->jsonPayload()['error']['details']['to']);
    }

    public function testIndexRejectsTooWideRange(): void
    {
        $this->authenticate(['calendar.view']);
        $this->bindInstance(CalendarService::class, $this->createMock(CalendarService::class));

        $result = $this->withGet(['from' => '2026-01-01', 'to' => '2028-01-01'])
            ->dispatch(CalendarApiController::class, 'index');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame(['range_too_wide'], $result->jsonPayload()['error']['details']['to']);
    }

    public function testIndexForbiddenWithoutScope(): void
    {
        $this->authenticate(['tasks.view']);
        $this->bindInstance(CalendarService::class, $this->createMock(CalendarService::class));

        $result = $this->dispatch(CalendarApiController::class, 'index');

        $this->assertSame(403, $result->jsonStatus());
    }

    public function testShowNotFoundReturns404(): void
    {
        $this->authenticate(['calendar.view']);
        $calendar = $this->createMock(CalendarService::class);
        $calendar->method('getEvent')->willReturn(null);
        $this->bindInstance(CalendarService::class, $calendar);

        $result = $this->dispatch(CalendarApiController::class, 'show', ['999']);

        $this->assertSame(404, $result->jsonStatus());
    }

    public function testShowPassesAdminFlagFromTokenRoles(): void
    {
        // Il Service non può usare is_admin() (sessione assente nell'API): il
        // flag arriva dai ruoli del token via ApiRequestContext.
        $this->authenticate(['calendar.view'], ['admin']);

        $capturedIsAdmin = null;
        $calendar = $this->createMock(CalendarService::class);
        $calendar->method('getEvent')->willReturnCallback(
            function (int $id, int $userId, ?bool $isAdmin = null) use (&$capturedIsAdmin) {
                $capturedIsAdmin = $isAdmin;
                return ['id' => $id, 'title' => 'X', 'visibility' => 'personal', 'created_by' => 1];
            }
        );
        $this->bindInstance(CalendarService::class, $calendar);

        $result = $this->dispatch(CalendarApiController::class, 'show', ['3']);

        $this->assertTrue($capturedIsAdmin);
        $this->assertSame(200, $result->jsonStatus());
    }
}
