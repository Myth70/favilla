<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\HistoryRepository;
use App\Modules\Reports\Repositories\StylePresetRepository;
use App\Modules\Reports\Repositories\TemplateRepository;
use App\Modules\Reports\Services\ExportProviderService;
use App\Modules\Reports\Services\TemplateService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

/**
 * generateReport()/quickExport() pieno dipendono da engine PDF/Excel e filesystem:
 * qui si testano gli helper puri (buildCsvColumns, defaultStylePreset) e la
 * validazione del formato in quickExport(). Le dipendenze sono mockate solo per
 * permettere la costruzione del service.
 */
class TemplateServiceTest extends TestCase
{
    use MakesContainer;

    private function service(): TemplateService
    {
        $this->freshContainer();
        $this->bindInstance(TemplateRepository::class, $this->createMock(TemplateRepository::class));
        $this->bindInstance(StylePresetRepository::class, $this->createMock(StylePresetRepository::class));
        $this->bindInstance(HistoryRepository::class, $this->createMock(HistoryRepository::class));
        $this->bindInstance(ExportProviderService::class, $this->createMock(ExportProviderService::class));
        return new TemplateService();
    }

    public function testBuildCsvColumnsNormalizesShape(): void
    {
        $out = $this->service()->buildCsvColumns([
            ['name' => 'nome', 'label' => 'Nome', 'type' => 'string', 'format' => null],
            ['name' => 'eta'], // label/type/format mancanti → default
        ]);

        $this->assertSame('Nome', $out[0]['label']);
        $this->assertSame('eta', $out[1]['label']);   // fallback su name
        $this->assertSame('string', $out[1]['type']); // default
        $this->assertNull($out[1]['format']);
    }

    public function testDefaultStylePresetHasExpectedKeys(): void
    {
        $preset = $this->service()->defaultStylePreset();

        $this->assertArrayHasKey('primary_color', $preset);
        $this->assertArrayHasKey('header_bg_color', $preset);
        $this->assertSame('#3b82f6', $preset['primary_color']);
    }

    public function testQuickExportRejectsUnsupportedFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->quickExport('Demo', 'src', 'xml');
    }
}
