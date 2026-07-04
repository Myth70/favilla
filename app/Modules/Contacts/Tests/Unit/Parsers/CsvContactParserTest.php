<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit\Parsers;

use App\Modules\Contacts\Services\Parsers\CsvContactParser;
use PHPUnit\Framework\TestCase;

class CsvContactParserTest extends TestCase
{
    private string $tmpFile = '';

    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    public function testInspectReadsHeadersAndPreviewRowsForCommaDelimited(): void
    {
        $csv = "nome,cognome,email\nMario,Rossi,mario@example.com\nLuca,Bianchi,luca@example.com\n";
        $info = (new CsvContactParser())->inspect($this->makeTmp($csv));

        $this->assertSame(['nome', 'cognome', 'email'], $info['headers']);
        $this->assertSame(',', $info['delimiter']);
        $this->assertSame(2, $info['totalRows']);
        $this->assertCount(2, $info['rows']);
        $this->assertSame('Mario', $info['rows'][0][0]);
    }

    public function testInspectDetectsSemicolonDelimiter(): void
    {
        $csv = "nome;cognome;email\nGiulia;Verdi;g@v.it\n";
        $info = (new CsvContactParser())->inspect($this->makeTmp($csv));

        $this->assertSame(';', $info['delimiter']);
        $this->assertSame(['nome', 'cognome', 'email'], $info['headers']);
        $this->assertSame(1, $info['totalRows']);
    }

    public function testInspectStripsUtf8Bom(): void
    {
        $csv = "\xEF\xBB\xBFnome,email\nAnna,a@a.it\n";
        $info = (new CsvContactParser())->inspect($this->makeTmp($csv));

        $this->assertSame(['nome', 'email'], $info['headers']);
    }

    public function testRowsYieldsAssociativeRowsWithHeaderKeys(): void
    {
        $csv = "nome,email\nMario,mario@example.com\nLuca,luca@example.com\n";
        $parser = new CsvContactParser();
        $rows   = iterator_to_array($parser->rows($this->makeTmp($csv)), false);

        $this->assertCount(2, $rows);
        $this->assertSame('Mario', $rows[0]['nome']);
        $this->assertSame('mario@example.com', $rows[0]['email']);
    }

    public function testInspectSkipsEmptyRows(): void
    {
        $csv = "nome,email\nMario,mario@example.com\n,\nLuca,luca@example.com\n";
        $info = (new CsvContactParser())->inspect($this->makeTmp($csv));

        $this->assertSame(2, $info['totalRows']);
    }

    public function testEmptyHeaderCellGetsFallbackKey(): void
    {
        $csv = "nome,,email\nMario,x,m@x.it\n";
        $parser = new CsvContactParser();
        $rows   = iterator_to_array($parser->rows($this->makeTmp($csv)), false);

        $this->assertSame('Mario', $rows[0]['nome']);
        $this->assertSame('x', $rows[0]['col_2']);
        $this->assertSame('m@x.it', $rows[0]['email']);
    }

    private function makeTmp(string $content): string
    {
        $dir = is_dir('C:\\xampp\\tmp') ? 'C:\\xampp\\tmp' : sys_get_temp_dir();
        $path = $dir . DIRECTORY_SEPARATOR . 'csv_' . bin2hex(random_bytes(8)) . '.tmp';
        file_put_contents($path, $content);
        $this->tmpFile = $path;
        return $path;
    }
}
