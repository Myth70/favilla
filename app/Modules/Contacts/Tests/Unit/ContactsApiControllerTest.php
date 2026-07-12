<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Api\Support\ApiRequestContext;
use App\Modules\Contacts\Controllers\Api\ContactsApiController;
use App\Modules\Contacts\Services\ContactsService;
use Tests\ControllerTestCase;

/**
 * API v1 Contatti: envelope paginato, 404, gate scope e — punto chiave — che
 * ricerca/proprietà siano scoping-ate sullo userId+ruoli del token. Le azioni
 * di scrittura verificano validazione, merge parziale in update (tags/geodati
 * non azzerati) e limite ai contatti di proprietà.
 */
class ContactsApiControllerTest extends ControllerTestCase
{
    private const WRITE_PERMISSIONS = ['contacts.view', 'contacts.create', 'contacts.edit', 'contacts.delete'];

    private function authenticate(
        ?array $scopes,
        array $roles = ['user'],
        array $permissions = ['contacts.view']
    ): ApiRequestContext {
        $context = new ApiRequestContext();
        $context->authenticate(
            7,
            ['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.test'],
            $roles,
            $permissions,
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

    public function testStoreCreatesContactAndReturns201(): void
    {
        $this->authenticate(null, ['user'], self::WRITE_PERMISSIONS);

        $capturedData = null;
        $contacts = $this->createMock(ContactsService::class);
        $contacts->method('create')->willReturnCallback(
            function (array $data, int $userId) use (&$capturedData) {
                $capturedData = $data;
                return 42;
            }
        );
        $contacts->method('find')->willReturn(['id' => 42, 'nome' => 'Nuovo', 'email' => 'n@example.test']);
        $this->bindInstance(ContactsService::class, $contacts);

        $result = $this->withPost(['nome' => 'Nuovo', 'email' => 'n@example.test', 'user_id' => 999])
            ->dispatch(ContactsApiController::class, 'store');

        $this->assertSame(201, $result->jsonStatus());
        $this->assertSame(42, $result->jsonPayload()['data']['id']);
        // Mass-assignment: user_id del client NON passa la whitelist.
        $this->assertIsArray($capturedData);
        $this->assertArrayNotHasKey('user_id', $capturedData);
        $this->assertSame('Nuovo', $capturedData['nome']);
    }

    public function testStoreRequiresNome(): void
    {
        $this->authenticate(null, ['user'], self::WRITE_PERMISSIONS);
        $this->bindInstance(ContactsService::class, $this->createMock(ContactsService::class));

        $result = $this->withPost(['nome' => '', 'email' => 'x@example.test'])
            ->dispatch(ContactsApiController::class, 'store');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame(['required'], $result->jsonPayload()['error']['details']['nome']);
    }

    public function testStoreRejectsInvalidEmail(): void
    {
        $this->authenticate(null, ['user'], self::WRITE_PERMISSIONS);
        $this->bindInstance(ContactsService::class, $this->createMock(ContactsService::class));

        $result = $this->withPost(['nome' => 'Ok', 'email' => 'non-una-email'])
            ->dispatch(ContactsApiController::class, 'store');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame(['invalid'], $result->jsonPayload()['error']['details']['email']);
    }

    public function testStoreForbiddenWithoutCreateScope(): void
    {
        $this->authenticate(['contacts.view'], ['user'], self::WRITE_PERMISSIONS);
        $this->bindInstance(ContactsService::class, $this->createMock(ContactsService::class));

        $result = $this->withPost(['nome' => 'X'])->dispatch(ContactsApiController::class, 'store');

        $this->assertSame(403, $result->jsonStatus());
    }

    public function testUpdateNotFoundForNonOwnedContact(): void
    {
        // La scrittura passa da findForUser (solo proprietà): un contatto solo
        // condiviso via ruoli risulta 404 in update.
        $this->authenticate(null, ['user'], self::WRITE_PERMISSIONS);
        $contacts = $this->createMock(ContactsService::class);
        $contacts->method('findForUser')->willReturn(null);
        $contacts->expects($this->never())->method('update');
        $this->bindInstance(ContactsService::class, $contacts);

        $result = $this->withPost(['nome' => 'Nuovo Nome'])
            ->dispatch(ContactsApiController::class, 'update', ['5']);

        $this->assertSame(404, $result->jsonStatus());
    }

    public function testUpdateIsPartialAndPreservesTagsAndGeo(): void
    {
        // Update parziale: i campi non inviati ripartono dall'esistente, così la
        // normalizzazione del Service non azzera tags e coordinate geocodificate.
        $this->authenticate(null, ['user'], self::WRITE_PERMISSIONS);

        $existing = [
            'id' => 5, 'nome' => 'Mario', 'cognome' => 'Rossi', 'tags' => 'vip, fornitore',
            'indirizzo' => 'Via Roma 1', 'latitude' => 45.1, 'longitude' => 9.2,
            'geocoding_source' => 'osm', 'geocoded_at' => '2026-01-01 00:00:00',
        ];
        $capturedData = null;
        $contacts = $this->createMock(ContactsService::class);
        $contacts->method('findForUser')->willReturn($existing);
        $contacts->method('update')->willReturnCallback(
            function (int $id, array $data, int $userId) use (&$capturedData) {
                $capturedData = $data;
                return true;
            }
        );
        $contacts->method('find')->willReturn($existing);
        $this->bindInstance(ContactsService::class, $contacts);

        $result = $this->withPost(['cognome' => 'Bianchi'])
            ->dispatch(ContactsApiController::class, 'update', ['5']);

        $this->assertSame(200, $result->jsonStatus());
        $this->assertIsArray($capturedData);
        $this->assertSame('Bianchi', $capturedData['cognome']);
        $this->assertSame('vip, fornitore', $capturedData['tags']);
        $this->assertSame('Via Roma 1', $capturedData['indirizzo']);
        $this->assertSame(45.1, $capturedData['latitude']);
    }

    public function testUpdateChangedAddressDropsStaleCoordinates(): void
    {
        $this->authenticate(null, ['user'], self::WRITE_PERMISSIONS);

        $existing = [
            'id' => 5, 'nome' => 'Mario', 'indirizzo' => 'Via Roma 1',
            'latitude' => 45.1, 'longitude' => 9.2,
            'geocoding_source' => 'osm', 'geocoded_at' => '2026-01-01 00:00:00',
        ];
        $capturedData = null;
        $contacts = $this->createMock(ContactsService::class);
        $contacts->method('findForUser')->willReturn($existing);
        $contacts->method('update')->willReturnCallback(
            function (int $id, array $data, int $userId) use (&$capturedData) {
                $capturedData = $data;
                return true;
            }
        );
        $contacts->method('find')->willReturn($existing);
        $this->bindInstance(ContactsService::class, $contacts);

        $this->withPost(['indirizzo' => 'Via Milano 2'])
            ->dispatch(ContactsApiController::class, 'update', ['5']);

        $this->assertIsArray($capturedData);
        $this->assertSame('Via Milano 2', $capturedData['indirizzo']);
        $this->assertArrayNotHasKey('latitude', $capturedData);
        $this->assertArrayNotHasKey('longitude', $capturedData);
    }

    public function testDestroyDeletesOwnedContact(): void
    {
        $this->authenticate(null, ['user'], self::WRITE_PERMISSIONS);
        $contacts = $this->createMock(ContactsService::class);
        $contacts->method('findForUser')->willReturn(['id' => 5, 'nome' => 'Mario']);
        $contacts->method('delete')->willReturn(true);
        $this->bindInstance(ContactsService::class, $contacts);

        $result = $this->dispatch(ContactsApiController::class, 'destroy', ['5']);

        $this->assertSame(200, $result->jsonStatus());
        $this->assertTrue($result->jsonPayload()['data']['deleted']);
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
