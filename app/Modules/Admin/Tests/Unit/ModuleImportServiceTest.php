<?php

declare(strict_types=1);

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\ModuleImportService;
use Tests\ModuleTestCase;
use Tests\Support\CreatesTempFiles;
use ZipArchive;

/**
 * Tests for ModuleImportService::import() — the Phase-1 validation branches
 * (unreadable archive, missing manifest, invalid module name) that run before
 * any extraction or DB work.
 */
class ModuleImportServiceTest extends ModuleTestCase
{
    use CreatesTempFiles;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('zip')) {
            $this->markTestSkipped("Estensione 'zip' non disponibile.");
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTempFiles();
        parent::tearDown();
    }

    public function testImportFailsWhenArchiveCannotBeOpened(): void
    {
        $result = ModuleImportService::import($this->tempDir() . '/does-not-exist.zip');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Impossibile aprire', (string) $result->error);
    }

    public function testImportFailsWhenManifestMissing(): void
    {
        $path = $this->makeZip(['readme.txt' => 'hello']);

        $result = ModuleImportService::import($path);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('manifest.json', (string) $result->error);
    }

    public function testImportRejectsInvalidModuleNameInManifest(): void
    {
        $path = $this->makeZip([
            'manifest.json' => json_encode(['module_name' => 'bad name']),
        ]);

        $result = ModuleImportService::import($path);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('non valido', (string) $result->error);
    }

    /**
     * @param array<string,string> $entries
     */
    private function makeZip(array $entries): string
    {
        $path = $this->makeTempFile('', 'import_', '.zip');

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }
}
