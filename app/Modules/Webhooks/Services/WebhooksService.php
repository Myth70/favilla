<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

use App\Modules\Webhooks\Repositories\WebhookDeliveryRepository;
use App\Modules\Webhooks\Repositories\WebhookEndpointRepository;
use RuntimeException;

/**
 * Logica di dominio degli endpoint webhook: validazione (anti-SSRF), gestione
 * del secret (generato server-side, mostrato una sola volta) e invio di prova.
 */
class WebhooksService
{
    private WebhookEndpointRepository $endpointRepo;
    private WebhookDeliveryRepository $deliveryRepo;
    private WebhookUrlValidator $urlValidator;
    private WebhookSigner $signer;
    private WebhookHttpClient $http;

    public function __construct()
    {
        $this->endpointRepo = app(WebhookEndpointRepository::class);
        $this->deliveryRepo = app(WebhookDeliveryRepository::class);
        $this->urlValidator = app(WebhookUrlValidator::class);
        $this->signer = app(WebhookSigner::class);
        $this->http = app(WebhookHttpClient::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $endpoints = $this->endpointRepo->allOrdered();
        foreach ($endpoints as &$endpoint) {
            $endpoint['event_types'] = json_decode((string) $endpoint['event_types'], true) ?: [];
        }
        unset($endpoint);
        return $endpoints;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $endpoint = $this->endpointRepo->find($id);
        if ($endpoint === null) {
            return null;
        }
        $endpoint['event_types'] = json_decode((string) $endpoint['event_types'], true) ?: [];
        return $endpoint;
    }

    /**
     * Crea un endpoint. Restituisce [id, secret in chiaro] — il secret va
     * mostrato una sola volta.
     *
     * @param string[] $eventTypes
     * @return array{id:int, secret:string}
     */
    public function create(string $url, array $eventTypes, ?string $description, int $userId): array
    {
        $this->assertValid($url, $eventTypes);

        $secret = $this->signer->generateSecret();
        $id = $this->endpointRepo->create([
            'url'         => trim($url),
            'secret'      => $secret,
            'event_types' => json_encode(array_values($eventTypes)),
            'description' => $description !== null && $description !== '' ? mb_substr($description, 0, 255) : null,
            'is_active'   => 1,
            'created_by'  => $userId,
        ]);

        return ['id' => $id, 'secret' => $secret];
    }

    /**
     * @param string[] $eventTypes
     */
    public function update(int $id, string $url, array $eventTypes, ?string $description, bool $isActive): bool
    {
        $this->assertValid($url, $eventTypes);

        return $this->endpointRepo->update($id, [
            'url'         => trim($url),
            'event_types' => json_encode(array_values($eventTypes)),
            'description' => $description !== null && $description !== '' ? mb_substr($description, 0, 255) : null,
            'is_active'   => $isActive ? 1 : 0,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->endpointRepo->delete($id);
    }

    /**
     * Rigenera il secret di un endpoint e lo restituisce (mostrato una volta).
     */
    public function regenerateSecret(int $id): ?string
    {
        $endpoint = $this->endpointRepo->find($id);
        if ($endpoint === null) {
            return null;
        }
        $secret = $this->signer->generateSecret();
        $this->endpointRepo->update($id, ['secret' => $secret]);
        return $secret;
    }

    /**
     * Invio di prova sincrono: valida (anti-SSRF), firma e fa POST, registrando
     * una delivery con l'esito. Restituisce un messaggio leggibile.
     */
    public function sendTest(int $id): string
    {
        $endpoint = $this->endpointRepo->find($id);
        if ($endpoint === null) {
            throw new RuntimeException('Endpoint non trovato.');
        }

        $ssrfError = $this->urlValidator->resolveAndAssertPublic((string) $endpoint['url']);
        if ($ssrfError !== null) {
            throw new RuntimeException($ssrfError);
        }

        $payload = json_encode([
            'event'       => 'webhooks.test',
            'module'      => 'Webhooks',
            'occurred_at' => date('c'),
            'title'       => 'Invio di prova',
            'body'        => 'Se ricevi questo messaggio, l\'endpoint è configurato correttamente.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        $deliveryId = $this->deliveryRepo->create([
            'endpoint_id' => $id,
            'event_type'  => 'webhooks.test',
            'payload'     => $payload,
            'status'      => 'pending',
        ]);

        $headers = [
            'X-Favilla-Event'     => 'webhooks.test',
            'X-Favilla-Delivery'  => (string) $deliveryId,
            WebhookSigner::HEADER => $this->signer->sign($payload, (string) $endpoint['secret']),
        ];

        $result = $this->http->post((string) $endpoint['url'], $payload, $headers);
        $status = $result['status'];

        if ($status !== null && $status >= 200 && $status < 300) {
            $this->deliveryRepo->markSent($deliveryId, $status);
            return 'Invio riuscito (HTTP ' . $status . ').';
        }

        $error = $result['error'] ?? ('HTTP ' . ($status ?? '000'));
        $this->deliveryRepo->releaseOrFail($deliveryId, 1, 1, $status, (string) $error, 0);
        throw new RuntimeException('Invio fallito: ' . $error);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deliveriesFor(int $endpointId): array
    {
        return $this->deliveryRepo->recentForEndpoint($endpointId);
    }

    /**
     * @return array{pending:int, sent:int, failed:int}
     */
    public function deliveryStats(): array
    {
        return $this->deliveryRepo->statusCounts();
    }

    /**
     * @param string[] $eventTypes
     */
    private function assertValid(string $url, array $eventTypes): void
    {
        $error = $this->urlValidator->validate($url);
        if ($error !== null) {
            throw new RuntimeException($error);
        }
        if ($eventTypes === []) {
            throw new RuntimeException('Seleziona almeno un evento a cui iscrivere l\'endpoint.');
        }
    }
}
