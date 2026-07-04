<?php

/**
 * Middleware aliases.
 *
 * Mappa alias stringa → FQCN. Aggiungere qui nuovi alias senza toccare app/Core/.
 * Usare nelle route come: 'middleware' => ['auth', 'csrf', 'mio_alias']
 */
return [
    'csrf'     => \App\Middleware\CsrfMiddleware::class,
    'auth'     => \App\Middleware\AuthMiddleware::class,
    'role'     => \App\Middleware\RoleMiddleware::class,
    'session_security' => \App\Middleware\SessionSecurityMiddleware::class,
    'rate_limit'       => \App\Middleware\RateLimitMiddleware::class,
    'security_headers' => \App\Middleware\SecurityHeadersMiddleware::class,
];
