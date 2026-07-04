<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\ModuleExportService;
use RuntimeException;
use Tests\ModuleTestCase;

/**
 * Tests for ModuleExportService::build() — the DB-free validation guards that
 * run before any archive work.
 */
class ModuleExportServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('zip')) {
            $this->markTestSkipped("Estensione 'zip' non disponibile.");
        }
    }

    public function testBuildRejectsInvalidModuleName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non valido');

        ModuleExportService::build('../etc/passwd');
    }

    public function testBuildFailsForUnknownModule(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non trovato');

        ModuleExportService::build('Zzzphantommodule');
    }
}
