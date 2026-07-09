<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

/**
 * POST HTTP verso l'endpoint webhook. Isolato in una classe dedicata (come il
 * pattern di TelegramChannelDriver) così il dispatcher è testabile mockando la
 * rete. Usa stream context — nessuna dipendenza aggiuntiva.
 */
class WebhookHttpClient
{
    private const TIMEOUT = 10;

    /**
     * @param array<string, string> $headers
     * @return array{status: int|null, error: string|null}
     */
    public function post(string $url, string $body, array $headers): array
    {
        $headerLines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method'         => 'POST',
                'header'         => implode("\r\n", $headerLines),
                'content'        => $body,
                'timeout'        => self::TIMEOUT,
                'ignore_errors'  => true, // leggi il body anche su 4xx/5xx
                // Anti-SSRF: NON seguire i redirect. Un endpoint validato potrebbe
                // rispondere 3xx verso un IP interno/metadata cloud, aggirando la
                // validazione fatta sull'URL originale. Un 3xx diventa così una
                // consegna non-2xx (retry poi failed), mai una richiesta seguita.
                'follow_location' => 0,
                'max_redirects'   => 0,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        // fopen + stream_get_meta_data invece della magic var $http_response_header:
        // con 'ignore_errors' l'handle si apre anche su 4xx/5xx e 'wrapper_data'
        // contiene gli header di risposta (status incluso).
        $handle = @fopen($url, 'r', false, $context);
        if ($handle === false) {
            return ['status' => null, 'error' => 'Endpoint non raggiungibile.'];
        }

        $meta = stream_get_meta_data($handle);
        stream_get_contents($handle); // drena e scarta il body
        fclose($handle);

        $responseHeaders = isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])
            ? $meta['wrapper_data']
            : [];

        $status = $this->parseStatus($responseHeaders);
        return ['status' => $status, 'error' => $status === null ? 'Risposta HTTP non interpretabile.' : null];
    }

    /**
     * @param array<int, mixed> $responseHeaders
     */
    private function parseStatus(array $responseHeaders): ?int
    {
        // La prima riga è del tipo "HTTP/1.1 200 OK".
        $statusLine = isset($responseHeaders[0]) ? (string) $responseHeaders[0] : '';
        if (preg_match('#HTTP/\S+\s+(\d{3})#', $statusLine, $m) === 1) {
            return (int) $m[1];
        }
        return null;
    }
}
