<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Services\EncryptionService;

/**
 * Stato della transazione OIDC (state, nonce, PKCE verifier, redirect post
 * login) tra /auth/oidc/start e il callback.
 *
 * NON può vivere in $_SESSION: il cookie di sessione è SameSite=Strict e non
 * viaggia sulla navigazione cross-site di ritorno dall'IdP. Viaggia quindi in
 * un cookie dedicato SameSite=Lax (inviato sui GET top-level), cifrato
 * AES-256-GCM (quindi anche tamper-proof), HttpOnly, TTL 10 minuti,
 * single-use: take() lo cancella prima di restituirlo (anti-replay).
 *
 * Sotto PHPUnit (FAVILLA_TESTING) usa uno store in-memory: setcookie() non è
 * praticabile a header già inviati.
 */
class OidcTransactionStore
{
    private const COOKIE_NAME = 'favilla_oidc_txn';
    private const TTL_SECONDS = 600;

    /** @var array<string,mixed>|null store in-memory per i test */
    private static ?array $memory = null;

    public function __construct(private readonly EncryptionService $encryption)
    {
    }

    /**
     * @param array<string,mixed> $data
     */
    public function put(array $data): void
    {
        $data['iat'] = time();

        if (defined('FAVILLA_TESTING')) {
            self::$memory = $data;

            return;
        }

        $payload = $this->encryption->encrypt((string) json_encode($data));
        setcookie(self::COOKIE_NAME, $payload, [
            'expires'  => time() + self::TTL_SECONDS,
            'path'     => $this->cookiePath(),
            'secure'   => \App\Support\RequestContext::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Legge e CANCELLA la transazione (single-use). Null se assente,
     * manomessa o più vecchia del TTL.
     *
     * @return array<string,mixed>|null
     */
    public function take(): ?array
    {
        if (defined('FAVILLA_TESTING')) {
            $data = self::$memory;
            self::$memory = null;

            return $this->validate($data);
        }

        $raw = $_COOKIE[self::COOKIE_NAME] ?? null;
        $this->clear();
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $data = json_decode($this->encryption->decrypt($raw), true);
        } catch (\Throwable) {
            return null; // manomesso o cifrato con altra APP_KEY
        }

        return $this->validate(is_array($data) ? $data : null);
    }

    public function clear(): void
    {
        if (defined('FAVILLA_TESTING')) {
            self::$memory = null;

            return;
        }

        unset($_COOKIE[self::COOKIE_NAME]);
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => $this->cookiePath(),
            'secure'   => \App\Support\RequestContext::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * @param array<string,mixed>|null $data
     * @return array<string,mixed>|null
     */
    private function validate(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        $iat = (int) ($data['iat'] ?? 0);
        if ($iat <= 0 || (time() - $iat) > self::TTL_SECONDS) {
            return null;
        }

        return $data;
    }

    private function cookiePath(): string
    {
        $base = (string) config('app.base_path', '');

        return $base !== '' ? $base : '/';
    }
}
