<?php

namespace Tests\Unit\Services;

use App\Services\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $svc;
    private string $tmpDir;

    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'test-app-key-for-unit-tests-32bytes!!';
        $this->svc = new EncryptionService();
        // sys_get_temp_dir() may be blocked by open_basedir — use project tmp
        $this->tmpDir = (defined('BASE_PATH') ? BASE_PATH : __DIR__ . '/../../..') . '/storage/cache';
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0777, true);
        }
    }

    private function makeTempFile(string $contents): string
    {
        $path = $this->tmpDir . '/enc_test_' . bin2hex(random_bytes(6));
        file_put_contents($path, $contents);
        return $path;
    }

    public function testConstructorRejectsShortKey(): void
    {
        $_ENV['APP_KEY'] = 'too-short';
        $this->expectException(\RuntimeException::class);
        new EncryptionService();
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plain = 'ciao, questo è un segreto';
        $enc   = $this->svc->encrypt($plain);
        $this->assertNotSame($plain, $enc);
        $this->assertSame($plain, $this->svc->decrypt($enc));
    }

    public function testEncryptProducesDifferentCiphertextEachCall(): void
    {
        $plain = 'identical-plaintext';
        $a = $this->svc->encrypt($plain);
        $b = $this->svc->encrypt($plain);
        $this->assertNotSame($a, $b, 'Nonce must randomize ciphertext');
        $this->assertSame($plain, $this->svc->decrypt($a));
        $this->assertSame($plain, $this->svc->decrypt($b));
    }

    public function testDecryptFailsOnTamperedCiphertext(): void
    {
        $enc = $this->svc->encrypt('secret');
        $raw = base64_decode($enc);
        // Flip last byte
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 0x01);
        $tampered = base64_encode($raw);

        $this->expectException(\RuntimeException::class);
        $this->svc->decrypt($tampered);
    }

    public function testDecryptFailsOnInvalidBase64(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->svc->decrypt('!!!not-base64!!!');
    }

    public function testDecryptFailsOnTooShortInput(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->svc->decrypt(base64_encode('short'));
    }

    public function testIsEncryptedDetectsOwnOutput(): void
    {
        $enc = $this->svc->encrypt('x');
        $this->assertTrue($this->svc->isEncrypted($enc));
    }

    public function testIsEncryptedReturnsFalseForPlaintextAndEmpty(): void
    {
        $this->assertFalse($this->svc->isEncrypted(''));
        $this->assertFalse($this->svc->isEncrypted('plain text value'));
    }

    public function testEncryptIfNeededIsIdempotent(): void
    {
        $enc1 = $this->svc->encryptIfNeeded('secret');
        $enc2 = $this->svc->encryptIfNeeded($enc1);
        $this->assertSame($enc1, $enc2);
        $this->assertSame('secret', $this->svc->decrypt($enc2));
    }

    public function testMaskHidesMiddleCharacters(): void
    {
        $this->assertSame('1234****5678', EncryptionService::mask('123456785678', 4));
        $this->assertSame('12**89', EncryptionService::mask('123489', 2));
    }

    public function testMaskReturnsAllStarsForShortInputs(): void
    {
        $this->assertSame('****', EncryptionService::mask('abcd', 4));
        $this->assertSame('********', EncryptionService::mask('abcdefgh', 4));
    }

    public function testEncryptDecryptFile(): void
    {
        $path = $this->makeTempFile('contenuto file riservato');

        $this->assertTrue($this->svc->encryptFile($path));
        $this->assertTrue($this->svc->isFileEncrypted($path));
        $this->assertStringStartsWith('ENC1', file_get_contents($path));

        // Idempotent: encrypting again should be a no-op
        $this->assertTrue($this->svc->encryptFile($path));

        $this->assertTrue($this->svc->decryptFile($path));
        $this->assertFalse($this->svc->isFileEncrypted($path));
        $this->assertSame('contenuto file riservato', file_get_contents($path));

        @unlink($path);
    }

    public function testEncryptFileReturnsFalseForMissing(): void
    {
        $missing = $this->tmpDir . '/definitely_missing_file.bin';
        @unlink($missing);

        $this->assertFalse($this->svc->encryptFile($missing));
        $this->assertFalse($this->svc->decryptFile($missing));
        $this->assertFalse($this->svc->isFileEncrypted($missing));
    }

    public function testDecryptFileToTempReturnsPath(): void
    {
        $path = $this->makeTempFile('payload');
        $this->svc->encryptFile($path);

        $tmp = $this->svc->decryptFileToTemp($path);
        $this->assertNotNull($tmp);
        $this->assertIsString($tmp);
        $this->assertSame('payload', file_get_contents($tmp));

        @unlink($tmp);
        @unlink($path);
    }

    public function testDecryptFileToTempReturnsNullIfNotEncrypted(): void
    {
        $path = $this->makeTempFile('plain');
        $this->assertNull($this->svc->decryptFileToTemp($path));
        @unlink($path);
    }
}
