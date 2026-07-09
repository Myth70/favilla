<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\Controller;
use App\Modules\Notifications\Repositories\PushSubscriptionRepository;
use App\Modules\Notifications\Services\VapidKeyService;
use App\Traits\ControllerHelpers;

/**
 * Registrazione/rimozione delle subscription Web Push del dispositivo.
 * Chiamato in fetch da nt-push.js (body form-encoded + header X-CSRF-Token);
 * risposte sempre JSON. I valori base64url e l'endpoint vengono validati in
 * modo stretto invece di passare dalla sanitizzazione generica, che potrebbe
 * alterarli.
 */
class PushController extends Controller
{
    use ControllerHelpers;

    private PushSubscriptionRepository $subscriptionRepo;
    private VapidKeyService $vapidService;

    public function __construct()
    {
        $this->subscriptionRepo = app(PushSubscriptionRepository::class);
        $this->vapidService = app(VapidKeyService::class);
    }

    public function subscribe(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        if (!$this->vapidService->isConfigured()) {
            $this->json(['ok' => false, 'error' => 'not_configured'], 409);
        }

        $endpoint = (string) ($_POST['endpoint'] ?? '');
        $p256dh = (string) ($_POST['p256dh'] ?? '');
        $auth = (string) ($_POST['auth'] ?? '');
        $contentEncoding = (string) ($_POST['content_encoding'] ?? 'aes128gcm');

        if (!$this->isValidEndpoint($endpoint) || !$this->isBase64Url($p256dh, 20) || !$this->isBase64Url($auth, 10)) {
            $this->json(['ok' => false, 'error' => 'invalid_subscription'], 422);
        }
        if (!in_array($contentEncoding, ['aes128gcm', 'aesgcm'], true)) {
            $contentEncoding = 'aes128gcm';
        }

        $this->subscriptionRepo->upsertForDevice(
            $userId,
            $endpoint,
            $p256dh,
            $auth,
            $contentEncoding,
            isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null
        );

        $this->json([
            'ok' => true,
            'device_count' => $this->subscriptionRepo->countForUser($userId),
        ]);
    }

    public function unsubscribe(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        $endpoint = (string) ($_POST['endpoint'] ?? '');
        if ($endpoint === '') {
            $this->json(['ok' => false, 'error' => 'invalid_endpoint'], 422);
        }

        // Idempotente: rimuovere una subscription già assente è comunque un successo.
        $this->subscriptionRepo->deleteForUserByEndpoint($userId, $endpoint);

        $this->json([
            'ok' => true,
            'device_count' => $this->subscriptionRepo->countForUser($userId),
        ]);
    }

    private function isValidEndpoint(string $endpoint): bool
    {
        if ($endpoint === '' || strlen($endpoint) > 2048) {
            return false;
        }
        return str_starts_with($endpoint, 'https://')
            && filter_var($endpoint, FILTER_VALIDATE_URL) !== false;
    }

    private function isBase64Url(string $value, int $minLength): bool
    {
        $length = strlen($value);
        return $length >= $minLength
            && $length <= 255
            && preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $value) === 1;
    }
}
