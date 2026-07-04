<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\HistoryRepository;
use App\Modules\Reports\Services\ReportsHistoryQueryService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\ModuleTestCase;

class ReportsHistoryQueryServiceTest extends ModuleTestCase
{
    /** @var HistoryRepository&MockObject */
    private HistoryRepository $repo;
    private ReportsHistoryQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = $this->getMockBuilder(HistoryRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listPaginated', 'getDistinctModules', 'findForUser', 'delete'])
            ->getMock();

        app()->instance(HistoryRepository::class, $this->repo);
        $this->service = new ReportsHistoryQueryService();
    }

    public function testGetPaginatedHistoryMergesRepositoryPayloadWithContext(): void
    {
        $filters = ['q' => 'cliente', 'module' => 'Reports'];

        $this->repo->expects($this->once())
            ->method('listPaginated')
            ->with($filters, 2, 20, 9, true)
            ->willReturn([
                'items' => [['id' => 1]],
                'total' => 1,
                'page' => 2,
                'per_page' => 20,
                'lastPage' => 1,
            ]);

        $this->repo->expects($this->once())
            ->method('getDistinctModules')
            ->willReturn(['Reports', 'Contatti']);

        $result = $this->service->getPaginatedHistory($filters, 2, 9, true);

        $this->assertSame(['Reports', 'Contatti'], $result['modules']);
        $this->assertTrue($result['adminView']);
        $this->assertSame($filters, $result['filters']);
        $this->assertCount(1, $result['items']);
    }

    public function testLatestForUserUsesSafeMinimumLimit(): void
    {
        $this->repo->expects($this->once())
            ->method('listPaginated')
            ->with(
                [
                    'q' => '',
                    'module' => '',
                    'format' => '',
                    'date_from' => '',
                    'date_to' => '',
                    'sort' => 'generated_at',
                    'dir' => 'DESC',
                ],
                1,
                1,
                3,
                false
            )
            ->willReturn(['items' => [['id' => 99]]]);

        $items = $this->service->latestForUser(3, false, 0);

        $this->assertSame([['id' => 99]], $items);
    }

    public function testBuildStoredFilePathRejectsEmptyAndSanitizesFilename(): void
    {
        $this->assertNull($this->service->buildStoredFilePath([]));

        $path = $this->service->buildStoredFilePath([
            'stored_filename' => '../unsafe/../../report-1.pdf',
        ]);

        $this->assertNotNull($path);
        $this->assertStringEndsWith('/storage/reports/report-1.pdf', (string) $path);
    }

    public function testBuildDownloadMetadataBuildsMimeAndSafeFilename(): void
    {
        $meta = $this->service->buildDownloadMetadata([
            'output_format' => 'excel',
            'template_name' => 'Vendite Q1/2026',
            'generated_at' => '2026-04-24 09:00:00',
        ]);

        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $meta['mime']);
        $this->assertSame('Vendite_Q1_2026_20260424.xlsx', $meta['downloadName']);
    }

    public function testFindAndDeleteDelegateToRepository(): void
    {
        $this->repo->expects($this->once())
            ->method('findForUser')
            ->with(5, 8, true)
            ->willReturn(['id' => 5]);

        $this->repo->expects($this->once())
            ->method('delete')
            ->with(5)
            ->willReturn(true);

        $this->assertSame(['id' => 5], $this->service->findEntryForUser(5, 8, true));
        $this->assertTrue($this->service->deleteEntry(5));
    }
}
