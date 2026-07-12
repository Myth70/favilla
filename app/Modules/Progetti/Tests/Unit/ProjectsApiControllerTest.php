<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Tests\Unit;

use App\Modules\Api\Support\ApiRequestContext;
use App\Modules\Progetti\Controllers\Api\ProjectsApiController;
use App\Modules\Progetti\Services\ProgettiService;
use Tests\ControllerTestCase;

/**
 * API v1 Progetti (sola lettura): envelope paginato, filtri validati, gate
 * scope e — punto chiave — il flag viewAll risolto dai permessi del token
 * (il Service senza sessione non può usare has_permission()).
 */
class ProjectsApiControllerTest extends ControllerTestCase
{
    private function authenticate(?array $scopes, array $permissions = ['progetti.view']): ApiRequestContext
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
        $this->authenticate(['progetti.view']);

        $projects = $this->createMock(ProgettiService::class);
        $projects->method('listForUser')->willReturn([
            'items' => [
                ['id' => 1, 'name' => 'Alpha', 'status' => 'active', 'progress_cached' => 40.5],
            ],
            'total' => 1,
            'page' => 1,
            'per_page' => 20,
        ]);
        $this->bindInstance(ProgettiService::class, $projects);

        $result = $this->dispatch(ProjectsApiController::class, 'index');

        $this->assertSame(200, $result->jsonStatus());
        $payload = $result->jsonPayload();
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Alpha', $payload['data'][0]['name']);
        $this->assertSame(40.5, $payload['data'][0]['progress']);
        $this->assertSame(1, $payload['meta']['total']);
    }

    public function testIndexRejectsInvalidStatus(): void
    {
        $this->authenticate(['progetti.view']);
        $this->bindInstance(ProgettiService::class, $this->createMock(ProgettiService::class));

        $result = $this->withGet(['status' => 'bogus'])
            ->dispatch(ProjectsApiController::class, 'index');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame(['invalid'], $result->jsonPayload()['error']['details']['status']);
    }

    public function testIndexForbiddenWithoutScope(): void
    {
        $this->authenticate(['tasks.view']);
        $this->bindInstance(ProgettiService::class, $this->createMock(ProgettiService::class));

        $result = $this->dispatch(ProjectsApiController::class, 'index');

        $this->assertSame(403, $result->jsonStatus());
    }

    public function testViewAllFalseForMemberOnlyToken(): void
    {
        // Senza progetti.view_all/manage_all nel token, il Service riceve
        // viewAll=false: scoping owner/membro come nella UI.
        $this->authenticate(['progetti.view']);

        $capturedViewAll = null;
        $projects = $this->createMock(ProgettiService::class);
        $projects->method('listForUser')->willReturnCallback(
            function (int $userId, array $filters = [], ?bool $viewAll = null) use (&$capturedViewAll) {
                $capturedViewAll = $viewAll;
                return ['items' => [], 'total' => 0, 'page' => 1];
            }
        );
        $this->bindInstance(ProgettiService::class, $projects);

        $this->dispatch(ProjectsApiController::class, 'index');

        $this->assertFalse($capturedViewAll);
    }

    public function testViewAllTrueWithViewAllScope(): void
    {
        $this->authenticate(
            ['progetti.view', 'progetti.view_all'],
            ['progetti.view', 'progetti.view_all']
        );

        $capturedViewAll = null;
        $projects = $this->createMock(ProgettiService::class);
        $projects->method('listForUser')->willReturnCallback(
            function (int $userId, array $filters = [], ?bool $viewAll = null) use (&$capturedViewAll) {
                $capturedViewAll = $viewAll;
                return ['items' => [], 'total' => 0, 'page' => 1];
            }
        );
        $this->bindInstance(ProgettiService::class, $projects);

        $this->dispatch(ProjectsApiController::class, 'index');

        $this->assertTrue($capturedViewAll);
    }

    public function testShowNotFoundReturns404(): void
    {
        $this->authenticate(['progetti.view']);
        $projects = $this->createMock(ProgettiService::class);
        $projects->method('findForUser')->willReturn(null);
        $this->bindInstance(ProgettiService::class, $projects);

        $result = $this->dispatch(ProjectsApiController::class, 'show', ['999']);

        $this->assertSame(404, $result->jsonStatus());
        $this->assertSame('not_found', $result->jsonPayload()['error']['code']);
    }

    public function testShowIsScopedToTokenUser(): void
    {
        $this->authenticate(['progetti.view']);

        $capturedUserId = null;
        $projects = $this->createMock(ProgettiService::class);
        $projects->method('findForUser')->willReturnCallback(
            function (int $projectId, int $userId, ?bool $viewAll = null) use (&$capturedUserId) {
                $capturedUserId = $userId;
                return ['id' => $projectId, 'name' => 'Alpha', 'status' => 'active'];
            }
        );
        $this->bindInstance(ProgettiService::class, $projects);

        $result = $this->dispatch(ProjectsApiController::class, 'show', ['5']);

        $this->assertSame(7, $capturedUserId);
        $this->assertSame(200, $result->jsonStatus());
    }
}
