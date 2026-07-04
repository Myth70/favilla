<?php

namespace Tests\Unit\Cli;

use App\Cli\Commands\MakeModuleCommand;
use PHPUnit\Framework\TestCase;

class MakeModuleCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Directory temporanea isolata per ogni test
        $baseTmp = defined('BASE_PATH') ? BASE_PATH . '/storage/tmp' : dirname(__DIR__, 3) . '/storage/tmp';
        if (!is_dir($baseTmp)) {
            mkdir($baseTmp, 0755, true);
        }
        $this->tmpDir = $baseTmp . '/favilla_test_' . uniqid('', true);
        mkdir($this->tmpDir . '/app/Modules', 0755, true);
        mkdir($this->tmpDir . '/app/Modules/_Template/stubs/Views/partials', 0755, true);
        mkdir($this->tmpDir . '/app/Modules/_Template/stubs/Tests', 0755, true);

        // Copia gli stubs reali nella directory temporanea
        $src = BASE_PATH . '/app/Modules/_Template/stubs';
        $this->copyDir($src, $this->tmpDir . '/app/Modules/_Template/stubs');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->tmpDir);
    }

    // ------------------------------------------------------------------

    public function test_creates_all_expected_files(): void
    {
        $cmd = $this->makeCommand();
        $cmd->handle(['Clienti']);

        $base = $this->tmpDir . '/app/Modules/Clienti';
        $expected = [
            'Controllers/ClientiController.php',
            'Services/ClientiService.php',
            'Repositories/ClientiRepository.php',
            'routes.php',
            'permissions.php',
            'module.json',
            'migrations/001_clienti.sql',
            'Views/index.php',
            'Views/form.php',
            'Views/show.php',
            'Views/partials/table.php',
            'Views/partials/search-results.php',
            'Tests/Unit/ClientiRepositoryTest.php',
            'Tests/Unit/ClientiServiceTest.php',
            'Tests/Unit/ClientiRoutesTest.php',
        ];

        foreach ($expected as $relative) {
            $this->assertFileExists($base . '/' . $relative, "File mancante: {$relative}");
        }
    }

    public function test_placeholders_replaced_in_controller(): void
    {
        $cmd = $this->makeCommand();
        $cmd->handle(['Prodotti']);

        $content = file_get_contents($this->tmpDir . '/app/Modules/Prodotti/Controllers/ProdottiController.php');

        $this->assertStringContainsString('namespace App\\Modules\\Prodotti\\Controllers', $content);
        $this->assertStringContainsString('class ProdottiController', $content);
        $this->assertStringContainsString('ProdottiService', $content);
        $this->assertStringNotContainsString('{{', $content);
    }

    public function test_module_json_has_correct_structure(): void
    {
        $cmd = $this->makeCommand();
        $cmd->handle(['Fatture']);

        $json = json_decode(
            file_get_contents($this->tmpDir . '/app/Modules/Fatture/module.json'),
            true
        );

        $this->assertSame('Fatture', $json['name']);
        $this->assertSame('1.0.0', $json['version']);
        $this->assertArrayHasKey('tables', $json);
        $this->assertArrayHasKey('notification_events', $json);
        $this->assertArrayHasKey('changelog', $json);
        $this->assertArrayHasKey('1.0.0', $json['changelog']);
    }

    public function test_namespace_correct_in_repository(): void
    {
        $cmd = $this->makeCommand();
        $cmd->handle(['Fornitori']);

        $content = file_get_contents($this->tmpDir . '/app/Modules/Fornitori/Repositories/FornitoriRepository.php');

        $this->assertStringContainsString('namespace App\\Modules\\Fornitori\\Repositories', $content);
        $this->assertStringContainsString('class FornitoriRepository', $content);
        $this->assertStringNotContainsString('{{', $content);
    }

    public function test_fails_if_module_already_exists(): void
    {
        mkdir($this->tmpDir . '/app/Modules/Esistente', 0755, true);

        $this->expectOutputRegex('/già esiste|ERR/');
        $cmd = $this->makeCommand();
        $cmd->handle(['Esistente']);

        $this->assertDirectoryDoesNotExist(
            $this->tmpDir . '/app/Modules/Esistente/Controllers'
        );
    }

    public function test_fails_if_name_not_pascal_case(): void
    {
        $this->expectOutputRegex('/non valido|ERR/');
        $cmd = $this->makeCommand();
        $cmd->handle(['test_modulo']);

        $this->assertDirectoryDoesNotExist($this->tmpDir . '/app/Modules/test_modulo');
    }

    public function test_fails_if_name_starts_lowercase(): void
    {
        $this->expectOutputRegex('/non valido|ERR/');
        $cmd = $this->makeCommand();
        $cmd->handle(['clienti']);

        $this->assertDirectoryDoesNotExist($this->tmpDir . '/app/Modules/clienti');
    }

    public function test_fails_if_name_empty(): void
    {
        $this->expectOutputRegex('/Uso:|ERR/');
        $cmd = $this->makeCommand();
        $cmd->handle([]);
    }

    public function test_routes_contain_correct_prefix(): void
    {
        $cmd = $this->makeCommand();
        $cmd->handle(['Ordini']);

        $content = file_get_contents($this->tmpDir . '/app/Modules/Ordini/routes.php');

        $this->assertStringContainsString("'prefix'     => 'ordini'", $content);
        $this->assertStringContainsString('OrdiniController', $content);
        $this->assertStringNotContainsString('{{', $content);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeCommand(): MakeModuleCommand
    {
        // Sovrascriviamo le proprietà private con reflection
        $cmd = new MakeModuleCommand();
        $ref = new \ReflectionClass($cmd);

        $p1 = $ref->getProperty('stubsDir');
        $p1->setAccessible(true);
        $p1->setValue($cmd, $this->tmpDir . '/app/Modules/_Template/stubs');

        $p2 = $ref->getProperty('modulesDir');
        $p2->setAccessible(true);
        $p2->setValue($cmd, $this->tmpDir . '/app/Modules');

        return $cmd;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;
            is_dir($s) ? $this->copyDir($s, $d) : copy($s, $d);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
