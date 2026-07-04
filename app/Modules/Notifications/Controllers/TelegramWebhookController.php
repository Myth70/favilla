<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Modules\Notifications\Services\TelegramLinkService;
use App\Security\RateLimiter;
use App\Support\ClientIp;

class TelegramWebhookController extends Controller
{
    /** Pseudo-account key used to bucket webhook auth failures in login_attempts. */
    private const RL_KEY = '__telegram_webhook__';

    /** Max wrong-secret attempts per IP within the rate-limit window. */
    private const RL_MAX_FAILURES = 20;

    private TelegramLinkService $linkService;

    public function __construct()
    {
        $this->linkService = app(TelegramLinkService::class);
    }

    public function webhook(string $secret): void
    {
        $ip          = ClientIp::resolve();
        $rateLimiter = app(RateLimiter::class);

        // Defense-in-depth: throttle repeated wrong-secret attempts per IP.
        // The secret itself (random_bytes, hash_equals compared) stays the primary barrier.
        if ($rateLimiter->isLimitedForIpAndAccount($ip, self::RL_KEY, self::RL_MAX_FAILURES)) {
            $this->json(['ok' => false, 'message' => 'Troppe richieste.'], 429);
            return;
        }

        $raw     = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            $this->json(['ok' => false, 'message' => 'Payload non valido.'], 400);
            return;
        }

        $result = $this->linkService->handleWebhook($secret, $payload);

        // Record authentication failures (wrong secret → 403) to feed the limiter.
        if ((int) $result['status'] === 403) {
            $rateLimiter->record(self::RL_KEY, $ip, false);
        }

        $this->json(
            ['ok' => (bool) $result['ok'], 'message' => (string) $result['message']],
            (int) $result['status']
        );
    }
}
