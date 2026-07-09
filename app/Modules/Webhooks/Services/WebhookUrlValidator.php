<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

/**
 * Difesa anti-SSRF per gli URL di destinazione dei webhook (definiti dagli
 * utenti). Consente solo https (http ammesso per il solo host locale in
 * sviluppo), vieta credenziali in URL e risolve il DNS bloccando IP privati,
 * loopback e link-local. La risoluzione va rifatta a ogni invio reale (il
 * dispatcher chiama resolveAndAssertPublic()) per mitigare il DNS rebinding.
 */
class WebhookUrlValidator
{
    /**
     * Validazione statica (formato + schema), senza toccare il DNS. Usata alla
     * creazione/modifica dell'endpoint. Restituisce un messaggio d'errore o null.
     */
    public function validate(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 1024) {
            return 'URL mancante o troppo lungo.';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            return 'URL non valido.';
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return 'Le credenziali nell\'URL non sono ammesse.';
        }

        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $devMode = (string) config('app.env', 'production') === 'development';

        if ($scheme === 'http') {
            if (!($isLocalHost && $devMode)) {
                return 'Sono ammessi solo endpoint HTTPS.';
            }
        } elseif ($scheme !== 'https') {
            return 'Schema URL non supportato (usa https).';
        }

        return null;
    }

    /**
     * Validazione completa al momento dell'invio: formato + risoluzione DNS con
     * blocco degli IP non instradabili pubblicamente. Restituisce un messaggio
     * d'errore o null se l'URL è sicuro da contattare adesso.
     */
    public function resolveAndAssertPublic(string $url): ?string
    {
        $staticError = $this->validate($url);
        if ($staticError !== null) {
            return $staticError;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        // In sviluppo consentiamo esplicitamente il loopback (test locali).
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)
            && (string) config('app.env', 'production') === 'development') {
            return null;
        }

        $ips = $this->resolveHost($host);
        if ($ips === []) {
            return 'Impossibile risolvere l\'host di destinazione.';
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                return 'La destinazione risolve a un indirizzo IP privato o riservato.';
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function resolveHost(string $host): array
    {
        // Un IP letterale non ha bisogno di DNS.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (!empty($r['ip'])) {
                    $ips[] = $r['ip'];
                }
                if (!empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = $v4;
            }
        }

        return $ips;
    }

    /**
     * Blocca loopback, link-local, private-use e altri range non pubblici
     * (RFC 1918 / RFC 4193 / RFC 3927 / ecc.). PHP fa il grosso con i flag
     * FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE; aggiungiamo i casi
     * che quei flag non coprono (0.0.0.0, loopback IPv6, IPv4-mapped IPv6).
     */
    public function isBlockedIp(string $ip): bool
    {
        // IPv4-mapped IPv6 (es. ::ffff:127.0.0.1): estrai la parte IPv4.
        if (stripos($ip, '::ffff:') === 0) {
            $mapped = substr($ip, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $ip = $mapped;
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true; // non parsabile => blocca per sicurezza
        }

        // 0.0.0.0/8 e loopback IPv6 non sono coperti dai flag NO_PRIV/NO_RES.
        if ($ip === '::' || str_starts_with($ip, '0.')) {
            return true;
        }

        $public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        // filter_var restituisce false se l'IP è privato/riservato => bloccato.
        return $public === false;
    }
}
