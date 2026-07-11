<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

/**
 * Firma HMAC-SHA256 delle consegne webhook, con timestamp incorporato per la
 * protezione anti-replay (schema stile Stripe).
 *
 * Il valore firmato NON è il solo body ma "{timestamp}.{body}", e il timestamp
 * viaggia in chiaro nell'header X-Favilla-Timestamp. Il destinatario:
 *   1. rifiuta le richieste il cui timestamp è fuori da una finestra di
 *      tolleranza (blocca il replay di catture vecchie);
 *   2. ricalcola hmac(secret, "{ts}.{body}") e lo confronta con X-Favilla-Signature.
 *
 * Header di firma: "t=<unix>,v1=<hmac hex>".
 */
class WebhookSigner
{
    public const HEADER = 'X-Favilla-Signature';
    public const TIMESTAMP_HEADER = 'X-Favilla-Timestamp';

    /** Tolleranza di default per la verifica (secondi). */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Genera un secret casuale (48 hex char) da mostrare una sola volta.
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * Valore dell'header di firma per un dato timestamp: "t=<ts>,v1=<hmac>".
     * L'HMAC copre "{ts}.{body}".
     */
    public function sign(string $body, string $secret, ?int $timestamp = null): string
    {
        $ts = $timestamp ?? time();
        $signature = hash_hmac('sha256', $ts . '.' . $body, $secret);
        return 't=' . $ts . ',v1=' . $signature;
    }

    /**
     * Verifica una firma con controllo della finestra temporale (anti-replay).
     * Usato nei test e per eventuali eventi di prova ricevuti da Favilla stessa.
     */
    public function verify(
        string $body,
        string $secret,
        string $signatureHeader,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null
    ): bool {
        $parsed = $this->parseHeader($signatureHeader);
        if ($parsed === null) {
            return false;
        }
        [$ts, $provided] = $parsed;

        $now ??= time();
        if (abs($now - $ts) > $tolerance) {
            return false; // fuori finestra => replay o clock skew eccessivo
        }

        $expected = hash_hmac('sha256', $ts . '.' . $body, $secret);
        return hash_equals($expected, $provided);
    }

    /**
     * Estrae (timestamp, firma) da "t=<ts>,v1=<hmac>". Null se malformato.
     *
     * @return array{0:int,1:string}|null
     */
    private function parseHeader(string $header): ?array
    {
        $ts = null;
        $sig = null;
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            [$key, $value] = $kv;
            if ($key === 't' && ctype_digit($value)) {
                $ts = (int) $value;
            } elseif ($key === 'v1') {
                $sig = $value;
            }
        }
        if ($ts === null || $sig === null || $sig === '') {
            return null;
        }
        return [$ts, $sig];
    }
}
