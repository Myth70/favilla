<?php

namespace Tests\Unit;

use App\Modules\Reports\Engines\DompdfExportEngine;
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * SSRF invariant for the report PDF engine (SECURITY.md): Dompdf must not fetch
 * remote resources unless an operator explicitly opts in via
 * REPORTS_PDF_ALLOW_REMOTE, and local file access must be chrooted to public/.
 */
class DompdfSsrfConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['REPORTS_PDF_ALLOW_REMOTE']);
    }

    private function optionsOf(DompdfExportEngine $engine): Options
    {
        $ref  = new ReflectionClass($engine);
        $prop = $ref->getProperty('dompdf');
        $prop->setAccessible(true);
        /** @var Dompdf $dompdf */
        $dompdf = $prop->getValue($engine);

        return $dompdf->getOptions();
    }

    public function testRemoteFetchDisabledByDefault(): void
    {
        unset($_ENV['REPORTS_PDF_ALLOW_REMOTE']);

        $options = $this->optionsOf(new DompdfExportEngine());

        $this->assertFalse(
            $options->getIsRemoteEnabled(),
            'Dompdf must not fetch remote resources by default (anti-SSRF)'
        );
        $this->assertFalse($options->getIsPhpEnabled(), 'inline PHP must stay disabled');
    }

    public function testRemoteFetchHonoursExplicitOptIn(): void
    {
        $_ENV['REPORTS_PDF_ALLOW_REMOTE'] = 'true';

        $options = $this->optionsOf(new DompdfExportEngine());

        $this->assertTrue($options->getIsRemoteEnabled());
    }

    public function testFileAccessChrootedToPublic(): void
    {
        unset($_ENV['REPORTS_PDF_ALLOW_REMOTE']);

        $options = $this->optionsOf(new DompdfExportEngine());
        $chroot  = str_replace('\\', '/', implode('|', (array) $options->getChroot()));

        $this->assertStringContainsString('/public', $chroot, 'local file access must be confined to public/');
    }
}
