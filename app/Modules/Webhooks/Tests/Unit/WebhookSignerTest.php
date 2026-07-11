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

    public function testSignBindsTimestampAndMatchesHmac(): void
    {
        $body = '{"event":"test"}';
        $secret = 'topsecret';
        $ts = 1_700_000_000;

        $expected = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $body, $secret);

        $this->assertSame($expected, $this->signer->sign($body, $secret, $ts));
    }

    public function testVerifyAcceptsCorrectSignatureWithinWindow(): void
    {
        $body = '{"a":1}';
        $secret = 's3cr3t';
        $ts = 1_700_000_000;
        $sig = $this->signer->sign($body, $secret, $ts);

        // "adesso" 60s dopo la firma: dentro la finestra di tolleranza.
        $this->assertTrue($this->signer->verify($body, $secret, $sig, 300, $ts + 60));
    }

    public function testVerifyRejectsWrongSecretOrBody(): void
    {
        $ts = 1_700_000_000;
        $sig = $this->signer->sign('{"a":1}', 'right', $ts);

        $this->assertFalse($this->signer->verify('{"a":1}', 'wrong', $sig, 300, $ts));
        $this->assertFalse($this->signer->verify('{"a":2}', 'right', $sig, 300, $ts));
    }

    public function testVerifyRejectsReplayOutsideWindow(): void
    {
        $body = '{"a":1}';
        $secret = 's3cr3t';
        $ts = 1_700_000_000;
        $sig = $this->signer->sign($body, $secret, $ts);

        // Cattura rigiocata 10 minuti dopo, con tolleranza 5 minuti: rifiutata.
        $this->assertFalse($this->signer->verify($body, $secret, $sig, 300, $ts + 600));
        // Anche un timestamp troppo nel futuro rispetto a "now" è fuori finestra.
        $this->assertFalse($this->signer->verify($body, $secret, $sig, 300, $ts - 600));
    }

    public function testVerifyRejectsMalformedHeader(): void
    {
        $this->assertFalse($this->signer->verify('{}', 's', 'sha256=deadbeef'));
        $this->assertFalse($this->signer->verify('{}', 's', 'garbage'));
        $this->assertFalse($this->signer->verify('{}', 's', 't=abc,v1=x'));
    }

    public function testGeneratedSecretIsHex48(): void
    {
        $secret = $this->signer->generateSecret();
        $this->assertSame(48, strlen($secret));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $secret);
    }
}
