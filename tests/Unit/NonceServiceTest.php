<?php

namespace Tests\Unit;

use App\Services\NonceService;
use PHPUnit\Framework\TestCase;

class NonceServiceTest extends TestCase
{
    public function test_nonce_is_base64_string(): void
    {
        $service = new NonceService();
        $nonce = $service->getNonce();

        $this->assertNotEmpty($nonce);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce);
    }

    public function test_nonce_is_consistent_within_instance(): void
    {
        $service = new NonceService();

        $this->assertSame($service->getNonce(), $service->getNonce());
    }

    public function test_nonce_is_unique_per_instance(): void
    {
        $nonce1 = (new NonceService())->getNonce();
        $nonce2 = (new NonceService())->getNonce();

        $this->assertNotSame($nonce1, $nonce2);
    }

    public function test_nonce_decodes_to_16_bytes(): void
    {
        $service = new NonceService();
        $decoded = base64_decode($service->getNonce(), true);

        $this->assertNotFalse($decoded);
        $this->assertSame(16, strlen($decoded));
    }
}
