<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\StylePresetRepository;
use App\Modules\Reports\Repositories\TemplateRepository;
use App\Modules\Reports\Services\BundledTemplateService;
use App\Modules\Reports\Services\ExportProviderService;
use App\Modules\Reports\Services\ReportsTemplateQueryService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\ModuleTestCase;

class ReportsTemplateQueryServiceTest extends ModuleTestCase
{
    /** @var TemplateRepository&MockObject */
    private TemplateRepository $templateRepo;
    /** @var StylePresetRepository&MockObject */
    private StylePresetRepository $styleRepo;
    /** @var ExportProviderService&MockObject */
    private ExportProviderService $exportProviderService;
    private ReportsTemplateQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateRepo = $this->getMockBuilder(TemplateRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'listVisible',
                'getDistinctModules',
                'findWithStyle',
                'create',
                'find',
                'update',
                'delete',
                'countBundledByModule',
            ])
            ->getMock();

        $this->styleRepo = $this->getMockBuilder(StylePresetRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listAll'])
            ->getMock();

        $this->exportProviderService = $this->getMockBuilder(ExportProviderService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSourcesForUser'])
            ->getMock();

        app()->instance(TemplateRepository::class, $this->templateRepo);
        app()->instance(StylePresetRepository::class, $this->styleRepo);
        app()->instance(ExportProviderService::class, $this->exportProviderService);

        $this->service = new ReportsTemplateQueryService();
    }

    public function testGetIndexDataReturnsRepositoryDataWithFiltersAndModules(): void
    {
        $filters = ['q' => 'fatture', 'module' => 'Contatti'];

        $this->templateRepo->expects($this->once())
            ->method('listVisible')
            ->with(4, ['manager'], $filters, 3, 20)
            ->willReturn([
                'items' => [['id' => 1]],
                'total' => 1,
                'page' => 3,
                'per_page' => 20,
                'lastPage' => 1,
            ]);

        $this->templateRepo->expects($this->once())
            ->method('getDistinctModules')
            ->willReturn(['Contatti', 'Reports']);

        $result = $this->service->getIndexData(4, ['manager'], $filters, 3);

        $this->assertSame($filters, $result['filters']);
        $this->assertSame(['Contatti', 'Reports'], $result['modules']);
        $this->assertCount(1, $result['items']);
    }

    public function testGetDesignerDataReturnsNullWhenTemplateDoesNotExist(): void
    {
        $this->templateRepo->expects($this->once())
            ->method('findWithStyle')
            ->with(999)
            ->willReturn(null);

        $this->assertNull($this->service->getDesignerData(['id' => 1], 999));
    }

    public function testGetDesignerDataWithoutTemplateReturnsSourcesAndStyles(): void
    {
        $user = ['id' => 8, 'roles' => ['admin']];

        $this->exportProviderService->expects($this->once())
            ->method('getSourcesForUser')
            ->with($user)
            ->willReturn([['key' => 'orders']]);

        $this->styleRepo->expects($this->once())
            ->method('listAll')
            ->willReturn([['id' => 10, 'name' => 'Default']]);

        $result = $this->service->getDesignerData($user, null);

        $this->assertNull($result['template']);
        $this->assertSame([['key' => 'orders']], $result['sources']);
        $this->assertSame([['id' => 10, 'name' => 'Default']], $result['styles']);
    }

    public function testDuplicateTemplateReturnsNullWhenSourceTemplateMissing(): void
    {
        $this->templateRepo->expects($this->once())
            ->method('find')
            ->with(404)
            ->willReturn(null);

        $this->templateRepo->expects($this->never())
            ->method('create');

        $this->assertNull($this->service->duplicateTemplate(404, 5));
    }

    public function testDuplicateTemplateCreatesPrivateCloneForRequester(): void
    {
        $this->templateRepo->expects($this->once())
            ->method('find')
            ->with(12)
            ->willReturn([
                'name' => 'Template Base',
                'description' => 'Descrizione',
                'module' => 'Contatti',
                'source_key' => 'contacts',
                'output_format' => 'pdf',
                'source_type' => 'list',
                'filters_config' => '{"a":1}',
                'sorting_config' => '{"b":1}',
                'template_html' => '<h1>ok</h1>',
                'style_preset_id' => 3,
                'max_rows' => 500,
            ]);

        $this->templateRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['name'] === 'Template Base (copia)'
                    && $data['visibility'] === 'private'
                    && $data['visible_to_roles'] === null
                    && $data['created_by'] === 55;
            }))
            ->willReturn(101);

        $cloneId = $this->service->duplicateTemplate(12, 55);

        $this->assertSame(101, $cloneId);
    }

    public function testGetBundledDataReturnsAvailableTemplatesAndCounts(): void
    {
        $bundled = $this->getMockBuilder(BundledTemplateService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['discoverAvailable'])
            ->getMock();

        $bundled->expects($this->once())
            ->method('discoverAvailable')
            ->willReturn([
                ['module' => 'Reports', 'templates' => 2],
            ]);

        $this->templateRepo->expects($this->once())
            ->method('countBundledByModule')
            ->willReturn(['Reports' => 4]);

        $result = $this->service->getBundledData($bundled);

        $this->assertSame([['module' => 'Reports', 'templates' => 2]], $result['available']);
        $this->assertSame(['Reports' => 4], $result['bundledCounts']);
    }
}
