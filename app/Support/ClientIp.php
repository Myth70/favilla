<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized client IP resolution.
 *
 * Rispetta TRUSTED_PROXIES (lista CSV). Se REMOTE_ADDR non è fra i proxy fidati
 * ritorna REMOTE_ADDR. Altrimenti usa X-Forwarded-For, rifiutando l'intero
 * header se qualunque elemento non è un IP valido (evita injection tramite
 * proxy intermedi malconfigurati).
 */
final class ClientIp
{
    public static function resolve(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $trustedRaw = (string) env('TRUSTED_PROXIES', '');

        if ($trustedRaw === '') {
            return $remoteAddr;
        }

        $trusted = array_map('trim', explode(',', $trustedRaw));
        if (!in_array($remoteAddr, $trusted, true)) {
            return $remoteAddr;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded === '') {
            return $remoteAddr;
        }

        $ips = array_map('trim', explode(',', $forwarded));
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return $remoteAddr;
            }
        }
        foreach (array_reverse($ips) as $ip) {
            if (!in_array($ip, $trusted, true)) {
                return $ip;
            }
        }

        return $remoteAddr;
    }
}
