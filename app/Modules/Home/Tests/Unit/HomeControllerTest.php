<?php

declare(strict_types=1);

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Controllers\HomeController;
use App\Modules\Home\Services\DashboardService;
use App\Modules\Home\Services\OggiService;
use App\Modules\Home\Services\WidgetPreferencesService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for HomeController via the HTTP harness.
 * The dashboard/oggi/widget services are mocked through the container.
 */
class HomeControllerTest extends ControllerTestCase
{
    public function testSaveWidgetLayoutDelegatesToWidgetPreferences(): void
    {
        $widgets = $this->createMock(WidgetPreferencesService::class);
        // php://input is empty under PHPUnit → an empty (but valid) layout is saved.
        $widgets->expects($this->once())->method('saveLayout')->with(1, []);
        $this->bindInstance(WidgetPreferencesService::class, $widgets);

        $this->actingAs(1);
        $result = $this->dispatch(HomeController::class, 'saveWidgetLayout');

        $this->assertFalse($result->isRedirect());
        $this->assertFalse($result->didRender());
    }

    public function testIndexRendersDashboard(): void
    {
        $dashboard = $this->createMock(DashboardService::class);
        $dashboard->method('buildDashboard')->willReturn(['widgets' => []]);
        $dashboard->method('getUnreadCount')->willReturn(2);
        $this->bindInstance(DashboardService::class, $dashboard);

        $this->actingAs(1);
        $result = $this->dispatch(HomeController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Home/Views/index', $result->renderedTemplate());
        $this->assertSame(2, $result->renderedData()['unreadCount']);
    }

    public function testOggiRendersFeed(): void
    {
        $oggi = $this->createMock(OggiService::class);
        $oggi->method('buildFeed')->willReturn([['type' => 'task']]);
        $oggi->method('getCompletedTodayList')->willReturn([]);
        $this->bindInstance(OggiService::class, $oggi);

        $this->actingAs(1);
        $result = $this->dispatch(HomeController::class, 'oggi');

        $this->assertSame('Home/Views/oggi', $result->renderedTemplate());
        $this->assertCount(1, $result->renderedData()['todayFeed']);
    }
}
