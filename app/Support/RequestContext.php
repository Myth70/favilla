<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Stato di trasporto della request corrente.
 *
 * isSecure() risponde a una sola domanda: "questa request sta viaggiando su HTTPS?"
 * Serve a decidere il flag `Secure` dei cookie. È volutamente DISACCOPPIATA da
 * APP_ENV: ciò che conta per il flag Secure è il transport reale, non l'ambiente.
 *
 * Riconosce tre casi:
 *   1. TLS diretto sul server PHP        → $_SERVER['HTTPS'] / porta 443
 *   2. TLS terminato su reverse proxy    → X-Forwarded-Proto: https
 *                                          (onorato SOLO se REMOTE_ADDR è in
 *                                          TRUSTED_PROXIES — coerente con ClientIp)
 *   3. nessun TLS (LAN HTTP)             → false
 *
 * Il caso 2 è lo scenario di produzione PMI tipico: Caddy/nginx davanti a XAMPP
 * che termina TLS e inoltra in HTTP. Senza questo, i cookie non prenderebbero
 * mai il flag Secure pur essendo l'utente su https://.
 */
final class RequestContext
{
    public static function isSecure(): bool
    {
        // 1. TLS diretto
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        // 2. TLS terminato su un proxy fidato
        $proto = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        if ($proto !== '' && self::behindTrustedProxy()) {
            // L'header può essere una lista "https, http": conta il primo (lato client).
            $first = trim(explode(',', $proto)[0]);
            if (strcasecmp($first, 'https') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * REMOTE_ADDR è uno dei proxy dichiarati in TRUSTED_PROXIES?
     * Senza proxy fidati gli header X-Forwarded-* sono ignorati (anti-spoofing).
     */
    private static function behindTrustedProxy(): bool
    {
        $trustedRaw = (string) env('TRUSTED_PROXIES', '');
        if ($trustedRaw === '') {
            return false;
        }
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $trusted = array_map('trim', explode(',', $trustedRaw));

        return in_array($remoteAddr, $trusted, true);
    }
}
