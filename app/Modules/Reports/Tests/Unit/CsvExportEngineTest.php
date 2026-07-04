<?php

declare(strict_types=1);

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Engines\CsvExportEngine;
use PHPUnit\Framework\TestCase;

class CsvExportEngineTest extends TestCase
{
    private function generateCsv(array $rows, array $columns): string
    {
        $engine = new CsvExportEngine(';');
        $dir = (defined('BASE_PATH') ? BASE_PATH : sys_get_temp_dir()) . '/storage/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $tmp = $dir . '/csvengine_' . uniqid('', true) . '.csv';
        try {
            $engine->generate($rows, $columns, $tmp, 'Test');
            return (string) file_get_contents($tmp);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function testFreeTextFormulaCellIsNeutralised(): void
    {
        $columns = [['name' => 'note', 'label' => 'Note', 'type' => 'string']];
        $rows = [['note' => '=HYPERLINK("http://evil","x")']];

        $content = $this->generateCsv($rows, $columns);

        // The dangerous cell must be prefixed with a single quote so spreadsheets
        // treat it as text rather than evaluating the formula.
        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function testFormattedNegativeNumberIsNotCorrupted(): void
    {
        $columns = [
            ['name' => 'saldo', 'label' => 'Saldo', 'type' => 'decimal'],
            ['name' => 'qty', 'label' => 'Qty', 'type' => 'integer'],
        ];
        $rows = [['saldo' => -1234.56, 'qty' => -7]];

        $content = $this->generateCsv($rows, $columns);

        // Negative numerics must stay numeric — they must NOT be quoted as text,
        // even though they begin with '-'.
        $this->assertStringContainsString('-1.234,56', $content);
        $this->assertStringNotContainsString("'-1.234,56", $content);
        $this->assertStringNotContainsString("'-7", $content);
    }

    public function testHtmlTemplateExportIsRejected(): void
    {
        $engine = new CsvExportEngine();
        $this->expectException(\LogicException::class);
        $engine->generateFromHtmlTemplate('<p>{{x}}</p>', [], [], 'unused.csv');
    }
}
