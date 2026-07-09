<?php

declare(strict_types=1);

namespace App\Modules\Api\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Core\Container;
use App\Exceptions\HttpException;
use App\Modules\Api\Repositories\PersonalAccessTokenRepository;
use App\Modules\Api\Support\ApiRequestContext;
use App\Repositories\UserRepository;

/**
 * Autenticazione stateless dell'API v1 via Personal Access Token.
 *
 * Legge `Authorization: Bearer <token>`, risolve lo sha256 in
 * personal_access_tokens (scadenza/revoca già filtrate dal repo), carica utente
 * + permessi dal DB (nessuna sessione) e popola ApiRequestContext. Ogni
 * fallimento produce un 401/503 JSON coerente con l'envelope dell'API.
 */
class ApiTokenMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        if (!(bool) setting('api_enabled', true)) {
            $this->fail(503, 'api_disabled', 'API pubblica disattivata.');
        }

        $token = $this->extractBearerToken();
        if ($token === null) {
            $this->fail(401, 'unauthenticated', 'Token di accesso mancante.');
        }

        $tokenRepo = app(PersonalAccessTokenRepository::class);
        $record = $tokenRepo->findValidByHash(hash('sha256', $token));
        if ($record === null) {
            $this->fail(401, 'invalid_token', 'Token non valido, scaduto o revocato.');
        }

        $user = app(UserRepository::class)->findWithPermissions((int) $record['user_id']);
        if ($user === null || (int) ($user['is_active'] ?? 1) !== 1) {
            $this->fail(401, 'inactive_account', 'Account non attivo.');
        }

        $scopes = $record['scopes'] !== null ? json_decode((string) $record['scopes'], true) : null;
        if (!is_array($scopes)) {
            $scopes = null;
        }

        $context = app(ApiRequestContext::class);
        $context->authenticate(
            (int) $record['user_id'],
            [
                'id'    => (int) $user['id'],
                'name'  => (string) ($user['name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
            ],
            array_column($user['roles'] ?? [], 'slug'),
            $user['permissions'] ?? [],
            $scopes === null ? null : array_map('strval', $scopes),
            (int) $record['id']
        );

        // Condivide il contesto con il resto della pipeline: senza questo bind
        // il Container auto-wira una nuova istanza vuota a ogni app() successivo
        // (rate limiter, controller), perdendo utente e scope.
        Container::getInstance()->instance(ApiRequestContext::class, $context);

        $tokenRepo->touchLastUsed((int) $record['id']);

        $next();
    }

    private function extractBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    $header = (string) $value;
                    break;
                }
            }
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m) === 1) {
            return $m[1];
        }
        return null;
    }

    private function fail(int $status, string $code, string $message): never
    {
        $body = json_encode([
            'error' => ['code' => $code, 'message' => $message],
        ], JSON_UNESCAPED_UNICODE) ?: '{"error":{"code":"error"}}';

        $headers = [];
        if ($status === 401) {
            $headers['WWW-Authenticate'] = 'Bearer';
        }

        throw new HttpException($status, $headers, $body, 'application/json; charset=utf-8', 'API auth: ' . $code);
    }
}
