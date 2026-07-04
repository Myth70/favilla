<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Tests\Unit;

use App\Modules\Calendar\Controllers\CalendarController;
use App\Modules\Calendar\Services\CalendarService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for CalendarController via the HTTP harness.
 * Covers the DB-free JSON validation guards (events/move) and the validation
 * re-render of store() (calendar service mocked for the roles list).
 */
class CalendarControllerTest extends ControllerTestCase
{
    public function testEventsRejectsInvalidDateRange(): void
    {
        $this->actingAs(1);

        $result = $this->withGet(['start' => 'not-a-date'])
            ->dispatch(CalendarController::class, 'events');

        $this->assertTrue($result->isJson());
        $this->assertSame(400, $result->jsonStatus());
    }

    public function testMoveRejectsMissingStart(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['start' => ''])
            ->dispatch(CalendarController::class, 'move', ['5']);

        $this->assertTrue($result->isJson());
        $this->assertSame(400, $result->jsonStatus());
    }

    public function testMoveRejectsEndBeforeStart(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['start' => '2026-02-10 10:00', 'end' => '2026-02-10 09:00'])
            ->dispatch(CalendarController::class, 'move', ['5']);

        $this->assertTrue($result->isJson());
        $this->assertSame(400, $result->jsonStatus());
    }

    public function testStoreRendersFormWithErrorsOnInvalidInput(): void
    {
        $service = $this->createMock(CalendarService::class);
        $service->method('getRolesList')->willReturn([]);
        $this->bindInstance(CalendarService::class, $service);

        $this->actingAs(1);
        // Empty title + missing start → validation fails, modal form re-rendered.
        $result = $this->withPost([])->dispatch(CalendarController::class, 'store');

        $this->assertSame('Calendar/Views/partials/modal_form', $result->renderedTemplate());
        $this->assertNotEmpty($result->renderedData()['errors']);
    }
}
