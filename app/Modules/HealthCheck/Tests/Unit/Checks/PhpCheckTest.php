<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Checks;

use App\Modules\HealthCheck\Checks\PhpCheck;
use PHPUnit\Framework\TestCase;

class PhpCheckTest extends TestCase
{
    public function testMetadata(): void
    {
        $check = new PhpCheck();

        $this->assertSame('php', $check->key());
        $this->assertSame('PHP', $check->label());
        $this->assertSame('fast', $check->depth());
    }

    public function testRunReturnsWellFormedGroup(): void
    {
        $group = (new PhpCheck())->run();

        $this->assertSame('PHP', $group['label']);
        $this->assertNotEmpty($group['checks']);

        foreach ($group['checks'] as $check) {
            $this->assertArrayHasKey('name', $check);
            $this->assertArrayHasKey('detail', $check);
            $this->assertContains($check['status'], ['ok', 'warn', 'fail']);
        }
    }

    public function testRuntimeVersionIsOkOnSupportedPhp(): void
    {
        // La suite gira su PHP 8.2+, quindi il check versione deve essere ok.
        $group = (new PhpCheck())->run();

        $versionCheck = null;
        foreach ($group['checks'] as $check) {
            if ($check['name'] === 'Versione runtime PHP') {
                $versionCheck = $check;
                break;
            }
        }

        $this->assertNotNull($versionCheck);
        $this->assertSame('ok', $versionCheck['status']);
    }
}
