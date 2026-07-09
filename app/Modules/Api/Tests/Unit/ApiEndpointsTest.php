<?php

declare(strict_types=1);

namespace App\Modules\Api\Tests\Unit;

use App\Modules\Api\Controllers\MeApiController;
use App\Modules\Api\Support\ApiRequestContext;
use App\Modules\Tasks\Controllers\Api\TasksApiController;
use App\Modules\Tasks\Services\TasksService;
use Tests\ControllerTestCase;

/**
 * Endpoint API via l'harness HTTP: envelope di successo (/me, /tasks) e gate
 * sugli scope (403). ApiRequestContext è pre-popolato (nei test il middleware
 * non gira); TasksService è mockato.
 */
class ApiEndpointsTest extends ControllerTestCase
{
    private function authenticate(?array $scopes, array $permissions = ['tasks.view', 'tasks.create', 'tasks.edit', 'tasks.delete']): ApiRequestContext
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

    public function testMeReturnsIdentityEnvelope(): void
    {
        $this->authenticate(['tasks.view']);

        $result = $this->dispatch(MeApiController::class, 'show');

        $this->assertTrue($result->isJson());
        $this->assertSame(200, $result->jsonStatus());
        $payload = $result->jsonPayload();
        $this->assertSame(7, $payload['data']['id']);
        $this->assertSame(['tasks.view'], $payload['data']['scopes']);
        $this->assertContains('user', $payload['data']['roles']);
    }

    public function testTasksIndexReturnsPaginatedEnvelope(): void
    {
        $this->authenticate(null); // permessi pieni

        $tasks = $this->createMock(TasksService::class);
        $tasks->method('list')->willReturn([
            'data'  => [
                ['id' => 1, 'title' => 'A', 'status' => 'todo', 'priority' => 'medium'],
                ['id' => 2, 'title' => 'B', 'status' => 'done', 'priority' => 'high'],
            ],
            'total' => 2,
            'page'  => 1,
        ]);
        $this->bindInstance(TasksService::class, $tasks);

        $result = $this->dispatch(TasksApiController::class, 'index');

        $this->assertSame(200, $result->jsonStatus());
        $payload = $result->jsonPayload();
        $this->assertCount(2, $payload['data']);
        $this->assertSame(1, $payload['data'][0]['id']);
        $this->assertSame(2, $payload['meta']['total']);
        $this->assertSame(15, $payload['meta']['per_page']);
    }

    public function testTasksStoreRequiresTitle(): void
    {
        $this->authenticate(null);
        $this->bindInstance(TasksService::class, $this->createMock(TasksService::class));

        $result = $this->withPost(['title' => ''])
            ->dispatch(TasksApiController::class, 'store');

        $this->assertSame(422, $result->jsonStatus());
        $this->assertSame('validation_failed', $result->jsonPayload()['error']['code']);
    }

    public function testTasksStoreForbiddenWhenScopeMissing(): void
    {
        // Token con scope limitato a sola lettura: create fuori scope => 403.
        $this->authenticate(['tasks.view']);
        $this->bindInstance(TasksService::class, $this->createMock(TasksService::class));

        $result = $this->withPost(['title' => 'X'])
            ->dispatch(TasksApiController::class, 'store');

        $this->assertSame(403, $result->jsonStatus());
        $this->assertSame('forbidden', $result->jsonPayload()['error']['code']);
    }

    public function testTasksShowNotFound(): void
    {
        $this->authenticate(null);
        $tasks = $this->createMock(TasksService::class);
        $tasks->method('find')->willReturn(null);
        $this->bindInstance(TasksService::class, $tasks);

        $result = $this->dispatch(TasksApiController::class, 'show', ['404']);

        $this->assertSame(404, $result->jsonStatus());
        $this->assertSame('not_found', $result->jsonPayload()['error']['code']);
    }
}
