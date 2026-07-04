<?php

/**
 * Security configuration — Headers, CSP, and HSTS.
 *
 * ISO 27001 A.13.1.1 (Network controls)
 * ISO 27001 A.14.1.2 (Secure transfer)
 */
return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Security Headers
    |--------------------------------------------------------------------------
    |
    | Questi header vengono applicati a tutte le risposte HTTP tramite
    | SecurityHeadersMiddleware.
    |
    */

    // Header aggiuntivi rispetto al set base gestito da SecurityHeadersMiddleware.
    'headers' => [
        // Disabilita feature del browser non necessarie
        'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=()',

        // Cross-Origin policies
        'X-Permitted-Cross-Domain-Policies' => 'none',

        // Previene caching di contenuto sensibile
        'Cache-Control'          => 'no-store, no-cache, must-revalidate, private',
        'Pragma'                 => 'no-cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (CSP)
    |--------------------------------------------------------------------------
    |
    | Politica CSP per prevenire XSS e data injection.
    | 'self' consente solo risorse dallo stesso dominio.
    | Il nonce viene aggiunto dinamicamente a script-src per ogni request.
    | 'unsafe-inline' in style-src è necessario per attributi style="..."
    | usati da Bootstrap e da valori dinamici (colori, progress bar).
    |
    */

    'csp' => [
        'default-src'     => ["'self'"],
        'script-src'      => ["'self'"],
        'style-src'       => ["'self'", "'unsafe-inline'"],
        'img-src'         => ["'self'", 'data:', 'blob:'],
        'font-src'        => ["'self'", 'data:'],
        'connect-src'     => ["'self'", 'https://nominatim.openstreetmap.org'],
        'frame-src'       => ['https://www.openstreetmap.org', 'https://openstreetmap.org'],
        'object-src'      => ["'none'"],
        'base-uri'        => ["'self'"],
        'form-action'     => ["'self'"],
        'frame-ancestors' => ["'none'"],
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict-Transport-Security (HSTS)
    |--------------------------------------------------------------------------
    |
    | Abilitare solo in produzione con HTTPS attivo.
    | In sviluppo locale (XAMPP HTTP) viene disabilitato automaticamente.
    |
    */

    'hsts' => [
        'enabled'            => env('HSTS_ENABLED', false),
        'max_age'            => 31536000, // 1 anno
        'include_subdomains' => true,
        'preload'            => false,
    ],
];
