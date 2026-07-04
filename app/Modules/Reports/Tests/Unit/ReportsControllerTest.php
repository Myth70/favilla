<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Controllers\ReportsController;
use App\Modules\Reports\Services\ReportsDashboardService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for ReportsController via the HTTP harness.
 * The DB-free params guard plus the JSON endpoints (mocked dashboard service).
 */
class ReportsControllerTest extends ControllerTestCase
{
    public function testSourceFieldsRequiresParams(): void
    {
        $this->actingAs(1);

        $result = $this->withGet([])->dispatch(ReportsController::class, 'sourceFields');

        $this->assertTrue($result->isJson());
        $this->assertSame(400, $result->jsonStatus());
        $this->assertArrayHasKey('error', $result->jsonPayload());
    }

    public function testSourceFieldsReturns404WhenSourceUnknown(): void
    {
        $service = $this->createMock(ReportsDashboardService::class);
        $service->method('getSourceFields')->willReturn(null);
        $this->bindInstance(ReportsDashboardService::class, $service);

        $this->actingAs(1);
        $result = $this->withGet(['module' => 'Foo', 'source_key' => 'bar'])
            ->dispatch(ReportsController::class, 'sourceFields');

        $this->assertTrue($result->isJson());
        $this->assertSame(404, $result->jsonStatus());
    }

    public function testSourcesReturnsJsonList(): void
    {
        $service = $this->createMock(ReportsDashboardService::class);
        $service->method('getSourcesForUser')->willReturn([['key' => 'contacts']]);
        $this->bindInstance(ReportsDashboardService::class, $service);

        $this->actingAs(1);
        $result = $this->dispatch(ReportsController::class, 'sources');

        $this->assertTrue($result->isJson());
        $this->assertSame([['key' => 'contacts']], $result->jsonPayload()['sources']);
    }
}
