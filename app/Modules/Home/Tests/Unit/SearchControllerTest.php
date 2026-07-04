<?php

declare(strict_types=1);

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Controllers\SearchController;
use App\Services\GlobalSearchService;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for SearchController via the HTTP harness.
 * The empty-query path is DB-free (the search service is never called); the
 * populated query uses a mocked GlobalSearchService.
 */
class SearchControllerTest extends ControllerTestCase
{
    public function testIndexWithEmptyQueryRendersNoResults(): void
    {
        $this->actingAs(1);

        $result = $this->withGet(['q' => ''])->dispatch(SearchController::class, 'index');

        $this->assertTrue($result->didRender());
        $this->assertSame('Home/Views/search', $result->renderedTemplate());
        $this->assertSame(0, $result->renderedData()['totalResults']);
    }

    public function testQuickWithEmptyQueryRendersPartial(): void
    {
        $this->actingAs(1);

        $result = $this->withGet(['q' => ''])->dispatch(SearchController::class, 'quick');

        $this->assertSame('Home/Views/partials/search_results', $result->renderedTemplate());
    }

    public function testIndexCountsResultsForQuery(): void
    {
        $service = $this->createMock(GlobalSearchService::class);
        $service->method('search')->willReturn([
            ['label' => 'Contatti', 'results' => [['id' => 1], ['id' => 2]]],
        ]);
        $this->bindInstance(GlobalSearchService::class, $service);

        $this->actingAs(1);
        $result = $this->withGet(['q' => 'mario'])->dispatch(SearchController::class, 'index');

        $this->assertSame('Home/Views/search', $result->renderedTemplate());
        $this->assertSame(2, $result->renderedData()['totalResults']);
    }
}
