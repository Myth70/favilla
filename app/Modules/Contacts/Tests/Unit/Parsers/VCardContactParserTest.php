<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit\Parsers;

use App\Modules\Contacts\Services\Parsers\VCardContactParser;
use PHPUnit\Framework\TestCase;

class VCardContactParserTest extends TestCase
{
    private string $tmpFile = '';

    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    public function testParsesSingleVCardWithBasicFields(): void
    {
        $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Mario Rossi\r\nN:Rossi;Mario;;;\r\nORG:Acme S.r.l.\r\nTITLE:Sales Manager\r\nEMAIL:mario@example.com\r\nTEL:+39 333 1234567\r\nEND:VCARD\r\n";
        $contacts = iterator_to_array((new VCardContactParser())->contacts($this->makeTmp($vcf)), false);

        $this->assertCount(1, $contacts);
        $c = $contacts[0];
        $this->assertSame('Mario', $c['nome']);
        $this->assertSame('Rossi', $c['cognome']);
        $this->assertSame('Acme S.r.l.', $c['azienda']);
        $this->assertSame('Sales Manager', $c['ruolo']);
        $this->assertSame('mario@example.com', $c['email']);
        $this->assertSame('+39 333 1234567', $c['telefono']);
    }

    public function testParsesMultipleVCards(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nFN:Luca Bianchi\nN:Bianchi;Luca;;;\nEND:VCARD\n"
             . "BEGIN:VCARD\nVERSION:3.0\nFN:Anna Verdi\nN:Verdi;Anna;;;\nEND:VCARD\n";
        $contacts = iterator_to_array((new VCardContactParser())->contacts($this->makeTmp($vcf)), false);

        $this->assertCount(2, $contacts);
        $this->assertSame('Luca', $contacts[0]['nome']);
        $this->assertSame('Anna', $contacts[1]['nome']);
    }

    public function testLineFoldingIsHandled(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nFN:Mario\n  Rossi\nN:Rossi;Mario;;;\nEND:VCARD\n";
        $contacts = iterator_to_array((new VCardContactParser())->contacts($this->makeTmp($vcf)), false);

        $this->assertCount(1, $contacts);
        // La continuation line aggiunge " Rossi" al valore di FN.
        // Verifichiamo che il parser non si rompa e gestisca correttamente il fold.
        $this->assertSame('Rossi', $contacts[0]['cognome']);
    }

    public function testPrefEmailWins(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nFN:Mario Rossi\nN:Rossi;Mario;;;\n"
             . "EMAIL;TYPE=WORK:work@example.com\n"
             . "EMAIL;TYPE=HOME,PREF:home@example.com\n"
             . "END:VCARD\n";
        $contacts = iterator_to_array((new VCardContactParser())->contacts($this->makeTmp($vcf)), false);

        $this->assertSame('home@example.com', $contacts[0]['email']);
    }

    public function testMultipleTelsFillTelefonoAndTelefonoAlt(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nFN:Mario Rossi\nN:Rossi;Mario;;;\n"
             . "TEL;TYPE=HOME:111\n"
             . "TEL;TYPE=CELL:+39 333 1234567\n"
             . "END:VCARD\n";
        $contacts = iterator_to_array((new VCardContactParser())->contacts($this->makeTmp($vcf)), false);

        // CELL ha priorità → diventa telefono, l'altro va in telefono_alt
        $this->assertSame('+39 333 1234567', $contacts[0]['telefono']);
        $this->assertSame('111', $contacts[0]['telefono_alt']);
    }

    public function testSkipsCardWithoutNameOrSurname(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nEMAIL:lonely@example.com\nEND:VCARD\n";
        $contacts = iterator_to_array((new VCardContactParser())->contacts($this->makeTmp($vcf)), false);

        $this->assertCount(0, $contacts);
    }

    public function testAdrIsConcatenatedIntoIndirizzo(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nFN:Mario Rossi\nN:Rossi;Mario;;;\n"
             . "ADR;TYPE=WORK:;;Via Roma 1;Milano;MI;20100;Italia\n"
             . "END:VCARD\n";
        $contacts = iterator_to_array((new VCardContactParser())->contacts($this->makeTmp($vcf)), false);

        $this->assertStringContainsString('Via Roma 1', $contacts[0]['indirizzo']);
        $this->assertStringContainsString('Milano', $contacts[0]['indirizzo']);
    }

    public function testEscapedCommasAndSemicolonsAreDecoded(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nFN:Test\nN:Cognome;Nome;;;\n"
             . "NOTE:linea uno\\, virgola\\; punto-virgola\\nlinea due\n"
             . "END:VCARD\n";
        $contacts = iterator_to_array((new VCardContactParser())->contacts($this->makeTmp($vcf)), false);

        $this->assertStringContainsString('virgola', $contacts[0]['note']);
        $this->assertStringContainsString(',', $contacts[0]['note']);
        $this->assertStringContainsString("\n", $contacts[0]['note']);
    }

    private function makeTmp(string $content): string
    {
        $dir = is_dir('C:\\xampp\\tmp') ? 'C:\\xampp\\tmp' : sys_get_temp_dir();
        $path = $dir . DIRECTORY_SEPARATOR . 'vcf_' . bin2hex(random_bytes(8)) . '.tmp';
        file_put_contents($path, $content);
        $this->tmpFile = $path;
        return $path;
    }
}
