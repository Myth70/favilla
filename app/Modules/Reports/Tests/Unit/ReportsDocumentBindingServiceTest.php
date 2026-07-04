<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\DocumentBindingRepository;
use App\Modules\Reports\Services\ExportProviderService;
use App\Modules\Reports\Services\ReportsDocumentBindingService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

/**
 * ReportsDocumentBindingService coordina DocumentBindingRepository ed
 * ExportProviderService: i test verificano l'inoltro con repository mockati.
 */
class ReportsDocumentBindingServiceTest extends TestCase
{
    use MakesContainer;

    private DocumentBindingRepository $bindingRepo;
    private ExportProviderService $exportProvider;

    private function service(): ReportsDocumentBindingService
    {
        $this->freshContainer();
        $this->bindInstance(DocumentBindingRepository::class, $this->bindingRepo);
        $this->bindInstance(ExportProviderService::class, $this->exportProvider);
        return new ReportsDocumentBindingService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindingRepo = $this->createMock(DocumentBindingRepository::class);
        $this->exportProvider = $this->createMock(ExportProviderService::class);
    }

    public function testGetIndexDataReturnsBindings(): void
    {
        $this->bindingRepo->method('listAll')->willReturn([['id' => 1]]);

        $this->assertSame(['bindings' => [['id' => 1]]], $this->service()->getIndexData());
    }

    public function testGetBindFormDataCombinesSourcesAndTemplates(): void
    {
        $this->exportProvider->method('getSourcesForUser')->willReturn(['src']);
        $this->bindingRepo->method('listDocumentTemplates')->willReturn(['tpl']);

        $data = $this->service()->getBindFormData(['id' => 1]);
        $this->assertSame(['src'], $data['sources']);
        $this->assertSame(['tpl'], $data['templates']);
    }

    public function testCreateAndUpdateAndDeleteDelegate(): void
    {
        $this->bindingRepo->method('create')->willReturn(5);
        $this->bindingRepo->method('update')->willReturn(true);
        $this->bindingRepo->method('delete')->willReturn(true);

        $service = $this->service();
        $this->assertSame(5, $service->createBinding(['x' => 1]));
        $this->assertTrue($service->updateBinding(5, ['x' => 2]));
        $this->assertTrue($service->deleteBinding(5));
    }

    public function testListBindingsForTemplateDelegates(): void
    {
        $this->bindingRepo->method('listForTemplate')->with(3)->willReturn([['id' => 9]]);
        $this->assertSame([['id' => 9]], $this->service()->listBindingsForTemplate(3));
    }
}
