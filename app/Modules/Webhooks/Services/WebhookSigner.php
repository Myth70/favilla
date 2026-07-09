<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

/**
 * Firma HMAC-SHA256 del corpo del webhook. Il destinatario ricalcola
 * hmac(secret, body) e la confronta con l'header X-Favilla-Signature per
 * autenticare origine e integrità del payload.
 */
class WebhookSigner
{
    public const HEADER = 'X-Favilla-Signature';

    /**
     * Genera un secret casuale (48 hex char) da mostrare una sola volta.
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * Valore dell'header di firma: "sha256=<hmac hex>".
     */
    public function sign(string $body, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    /**
     * Verifica una firma in modo resistente al timing (utile nei test e per una
     * eventuale spedizione di eventi di prova verso Favilla stessa).
     */
    public function verify(string $body, string $secret, string $signature): bool
    {
        return hash_equals($this->sign($body, $secret), $signature);
    }
}
