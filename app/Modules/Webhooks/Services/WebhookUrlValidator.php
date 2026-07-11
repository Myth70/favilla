<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

/**
 * Difesa anti-SSRF per gli URL di destinazione dei webhook (definiti dagli
 * utenti). Consente solo https (http ammesso per il solo host locale in
 * sviluppo), vieta credenziali in URL e risolve il DNS bloccando IP privati,
 * loopback e link-local.
 *
 * Difesa in profondità (nessun singolo controllo è sufficiente da solo):
 *  - il dispatcher richiama resolveVetted() a OGNI invio, non solo alla
 *    creazione dell'endpoint;
 *  - gli IP vettati vengono PINNATI nel client HTTP (CURLOPT_RESOLVE): la
 *    connessione va esattamente all'indirizzo validato, senza ri-risoluzione →
 *    chiude la finestra TOCTOU di DNS-rebinding tra questa validazione e la
 *    connessione reale, mantenendo Host/SNI/verifica certificato sull'hostname;
 *  - WebhookHttpClient NON segue i redirect (un 3xx verso un IP interno non
 *    viene inseguito).
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
            return t('webhooks.error.url_missing');
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            return t('webhooks.error.url_invalid');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return t('webhooks.error.url_credentials');
        }

        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $devMode = (string) config('app.env', 'production') === 'development';

        if ($scheme === 'http') {
            if (!($isLocalHost && $devMode)) {
                return t('webhooks.error.https_only');
            }
        } elseif ($scheme !== 'https') {
            return t('webhooks.error.scheme_unsupported');
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
        return $this->resolveVetted($url)['error'];
    }

    /**
     * Come resolveAndAssertPublic() ma restituisce anche gli IP vettati, così il
     * client HTTP può *pinnarli* (CURLOPT_RESOLVE) ed evitare che il wrapper di
     * rete ri-risolva l'host in modo indipendente — chiudendo la finestra TOCTOU
     * di DNS-rebinding tra questa validazione e la connessione reale.
     *
     * @return array{error: ?string, ips: string[]}
     */
    public function resolveVetted(string $url): array
    {
        $staticError = $this->validate($url);
        if ($staticError !== null) {
            return ['error' => $staticError, 'ips' => []];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $isDevLoopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && (string) config('app.env', 'production') === 'development';

        $ips = $this->resolveHost($host);
        if ($ips === []) {
            return ['error' => t('webhooks.error.unresolvable'), 'ips' => []];
        }

        // In sviluppo il loopback è consentito ma va comunque pinnato.
        if (!$isDevLoopback) {
            foreach ($ips as $ip) {
                if ($this->isBlockedIp($ip)) {
                    return ['error' => t('webhooks.error.private_ip'), 'ips' => []];
                }
            }
        }

        return ['error' => null, 'ips' => array_values(array_unique($ips))];
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
     * IPv4 non instradabili pubblicamente (RFC 1918 / 6598 / 3927 / 5737 / ecc.).
     * I flag FILTER_FLAG_NO_PRIV_RANGE|NO_RES_RANGE di PHP NON coprono diversi di
     * questi range (CGNAT, multicast, benchmarking, ecc.), quindi li elenchiamo.
     */
    private const BLOCKED_V4_CIDRS = [
        '0.0.0.0/8',        // "this host" / sorgente non instradabile
        '10.0.0.0/8',       // RFC 1918 privato
        '100.64.0.0/10',    // RFC 6598 CGNAT (proxy metadata di alcuni cloud)
        '127.0.0.0/8',      // loopback
        '169.254.0.0/16',   // link-local (include 169.254.169.254 metadata cloud)
        '172.16.0.0/12',    // RFC 1918 privato
        '192.0.0.0/24',     // IETF protocol assignments
        '192.0.2.0/24',     // TEST-NET-1
        '192.88.99.0/24',   // 6to4 relay anycast
        '192.168.0.0/16',   // RFC 1918 privato
        '198.18.0.0/15',    // benchmarking
        '198.51.100.0/24',  // TEST-NET-2
        '203.0.113.0/24',   // TEST-NET-3
        '224.0.0.0/4',      // multicast
        '240.0.0.0/4',      // riservato (include 255.255.255.255 broadcast)
    ];

    /**
     * IPv6 non instradabili pubblicamente. Gli IPv4-mapped (::ffff:0:0/96) sono
     * ridotti a IPv4 prima di questo elenco, così sia la forma dotted che quella
     * esadecimale (es. ::ffff:7f00:1) vengono valutate come il loro IPv4.
     */
    private const BLOCKED_V6_CIDRS = [
        '::1/128',          // loopback
        '::/128',           // unspecified
        '::/96',            // IPv4-compatible (deprecato)
        '64:ff9b::/96',     // NAT64 well-known (incapsula IPv4, es. metadata)
        '100::/64',         // discard-only
        '2001:db8::/32',    // documentazione
        'fc00::/7',         // unique local (ULA)
        'fe80::/10',        // link-local
        'ff00::/8',         // multicast
    ];

    /**
     * Blocca loopback, link-local, private-use, CGNAT, multicast e altri range
     * non pubblici. Normalizza gli IPv4-mapped IPv6 (in QUALSIASI forma) al loro
     * IPv4 prima del controllo, chiudendo il bypass ::ffff:<hex>.
     */
    public function isBlockedIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return true; // non parsabile => blocca per sicurezza
        }

        // IPv4-mapped IPv6 (::ffff:0:0/96): collassa ai 4 byte IPv4 finali e
        // valuta come IPv4. Copre sia ::ffff:127.0.0.1 sia ::ffff:7f00:1.
        if (strlen($packed) === 16
            && str_starts_with($packed, "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff")) {
            $packed = substr($packed, 12);
        }

        if (strlen($packed) === 4) {
            foreach (self::BLOCKED_V4_CIDRS as $cidr) {
                if ($this->packedInCidr($packed, $cidr)) {
                    return true;
                }
            }
            return false;
        }

        foreach (self::BLOCKED_V6_CIDRS as $cidr) {
            if ($this->packedInCidr($packed, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True se l'IP (in forma binaria inet_pton) ricade nel CIDR indicato.
     * Confronta byte pieni + eventuale bit residuo mascherato. Funziona sia per
     * IPv4 (4 byte) sia per IPv6 (16 byte); ritorna false se le lunghezze
     * (famiglie) non coincidono.
     */
    private function packedInCidr(string $packedIp, string $cidr): bool
    {
        [$net, $bitsStr] = explode('/', $cidr, 2);
        $packedNet = @inet_pton($net);
        if ($packedNet === false || strlen($packedIp) !== strlen($packedNet)) {
            return false;
        }

        $bits = (int) $bitsStr;
        $fullBytes = intdiv($bits, 8);
        $remBits = $bits % 8;

        if ($fullBytes > 0 && substr($packedIp, 0, $fullBytes) !== substr($packedNet, 0, $fullBytes)) {
            return false;
        }
        if ($remBits === 0) {
            return true;
        }
        $mask = (~0 << (8 - $remBits)) & 0xff;
        return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedNet[$fullBytes]) & $mask);
    }
}
