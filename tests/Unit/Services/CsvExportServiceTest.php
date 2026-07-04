<?php

namespace Tests\Unit\Services;

use App\Services\CsvExportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CsvExportServiceTest extends TestCase
{
    private function sanitize(array $row): array
    {
        $ref = new ReflectionClass(CsvExportService::class);
        $m   = $ref->getMethod('sanitizeRow');
        $m->setAccessible(true);
        return $m->invoke(null, $row);
    }

    public function testSanitizeRowPrefixesFormulaTriggers(): void
    {
        $row = [
            'eq'    => '=SUM(A1)',
            'plus'  => '+1234',
            'minus' => '-abc',
            'at'    => '@mention',
            'tab'   => "\tvalue",
            'cr'    => "\rvalue",
        ];
        $out = $this->sanitize($row);
        $this->assertSame("'=SUM(A1)", $out['eq']);
        $this->assertSame("'+1234", $out['plus']);
        $this->assertSame("'-abc", $out['minus']);
        $this->assertSame("'@mention", $out['at']);
        $this->assertSame("'\tvalue", $out['tab']);
        $this->assertSame("'\rvalue", $out['cr']);
    }

    public function testSanitizeRowLeavesSafeValuesUntouched(): void
    {
        $row = [
            'name'   => 'Mario Rossi',
            'email'  => 'mario@example.com',
            'number' => 42,
            'null'   => null,
            'empty'  => '',
            'float'  => 3.14,
        ];
        $out = $this->sanitize($row);
        $this->assertSame($row, $out);
    }

    public function testSanitizeRowPreservesKeysOrder(): void
    {
        $row = ['a' => '=1', 'b' => 'safe', 'c' => '+2'];
        $out = $this->sanitize($row);
        $this->assertSame(['a', 'b', 'c'], array_keys($out));
    }

    public function testEscapeFormulaPrefixesTriggerCharacters(): void
    {
        $this->assertSame("'=cmd", CsvExportService::escapeFormula('=cmd'));
        $this->assertSame("'+1", CsvExportService::escapeFormula('+1'));
        $this->assertSame("'-1", CsvExportService::escapeFormula('-1'));
        $this->assertSame("'@x", CsvExportService::escapeFormula('@x'));
    }

    public function testEscapeFormulaLeavesSafeStringsUntouched(): void
    {
        $this->assertSame('Mario Rossi', CsvExportService::escapeFormula('Mario Rossi'));
        $this->assertSame('', CsvExportService::escapeFormula(''));
        $this->assertSame('1+1', CsvExportService::escapeFormula('1+1'));
    }
}
