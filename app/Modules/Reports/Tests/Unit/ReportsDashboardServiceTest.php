<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\TemplateRepository;
use App\Modules\Reports\Services\ExportProviderService;
use App\Modules\Reports\Services\HistoryService;
use App\Modules\Reports\Services\ReportsDashboardService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

class ReportsDashboardServiceTest extends TestCase
{
    use MakesContainer;

    public function testGetDashboardDataCombinesStatsCountAndSources(): void
    {
        $history = $this->createMock(HistoryService::class);
        $history->method('getStats')->willReturn(['total' => 4]);

        $templateRepo = $this->createMock(TemplateRepository::class);
        $templateRepo->method('count')->willReturn(9);

        $exportProvider = $this->createMock(ExportProviderService::class);
        $exportProvider->method('getSourcesForUser')->willReturn([['module' => 'Demo']]);

        $this->freshContainer();
        $this->bindInstance(HistoryService::class, $history);
        $this->bindInstance(TemplateRepository::class, $templateRepo);
        $this->bindInstance(ExportProviderService::class, $exportProvider);

        $data = (new ReportsDashboardService())->getDashboardData(['id' => 1]);

        $this->assertSame(['total' => 4], $data['stats']);
        $this->assertSame(9, $data['templateCount']);
        $this->assertSame([['module' => 'Demo']], $data['sources']);
    }
}
