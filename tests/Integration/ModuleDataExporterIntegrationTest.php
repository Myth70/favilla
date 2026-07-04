<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Admin\Services\ModuleDataExporter;

/**
 * exportTable() con dati reali usa SHOW COLUMNS e LIMIT offset,count (MySQL-only):
 * verificato sul dialetto reale.
 */
class ModuleDataExporterIntegrationTest extends DatabaseIntegrationTestCase
{
    public function testExportTableProducesInsertStatements(): void
    {
        // notification_channels è una tabella semplice già nello schema canonico.
        $this->insertRow('notification_channels', ['slug' => 'email', 'name' => 'Email']);
        $this->insertRow('notification_channels', ['slug' => 'in_app', 'name' => 'In-App']);

        $sql = ModuleDataExporter::exportTable(self::$pdo, 'notification_channels');

        $this->assertStringContainsString('INSERT INTO `notification_channels`', $sql);
        $this->assertStringContainsString("'email'", $sql);
        $this->assertStringContainsString("'in_app'", $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', $sql);
    }

    public function testExportTableRejectsInvalidNameOnRealDb(): void
    {
        $this->assertSame('', ModuleDataExporter::exportTable(self::$pdo, 'bad; DROP'));
    }
}
