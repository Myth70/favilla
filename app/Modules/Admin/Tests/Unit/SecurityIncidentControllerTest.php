<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Controllers\SecurityIncidentController;
use App\Services\SecurityIncidentService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for SecurityIncidentController via the HTTP harness.
 *
 * The underlying service relies on MySQL-only date arithmetic (DATE_SUB/INTERVAL),
 * so the collaborator is mocked through the container and we assert the
 * controller's delegation + render contract.
 */
class SecurityIncidentControllerTest extends ControllerTestCase
{
    private function fakeService(): SecurityIncidentService
    {
        $service = $this->createMock(SecurityIncidentService::class);
        $service->method('getRecent')->willReturn([
            'items' => [['id' => 1, 'type' => 'brute_force', 'severity' => 'high']],
            'total' => 1,
        ]);
        $service->method('getSummary')->willReturn(['24h' => [], '7d' => [], '30d' => []]);

        return $service;
    }

    public function testIndexRendersFullPage(): void
    {
        $this->bindInstance(SecurityIncidentService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->dispatch(SecurityIncidentController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Admin/Views/security-incidents', $result->renderedTemplate());
        $this->assertSame(1, $result->renderedData()['total']);
        $this->assertCount(1, $result->renderedData()['incidents']);
    }

    public function testIndexRendersPartialForHtmx(): void
    {
        $this->bindInstance(SecurityIncidentService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->asHtmx()->dispatch(SecurityIncidentController::class, 'index');

        $this->assertSame('Admin/Views/partials/security_incidents_table', $result->renderedTemplate());
    }

    public function testSummaryWidgetRendersSummaryPartial(): void
    {
        $this->bindInstance(SecurityIncidentService::class, $this->fakeService());
        $this->actingAsAdmin();

        $result = $this->dispatch(SecurityIncidentController::class, 'summaryWidget');

        $this->assertSame('Admin/Views/partials/security_incidents_summary', $result->renderedTemplate());
        $this->assertArrayHasKey('summary', $result->renderedData());
    }
}
