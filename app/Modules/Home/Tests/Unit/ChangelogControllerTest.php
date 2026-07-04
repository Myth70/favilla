<?php

declare(strict_types=1);

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Controllers\ChangelogController;
use App\Modules\Home\Services\ChangelogService;
use Tests\ControllerTestCase;

/**
 * Controller-level test for the public Home ChangelogController via the HTTP
 * harness. The timeline service is mocked so the render contract is asserted
 * without a changelogs schema.
 */
class ChangelogControllerTest extends ControllerTestCase
{
    public function testIndexRendersPublicTimeline(): void
    {
        $service = $this->createMock(ChangelogService::class);
        $service->method('getPublicTimeline')->willReturn([
            'items'  => [['version' => '1.0.0']],
            'total'  => 1,
            'latest' => ['version' => '1.0.0', 'release_date' => '2026-01-15'],
        ]);
        $this->bindInstance(ChangelogService::class, $service);

        $this->actingAs(1);
        $result = $this->dispatch(ChangelogController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Home/Views/changelog', $result->renderedTemplate());
        $this->assertSame(1, $result->renderedData()['totalPublished']);
        $this->assertSame('1.0.0', $result->renderedData()['latestVersion']);
    }
}
