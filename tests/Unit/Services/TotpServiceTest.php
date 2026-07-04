<?php

namespace Tests\Unit\Services;

use App\Services\EncryptionService;
use App\Services\TotpService;
use ReflectionClass;
use Tests\ModuleTestCase;

class TotpServiceTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate("\n            CREATE TABLE user_totp_secrets (\n                user_id INTEGER PRIMARY KEY,\n                secret TEXT NOT NULL,\n                algorithm TEXT NOT NULL,\n                digits INTEGER NOT NULL,\n                period INTEGER NOT NULL,\n                enabled INTEGER NOT NULL DEFAULT 0,\n                verified_at TEXT DEFAULT NULL,\n                last_used_timestep INTEGER DEFAULT NULL,\n                updated_at TEXT DEFAULT NULL\n            )\n        ");

        $this->migrate("\n            CREATE TABLE user_totp_backup_codes (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                user_id INTEGER NOT NULL,\n                code_hash TEXT NOT NULL,\n                used_at TEXT DEFAULT NULL\n            )\n        ");
    }

    public function test_provisioning_uri_format(): void
    {
        $service = new TotpService();
        $uri = $service->getProvisioningUri('JBSWY3DPEHPK3PXP', 'user@example.com');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=', $uri);
        $this->assertStringContainsString('algorithm=SHA1', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }

    public function test_verify_login_accepts_valid_code(): void
    {
        $service = new TotpService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->seedEnabledSecret(10, $secret);

        $code = $this->generateTotpCode($secret, time());

        $this->assertTrue($service->verifyLogin(10, $code));
    }

    public function test_verify_login_rejects_wrong_code(): void
    {
        $service = new TotpService();
        $this->seedEnabledSecret(11, 'JBSWY3DPEHPK3PXP');

        $this->assertFalse($service->verifyLogin(11, '000000'));
    }

    public function test_verify_login_accepts_previous_window_code(): void
    {
        $service = new TotpService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->seedEnabledSecret(12, $secret);

        $previousCode = $this->generateTotpCode($secret, time() - 30);

        $this->assertTrue($service->verifyLogin(12, $previousCode));
    }

    public function test_verify_login_rejects_replayed_code(): void
    {
        $service = new TotpService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->seedEnabledSecret(13, $secret);

        $code = $this->generateTotpCode($secret, time());

        $this->assertTrue($service->verifyLogin(13, $code), 'Primo uso: il codice deve essere valido');
        $this->assertFalse($service->verifyLogin(13, $code), 'Replay: lo stesso codice non deve essere riutilizzabile');
    }

    public function test_verify_login_rejects_older_window_after_consumption(): void
    {
        $service = new TotpService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->seedEnabledSecret(14, $secret);

        $currentCode  = $this->generateTotpCode($secret, time());
        $previousCode = $this->generateTotpCode($secret, time() - 30);

        $this->assertTrue($service->verifyLogin(14, $currentCode));
        // Consumato il timestep corrente, quello precedente e' <= last_used: rifiutato.
        $this->assertFalse($service->verifyLogin(14, $previousCode));
    }

    public function test_verify_login_accepts_newer_code_after_consumption(): void
    {
        $service = new TotpService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->seedEnabledSecret(15, $secret);

        $previousCode = $this->generateTotpCode($secret, time() - 30);
        $currentCode  = $this->generateTotpCode($secret, time());

        $this->assertTrue($service->verifyLogin(15, $previousCode));
        // Timestep successivo a quello consumato: accettato.
        $this->assertTrue($service->verifyLogin(15, $currentCode));
    }

    private function seedEnabledSecret(int $userId, string $base32Secret): void
    {
        $encrypted = app(EncryptionService::class)->encrypt($base32Secret);
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_totp_secrets (user_id, secret, algorithm, digits, period, enabled, verified_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, datetime(\'now\'), datetime(\'now\'))'
        );
        $stmt->execute([$userId, $encrypted, 'sha1', 6, 30]);
    }

    private function generateTotpCode(string $base32Secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, 30);
        $rawSecret = $this->base32Decode($base32Secret);

        $ref = new ReflectionClass(TotpService::class);
        $hotp = $ref->getMethod('hotp');
        $hotp->setAccessible(true);

        return $hotp->invoke(null, $rawSecret, $counter, 6);
    }

    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper(rtrim($data, '='));
        $binary = '';

        foreach (str_split($data) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $result .= chr(bindec($byte));
        }

        return $result;
    }
}
