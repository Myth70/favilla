<?php

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Services\DocumentiMimeRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Verifica B/M1: la whitelist MIME è hardcoded come const. Nessuna API può
 * estendere la whitelist: l'admin può solo restringere via "disabled".
 */
class DocumentiMimeRegistryTest extends TestCase
{
    public function testActiveMimesIsSubsetOfConstWhitelist(): void
    {
        $active = DocumentiMimeRegistry::activeMimes();
        foreach (array_keys($active) as $mime) {
            $this->assertArrayHasKey(
                $mime,
                DocumentiMimeRegistry::MIMES,
                "activeMimes() ha restituito $mime ma non è in const MIMES — leak della whitelist"
            );
        }
    }

    public function testActiveMimesAlwaysExcludesDangerousTypes(): void
    {
        // Nessuna delle const MIMES deve essere un binario eseguibile o uno script.
        $dangerous = [
            'application/x-msdownload',
            'application/x-msdos-program',
            'application/x-executable',
            'application/x-elf',
            'application/x-shellscript',
            'application/x-php',
            'text/x-php',
            'application/x-httpd-php',
        ];
        foreach ($dangerous as $d) {
            $this->assertArrayNotHasKey(
                $d,
                DocumentiMimeRegistry::MIMES,
                "MIME pericoloso $d non deve essere in whitelist"
            );
        }
    }

    public function testAcceptAttrReturnsCommaSeparatedMimes(): void
    {
        $accept = DocumentiMimeRegistry::acceptAttr();
        $this->assertIsString($accept);
        $this->assertStringContainsString('application/pdf', $accept);
        $this->assertStringContainsString('image/png', $accept);
        $this->assertStringNotContainsString(' ', $accept, 'acceptAttr non deve contenere spazi');
    }
}
