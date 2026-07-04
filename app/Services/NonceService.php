<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Generates a per-request cryptographic nonce for Content-Security-Policy.
 * Must be registered as singleton — the nonce must be identical for the entire request.
 */
class NonceService
{
    private string $nonce;

    public function __construct()
    {
        $this->nonce = base64_encode(random_bytes(16));
    }

    public function getNonce(): string
    {
        return $this->nonce;
    }
}
