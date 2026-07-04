<?php

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Services\DashboardStatTextFormatter;
use Tests\ModuleTestCase;

class DashboardStatTextFormatterTest extends ModuleTestCase
{
    private DashboardStatTextFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new DashboardStatTextFormatter();
    }

    public function testDropsIdenticalSubtitle(): void
    {
        $result = $this->formatter->format('Messaggi non letti', 'messaggi non letti');

        $this->assertSame('Messaggi non letti', $result['label']);
        $this->assertNull($result['subtitle']);
    }

    public function testRemovesRepeatedLeadingNounFromSubtitle(): void
    {
        $result = $this->formatter->format('Articoli pubblicati', 'articoli nelle Comunicazioni');

        $this->assertSame('nelle Comunicazioni', $result['subtitle']);
    }

    public function testCompactsRepeatedTimeWindowPhrase(): void
    {
        $result = $this->formatter->format('Prossimi eventi', 'eventi nei prossimi 7 giorni');

        $this->assertSame('entro 7 giorni', $result['subtitle']);
    }

    public function testSimplifiesRepeatedModuleNameInTotals(): void
    {
        $result = $this->formatter->format('Progetti attivi', 'Totale progetti: 0');

        $this->assertSame('Totale: 0', $result['subtitle']);
    }

    public function testDropsRepeatedTimeWindowWhenLabelAlreadyContainsIt(): void
    {
        $result = $this->formatter->format('Documenti in scadenza (14 gg)', 'nei prossimi 14 giorni');

        $this->assertNull($result['subtitle']);
    }

    public function testKeepsUsefulSubtitleWhenItAddsContext(): void
    {
        $result = $this->formatter->format('Notifiche', 'non lette');

        $this->assertSame('non lette', $result['subtitle']);
    }
}
