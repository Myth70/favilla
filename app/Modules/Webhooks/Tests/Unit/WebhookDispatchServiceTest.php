<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Tests\Unit;

use App\Modules\Webhooks\Repositories\WebhookDeliveryRepository;
use App\Modules\Webhooks\Services\WebhookDispatchService;
use App\Modules\Webhooks\Services\WebhookHttpClient;
use App\Modules\Webhooks\Services\WebhookSigner;
use App\Modules\Webhooks\Services\WebhookUrlValidator;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Dispatcher: 2xx => sent (con firma corretta negli header inviati); non-2xx =>
 * retry (status torna pending, attempts incrementa); SSRF bloccato => retry/fail
 * senza toccare la rete. HTTP client e URL validator mockati.
 */
class WebhookDispatchServiceTest extends ModuleTestCase
{
    use MakesContainer;

    private WebhookDeliveryRepository $deliveryRepo;
    private WebhookHttpClient $http;
    private WebhookUrlValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE webhook_endpoints (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL, secret TEXT NOT NULL, event_types TEXT NOT NULL,
                description TEXT NULL, is_active INTEGER NOT NULL DEFAULT 1,
                created_by INTEGER NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP, deleted_at TEXT NULL
            );
            CREATE TABLE webhook_deliveries (
                id INTEGER PRIMARY KEY AUTOINCREMENT, endpoint_id INTEGER NOT NULL,
                event_type TEXT NOT NULL, payload TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "pending", attempts INTEGER NOT NULL DEFAULT 0,
                response_code INTEGER NULL, last_error TEXT NULL, next_retry_at TEXT NULL,
                locked_at TEXT NULL, delivered_at TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->deliveryRepo = new WebhookDeliveryRepository();

        // URL validator: di default "sicuro" (nessun errore SSRF, IP vettato).
        $this->validator = $this->createMock(WebhookUrlValidator::class);
        $this->validator->method('resolveVetted')->willReturn(['error' => null, 'ips' => ['93.184.216.34']]);
        $this->bindInstance(WebhookUrlValidator::class, $this->validator);

        $this->http = $this->createMock(WebhookHttpClient::class);
        $this->bindInstance(WebhookHttpClient::class, $this->http);

        $this->bindInstance(WebhookSigner::class, new WebhookSigner());
    }

    private function seedDelivery(int $attempts = 0): int
    {
        $endpointId = $this->insertRow('webhook_endpoints', [
            'url' => 'https://hooks.example.com/x', 'secret' => 'topsecret',
            'event_types' => json_encode(['tasks.task_overdue']),
        ]);
        return $this->insertRow('webhook_deliveries', [
            'endpoint_id' => $endpointId,
            'event_type'  => 'tasks.task_overdue',
            'payload'     => '{"event":"tasks.task_overdue"}',
            'status'      => 'pending',
            'attempts'    => $attempts,
        ]);
    }

    public function testSuccessfulDeliveryMarksSentWithSignature(): void
    {
        $id = $this->seedDelivery();

        $captured = [];
        $this->http->method('post')->willReturnCallback(function ($url, $body, $headers) use (&$captured) {
            $captured = $headers;
            return ['status' => 200, 'error' => null];
        });

        $stats = (new WebhookDispatchService())->dispatch(10);

        $this->assertSame(1, $stats['sent']);
        $row = $this->deliveryRepo->find($id);
        $this->assertSame('sent', $row['status']);
        $this->assertSame(200, (int) $row['response_code']);
        $this->assertSame(1, (int) $row['attempts']);

        // Header di firma presente, con timestamp, verificabile col secret.
        $this->assertArrayHasKey(WebhookSigner::TIMESTAMP_HEADER, $captured);
        $this->assertTrue(
            (new WebhookSigner())->verify(
                '{"event":"tasks.task_overdue"}',
                'topsecret',
                $captured[WebhookSigner::HEADER]
            ),
            'La firma inviata deve verificare col secret dell\'endpoint'
        );
        $this->assertSame('tasks.task_overdue', $captured['X-Favilla-Event']);
    }

    public function testServerErrorReleasesForRetry(): void
    {
        $id = $this->seedDelivery();
        $this->http->method('post')->willReturn(['status' => 503, 'error' => null]);

        $stats = (new WebhookDispatchService())->dispatch(10);

        $this->assertSame(1, $stats['released']);
        $row = $this->deliveryRepo->find($id);
        $this->assertSame('pending', $row['status']);
        $this->assertSame(1, (int) $row['attempts']);
        $this->assertNotNull($row['next_retry_at']);
    }

    public function testLastAttemptMarksFailed(): void
    {
        // 5° tentativo (attempts già a 4): supera MAX_ATTEMPTS => failed.
        $id = $this->seedDelivery(attempts: 4);
        $this->http->method('post')->willReturn(['status' => 500, 'error' => null]);

        $stats = (new WebhookDispatchService())->dispatch(10);

        $this->assertSame(1, $stats['failed']);
        $this->assertSame('failed', $this->deliveryRepo->find($id)['status']);
    }

    public function testSsrfBlockedDoesNotCallHttp(): void
    {
        $this->seedDelivery();

        // Validator ora rifiuta: la rete non deve essere toccata.
        $validator = $this->createMock(WebhookUrlValidator::class);
        $validator->method('resolveVetted')->willReturn(['error' => 'IP privato', 'ips' => []]);
        $this->bindInstance(WebhookUrlValidator::class, $validator);
        $this->http->expects($this->never())->method('post');

        $stats = (new WebhookDispatchService())->dispatch(10);

        $this->assertSame(1, $stats['released']);
    }

    public function testConcurrentClaimSendsEachDeliveryOnce(): void
    {
        $id = $this->seedDelivery();

        // Primo run reclama la consegna: passa a 'processing' (claim atomico).
        $this->assertCount(1, $this->deliveryRepo->claimDue(10));
        // Un secondo run concorrente NON deve poterla reclamare di nuovo.
        $this->assertCount(0, $this->deliveryRepo->claimDue(10));

        $row = $this->deliveryRepo->find($id);
        $this->assertSame('processing', $row['status']);
    }

    public function testInactiveEndpointDeliveriesAreNotClaimed(): void
    {
        $id = $this->seedDelivery();
        // Disattiva l'endpoint della consegna.
        $this->pdo->exec('UPDATE webhook_endpoints SET is_active = 0');

        $this->assertCount(0, $this->deliveryRepo->claimDue(10));
        $this->assertSame('pending', $this->deliveryRepo->find($id)['status']);
    }
}
