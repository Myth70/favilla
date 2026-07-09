<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Modules\Api\Controllers\MeApiController;
use App\Modules\Api\Controllers\OpenApiController;
use App\Modules\Api\Controllers\TokensController;
use App\Modules\Api\Middleware\ApiRateLimitMiddleware;
use App\Modules\Api\Middleware\ApiTokenMiddleware;

// ---------------------------------------------------------------------------
// Documentazione: spec OpenAPI pubblica (nessuna auth, nessun dato sensibile).
// ---------------------------------------------------------------------------
$router->get('/api/v1/openapi.json', [OpenApiController::class, 'spec'])
    ->name('api.openapi');

// ---------------------------------------------------------------------------
// API v1 — token-based, stateless. Niente CsrfMiddleware (non è cookie-based).
// L'ordine dei middleware conta: il token popola il contesto prima del rate limit.
// ---------------------------------------------------------------------------
$router->group([
    'prefix'     => 'api/v1',
    'middleware' => [ApiTokenMiddleware::class, ApiRateLimitMiddleware::class],
], function ($r) {
    $r->get('/me', [MeApiController::class, 'show'])->name('api.me');
});

// ---------------------------------------------------------------------------
// Gestione token (UI self-service nel profilo). Solo auth web + CSRF: gli scope
// selezionabili sono già limitati ai permessi dell'utente.
// ---------------------------------------------------------------------------
$router->group([
    'prefix'     => 'profile/api-tokens',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class],
], function ($r) {
    $r->get('/', [TokensController::class, 'index'])->name('api.tokens.index');
    $r->post('/', [TokensController::class, 'store'])->name('api.tokens.store');
    $r->post('/{id}/revoke', [TokensController::class, 'revoke'])->name('api.tokens.revoke');
});
