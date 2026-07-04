<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\AdminIndexController;
use App\Modules\Admin\Services\AdminIndexService;
use Tests\ControllerTestCase;

/**
 * Controller-level test for AdminIndexController via the HTTP harness.
 * The catalog service is mocked through the container; we assert the render
 * contract and that the controller forwards the catalog payload to the view.
 */
class AdminIndexControllerTest extends ControllerTestCase
{
    public function testIndexRendersCatalog(): void
    {
        $service = $this->createMock(AdminIndexService::class);
        $service->method('getCatalog')->willReturn([
            'sections' => [['id' => 'users', 'label' => 'Utenti']],
            'summary'  => ['count' => 1],
        ]);
        $this->bindInstance(AdminIndexService::class, $service);

        $this->actingAsAdmin();
        $result = $this->dispatch(AdminIndexController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Admin/Views/index', $result->renderedTemplate());
        $this->assertSame([['id' => 'users', 'label' => 'Utenti']], $result->renderedData()['sections']);
        $this->assertSame(['count' => 1], $result->renderedData()['summary']);
    }
}
