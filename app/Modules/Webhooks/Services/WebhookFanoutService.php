<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

use App\Modules\Webhooks\Repositories\WebhookDeliveryRepository;
use App\Modules\Webhooks\Repositories\WebhookEndpointRepository;

/**
 * Accoda una consegna webhook per ogni endpoint sottoscritto a un evento.
 * Invocato dal dispatcher delle notifiche: ogni evento che alimenta le
 * notifiche può fare fan-out anche come webhook. Non tocca la rete (solo INSERT
 * in webhook_deliveries); la consegna vera la fa lo scheduler.
 */
class WebhookFanoutService
{
    private WebhookEndpointRepository $endpointRepo;
    private WebhookDeliveryRepository $deliveryRepo;

    public function __construct()
    {
        $this->endpointRepo = app(WebhookEndpointRepository::class);
        $this->deliveryRepo = app(WebhookDeliveryRepository::class);
    }

    /**
     * @param array<string, mixed> $message payload logico dell'evento
     * @return int numero di consegne accodate
     */
    public function enqueueForEvent(string $eventSlug, string $sourceModule, array $message): int
    {
        $endpoints = $this->endpointRepo->activeForEvent($eventSlug);
        if ($endpoints === []) {
            return 0;
        }

        $payload = json_encode(
            $this->buildPayload($eventSlug, $sourceModule, $message),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($payload === false) {
            return 0;
        }

        $count = 0;
        foreach ($endpoints as $endpoint) {
            $this->deliveryRepo->create([
                'endpoint_id' => (int) $endpoint['id'],
                'event_type'  => $eventSlug,
                'payload'     => $payload,
                'status'      => 'pending',
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function buildPayload(string $eventSlug, string $sourceModule, array $message): array
    {
        return [
            'event'       => $eventSlug,
            'module'      => $sourceModule,
            'occurred_at' => date('c'),
            'title'       => (string) ($message['title'] ?? ''),
            'body'        => (string) ($message['body'] ?? ''),
            'link'        => $message['link'] ?? null,
            'context'     => is_array($message['context'] ?? null) ? $message['context'] : [],
        ];
    }
}
