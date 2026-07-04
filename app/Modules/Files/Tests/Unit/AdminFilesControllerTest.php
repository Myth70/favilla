<?php

declare(strict_types=1);

namespace App\Modules\Files\Tests\Unit;

use App\Modules\Files\Controllers\AdminFilesController;
use App\Modules\Files\Services\FilesService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for AdminFilesController via the HTTP harness.
 * The file service is mocked so the list/stats render contracts are asserted
 * without a files schema.
 */
class AdminFilesControllerTest extends ControllerTestCase
{
    private function fakeService(): FilesService
    {
        $service = $this->createMock(FilesService::class);
        $service->method('listPaginated')->willReturn([
            'items'    => [['id' => 1, 'original_name' => 'a.pdf']],
            'total'    => 1,
            'page'     => 1,
            'perPage'  => 20,
            'lastPage' => 1,
        ]);
        $service->method('adminStats')->willReturn(['total' => 1]);
        $service->method('listUsers')->willReturn([]);

        return $service;
    }

    public function testIndexRendersFullPage(): void
    {
        $this->bindInstance(FilesService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->dispatch(AdminFilesController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Files/Views/admin/index', $result->renderedTemplate());
        $this->assertSame(1, $result->renderedData()['total']);
    }

    public function testIndexRendersPartialForHtmx(): void
    {
        $this->bindInstance(FilesService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->asHtmx()->dispatch(AdminFilesController::class, 'index');

        $this->assertSame('Files/Views/admin/partials/table', $result->renderedTemplate());
    }

    public function testStatsWidgetRendersPartial(): void
    {
        $this->bindInstance(FilesService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->dispatch(AdminFilesController::class, 'statsWidget');

        $this->assertSame('Files/Views/admin/partials/stats_widget', $result->renderedTemplate());
        $this->assertSame(['total' => 1], $result->renderedData()['stats']);
    }
}
