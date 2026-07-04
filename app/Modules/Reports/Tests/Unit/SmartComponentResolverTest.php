<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Services\SmartComponentResolver;
use PHPUnit\Framework\TestCase;

/**
 * Logica pura di espansione degli Smart Component (data-prm-type) nel template HTML.
 */
class SmartComponentResolverTest extends TestCase
{
    private SmartComponentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SmartComponentResolver();
    }

    public function testHtmlWithoutSmartComponentsIsReturnedUnchanged(): void
    {
        $html = '<p>Solo testo</p>';
        $this->assertSame($html, $this->resolver->resolve($html, [], [], []));
    }

    public function testDataTableRendersRowsAndColumns(): void
    {
        $html = '<div data-prm-type="data_table" data-prm-config="{&quot;columns&quot;:[&quot;nome&quot;]}"></div>';
        $rows = [['nome' => 'Mario'], ['nome' => 'Lucia']];
        $meta = ['source_fields' => [['name' => 'nome', 'label' => 'Nome']]];

        $out = $this->resolver->resolve($html, $rows, $meta, []);

        $this->assertStringContainsString('<table', $out);
        $this->assertStringContainsString('Nome', $out);   // header label
        $this->assertStringContainsString('Mario', $out);
        $this->assertStringContainsString('Lucia', $out);
    }

    public function testDataTableShowsEmptyMessageWithoutRows(): void
    {
        $html = '<div data-prm-type="data_table" data-prm-config="{&quot;columns&quot;:[&quot;nome&quot;]}"></div>';
        $meta = ['source_fields' => [['name' => 'nome', 'label' => 'Nome']]];

        $out = $this->resolver->resolve($html, [], $meta, []);
        $this->assertStringContainsString('Nessun dato disponibile', $out);
    }

    public function testCalculatedSumAndCount(): void
    {
        $rows = [['importo' => 10], ['importo' => 5], ['importo' => 'x']];

        $sumHtml = '<span data-prm-type="calculated" data-prm-config="{&quot;op&quot;:&quot;sum&quot;,&quot;field&quot;:&quot;importo&quot;,&quot;format&quot;:&quot;integer&quot;}"></span>';
        $out = $this->resolver->resolve($sumHtml, $rows, [], []);
        $this->assertStringContainsString('15', $out); // 10 + 5, 'x' ignorato

        $countHtml = '<span data-prm-type="calculated" data-prm-config="{&quot;op&quot;:&quot;count&quot;}"></span>';
        $out2 = $this->resolver->resolve($countHtml, $rows, [], []);
        $this->assertStringContainsString('3', $out2);
    }

    public function testSystemComponentRendersTitleFromMeta(): void
    {
        $html = '<span data-prm-type="system" data-prm-config="{&quot;kind&quot;:&quot;title&quot;}"></span>';
        $out = $this->resolver->resolve($html, [], ['title' => 'Report Vendite'], []);
        $this->assertStringContainsString('Report Vendite', $out);
    }

    public function testFiltersSummaryListsAppliedFilters(): void
    {
        $html = '<div data-prm-type="filters_summary" data-prm-config="{}"></div>';
        $out = $this->resolver->resolve($html, [], ['filters' => ['stato' => 'attivo']], []);

        $this->assertStringContainsString('stato', $out);
        $this->assertStringContainsString('attivo', $out);
    }

    public function testFiltersSummaryShowsEmptyLabelWithoutFilters(): void
    {
        $html = '<div data-prm-type="filters_summary" data-prm-config="{}"></div>';
        $out = $this->resolver->resolve($html, [], ['filters' => []], []);
        $this->assertStringContainsString('Nessun filtro applicato', $out);
    }

    public function testUnknownTypeIsLeftUntouched(): void
    {
        $html = '<div data-prm-type="qualcosa">contenuto</div>';
        $out = $this->resolver->resolve($html, [], [], []);
        // Tipo sconosciuto → nodo non rimpiazzato, contenuto preservato.
        $this->assertStringContainsString('contenuto', $out);
    }

    public function testDataTableEscapesCellValues(): void
    {
        $html = '<div data-prm-type="data_table" data-prm-config="{&quot;columns&quot;:[&quot;nome&quot;]}"></div>';
        $rows = [['nome' => '<script>alert(1)</script>']];
        $meta = ['source_fields' => [['name' => 'nome', 'label' => 'Nome']]];

        $out = $this->resolver->resolve($html, $rows, $meta, []);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }
}
