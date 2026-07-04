<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\DocumentBindingRepository;
use App\Modules\Reports\Repositories\HistoryRepository;
use App\Modules\Reports\Repositories\StylePresetRepository;
use App\Modules\Reports\Services\DocumentService;
use App\Modules\Reports\Services\ExportProviderService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

/**
 * generate() completo richiede Dompdf e filesystem: qui si testano i guard
 * raggiungibili prima della generazione (binding mancante, layout vuoto).
 */
class DocumentServiceTest extends TestCase
{
    use MakesContainer;

    private DocumentBindingRepository $bindingRepo;

    private function service(): DocumentService
    {
        $this->freshContainer();
        $this->bindInstance(DocumentBindingRepository::class, $this->bindingRepo);
        $this->bindInstance(StylePresetRepository::class, $this->createMock(StylePresetRepository::class));
        $this->bindInstance(HistoryRepository::class, $this->createMock(HistoryRepository::class));
        $this->bindInstance(ExportProviderService::class, $this->createMock(ExportProviderService::class));
        return new DocumentService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindingRepo = $this->createMock(DocumentBindingRepository::class);
    }

    public function testGenerateThrowsWhenBindingMissing(): void
    {
        $this->bindingRepo->method('findByOperation')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nessun binding');
        $this->service()->generate('Contacts', 'invoice', 1);
    }

    public function testGenerateThrowsWhenTemplateHasNoLayout(): void
    {
        $this->bindingRepo->method('findByOperation')->willReturn([
            'id' => 1, 'template_html' => '   ', 'template_name' => 'Fattura',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('layout salvato');
        $this->service()->generate('Contacts', 'invoice', 1);
    }
}
