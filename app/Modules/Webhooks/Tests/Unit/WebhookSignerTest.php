<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Tests\Unit;

use App\Modules\Webhooks\Services\WebhookSigner;
use PHPUnit\Framework\TestCase;

class WebhookSignerTest extends TestCase
{
    private WebhookSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new WebhookSigner();
    }

    public function testSignMatchesHmacSha256(): void
    {
        $body = '{"event":"test"}';
        $secret = 'topsecret';
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $this->assertSame($expected, $this->signer->sign($body, $secret));
    }

    public function testVerifyAcceptsCorrectSignature(): void
    {
        $body = '{"a":1}';
        $secret = 's3cr3t';
        $sig = $this->signer->sign($body, $secret);

        $this->assertTrue($this->signer->verify($body, $secret, $sig));
    }

    public function testVerifyRejectsWrongSecretOrBody(): void
    {
        $sig = $this->signer->sign('{"a":1}', 'right');

        $this->assertFalse($this->signer->verify('{"a":1}', 'wrong', $sig));
        $this->assertFalse($this->signer->verify('{"a":2}', 'right', $sig));
    }

    public function testGeneratedSecretIsHex48(): void
    {
        $secret = $this->signer->generateSecret();
        $this->assertSame(48, strlen($secret));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $secret);
    }
}
