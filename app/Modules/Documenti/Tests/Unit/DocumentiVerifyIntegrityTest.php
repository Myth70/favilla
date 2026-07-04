<?php

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Services\DocumentiStorageService;
use PHPUnit\Framework\TestCase;

/**
 * Verifica la logica di integrità file usata da documenti:verify-integrity e
 * dalla verifica opzionale on-download. Rileva sostituzioni "fuori banda".
 */
class DocumentiVerifyIntegrityTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        // __DIR__ è dentro open_basedir (a differenza di sys_get_temp_dir su XAMPP).
        $this->tmp = __DIR__ . DIRECTORY_SEPARATOR . 'dcint_' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($this->tmp, 'contenuto originale');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmp)) {
            @unlink($this->tmp);
        }
    }

    public function testMatchingChecksumReturnsOk(): void
    {
        $hash = hash_file('sha256', $this->tmp);
        $this->assertSame('ok', DocumentiStorageService::verifyChecksum($this->tmp, $hash));
    }

    public function testMatchingChecksumIsCaseInsensitive(): void
    {
        $hash = strtoupper(hash_file('sha256', $this->tmp));
        $this->assertSame('ok', DocumentiStorageService::verifyChecksum($this->tmp, $hash));
    }

    public function testTamperedFileReturnsMismatch(): void
    {
        $hash = hash_file('sha256', $this->tmp);
        file_put_contents($this->tmp, 'contenuto manomesso fuori banda');
        $this->assertSame('mismatch', DocumentiStorageService::verifyChecksum($this->tmp, $hash));
    }

    public function testMissingFileReturnsMissing(): void
    {
        $this->assertSame('missing', DocumentiStorageService::verifyChecksum($this->tmp . '_inesistente', 'qualsiasi'));
    }

    public function testNullOrEmptyChecksumReturnsNoChecksum(): void
    {
        $this->assertSame('no_checksum', DocumentiStorageService::verifyChecksum($this->tmp, null));
        $this->assertSame('no_checksum', DocumentiStorageService::verifyChecksum($this->tmp, ''));
    }
}
