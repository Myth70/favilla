<?php

declare(strict_types=1);

namespace App\Security;

class CsrfToken
{
    /**
     * Generate a CSRF token and store it in session.
     * Uses HMAC-SHA256 signed with APP_KEY for integrity.
     */
    public static function generate(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
        $_SESSION['_csrf_token_created_at'] = time();
        return self::sign($token);
    }

    /**
     * Get the current token (generate if missing or expired).
     * Token è rigenerato automaticamente ogni 60 minuti.
     */
    public static function get(): string
    {
        $ttl = 3600; // 60 minuti
        $createdAt = $_SESSION['_csrf_token_created_at'] ?? 0;

        if (empty($_SESSION['_csrf_token']) || (time() - $createdAt) >= $ttl) {
            return self::generate();
        }
        return self::sign($_SESSION['_csrf_token']);
    }

    /**
     * Verify a submitted token against the session token.
     */
    public static function verify(?string $submittedToken): bool
    {
        if ($submittedToken === null || $submittedToken === '') {
            return false;
        }

        if (empty($_SESSION['_csrf_token'])) {
            return false;
        }

        $expected = self::sign($_SESSION['_csrf_token']);
        return hash_equals($expected, $submittedToken);
    }

    /**
     * Regenerate the token (call after successful form submission if desired).
     */
    public static function regenerate(): string
    {
        return self::generate();
    }

    /**
     * Sign a token value using HMAC-SHA256 with the APP_KEY.
     */
    private static function sign(string $token): string
    {
        $key = env('APP_KEY', '');
        if ($key === '' || $key === null) {
            throw new \RuntimeException('APP_KEY is not set. CSRF signing requires a valid key.');
        }
        return hash_hmac('sha256', $token, $key);
    }
}
