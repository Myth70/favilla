<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

/**
 * POST HTTP verso l'endpoint webhook. Usa cURL così possiamo:
 *  - PINNARE gli IP già vettati (CURLOPT_RESOLVE): la connessione va esattamente
 *    all'indirizzo validato da WebhookUrlValidator, senza ri-risoluzione DNS →
 *    elimina la finestra TOCTOU di DNS-rebinding, mantenendo Host/SNI/verifica
 *    certificato sull'hostname originale;
 *  - NON seguire i redirect (un 3xx verso un IP interno non viene inseguito);
 *  - limitare la dimensione del corpo di risposta (anti memory-DoS): serve solo
 *    lo status, non il body.
 */
class WebhookHttpClient
{
    private const TIMEOUT = 10;
    private const CONNECT_TIMEOUT = 5;
    /** Non leggiamo il body oltre questa soglia: basta lo status. */
    private const MAX_RESPONSE_BYTES = 64 * 1024;

    /**
     * @param array<string, string> $headers
     * @param string[] $pinnedIps IP già validati a cui pinnare la connessione.
     * @return array{status: int|null, error: string|null}
     */
    public function post(string $url, string $body, array $headers, array $pinnedIps = []): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => null, 'error' => 'Estensione cURL non disponibile.'];
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return ['status' => null, 'error' => 'URL non valido.'];
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        $headerLines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $received = 0;
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,   // anti-SSRF: mai inseguire i 3xx
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_RETURNTRANSFER => true,
            // Restringi i protocolli: solo HTTPS (HTTP resta possibile solo se lo
            // schema originale era http — validato a monte per il solo loopback dev).
            CURLOPT_PROTOCOLS      => $scheme === 'https' ? CURLPROTO_HTTPS : (CURLPROTO_HTTPS | CURLPROTO_HTTP),
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            // Cap del body: appena superata la soglia, aborta il transfer (lo
            // status è già noto perché arriva prima del corpo).
            CURLOPT_WRITEFUNCTION  => function ($handle, string $chunk) use (&$received): int {
                $received += strlen($chunk);
                if ($received > self::MAX_RESPONSE_BYTES) {
                    return 0; // aborta: CURLE_WRITE_ERROR
                }
                return strlen($chunk);
            },
        ];

        // IP-pinning: forziamo host:porta → IP vettati (nessuna ri-risoluzione).
        if ($pinnedIps !== []) {
            $opts[CURLOPT_RESOLVE] = [sprintf('%s:%d:%s', $host, $port, implode(',', $pinnedIps))];
        }

        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // Se abbiamo uno status HTTP valido lo usiamo, anche quando il transfer è
        // stato abortito dal cap sul body (errno CURLE_WRITE_ERROR ma status noto).
        if ($status >= 100) {
            return ['status' => $status, 'error' => null];
        }

        $error = $errno !== 0
            ? ('Endpoint non raggiungibile (cURL ' . $errno . ').')
            : 'Risposta HTTP non interpretabile.';
        return ['status' => null, 'error' => $error];
    }
}
