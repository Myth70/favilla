<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Tests\Unit;

use App\Modules\Webhooks\Services\WebhookHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Il client cerca di NON toccare la rete se l'URL è malformato. Le proprietà di
 * rete vere (IP-pinning via CURLOPT_RESOLVE, no-redirect, cap sul body)
 * richiedono un server locale e sono verificate in integrazione/e2e; qui
 * copriamo la forma della risposta e i guard pre-connessione.
 */
class WebhookHttpClientTest extends TestCase
{
    private WebhookHttpClient $client;

    protected function setUp(): void
    {
        $this->client = new WebhookHttpClient();
    }

    public function testInvalidUrlReturnsErrorEnvelopeWithoutNetwork(): void
    {
        $result = $this->client->post('not a url', '{}', []);

        $this->assertNull($result['status']);
        $this->assertNotNull($result['error']);
    }

    public function testEmptyHostUrlIsRejected(): void
    {
        $result = $this->client->post('https:///no-host', '{}', []);

        $this->assertNull($result['status']);
        $this->assertNotNull($result['error']);
    }
}
