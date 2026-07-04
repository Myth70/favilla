<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Services\NonceService;

/**
 * ISO 27001 A.13.1.1 — Network controls.
 * ISO 27001 A.14.1.2 — Secure transfer.
 *
 * Applica security headers HTTP a tutte le risposte:
 * CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy,
 * Permissions-Policy, HSTS (se abilitato).
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        if (!headers_sent()) {
            $nonce = app(NonceService::class)->getNonce();

            // Backward-compatible: expose nonce via $_SERVER for existing views.
            $_SERVER['CSP_NONCE'] = $nonce;

            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Content-Security-Policy: ' . $this->buildCsp($nonce));

            foreach (config('security.headers', []) as $name => $value) {
                header("{$name}: {$value}");
            }

            $hsts = config('security.hsts', []);
            if ($this->isHttps() && !empty($hsts['enabled'])) {
                $value = 'max-age=' . (int) ($hsts['max_age'] ?? 31536000);
                if (!empty($hsts['include_subdomains'])) {
                    $value .= '; includeSubDomains';
                }
                if (!empty($hsts['preload'])) {
                    $value .= '; preload';
                }
                header('Strict-Transport-Security: ' . $value);
            }
        }

        $next();
    }

    /**
     * Build Content-Security-Policy header from config, injecting nonce.
     */
    private function buildCsp(string $nonce): string
    {
        $csp = config('security.csp', [
            'default-src' => ["'self'"],
            'script-src'  => ["'self'"],
            'style-src'   => ["'self'", "'unsafe-inline'"],
            'img-src'     => ["'self'", 'data:'],
            'font-src'    => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
        ]);

        if (isset($csp['script-src'])) {
            $csp['script-src'][] = "'nonce-{$nonce}'";
        }

        $parts = [];
        foreach ($csp as $directive => $values) {
            $parts[] = $directive . ' ' . implode(' ', $values);
        }

        return implode('; ', $parts);
    }

    private function isHttps(): bool
    {
        // Sorgente unica con il flag Secure dei cookie (X-Forwarded-Proto onorato
        // solo dietro proxy fidato → niente HSTS basato su header spoofabili).
        return \App\Support\RequestContext::isSecure();
    }
}
