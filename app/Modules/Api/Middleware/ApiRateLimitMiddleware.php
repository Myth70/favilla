<?php

declare(strict_types=1);

namespace App\Modules\Api\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Exceptions\HttpException;
use App\Modules\Api\Support\ApiRequestContext;
use App\Support\ClientIp;
use PDO;

/**
 * Rate limiting per-token dell'API v1. Sliding window sulla tabella condivisa
 * rate_limits, con chiave api-token:<id> (fallback IP se il contesto non è
 * autenticato). Il limite si legge da app_settings (api_rate_limit_per_minute).
 * Deve girare DOPO ApiTokenMiddleware, che popola ApiRequestContext.
 */
class ApiRateLimitMiddleware implements MiddlewareInterface
{
    private const WINDOW_SECONDS = 60;

    public function handle(callable $next): void
    {
        $context = app(ApiRequestContext::class);
        // Questo middleware gira sempre DOPO ApiTokenMiddleware, che risponde 401
        // alle richieste non autenticate: il token è quindi garantito e il bucket
        // è per-token.
        $key = 'api-token:' . (int) $context->tokenId();

        $max = max(1, (int) setting('api_rate_limit_per_minute', 120));
        $endpoint = 'api/v1';

        $pdo = app(PDO::class);

        if (random_int(1, 50) === 1) {
            $this->cleanup($pdo);
        }

        // Registra QUESTA richiesta prima di contare: evita la finestra di race
        // COUNT-poi-INSERT in cui due richieste concorrenti passano entrambe
        // sotto il limite. Il COUNT successivo include già questa riga.
        $ip = ClientIp::resolve();
        $insert = $pdo->prepare(
            'INSERT INTO rate_limits (rate_key, endpoint, ip_address, user_id, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $insert->execute([$key, $endpoint, $ip, $context->isAuthenticated() ? $context->userId() : null]);

        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c, MIN(created_at) AS oldest
             FROM rate_limits WHERE rate_key = ? AND endpoint = ? AND created_at > ?'
        );
        $stmt->execute([$key, $endpoint, $cutoff]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 'oldest' => null];
        $count = (int) $row['c'];

        // Reset coerente con la finestra scorrevole: quando la richiesta più
        // vecchia esce dalla finestra si libera capacità.
        $oldestTs = $row['oldest'] !== null ? (int) strtotime((string) $row['oldest']) : time();
        $reset = $oldestTs + self::WINDOW_SECONDS;
        $retryAfter = max(1, $reset - time());

        if (!headers_sent()) {
            header('X-RateLimit-Limit: ' . $max);
            header('X-RateLimit-Remaining: ' . max(0, $max - $count));
            header('X-RateLimit-Reset: ' . $reset);
        }

        if ($count > $max) {
            $body = json_encode([
                'error' => [
                    'code'        => 'rate_limited',
                    'message'     => 'Too many requests. Please retry shortly.',
                    'retry_after' => $retryAfter,
                ],
            ], JSON_UNESCAPED_UNICODE) ?: '{"error":{"code":"rate_limited"}}';

            throw new HttpException(
                429,
                ['Retry-After' => (string) $retryAfter],
                $body,
                'application/json; charset=utf-8',
                'API rate limit exceeded'
            );
        }

        $next();
    }

    private function cleanup(PDO $pdo): void
    {
        try {
            $pdo->exec('DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        } catch (\Throwable) {
            // Non-blocking
        }
    }
}
