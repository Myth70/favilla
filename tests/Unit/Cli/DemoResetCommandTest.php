<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use App\Cli\Commands\DemoResetCommand;
use App\Cli\Console;
use PHPUnit\Framework\TestCase;

/**
 * demo:reset è distruttivo per costruzione (svuota upload + migrate --fresh):
 * il contratto critico da difendere è la GUARDIA — senza DEMO_MODE=true il
 * comando deve rifiutarsi PRIMA di toccare qualsiasi cosa. Il percorso di
 * esecuzione reale è coperto dal deploy demo (docs/demo-instance.md), non
 * eseguibile in unit test senza sacrificare il database di test.
 */
class DemoResetCommandTest extends TestCase
{
    private string|false $originalDemoMode;

    protected function setUp(): void
    {
        $this->originalDemoMode = getenv('DEMO_MODE');
    }

    protected function tearDown(): void
    {
        if ($this->originalDemoMode === false) {
            putenv('DEMO_MODE');
            unset($_ENV['DEMO_MODE']);
        } else {
            putenv('DEMO_MODE=' . $this->originalDemoMode);
            $_ENV['DEMO_MODE'] = $this->originalDemoMode;
        }
    }

    public function testRefusesWithoutDemoMode(): void
    {
        putenv('DEMO_MODE');
        unset($_ENV['DEMO_MODE']);

        ob_start();
        (new DemoResetCommand())->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('RIFIUTATO', $output);
        $this->assertStringContainsString('DEMO_MODE=true', $output);
        // Nessuna fase distruttiva avviata.
        $this->assertStringNotContainsString('migrate --fresh', $output);
        $this->assertStringNotContainsString('[1/3]', $output);
    }

    public function testRefusesWithFalsyDemoMode(): void
    {
        foreach (['false', '0', 'no', ''] as $value) {
            putenv('DEMO_MODE=' . $value);

            ob_start();
            (new DemoResetCommand())->handle([]);
            $output = (string) ob_get_clean();

            $this->assertStringContainsString('RIFIUTATO', $output, "DEMO_MODE='{$value}' deve rifiutare");
        }
    }

    public function testCommandIsRegisteredInConsole(): void
    {
        $this->assertTrue((new Console())->hasCommand('demo:reset'));
    }
}
