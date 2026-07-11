<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Api\Support\ApiRequestContext;
use App\Modules\Contacts\Controllers\Api\ContactsApiController;
use App\Modules\Contacts\Services\ContactsService;
use Tests\ControllerTestCase;

/**
 * API v1 Contatti (sola lettura): envelope paginato, 404, gate scope e — punto
 * chiave — che ricerca/proprietà siano scoping-ate sullo userId+ruoli del token.
 */
class ContactsApiControllerTest extends ControllerTestCase
{
    private function authenticate(?array $scopes, array $roles = ['user']): ApiRequestContext
    {
        $context = new ApiRequestContext();
        $context->authenticate(
            7,
            ['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.test'],
            $roles,
            ['contacts.view'],
            $scopes,
            9
        );
        $this->bindInstance(ApiRequestContext::class, $context);
        return $context;
    }

    public function testIndexReturnsPaginatedEnvelope(): void
    {
        $this->authenticate(['contacts.view']);

        $contacts = $this->createMock(ContactsService::class);
        $contacts->method('list')->willReturn([
            'data'    => [
                ['id' => 1, 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'm@example.test'],
            ],
            'total'   => 1,
            'page'    => 1,
            'perPage' => 24,
        ]);
        $this->bindInstance(ContactsService::class, $contacts);

        $result = $this->dispatch(ContactsApiController::class, 'index');

        $this->assertSame(200, $result->jsonStatus());
        $payload = $result->jsonPayload();
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Mario', $payload['data'][0]['nome']);
        $this->assertSame(24, $payload['meta']['per_page']);
        $this->assertSame(1, $payload['meta']['total']);
    }

    public function testShowNotFoundReturns404(): void
    {
        $this->authenticate(['contacts.view']);
        $contacts = $this->createMock(ContactsService::class);
        $contacts->method('find')->willReturn(null);
        $this->bindInstance(ContactsService::class, $contacts);

        $result = $this->dispatch(ContactsApiController::class, 'show', ['999']);

        $this->assertSame(404, $result->jsonStatus());
        $this->assertSame('not_found', $result->jsonPayload()['error']['code']);
    }

    public function testContactLookupIsScopedToTokenUserAndRoles(): void
    {
        // IDOR guard: find() riceve lo userId (7) e i ruoli del token, non input
        // del client → un contatto non proprio/non condiviso non è raggiungibile.
        $this->authenticate(['contacts.view'], ['manager']);

        $capturedUserId = null;
        $capturedRoles = null;
        $contacts = $this->createMock(ContactsService::class);
        $contacts->method('find')->willReturnCallback(
            function (int $id, int $userId, array $roles) use (&$capturedUserId, &$capturedRoles) {
                $capturedUserId = $userId;
                $capturedRoles = $roles;
                return null;
            }
        );
        $this->bindInstance(ContactsService::class, $contacts);

        $result = $this->dispatch(ContactsApiController::class, 'show', ['5']);

        $this->assertSame(7, $capturedUserId);
        $this->assertSame(['manager'], $capturedRoles);
        $this->assertSame(404, $result->jsonStatus());
    }

    public function testScopeDeniedReturns403(): void
    {
        // Token senza lo scope contacts.view (solo tasks.view) → 403.
        $context = new ApiRequestContext();
        $context->authenticate(
            7,
            ['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.test'],
            ['user'],
            ['contacts.view'],
            ['tasks.view'], // scope del token: NON include contacts.view
            9
        );
        $this->bindInstance(ApiRequestContext::class, $context);
        $this->bindInstance(ContactsService::class, $this->createMock(ContactsService::class));

        $result = $this->dispatch(ContactsApiController::class, 'index');

        $this->assertSame(403, $result->jsonStatus());
        $this->assertSame('forbidden', $result->jsonPayload()['error']['code']);
    }
}
