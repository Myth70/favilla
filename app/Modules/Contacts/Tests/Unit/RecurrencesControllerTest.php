<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Controllers\RecurrencesController;
use App\Modules\Contacts\Services\ContactsService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for RecurrencesController via the HTTP harness.
 * ContactsService is mocked so ownership resolution is deterministic; we then
 * assert the not-found guard and the validation-failure partial render.
 */
class RecurrencesControllerTest extends ControllerTestCase
{
    private function bindContactsService(?array $contatto): void
    {
        $service = $this->createMock(ContactsService::class);
        $service->method('findForUser')->willReturn($contatto);
        $this->bindInstance(ContactsService::class, $service);
    }

    public function testStoreReturns404WhenContactNotFound(): void
    {
        $this->bindContactsService(null);
        $this->actingAs(1);

        $result = $this->withPost([])->dispatch(RecurrencesController::class, 'store', ['5']);

        $this->assertFalse($result->isRedirect());
        $this->assertFalse($result->didRender());
        $this->assertStringContainsString('non trovato', $result->echoed);
    }

    public function testStoreRendersFormWithErrorsOnInvalidInput(): void
    {
        $this->bindContactsService(['id' => 5, 'nome' => 'Mario', 'cognome' => 'Rossi']);
        $this->actingAs(1);

        // Missing titolo/data → validation fails, recurrence form re-rendered.
        $result = $this->withPost(['tipo' => 'evento'])
            ->dispatch(RecurrencesController::class, 'store', ['5']);

        $this->assertSame('Contacts/Views/partials/recurrence_form', $result->renderedTemplate());
        $this->assertNotEmpty($result->renderedData()['errors']);
    }

    public function testEditFormRendersBlankFormForNew(): void
    {
        $this->bindContactsService(['id' => 5, 'nome' => 'Mario', 'cognome' => 'Rossi']);
        $this->actingAs(1);

        $result = $this->dispatch(RecurrencesController::class, 'editForm', ['5', 'new']);

        $this->assertSame('Contacts/Views/partials/recurrence_form', $result->renderedTemplate());
        $this->assertNull($result->renderedData()['ric']);
    }
}
