<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Repositories\ContactsRepository;
use App\Modules\Contacts\Services\ContactFileImportService;
use App\Modules\Contacts\Services\Parsers\CsvContactParser;
use App\Modules\Contacts\Services\Parsers\VCardContactParser;
use PHPUnit\Framework\TestCase;

/**
 * Test focalizzati sulle parti pure del servizio: detect formato e suggerimento
 * mapping. Il flusso end-to-end (preview/import) richiede DB e viene coperto
 * via test manuale (vedi piano di verifica).
 */
class ContactFileImportServiceTest extends TestCase
{
    private function makeService(): ContactFileImportService
    {
        // Repo non viene usato nei metodi pure testati qui.
        $repo = $this->createStub(ContactsRepository::class);
        return new ContactFileImportService($repo, new CsvContactParser(), new VCardContactParser());
    }

    public function testDetectFormatRecognisesCsvAndVcf(): void
    {
        $svc = $this->makeService();
        $this->assertSame('csv', $svc->detectFormat('contacts.csv'));
        $this->assertSame('csv', $svc->detectFormat('export.CSV'));
        $this->assertSame('csv', $svc->detectFormat('dump.txt'));
        $this->assertSame('vcf', $svc->detectFormat('rubrica.vcf'));
        $this->assertSame('vcf', $svc->detectFormat('apple.VCARD'));
        $this->assertNull($svc->detectFormat('foo.xlsx'));
        $this->assertNull($svc->detectFormat('noext'));
    }

    public function testSuggestMappingMatchesItalianAndEnglishHeaders(): void
    {
        $svc = $this->makeService();

        $mapping = $svc->suggestMapping([
            'Nome', 'Cognome', 'E-mail', 'Telefono', 'Azienda',
        ]);

        $this->assertSame('nome', $mapping[0]);
        $this->assertSame('cognome', $mapping[1]);
        $this->assertSame('email', $mapping[2]);
        $this->assertSame('telefono', $mapping[3]);
        $this->assertSame('azienda', $mapping[4]);
    }

    public function testSuggestMappingMatchesGoogleStyleHeaders(): void
    {
        $svc = $this->makeService();

        $mapping = $svc->suggestMapping([
            'First Name', 'Last Name', 'Organization', 'Job Title', 'Notes',
        ]);

        $this->assertSame('nome', $mapping[0]);
        $this->assertSame('cognome', $mapping[1]);
        $this->assertSame('azienda', $mapping[2]);
        $this->assertSame('ruolo', $mapping[3]);
        $this->assertSame('note', $mapping[4]);
    }

    public function testSuggestMappingLeavesUnknownColumnsEmpty(): void
    {
        $svc = $this->makeService();

        $mapping = $svc->suggestMapping(['Nome', 'Foo bar baz', 'Email']);

        $this->assertSame('nome', $mapping[0]);
        $this->assertSame('', $mapping[1]);
        $this->assertSame('email', $mapping[2]);
    }

    public function testSuggestMappingDoesNotReuseSameTargetTwice(): void
    {
        $svc = $this->makeService();

        // Due colonne con header simili: solo la prima deve mappare su 'nome'.
        $mapping = $svc->suggestMapping(['Nome', 'Nome contatto', 'Email']);

        $this->assertSame('nome', $mapping[0]);
        $this->assertNotSame('nome', $mapping[1]);
        $this->assertSame('email', $mapping[2]);
    }
}
