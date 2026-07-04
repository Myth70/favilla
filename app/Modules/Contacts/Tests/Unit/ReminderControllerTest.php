<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Controllers\ReminderController;
use App\Modules\Contacts\Services\ContactsReminderService;
use Tests\ControllerTestCase;

/**
 * Controller-level test for the contacts reminder endpoint via the HTTP harness.
 * The reminder service is mocked so the JSON response contract is asserted
 * without a real reminders schema.
 */
class ReminderControllerTest extends ControllerTestCase
{
    public function testProcessReturnsSentCountAsJson(): void
    {
        $service = $this->createMock(ContactsReminderService::class);
        $service->expects($this->once())
            ->method('processForUser')
            ->with(7)
            ->willReturn(4);
        $this->bindInstance(ContactsReminderService::class, $service);

        $this->actingAs(7);
        $result = $this->dispatch(ReminderController::class, 'process');

        $this->assertTrue($result->isJson());
        $this->assertSame(['ok' => true, 'sent' => 4], $result->jsonPayload());
    }
}
