<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\ModuleDataExporter;
use Tests\ModuleTestCase;

/**
 * L'export con dati reali usa SHOW COLUMNS (MySQL-only) → coperto in
 * tests/Integration/ModuleDataExporterIntegrationTest. Qui i rami difensivi
 * portabili: nome tabella non valido, tabella assente, tabella vuota.
 */
class ModuleDataExporterTest extends ModuleTestCase
{
    public function testRejectsInvalidTableName(): void
    {
        // Nome con caratteri non ammessi → '' senza toccare il DB (anti-injection).
        $this->assertSame('', ModuleDataExporter::exportTable($this->pdo, 'bad-name; DROP TABLE x'));
        $this->assertSame('', ModuleDataExporter::exportTable($this->pdo, '1nvalid'));
    }

    public function testReturnsEmptyForMissingTable(): void
    {
        $this->assertSame('', ModuleDataExporter::exportTable($this->pdo, 'non_esiste'));
    }

    public function testReturnsEmptyForEmptyTable(): void
    {
        $this->migrate('CREATE TABLE vuota (id INTEGER PRIMARY KEY, val TEXT);');
        $this->assertSame('', ModuleDataExporter::exportTable($this->pdo, 'vuota'));
    }
}
