<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

use App\Modules\Webhooks\Repositories\WebhookDeliveryRepository;

/**
 * Drena la coda webhook_deliveries: per ogni consegna dovuta ri-valida l'URL
 * (anti-SSRF, DNS a ogni tentativo), firma il body HMAC e fa POST. 2xx = sent;
 * altrimenti retry con backoff esponenziale (5→15→45→135 min) fino a
 * MAX_ATTEMPTS, poi failed.
 */
class WebhookDispatchService
{
    private const MAX_ATTEMPTS = 5;
    /** Backoff in minuti per numero di tentativo (indice = attempts già fatti). */
    private const BACKOFF = [5, 15, 45, 135, 135];

    private WebhookDeliveryRepository $deliveryRepo;
    private WebhookUrlValidator $urlValidator;
    private WebhookSigner $signer;
    private WebhookHttpClient $http;

    public function __construct()
    {
        $this->deliveryRepo = app(WebhookDeliveryRepository::class);
        $this->urlValidator = app(WebhookUrlValidator::class);
        $this->signer = app(WebhookSigner::class);
        $this->http = app(WebhookHttpClient::class);
    }

    /**
     * @return array{processed:int, sent:int, failed:int, released:int}
     */
    public function dispatch(int $limit = 50): array
    {
        $due = $this->deliveryRepo->claimDue($limit);
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'released' => 0];

        foreach ($due as $delivery) {
            $stats['processed']++;
            $id = (int) $delivery['id'];
            $attempts = (int) $delivery['attempts'] + 1;
            $url = (string) $delivery['endpoint_url'];

            // Anti-SSRF ricontrollato a ogni invio (mitiga il DNS rebinding).
            $ssrfError = $this->urlValidator->resolveAndAssertPublic($url);
            if ($ssrfError !== null) {
                $this->deliveryRepo->releaseOrFail($id, $attempts, self::MAX_ATTEMPTS, null, 'SSRF: ' . $ssrfError, $this->backoffFor($attempts));
                $this->tally($stats, $attempts, false);
                continue;
            }

            $body = (string) $delivery['payload'];
            $headers = [
                'X-Favilla-Event'    => (string) $delivery['event_type'],
                'X-Favilla-Delivery' => (string) $id,
                WebhookSigner::HEADER => $this->signer->sign($body, (string) $delivery['endpoint_secret']),
            ];

            $result = $this->http->post($url, $body, $headers);
            $status = $result['status'];

            if ($status !== null && $status >= 200 && $status < 300) {
                $this->deliveryRepo->markSent($id, $status);
                $stats['sent']++;
                continue;
            }

            $error = $result['error'] ?? ('HTTP ' . ($status ?? '000'));
            $this->deliveryRepo->releaseOrFail($id, $attempts, self::MAX_ATTEMPTS, $status, (string) $error, $this->backoffFor($attempts));
            $this->tally($stats, $attempts, false);
        }

        return $stats;
    }

    /**
     * @param array{processed:int, sent:int, failed:int, released:int} $stats
     */
    private function tally(array &$stats, int $attempts, bool $sent): void
    {
        if ($sent) {
            $stats['sent']++;
        } elseif ($attempts >= self::MAX_ATTEMPTS) {
            $stats['failed']++;
        } else {
            $stats['released']++;
        }
    }

    private function backoffFor(int $attempts): int
    {
        $idx = max(0, min($attempts - 1, count(self::BACKOFF) - 1));
        return self::BACKOFF[$idx];
    }
}
