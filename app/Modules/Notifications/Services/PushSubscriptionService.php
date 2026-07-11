<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\PushSubscriptionRepository;

/**
 * Logica di dominio delle subscription Web Push del dispositivo: validazione
 * stretta di endpoint/chiavi (base64url) e persistenza idempotente. Tiene il
 * PushController sottile e rispetta il layering Controller → Service → Repository.
 */
class PushSubscriptionService
{
    private PushSubscriptionRepository $repo;
    private VapidKeyService $vapid;

    public function __construct()
    {
        $this->repo = app(PushSubscriptionRepository::class);
        $this->vapid = app(VapidKeyService::class);
    }

    public function isConfigured(): bool
    {
        return $this->vapid->isConfigured();
    }

    public function deviceCount(int $userId): int
    {
        return $this->repo->countForUser($userId);
    }

    /**
     * Registra/aggiorna la subscription del dispositivo dopo la validazione.
     *
     * @return array{ok: bool, error?: string, device_count?: int}
     */
    public function subscribe(
        int $userId,
        string $endpoint,
        string $p256dh,
        string $auth,
        string $contentEncoding,
        ?string $userAgent
    ): array {
        // Soglie realistiche: p256dh = punto EC non compresso (65 byte ≈ 88 char
        // base64url), auth = 16 byte ≈ 22 char.
        if (!$this->isValidEndpoint($endpoint)
            || !$this->isBase64Url($p256dh, 80)
            || !$this->isBase64Url($auth, 16)) {
            return ['ok' => false, 'error' => 'invalid_subscription'];
        }

        if (!in_array($contentEncoding, ['aes128gcm', 'aesgcm'], true)) {
            $contentEncoding = 'aes128gcm';
        }

        $this->repo->upsertForDevice($userId, $endpoint, $p256dh, $auth, $contentEncoding, $userAgent);

        return ['ok' => true, 'device_count' => $this->repo->countForUser($userId)];
    }

    /**
     * Rimuove la subscription di un endpoint dell'utente (idempotente).
     *
     * @return array{ok: bool, error?: string, device_count?: int}
     */
    public function unsubscribe(int $userId, string $endpoint): array
    {
        if ($endpoint === '') {
            return ['ok' => false, 'error' => 'invalid_endpoint'];
        }

        $this->repo->deleteForUserByEndpoint($userId, $endpoint);

        return ['ok' => true, 'device_count' => $this->repo->countForUser($userId)];
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
