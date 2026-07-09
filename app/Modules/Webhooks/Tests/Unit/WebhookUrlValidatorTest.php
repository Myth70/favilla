<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Tests\Unit;

use App\Modules\Webhooks\Services\WebhookUrlValidator;
use PHPUnit\Framework\TestCase;

/**
 * Il controllo più importante della slice: l'anti-SSRF. La validazione statica
 * (schema/formato) e il blocco IP sono testati come pura logica (nessuna rete).
 * resolveAndAssertPublic() dipende dal DNS quindi non è testata qui.
 */
class WebhookUrlValidatorTest extends TestCase
{
    private WebhookUrlValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new WebhookUrlValidator();
    }

    public function testAcceptsHttpsPublicUrl(): void
    {
        $this->assertNull($this->validator->validate('https://hooks.example.com/endpoint'));
    }

    public function testRejectsPlainHttp(): void
    {
        $this->assertNotNull($this->validator->validate('http://example.com/hook'));
    }

    public function testRejectsNonHttpScheme(): void
    {
        $this->assertNotNull($this->validator->validate('ftp://example.com/x'));
        $this->assertNotNull($this->validator->validate('file:///etc/passwd'));
    }

    public function testRejectsCredentialsInUrl(): void
    {
        $this->assertNotNull($this->validator->validate('https://user:pass@example.com/hook'));
    }

    public function testRejectsEmptyOrOverlong(): void
    {
        $this->assertNotNull($this->validator->validate(''));
        $this->assertNotNull($this->validator->validate('https://example.com/' . str_repeat('a', 1100)));
    }

    /**
     * @dataProvider blockedIps
     */
    public function testBlocksPrivateAndReservedIps(string $ip): void
    {
        $this->assertTrue($this->validator->isBlockedIp($ip), "{$ip} deve essere bloccato");
    }

    /**
     * @return array<int, array{string}>
     */
    public static function blockedIps(): array
    {
        return [
            ['127.0.0.1'],      // loopback
            ['10.0.0.5'],       // private A
            ['172.16.3.4'],     // private B
            ['192.168.1.1'],    // private C
            ['169.254.10.1'],   // link-local
            ['0.0.0.0'],        // "this host"
            ['::1'],            // IPv6 loopback
            ['fe80::1'],        // IPv6 link-local
            ['fc00::1'],        // IPv6 unique-local
            ['::ffff:127.0.0.1'], // IPv4-mapped loopback
            ['not-an-ip'],      // non parsabile
        ];
    }

    /**
     * @dataProvider publicIps
     */
    public function testAllowsPublicIps(string $ip): void
    {
        $this->assertFalse($this->validator->isBlockedIp($ip), "{$ip} deve essere consentito");
    }

    /**
     * @return array<int, array{string}>
     */
    public static function publicIps(): array
    {
        return [
            ['8.8.8.8'],
            ['1.1.1.1'],
            ['93.184.216.34'], // example.com
            ['2606:4700:4700::1111'], // Cloudflare IPv6 pubblico
        ];
    }
}
