<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\AdminDashboardController;
use App\Modules\Admin\Services\AdminDashboardService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for AdminDashboardController via the HTTP harness.
 * Every aggregate is provided by a mocked AdminDashboardService so the tests
 * focus on the controller's render/partial contract.
 */
class AdminDashboardControllerTest extends ControllerTestCase
{
    private function fakeService(): AdminDashboardService
    {
        $service = $this->createMock(AdminDashboardService::class);
        $service->method('getStats')->willReturn(['users' => 3]);
        $service->method('getUnifiedTimeline')->willReturn([['id' => 1]]);
        $service->method('getLoginSecurityChartData')->willReturn([]);
        $service->method('getAuditTypeDistribution')->willReturn([]);
        $service->method('getTopActiveUsers')->willReturn([]);
        $service->method('getOnlineSessions')->willReturn([['user_id' => 1]]);
        $service->method('getModuleStatus')->willReturn(['Admin' => true]);
        $service->method('getSystemInfo')->willReturn(['php' => PHP_VERSION]);

        return $service;
    }

    public function testIndexRendersDashboard(): void
    {
        $this->bindInstance(AdminDashboardService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->dispatch(AdminDashboardController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Admin/Views/dashboard/index', $result->renderedTemplate());
        $this->assertSame(['users' => 3], $result->renderedData()['stats']);
    }

    public function testStatsWidgetRendersPartial(): void
    {
        $this->bindInstance(AdminDashboardService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->dispatch(AdminDashboardController::class, 'statsWidget');

        $this->assertSame('Admin/Views/dashboard/partials/stats-widget', $result->renderedTemplate());
        $this->assertSame(['users' => 3], $result->renderedData()['stats']);
    }

    public function testOnlineWidgetRendersPartial(): void
    {
        $this->bindInstance(AdminDashboardService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->dispatch(AdminDashboardController::class, 'onlineWidget');

        $this->assertSame('Admin/Views/dashboard/partials/online-widget', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['onlineSessions']);
    }
}
