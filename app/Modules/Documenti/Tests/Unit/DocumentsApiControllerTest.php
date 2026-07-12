<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Api\Support\ApiRequestContext;
use App\Modules\Documenti\Controllers\Api\DocumentsApiController;
use App\Modules\Documenti\Services\DocumentoService;
use Tests\ControllerTestCase;

/**
 * API v1 Documenti (sola lettura, metadati): envelope paginato, filtri
 * validati, gate scope e — punto chiave — il permission-checker passato al
 * Service risolve i permessi dal token (min(permessi utente, scope)), non
 * dalla sessione.
 */
class DocumentsApiControllerTest extends ControllerTestCase
{
    private function authenticate(?array $scopes, array $permissions = ['documenti.view']): ApiRequestContext
    {
        $context = new ApiRequestContext();
        $context->authenticate(
            7,
            ['id' => 7, 'name' => 'Ada', 'email' => 'ada@example.test'],
            ['user'],
            $permissions,
            $scopes,
            9
        );
        $this->bindInstance(ApiRequestContext::class, $context);
        return $context;
    }

    public function testIndexReturnsPaginatedEnvelope(): void
    {
        $this->authenticate(['documenti.view']);

        $documents = $this->createMock(DocumentoService::class);
        $documents->method('listPaginated')->willReturn([
            'items' => [
                ['id' => 1, 'titolo' => 'Procedura X', 'stato' => 'pubblicato', 'versione_no' => 3],
            ],
            'total' => 1,
            'page' => 1,
        ]);
        $this->bindInstance(DocumentoService::class, $documents);

        $result = $this->dispatch(DocumentsApiController::class, 'index');

        $this->assertSame(200, $result->jsonStatus());
        $payload = $result->jsonPayload();
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Procedura X', $payload['data'][0]['titolo']);
        $this->assertSame(3, $payload['data'][0]['versione_no']);
        $this->assertSame(1, $payload['meta']['total']);
    }

    public function testIndexRejectsInvalidStato(): void
    {
        $this->authenticate(['documenti.view']);
        $this->bindInstance(DocumentoService::class, $this->createMock(DocumentoService::class));

        $result = $this->withGet(['stato' => 'bogus'])
            ->dispatch(DocumentsApiController::class, 'index');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame(['invalid'], $result->jsonPayload()['error']['details']['stato']);
    }

    public function testIndexForbiddenWithoutScope(): void
    {
        $this->authenticate(['tasks.view']);
        $this->bindInstance(DocumentoService::class, $this->createMock(DocumentoService::class));

        $result = $this->dispatch(DocumentsApiController::class, 'index');

        $this->assertSame(403, $result->jsonStatus());
    }

    public function testPermissionCheckerReflectsTokenScopes(): void
    {
        // L'utente HA documenti.admin, ma il token è limitato a documenti.view:
        // il checker passato al Service deve negare documenti.admin (niente
        // escalation della visibilità oltre gli scope del token).
        $this->authenticate(['documenti.view'], ['documenti.view', 'documenti.admin']);

        $capturedCan = null;
        $documents = $this->createMock(DocumentoService::class);
        $documents->method('listPaginated')->willReturnCallback(
            function (array $filters, int $userId, ?callable $can = null) use (&$capturedCan) {
                $capturedCan = $can;
                return ['items' => [], 'total' => 0, 'page' => 1];
            }
        );
        $this->bindInstance(DocumentoService::class, $documents);

        $this->dispatch(DocumentsApiController::class, 'index');

        $this->assertIsCallable($capturedCan);
        $this->assertTrue($capturedCan('documenti.view'));
        $this->assertFalse($capturedCan('documenti.admin'));
    }

    public function testShowNotFoundReturns404(): void
    {
        $this->authenticate(['documenti.view']);
        $documents = $this->createMock(DocumentoService::class);
        $documents->method('findVisible')->willReturn(null);
        $this->bindInstance(DocumentoService::class, $documents);

        $result = $this->dispatch(DocumentsApiController::class, 'show', ['999']);

        $this->assertSame(404, $result->jsonStatus());
        $this->assertSame('not_found', $result->jsonPayload()['error']['code']);
    }

    public function testShowSerializesVisibleDocument(): void
    {
        $this->authenticate(['documenti.view']);

        $capturedUserId = null;
        $documents = $this->createMock(DocumentoService::class);
        $documents->method('findVisible')->willReturnCallback(
            function (int $docId, int $userId, ?callable $can = null) use (&$capturedUserId) {
                $capturedUserId = $userId;
                return [
                    'id' => $docId, 'protocollo' => 'DOC-2026-0001', 'titolo' => 'Procedura X',
                    'stato' => 'pubblicato', 'categoria_id' => 2, 'versione_no' => 3,
                    'owner_user_id' => 4,
                ];
            }
        );
        $this->bindInstance(DocumentoService::class, $documents);

        $result = $this->dispatch(DocumentsApiController::class, 'show', ['5']);

        $this->assertSame(7, $capturedUserId);
        $this->assertSame(200, $result->jsonStatus());
        $payload = $result->jsonPayload();
        $this->assertSame('DOC-2026-0001', $payload['data']['protocollo']);
        $this->assertSame(2, $payload['data']['categoria_id']);
    }
}
