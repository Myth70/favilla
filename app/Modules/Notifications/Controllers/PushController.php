<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Modules\Notifications\Services\PushSubscriptionService;
use App\Traits\ControllerHelpers;

/**
 * Registrazione/rimozione delle subscription Web Push del dispositivo.
 * Chiamato in fetch da nt-push.js (body form-encoded + header X-CSRF-Token);
 * risposte sempre JSON. La validazione stretta di endpoint/chiavi e la
 * persistenza vivono in PushSubscriptionService (layering).
 */
class PushController extends Controller
{
    use ControllerHelpers;

    private PushSubscriptionService $subscriptions;

    public function __construct()
    {
        $this->subscriptions = app(PushSubscriptionService::class);
    }

    public function subscribe(): void
    {
        $userId = (int) (auth()['id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        if (!$this->subscriptions->isConfigured()) {
            $this->json(['ok' => false, 'error' => 'not_configured'], 409);
        }

        $result = $this->subscriptions->subscribe(
            $userId,
            (string) ($_POST['endpoint'] ?? ''),
            (string) ($_POST['p256dh'] ?? ''),
            (string) ($_POST['auth'] ?? ''),
            (string) ($_POST['content_encoding'] ?? 'aes128gcm'),
            isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null
        );

        if (!$result['ok']) {
            $this->json(['ok' => false, 'error' => $result['error'] ?? 'invalid_subscription'], 422);
        }

        $this->json(['ok' => true, 'device_count' => $result['device_count'] ?? 0]);
    }

    public function unsubscribe(): void
    {
        $userId = (int) (auth()['id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        $result = $this->subscriptions->unsubscribe($userId, (string) ($_POST['endpoint'] ?? ''));

        if (!$result['ok']) {
            $this->json(['ok' => false, 'error' => $result['error'] ?? 'invalid_endpoint'], 422);
        }

        $this->json(['ok' => true, 'device_count' => $result['device_count'] ?? 0]);
    }
}
