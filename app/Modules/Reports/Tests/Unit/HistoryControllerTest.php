<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Controllers\HistoryController;
use App\Modules\Reports\Services\ReportsHistoryQueryService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for HistoryController via the HTTP harness.
 * The paginated history query is mocked; download/destroy/cleanup terminate
 * with raw exit and are left to the Integration suite.
 */
class HistoryControllerTest extends ControllerTestCase
{
    private function bindQuery(): void
    {
        $service = $this->createMock(ReportsHistoryQueryService::class);
        $service->method('getPaginatedHistory')->willReturn([
            'items'     => [['id' => 1, 'template_name' => 'Foo']],
            'total'     => 1,
            'page'      => 1,
            'per_page'  => 20,
            'lastPage'  => 1,
            'filters'   => [],
            'adminView' => false,
            'modules'   => [],
        ]);
        $this->bindInstance(ReportsHistoryQueryService::class, $service);
    }

    public function testIndexRendersFullPage(): void
    {
        $this->bindQuery();
        $this->actingAs(1);

        $result = $this->dispatch(HistoryController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Reports/Views/history/index', $result->renderedTemplate());
        $this->assertSame(1, $result->renderedData()['total']);
    }

    public function testIndexRendersPartialForHtmx(): void
    {
        $this->bindQuery();
        $this->actingAs(1);

        $result = $this->asHtmx()->dispatch(HistoryController::class, 'index');

        $this->assertSame('Reports/Views/history/partials/history_table', $result->renderedTemplate());
    }
}
