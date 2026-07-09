<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Services\VapidKeyService;
use App\Services\SettingsService;
use Base64Url\Base64Url;
use Tests\ModuleTestCase;

/**
 * Generazione e lettura delle chiavi VAPID su app_settings reale (SQLite).
 * La generazione EC passa da OpensslEcKeyFactory: su Linux/CI la chiamata bare
 * riesce, su Windows/XAMPP la factory trova openssl.cnf. Se nessun percorso è
 * utilizzabile il test viene saltato invece di fallire.
 */
class VapidKeyServiceTest extends ModuleTestCase
{
    private VapidKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('CREATE TABLE app_settings (`key` TEXT PRIMARY KEY, `value` TEXT, `type` TEXT, `group` TEXT, `label` TEXT, updated_at TEXT)');
        $insert = $this->pdo->prepare(
            'INSERT INTO app_settings (`key`, `value`, `type`, `group`, `label`) VALUES (?, "", "string", "notifications", ?)'
        );
        foreach ([
            VapidKeyService::SETTING_PUBLIC_KEY,
            VapidKeyService::SETTING_PRIVATE_KEY,
            VapidKeyService::SETTING_SUBJECT,
        ] as $key) {
            $insert->execute([$key, $key]);
        }
        SettingsService::clearCache();

        $this->service = new VapidKeyService();
    }

    protected function tearDown(): void
    {
        SettingsService::clearCache();
        parent::tearDown();
    }

    public function testNotConfiguredBeforeGeneration(): void
    {
        $this->assertFalse($this->service->isConfigured());
        $this->assertNull($this->service->publicKey());
    }

    public function testGenerateProducesValidKeyPair(): void
    {
        $result = $this->generateOrSkip();

        $this->assertTrue($result['generated']);
        $this->assertTrue($this->service->isConfigured());

        // Chiave pubblica: punto EC non compresso (0x04 + X + Y = 65 byte).
        $decoded = Base64Url::decode((string) $this->service->publicKey());
        $this->assertSame(65, strlen($decoded));
        $this->assertSame("\x04", $decoded[0]);

        // Chiave privata: 32 byte (scalare d).
        $this->assertSame(32, strlen(Base64Url::decode((string) $this->service->privateKey())));
    }

    public function testGenerateIsIdempotentWithoutForce(): void
    {
        $first = $this->generateOrSkip();
        $second = $this->service->generate();

        $this->assertFalse($second['generated'], 'Senza force le chiavi non vengono sostituite');
        $this->assertSame($first['publicKey'], $second['publicKey']);
    }

    public function testForceRegeneratesKeys(): void
    {
        $first = $this->generateOrSkip();
        $second = $this->service->generate(true);

        $this->assertTrue($second['generated']);
        $this->assertNotSame($first['publicKey'], $second['publicKey']);
    }

    /**
     * @return array{publicKey: string, generated: bool}
     */
    private function generateOrSkip(): array
    {
        try {
            return $this->service->generate();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('OpenSSL EC keygen non disponibile: ' . $e->getMessage());
        }
    }
}
