<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\PushSubscriptionRepository;

class WebPushChannelDriver implements NotificationChannelDriverInterface
{
    private const MAX_BODY_CHARS = 500;

    private PushSubscriptionRepository $subscriptionRepo;
    private VapidKeyService $vapidService;
    private WebPushSender $sender;

    public function __construct()
    {
        $this->subscriptionRepo = app(PushSubscriptionRepository::class);
        $this->vapidService = app(VapidKeyService::class);
        $this->sender = app(WebPushSender::class);
    }

    public function channel(): string
    {
        return 'web_push';
    }

    public function send(array $job): array
    {
        if (!$this->vapidService->isConfigured()) {
            return [
                'status' => 'skipped',
                'provider_message_id' => null,
                'error_message' => 'Chiavi VAPID non configurate (Admin → Notifiche).',
            ];
        }

        $subscriptions = $this->subscriptionRepo->activeForUser((int) $job['user_id']);
        if ($subscriptions === []) {
            return [
                'status' => 'skipped',
                'provider_message_id' => null,
                'error_message' => 'Nessuna subscription push attiva per l\'utente.',
            ];
        }

        $payload = json_encode($this->buildPayload($job), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'error_message' => 'Payload push non serializzabile.',
            ];
        }

        try {
            $results = $this->sender->send(
                $subscriptions,
                $payload,
                (string) $this->vapidService->publicKey(),
                (string) $this->vapidService->privateKey(),
                $this->vapidService->subject()
            );
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'error_message' => 'Invio push fallito: ' . $e->getMessage(),
            ];
        }

        $sentIds = [];
        $expired = 0;
        $failures = [];
        foreach ($subscriptions as $subscription) {
            $result = $results[$subscription['endpoint_hash']] ?? null;
            if ($result === null) {
                continue;
            }
            if (!empty($result['expired'])) {
                // 404/410 dal push service: subscription morta, si elimina e non è un errore.
                $this->subscriptionRepo->deleteByEndpointHash((string) $subscription['endpoint_hash']);
                $expired++;
            } elseif (!empty($result['success'])) {
                $sentIds[] = (int) $subscription['id'];
            } else {
                $failures[] = (string) ($result['error'] ?? 'errore sconosciuto');
            }
        }

        if ($sentIds !== []) {
            $this->subscriptionRepo->touchLastUsed($sentIds);
            return [
                'status' => 'sent',
                'provider_message_id' => null,
                'error_message' => null,
            ];
        }

        if ($failures === [] && $expired > 0) {
            return [
                'status' => 'skipped',
                'provider_message_id' => null,
                'error_message' => 'Tutte le subscription push risultavano scadute e sono state rimosse.',
            ];
        }

        return [
            'status' => 'failed',
            'provider_message_id' => null,
            'error_message' => 'Invio push fallito su tutti i dispositivi: ' . implode(' | ', array_slice($failures, 0, 3)),
        ];
    }

    /**
     * Payload consumato da public/sw.js (handler 'push'): title, body, url, tag.
     *
     * @param array<string, mixed> $job
     * @return array{title: string, body: string, url: string, tag: string}
     */
    private function buildPayload(array $job): array
    {
        $title = trim((string) ($job['delivery_subject'] ?? $job['dispatch_title'] ?? 'Notifica'));
        $body = trim(strip_tags((string) ($job['delivery_body'] ?? $job['dispatch_body'] ?? '')));
        $link = trim((string) (($job['delivery_link'] ?? '') ?: ($job['dispatch_link'] ?? '')));

        if (mb_strlen($body) > self::MAX_BODY_CHARS) {
            $body = mb_substr($body, 0, self::MAX_BODY_CHARS - 1) . '…';
        }

        return [
            'title' => $title !== '' ? $title : 'Notifica',
            'body'  => $body,
            'url'   => $link,
            'tag'   => 'favilla-' . (string) ($job['delivery_id'] ?? uniqid('', false)),
        ];
    }
}
